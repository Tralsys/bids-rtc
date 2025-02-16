<?php

namespace dev_t0r\bids_rtc\signaling\model;

use dev_t0r\bids_rtc\signaling\model\DecryptedSdpRecord;
use dev_t0r\bids_rtc\signaling\model\PostSDPOfferInfoResponse;

class RegisterOfferAndGetAnswerableOffersResult
{
	public function __construct(
		public readonly DecryptedSdpRecord $registeredOffer,
		/** @var array<DecryptedSdpRecord> */
		public readonly array $receivedOfferArray,
	) {
	}

	public function toResObj(): PostSDPOfferInfoResponse
	{
		$receivedOfferArray = array_map(
			fn(DecryptedSdpRecord $record) => $record->toSDPOfferInfo(),
			$this->receivedOfferArray,
		);
		$resObj = new PostSDPOfferInfoResponse();
		$resObj->setData([
			'registered_offer' => $this->registeredOffer->toSDPOfferInfo(),
			'received_offers' => $receivedOfferArray,
		]);
		return $resObj;
	}
}
