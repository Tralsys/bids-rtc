<?php

namespace dev_t0r\bids_rtc\signaling\model;

use dev_t0r\bids_rtc\signaling\service\SDPEncryptAndDecrypt;
use Ramsey\Uuid\UuidInterface;

class SdpIdAndAnswer
{
	public function __construct(
		public readonly UuidInterface $sdpId,
		private readonly string $rawAnswer,
	) {
	}

	public function getProtectedAnswer(
		SDPEncryptAndDecrypt $encryptAndDecrypt,
	): string {
		return $encryptAndDecrypt->encrypt($this->rawAnswer);
	}
}
