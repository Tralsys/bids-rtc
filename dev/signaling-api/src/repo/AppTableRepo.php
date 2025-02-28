<?php

namespace dev_t0r\bids_rtc\signaling\repo;

use dev_t0r\bids_rtc\signaling\model\DbAppInfo;
use dev_t0r\bids_rtc\signaling\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class AppTableRepo
{
	public function __construct(
		protected readonly PDO $db,
		protected readonly LoggerInterface $logger,
	) {
	}

	public function selectOne(
		UuidInterface $app_id,
	): ?DbAppInfo {
		$this->logger->debug(
			'select AppTable (app_id: "{app_id}")',
			[
				'app_id' => $app_id,
			],
		);

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
				$this->logger->warning(
					'select applications({app_id}) - rowCount is 0',
					[
						'app_id' => $app_id,
					],
				);
				return null;
			}

			return new DbAppInfo(
				$app_id,
				$result['name'],
				$result['description'],
				$result['owner'],
				Utils::dbDateStrToDateTime($result['created_at']),
			);
		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);

			throw $ex;
		}
	}

	/**
	 * select all apps
	 *
	 * @return DbAppInfo[]
	 */
	public function selectAll(
		int $offset,
		int $limit,
	): array {
		$this->logger->debug(
			'select AppTable ALL (offset: "{offset}", limit: "{limit}")',
			[
				'offset' => $offset,
				'limit' => $limit,
			],
		);

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
				LIMIT
					:offset, :limit
				SQL,
			);

			$query->bindValue(':offset', $offset, PDO::PARAM_INT);
			$query->bindValue(':limit', $limit, PDO::PARAM_INT);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning(
					'select apps ALL - rowCount is 0',
				);
				return [];
			}

			$apps = [];
			do {
				$apps[] = new DbAppInfo(
					Uuid::fromBytes($result['app_id']),
					$result['name'],
					$result['description'],
					$result['owner'],
					Utils::dbDateStrToDateTime($result['created_at']),
				);
			} while ($result = $query->fetch(PDO::FETCH_ASSOC));
			return $apps;
		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);

			throw $ex;
		}
	}

	public function createNewApp(
		UuidInterface $app_id,
		string $name,
		string $description,
		string $owner,
	): int {
		$this->logger->debug(
			'insert AppTable (app_id: "{app_id}", name: "{name}", description: "{description}", owner: "{owner}")',
			[
				'app_id' => $app_id,
				'name' => $name,
				'description' => $description,
				'owner' => $owner,
			],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				INSERT INTO
					`applications` (
						`app_id`,
						`name`,
						`description`,
						`owner`
					)
				VALUES (
					:app_id,
					:name,
					:description,
					:owner
				)
				SQL,
			);

			$query->bindValue(':app_id', $app_id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':name', $name, PDO::PARAM_STR);
			$query->bindValue(':description', $description, PDO::PARAM_STR);
			$query->bindValue(':owner', $owner, PDO::PARAM_STR);

			$query->execute();
			return $query->rowCount();
		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);

			throw $ex;
		}
	}

	public function delete(
		UuidInterface $app_id,
	): int {
		$this->logger->debug(
			'delete AppTable (app_id: "{app_id}")',
			[
				'app_id' => $app_id,
			],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				UPDATE
					`applications`
				SET
					`deleted_at` = NOW()
				WHERE
					`app_id` = :app_id
					AND `deleted_at` IS NULL
				SQL,
			);

			$query->bindValue(':app_id', $app_id->getBytes(), PDO::PARAM_STR);

			$query->execute();
			return $query->rowCount();
		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);

			throw $ex;
		}
	}
}
