<?php

namespace dev_t0r\bids_rtc\signaling\auth;

use DateInterval;
use dev_t0r\bids_rtc\signaling\RetValueOrError;
use dev_t0r\bids_rtc\signaling\UtcClock;
use Kreait\Firebase\Contract\Auth;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Ramsey\Uuid\Uuid;

class MyAuthUtil
{
	private const string ISSUER_CLAIM_NAME = 'iss';
	private const string USER_ID_CLAIM_NAME = RegisteredClaims::SUBJECT; // sub以外にする場合、MyJWT生成の処理に修正が必要
	private const string APP_ID_CLAIM_NAME = 'app_id';
	private const string CLIENT_ID_CLAIM_NAME = 'client_id';
	private const string KEY_TYPE_CLAIM_NAME = 'typ';
	private const string ROLE_CLAIM_NAME = 'role';

	private readonly DateInterval $ACCESS_TOKEN_EXPIRE_INTERVAL;

	private readonly UtcClock $utcClock;
	private readonly bool $isDebug;

	public function __construct(
		private readonly Auth $firebaseAuth,
		private readonly Configuration $myAuthJoseConfig,
		private readonly LoggerInterface $logger,
		private readonly string $issuer,
		string $isDebug,
	) {
		$this->ACCESS_TOKEN_EXPIRE_INTERVAL = new DateInterval('PT1H');
		$myAuthJoseConfig->setValidationConstraints(
			new SignedWith($myAuthJoseConfig->signer(), $myAuthJoseConfig->signingKey()),
			new IssuedBy($issuer),
		);
		$this->utcClock = new UtcClock();
		$this->isDebug = $isDebug === 'true';
	}

	public function parse(
		string $tokenStr,
	): MyAuthCheckResult {
		try {
			$token = $this->myAuthJoseConfig->parser()->parse($tokenStr);
			$tokenIssuer = $token->claims()->get(self::ISSUER_CLAIM_NAME);
			if ($tokenIssuer == $this->issuer) {
				return $this->validateMyToken($token);
			}
		} catch (\Exception $e) {
			if (!($e instanceof InvalidTokenStructure && $this->isDebug)) {
				$this->logger->error("Failed to parse token: " . $e->getMessage());
				return new MyAuthCheckResult(
					null,
					null,
					null,
					RetValueOrError::withError(401, 'Invalid token'),
				);
			}
		}

		try {
			// 非効率だが、結局中でtoStringしており、また中の処理だけを切り出すのも面倒なため、strで渡す
			$verifiedIdToken = $this->firebaseAuth->verifyIdToken($tokenStr);
			$uid = $verifiedIdToken->claims()->get(self::USER_ID_CLAIM_NAME);
			$role = $verifiedIdToken->claims()->get(self::ROLE_CLAIM_NAME);
			$this->logger->debug("Firebase token verified: uid=$uid, role=$role");
			return new MyAuthCheckResult(
				$uid,
				null,
				null,
				null,
				MyAuthCheckResult::KEY_TYPE_ACCESS,
				$role,
			);
		} catch (\Exception $e) {
			$this->logger->error("Failed to parse token: " . $e->getMessage());
			return new MyAuthCheckResult(
				null,
				null,
				null,
				RetValueOrError::withError(401, 'Invalid token'),
			);
		}
	}

	public function parseMyToken(
		string $tokenStr,
	): UnencryptedToken {
		$this->logger->debug("parseMyToken: $tokenStr");
		return $this->myAuthJoseConfig->parser()->parse($tokenStr);
	}

	public function validateMyToken(
		UnencryptedToken $token,
	): MyAuthCheckResult {
		$isValidToken = $this->myAuthJoseConfig->validator()->validate(
			$token,
			new SignedWith($this->myAuthJoseConfig->signer(), $this->myAuthJoseConfig->verificationKey()),
			new IssuedBy($this->issuer),
		);
		if (!$isValidToken) {
			return new MyAuthCheckResult(
				null,
				null,
				null,
				RetValueOrError::withError(401, 'Invalid token signature'),
			);
		}
		$isExpired = $token->isExpired($this->utcClock->now());
		if ($isExpired) {
			return new MyAuthCheckResult(
				null,
				null,
				null,
				RetValueOrError::withError(400, 'Token is expired'),
			);
		}
		$uid = $token->claims()->get(self::USER_ID_CLAIM_NAME);
		$appId = $token->claims()->get(self::APP_ID_CLAIM_NAME);
		$clientId = $token->claims()->get(self::CLIENT_ID_CLAIM_NAME);
		$keyType = $token->claims()->get(self::KEY_TYPE_CLAIM_NAME);
		if ($uid == null || $appId == null || $clientId == null || $keyType == null) {
			return new MyAuthCheckResult(
				null,
				null,
				null,
				RetValueOrError::withError(401, 'Invalid token: some fields are missing'),
			);
		}
		if ($uid === "" || $appId === "" || $clientId === "" || $keyType === "") {
			return new MyAuthCheckResult(
				null,
				null,
				null,
				RetValueOrError::withError(401, 'Invalid token: some fields are empty'),
			);
		}
		if (!Uuid::isValid($appId) || !Uuid::isValid($clientId)) {
			return new MyAuthCheckResult(
				$uid,
				null,
				null,
				RetValueOrError::withError(401, 'Invalid token: not valid UUID'),
			);
		}
		if (!MyAuthCheckResult::isKeyTypeValid($keyType)) {
			return new MyAuthCheckResult(
				$uid,
				null,
				null,
				RetValueOrError::withError(401, 'Invalid token: key_type is invalid'),
			);
		}
		return new MyAuthCheckResult(
			$uid,
			Uuid::fromString($appId),
			Uuid::fromString($clientId),
			null,
			$keyType,
		);
	}

	public function generateRefreshToken(
		string $rawUserId,
		UuidInterface $appId,
		UuidInterface $clientId,
	): string {
		return $this->generateMyToken(
			$rawUserId,
			$appId,
			$clientId,
			MyAuthCheckResult::KEY_TYPE_REFRESH,
		);
	}

	public function generateAccessToken(
		string $rawUserId,
		UuidInterface $appId,
		UuidInterface $clientId,
	): string {
		return $this->generateMyToken(
			$rawUserId,
			$appId,
			$clientId,
			MyAuthCheckResult::KEY_TYPE_ACCESS,
		);
	}

	private function generateMyToken(
		string $rawUserId,
		UuidInterface $appId,
		UuidInterface $clientId,
		string $keyType,
	): string {
		$now = $this->utcClock->now();
		$tokenBuilder = $this->myAuthJoseConfig->builder()
			->issuedBy($this->issuer)
			->issuedAt($now)
			->relatedTo($rawUserId) // sub
			->withClaim(self::APP_ID_CLAIM_NAME, $appId->toString())
			->withClaim(self::CLIENT_ID_CLAIM_NAME, $clientId->toString())
			->withClaim(self::KEY_TYPE_CLAIM_NAME, $keyType)
		;
		if ($keyType == MyAuthCheckResult::KEY_TYPE_ACCESS) {
			$tokenBuilder = $tokenBuilder
				->expiresAt($now->add($this->ACCESS_TOKEN_EXPIRE_INTERVAL))
			;
		}
		return $tokenBuilder
			->getToken($this->myAuthJoseConfig->signer(), $this->myAuthJoseConfig->signingKey())
			->toString();
	}
}
