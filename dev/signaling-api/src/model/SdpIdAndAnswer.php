<?php

namespace dev_t0r\bids_rtc\signaling\model;

use Ramsey\Uuid\UuidInterface;

class SdpIdAndAnswer
{
	public function __construct(
		private readonly UuidInterface $sdpId,
		private readonly string $rawAnswer,
	) {
	}
}
