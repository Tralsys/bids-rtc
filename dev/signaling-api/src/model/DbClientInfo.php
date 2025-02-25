<?php

namespace dev_t0r\bids_rtc\signaling\model;

use dev_t0r\bids_rtc\signaling\Utils;
use Ramsey\Uuid\UuidInterface;

class DbClientInfo
{
	public function __construct(
		public readonly UuidInterface $client_id,
		public readonly UuidInterface $app_id,
		public readonly string $name,
		public readonly JsonDateTime $created_at,
	) {
	}

	public function toApiClientInfo(): ClientInfo
	{
		$resObj = new ClientInfo();
		$resObj->setData([
			'client_id' => $this->client_id->toString(),
			'app_id' => $this->app_id->toString(),
			'name' => $this->name,
			'created_at' => $this->created_at,
		]);
		return $resObj;
	}

	public function toApiClientInfoWithToken(
		string $refreshToken,
	): ClientInfoWithToken {
		$resObj = new ClientInfoWithToken();
		$resObj->setData([
			'client_id' => $this->client_id->toString(),
			'app_id' => $this->app_id->toString(),
			'name' => $this->name,
			'created_at' => $this->created_at,
			'refresh_token' => $refreshToken,
		]);
		return $resObj;
	}
}
