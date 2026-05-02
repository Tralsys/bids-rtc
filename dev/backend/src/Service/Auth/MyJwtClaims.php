<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Service\Auth;

use Ramsey\Uuid\UuidInterface;

/**
 * JWT クレームを保持する value object
 */
final class MyJwtClaims
{
	public const string KEY_TYPE_ACCESS = 'access';
	public const string KEY_TYPE_REFRESH = 'refresh';

	public function __construct(
		public readonly string $uid,
		public readonly UuidInterface $appId,
		public readonly UuidInterface $clientId,
		public readonly string $keyType,
		public readonly \DateTimeImmutable $issuedAt,
	) {
	}
}
