<?php

namespace dev_t0r\bids_rtc\signaling\model;

use dev_t0r\bids_rtc\signaling\Utils;
use Ramsey\Uuid\UuidInterface;

class DecryptedSdpRecord
{
	public function __construct(
		public readonly UuidInterface $sdp_id,
		public readonly string $raw_user_id,
		public readonly UuidInterface $offer_client_id,
		public readonly string $role,
		public readonly ?UuidInterface $answer_client_id,
		public readonly ?string $answer_process_id,
		public readonly string $raw_offer,
		public readonly ?string $raw_answer,
		public readonly ?string $error_message,
		public readonly \DateTime $created_at,
		// deleted_atは含めない
	) {
	}

	public function toSDPOfferInfo(): SDPOfferInfo
	{
		$resObj = new SDPOfferInfo();
		$resObj->setData([
			'sdp_id' => $this->sdp_id->toString(),
			'offer_client_id' => $this->offer_client_id->toString(),
			'offer_client_role' => $this->role,
			'created_at' => Utils::utcDateStrOrNull($this->created_at),
			'offer' => base64_encode($this->raw_offer),
		]);
		return $resObj;
	}
}
