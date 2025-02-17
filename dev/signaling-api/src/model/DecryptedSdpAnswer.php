<?php

namespace dev_t0r\bids_rtc\signaling\model;

use dev_t0r\bids_rtc\signaling\Utils;
use Ramsey\Uuid\UuidInterface;

class DecryptedSdpAnswer
{
	public function __construct(
		public readonly UuidInterface $sdp_id,
		public readonly UuidInterface $answer_client_id,
		public readonly string $raw_answer,
	) {
	}

	public function toSDPOfferInfo(): SDPAnswerInfo
	{
		$resObj = new SDPAnswerInfo();
		$resObj->setData([
			'sdp_id' => $this->sdp_id->toString(),
			'answer_client_id' => $this->answer_client_id->toString(),
			'answer' => base64_encode($this->raw_answer),
		]);
		return $resObj;
	}
}
