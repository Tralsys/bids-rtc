<?php

namespace dev_t0r\bids_rtc\signaling\model;

use dev_t0r\bids_rtc\signaling\service\SDPEncryptAndDecrypt;
use Ramsey\Uuid\UuidInterface;

class SdpRecord
{
	public function __construct(
		public readonly UuidInterface $sdp_id,
		public readonly string $hashed_user_id,
		public readonly UuidInterface $offer_client_id,
		public readonly string $role,
		public readonly ?UuidInterface $answer_client_id,
		public readonly ?string $answer_process_id,
		public readonly string $protected_offer,
		public readonly ?string $protected_answer,
		public readonly ?string $error_message,
		public readonly \DateTime $created_at,
		// deleted_atは含めない
	) {
	}

	public function decrypt(
		string $rawUserId,
		SDPEncryptAndDecrypt $encryptAndDecrypt,
	): DecryptedSdpRecord {
		$offer = $encryptAndDecrypt->decrypt($this->protected_offer);
		$answer = $this->protected_answer == null ? null : $encryptAndDecrypt->decrypt($this->protected_answer);
		return new DecryptedSdpRecord(
			$this->sdp_id,
			$rawUserId,
			$this->offer_client_id,
			$this->role,
			$this->answer_client_id,
			$this->answer_process_id,
			$offer,
			$answer,
			$this->error_message,
			$this->created_at,
		);
	}
}
