<?php

namespace dev_t0r\bids_rtc\signaling\model;

use dev_t0r\bids_rtc\signaling\Utils;
use Ramsey\Uuid\UuidInterface;

class DbAppInfo
{
	public function __construct(
		public readonly UuidInterface $app_id,
		public readonly string $name,
		public readonly string $description,
		public readonly string $owner,
		public readonly JsonDateTime $created_at,
	) {
	}

	public function toApiAppInfo(): ApplicationInfo
	{
		$resObj = new ApplicationInfo();
		$resObj->setData([
			'app_id' => $this->app_id->toString(),
			'name' => $this->name,
			'description' => $this->description,
			'owner' => $this->owner,
			'created_at' => $this->created_at,
		]);
		return $resObj;
	}
}
