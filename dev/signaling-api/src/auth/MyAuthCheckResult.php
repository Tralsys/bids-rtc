<?php

namespace dev_t0r\bids_rtc\signaling\auth;

use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\UuidInterface;

class MyAuthCheckResult
{
	public function __construct(
		public readonly ?string $uid,
		public readonly ?UuidInterface $clientId,
		public readonly ?ResponseInterface $errorResponse,
	) {
	}
}
