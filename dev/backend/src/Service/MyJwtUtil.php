<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Service;

use BidsRtc\Backend\RetValueOrError;
use BidsRtc\Backend\Service\Auth\MyJwtClaims;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * 自前 JWT の生成・検証ユーティリティ
 *
 * DI: autowire()->constructorParameter('issuer', DI\get('my-auth.issuer'))
 */
class MyJwtUtil
{
	private const string APP_ID_CLAIM = 'app_id';
	private const string CLIENT_ID_CLAIM = 'client_id';
	private const string KEY_TYPE_CLAIM = 'typ';

	private const string ACCESS_TOKEN_EXPIRE_INTERVAL = 'PT1H';
	private const string REFRESH_TOKEN_EXPIRE_INTERVAL = 'P1Y';

	public function __construct(
		private readonly Configuration $jwtConfig,
		private readonly string $issuer,
	) {
	}

	/**
	 * JWT 文字列をパースし、署名・issuer・クレームを検証して MyJwtClaims を返す。
	 * 失敗時は RetValueOrError を throw する。
	 */
	public function parseAndValidate(string $rawToken): MyJwtClaims
	{
		// パース
		try {
			/** @var \Lcobucci\JWT\UnencryptedToken $token */
			$token = $this->jwtConfig->parser()->parse($rawToken);
		} catch (\Throwable) {
			throw new RetValueOrError(400, 'Invalid token format');
		}

		// 署名 + issuer 検証 (validate は bool を返す)
		$isValid = $this->jwtConfig->validator()->validate(
			$token,
			new SignedWith($this->jwtConfig->signer(), $this->jwtConfig->verificationKey()),
			new IssuedBy($this->issuer),
		);
		if (!$isValid) {
			throw new RetValueOrError(401, 'Invalid token');
		}

		// typ クレームを取得・検証
		$keyType = $token->claims()->get(self::KEY_TYPE_CLAIM);
		if (
			$keyType !== MyJwtClaims::KEY_TYPE_ACCESS
			&& $keyType !== MyJwtClaims::KEY_TYPE_REFRESH
		) {
			throw new RetValueOrError(401, 'Invalid token: unknown key type');
		}

		// 有効期限チェック
		$now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
		if ($token->isExpired($now)) {
			throw new RetValueOrError(401, 'Token is expired');
		}

		// 必須クレームを取り出す
		$uid = $token->claims()->get(RegisteredClaims::SUBJECT);
		$appIdStr = $token->claims()->get(self::APP_ID_CLAIM);
		$clientIdStr = $token->claims()->get(self::CLIENT_ID_CLAIM);
		/** @var \DateTimeImmutable|null $issuedAt */
		$issuedAt = $token->claims()->get(RegisteredClaims::ISSUED_AT);

		if ($uid === null || $appIdStr === null || $clientIdStr === null || $issuedAt === null) {
			throw new RetValueOrError(401, 'Invalid token: missing required claims');
		}

		if (!Uuid::isValid($appIdStr) || !Uuid::isValid($clientIdStr)) {
			throw new RetValueOrError(401, 'Invalid token: claim is not a valid UUID');
		}

		return new MyJwtClaims(
			uid: $uid,
			appId: Uuid::fromString($appIdStr),
			clientId: Uuid::fromString($clientIdStr),
			keyType: $keyType,
			issuedAt: $issuedAt,
		);
	}

	/**
	 * アクセストークン (typ=access, exp=now+1h) を発行して JWT 文字列を返す。
	 */
	public function issueAccessToken(string $uid, UuidInterface $appId, UuidInterface $clientId): string
	{
		$now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
		$exp = $now->add(new \DateInterval(self::ACCESS_TOKEN_EXPIRE_INTERVAL));

		return $this->jwtConfig->builder()
			->issuedBy($this->issuer)
			->issuedAt($now)
			->expiresAt($exp)
			->relatedTo($uid)
			->withClaim(self::APP_ID_CLAIM, $appId->toString())
			->withClaim(self::CLIENT_ID_CLAIM, $clientId->toString())
			->withClaim(self::KEY_TYPE_CLAIM, MyJwtClaims::KEY_TYPE_ACCESS)
			->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey())
			->toString();
	}

	/**
	 * リフレッシュトークン (typ=refresh, exp=now+1y) を発行して JWT 文字列を返す。
	 */
	public function issueRefreshToken(string $uid, UuidInterface $appId, UuidInterface $clientId): string
	{
		$now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
		$exp = $now->add(new \DateInterval(self::REFRESH_TOKEN_EXPIRE_INTERVAL));

		return $this->jwtConfig->builder()
			->issuedBy($this->issuer)
			->issuedAt($now)
			->expiresAt($exp)
			->relatedTo($uid)
			->withClaim(self::APP_ID_CLAIM, $appId->toString())
			->withClaim(self::CLIENT_ID_CLAIM, $clientId->toString())
			->withClaim(self::KEY_TYPE_CLAIM, MyJwtClaims::KEY_TYPE_REFRESH)
			->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey())
			->toString();
	}
}
