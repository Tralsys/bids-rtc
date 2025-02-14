<?php

namespace dev_t0r\bids_rtc\signaling\model;

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
}
