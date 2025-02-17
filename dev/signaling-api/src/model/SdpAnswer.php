<?php

namespace dev_t0r\bids_rtc\signaling\model;

use dev_t0r\bids_rtc\signaling\service\SDPEncryptAndDecrypt;
use dev_t0r\bids_rtc\signaling\Utils;
use Ramsey\Uuid\UuidInterface;

class SdpAnswer
{
	public function __construct(
		public readonly UuidInterface $sdp_id,
		public readonly ?string $answer_client_id,
		public readonly ?string $protected_answer,
	) {
	}

	public function decrypt(
		SDPEncryptAndDecrypt $encryptAndDecrypt,
	): ?DecryptedSdpAnswer {
		if ($this->answer_client_id == null || $this->protected_answer == null) {
			return null;
		}
		return new DecryptedSdpAnswer(
			$this->sdp_id,
			Utils::uuidFromBytesOrNull($this->answer_client_id),
			$encryptAndDecrypt->decrypt($this->protected_answer),
		);
	}
}
