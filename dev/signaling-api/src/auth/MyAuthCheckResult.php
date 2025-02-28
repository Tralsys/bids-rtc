<?php

namespace dev_t0r\bids_rtc\signaling\auth;

use dev_t0r\bids_rtc\signaling\RetValueOrError;
use Ramsey\Uuid\UuidInterface;

class MyAuthCheckResult
{
	public const string KEY_TYPE_REFRESH = 'refresh';
	public const string KEY_TYPE_ACCESS = 'access';

	public static function isKeyTypeValid(string $keyType): bool
	{
		return $keyType == self::KEY_TYPE_REFRESH || $keyType == self::KEY_TYPE_ACCESS;
	}

	public function __construct(
		public readonly ?string $uid,
		public readonly ?UuidInterface $appId,
		public readonly ?UuidInterface $clientId,
		public readonly ?RetValueOrError $error,
		public readonly string $keyType = self::KEY_TYPE_ACCESS,
		public readonly ?string $role,
	) {
	}
}
