<?php

namespace dev_t0r\bids_rtc\signaling\service;

use dev_t0r\bids_rtc\signaling\model\ApplicationInfo;
use dev_t0r\bids_rtc\signaling\repo\AppTableRepo;
use dev_t0r\bids_rtc\signaling\RetValueOrError;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class AppManagementService
{
	private const int LIST_LIMIT = 1000;

	private readonly AppTableRepo $repo;
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->repo = new AppTableRepo($this->db, $this->logger);
	}


	public function getAppInfo(
		UuidInterface $appId,
	): ApplicationInfo {
		try {
			$appInfo = $this->repo->selectOne(
				$appId,
			);
			if ($appInfo == null) {
				throw RetValueOrError::withError(404, "Not Found: $appId");
			}

			return $appInfo->toApiAppInfo();
		} catch (\PDOException $e) {
			throw RetValueOrError::withError(500, "Database error: " . $e->getMessage());
		}
	}

	/**
	 * App情報をリストで返す
	 * @return ApplicationInfo[]
	 */
	public function getClientInfoList(
	): array {
		try {
			$appInfoList = $this->repo->selectAll(
				0,
				self::LIST_LIMIT,
			);
			$apiAppInfoList = array_map(
				fn($clientInfo) => $clientInfo->toApiAppInfo(),
				$appInfoList,
			);

			return $apiAppInfoList;
		} catch (\PDOException $e) {
			throw RetValueOrError::withError(500, "Database error: " . $e->getMessage());
		}
	}

	public function createApp(
		string $name,
		string $description,
		string $owner,
	): ApplicationInfo {
		try {
			$appId = Uuid::uuid7();

			$this->db->beginTransaction();
			$insertResult = $this->repo->createNewApp(
				$appId,
				$name,
				$description,
				$owner,
			);
			if ($insertResult == 0) {
				throw RetValueOrError::withError(500, "Database error: insert failed");
			}

			$appInfo = $this->repo->selectOne(
				$appId,
			);
			if ($appInfo == null) {
				throw RetValueOrError::withError(500, "Database error: selectOne failed");
			}

			$this->db->commit();
			return $appInfo->toApiAppInfo();
		} catch (\PDOException $e) {
			throw RetValueOrError::withError(500, "Database error: " . $e->getMessage());
		} finally {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
		}
	}
}
