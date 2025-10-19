<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Service;

use BidsRtc\Backend\Model\ApplicationInfo;
use BidsRtc\Backend\Repository\AppTableRepository;
use BidsRtc\Backend\RetValueOrError;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * アプリケーション管理サービス
 */
class AppManagementService
{
	private const int LIST_LIMIT = 1000;

	private readonly AppTableRepository $repo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->repo = new AppTableRepository($this->db, $this->logger);
	}

	/**
	 * アプリケーション情報を取得
	 */
	public function getAppInfo(UuidInterface $appId): ApplicationInfo
	{
		try {
			$appInfo = $this->repo->selectOne($appId);
			if ($appInfo === null) {
				throw new RetValueOrError(404, "Not Found: $appId");
			}

			return $appInfo->toApiAppInfo();
		} catch (\PDOException $e) {
			throw new RetValueOrError(500, "Database error: " . $e->getMessage());
		}
	}

	/**
	 * アプリケーション情報をリストで返す
	 *
	 * @return ApplicationInfo[]
	 */
	public function getAppInfoList(): array
	{
		try {
			$appInfoList = $this->repo->selectAll(0, self::LIST_LIMIT);

			return array_map(
				fn($appInfo) => $appInfo->toApiAppInfo(),
				$appInfoList,
			);
		} catch (\PDOException $e) {
			throw new RetValueOrError(500, "Database error: " . $e->getMessage());
		}
	}

	/**
	 * 新しいアプリケーションを作成
	 */
	public function createApp(
		string $name,
		string $description,
		string $owner,
	): ApplicationInfo {
		try {
			$appId = Uuid::uuid7();

			$this->db->beginTransaction();

			$insertResult = $this->repo->createNewApp($appId, $name, $description, $owner);
			if ($insertResult === 0) {
				throw new RetValueOrError(500, "Database error: insert failed");
			}

			$appInfo = $this->repo->selectOne($appId);
			if ($appInfo === null) {
				throw new RetValueOrError(500, "Database error: selectOne failed");
			}

			$this->db->commit();

			return $appInfo->toApiAppInfo();
		} catch (\PDOException $e) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			throw new RetValueOrError(500, "Database error: " . $e->getMessage());
		} catch (RetValueOrError $e) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			throw $e;
		}
	}
}
