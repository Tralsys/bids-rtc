<?php

namespace dev_t0r\bids_rtc\signaling\auth;

use dev_t0r\bids_rtc\signaling\Utils;
use Kreait\Firebase\Contract\Auth;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;

class MyAuthUtil
{
	private const string APP_ID_CLAIM_NAME = 'app_id';
	private const string CLIENT_ID_CLAIM_NAME = 'client_id';

	public function __construct(
		private readonly Auth $firebaseAuth,
		private readonly Configuration $myAuthJoseConfig,
		private readonly LoggerInterface $logger,
		private readonly string $issuer,
	) {
		$myAuthJoseConfig->setValidationConstraints(
			new SignedWith($myAuthJoseConfig->signer(), $myAuthJoseConfig->signingKey()),
			new IssuedBy($issuer),
		);
	}

	public function parse(
		string $tokenStr,
		ResponseFactoryInterface $responseFactory,
	): MyAuthCheckResult {
		try {
			$token = $this->myAuthJoseConfig->parser()->parse($tokenStr);
			$tokenIssuer = $token->claims()->get('iss');
			if ($tokenIssuer == $this->issuer) {
				return $this->parseMyToken($token, $responseFactory);
			}

			// 非効率だが、結局中でtoStringしており、また中の処理だけを切り出すのも面倒なため、strで渡す
			$verifiedIdToken = $this->firebaseAuth->verifyIdToken($tokenStr);
			$uid = $verifiedIdToken->claims()->get('sub');
			return new MyAuthCheckResult(
				$uid,
				null,
				null,
			);
		} catch (\Exception $e) {
			$this->logger->error("Failed to parse token: " . $e->getMessage());
			return new MyAuthCheckResult(
				null,
				null,
				Utils::withError($responseFactory->createResponse(), 401, 'Invalid token'),
			);
		}
	}

	private function parseMyToken(
		UnencryptedToken $token,
		ResponseFactoryInterface $responseFactory,
	): MyAuthCheckResult {
		if (!$this->isValidMyTokenSign($token)) {
			return new MyAuthCheckResult(
				null,
				null,
				Utils::withError($responseFactory->createResponse(), 401, 'Invalid token signature'),
			);
		}
		$uid = $token->claims()->get('sub');
		$clientId = $token->claims()->get(self::CLIENT_ID_CLAIM_NAME);
		return new MyAuthCheckResult(
			$uid,
			$clientId,
			null,
		);
	}

	public function isValidMyTokenSign(
		UnencryptedToken $token,
	): bool {
		return $this->myAuthJoseConfig->validator()->validate($token);
	}
}
