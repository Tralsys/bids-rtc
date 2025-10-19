<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Repository;

use BidsRtc\Backend\Model\DbAppInfo;
use BidsRtc\Backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * アプリケーションテーブルのリポジトリ
 */
class AppTableRepository
{
	public function __construct(
		protected readonly PDO $db,
		protected readonly LoggerInterface $logger,
	) {
	}

	/**
	 * アプリケーション情報を1件取得
	 */
	public function selectOne(UuidInterface $app_id): ?DbAppInfo
	{
		$this->logger->debug('select AppTable (app_id: "{app_id}")', ['app_id' => $app_id]);

		try {
			$query = $this->db->prepare(<<<SQL
                SELECT
                    `name`,
                    `description`,
                    `owner`,
                    `created_at`
                FROM
                    `applications`
                WHERE
                    `app_id` = :app_id
                    AND `deleted_at` IS NULL
                SQL,
			);

			$query->bindValue(':app_id', $app_id->getBytes(), PDO::PARAM_STR);
			$query->execute();

			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning('select applications({app_id}) - rowCount is 0', ['app_id' => $app_id]);
				return null;
			}

			return new DbAppInfo(
				app_id: $app_id,
				name: $result['name'],
				description: $result['description'],
				owner: $result['owner'],
				created_at: $this->dbDateStrToDateTime($result['created_at']),
			);
		} catch (\PDOException $ex) {
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				['errorCode' => $ex->getCode(), 'errorInfo' => $ex->getMessage()],
			);
			throw $ex;
		}
	}

	/**
	 * すべてのアプリケーション情報を取得
	 *
	 * @return DbAppInfo[]
	 */
	public function selectAll(int $offset, int $limit): array
	{
		$this->logger->debug('select AppTable ALL (offset: "{offset}", limit: "{limit}")', [
			'offset' => $offset,
			'limit' => $limit,
		]);

		try {
			$query = $this->db->prepare(<<<SQL
                SELECT
                    `app_id`,
                    `name`,
                    `description`,
                    `owner`,
                    `created_at`
                FROM
                    `applications`
                WHERE
                    `deleted_at` IS NULL
                ORDER BY
                    `created_at` DESC
                LIMIT :limit OFFSET :offset
                SQL,
			);

			$query->bindValue(':limit', $limit, PDO::PARAM_INT);
			$query->bindValue(':offset', $offset, PDO::PARAM_INT);
			$query->execute();

			$results = $query->fetchAll(PDO::FETCH_ASSOC);

			return array_map(function ($row) {
				return new DbAppInfo(
					app_id: \Ramsey\Uuid\Uuid::fromBytes($row['app_id']),
					name: $row['name'],
					description: $row['description'],
					owner: $row['owner'],
					created_at: $this->dbDateStrToDateTime($row['created_at']),
				);
			}, $results);
		} catch (\PDOException $ex) {
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				['errorCode' => $ex->getCode(), 'errorInfo' => $ex->getMessage()],
			);
			throw $ex;
		}
	}

	/**
	 * 新しいアプリケーションを作成
	 */
	public function createNewApp(
		UuidInterface $app_id,
		string $name,
		string $description,
		string $owner,
	): int {
		$this->logger->debug('insert AppTable (app_id: "{app_id}")', ['app_id' => $app_id]);

		try {
			$query = $this->db->prepare(<<<SQL
                INSERT INTO `applications` (
                    `app_id`,
                    `name`,
                    `description`,
                    `owner`,
                    `created_at`
                ) VALUES (
                    :app_id,
                    :name,
                    :description,
                    :owner,
                    :created_at
                )
                SQL,
			);

			$query->bindValue(':app_id', $app_id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':name', $name, PDO::PARAM_STR);
			$query->bindValue(':description', $description, PDO::PARAM_STR);
			$query->bindValue(':owner', $owner, PDO::PARAM_STR);
			$query->bindValue(':created_at', Utils::getUtcNow()->format('Y-m-d H:i:s'), PDO::PARAM_STR);

			$query->execute();

			return $query->rowCount();
		} catch (\PDOException $ex) {
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				['errorCode' => $ex->getCode(), 'errorInfo' => $ex->getMessage()],
			);
			throw $ex;
		}
	}

	/**
	 * データベースの日時文字列をDateTimeオブジェクトに変換
	 */
	private function dbDateStrToDateTime(string $dateStr): \DateTime
	{
		$dt = \DateTime::createFromFormat('Y-m-d H:i:s', $dateStr, Utils::getUTC());
		if ($dt === false) {
			throw new \RuntimeException("Failed to parse date: $dateStr");
		}
		return $dt;
	}
}
