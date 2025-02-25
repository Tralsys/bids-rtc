<?php

namespace dev_t0r\bids_rtc\signaling\repo;

use dev_t0r\bids_rtc\signaling\model\DbClientInfo;
use dev_t0r\bids_rtc\signaling\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class ClientTableRepo
{
	public function __construct(
		protected readonly PDO $db,
		protected readonly LoggerInterface $logger,
	) {
	}

	public function count(
		string $hashed_user_id,
	): int {
		$this->logger->debug(
			'select ClientTable COUNT (hashed_user_id: "{hashed_user_id}")',
			[
				'hashed_user_id' => $hashed_user_id,
			],
		);

		try {
			// 削除済みのものも含めてカウントする
			$query = $this->db->prepare(<<<SQL
				SELECT
					COUNT(*) AS `count`
				FROM
					`clients`
				WHERE
					`user_id` = :hashed_user_id
				SQL,
			);

			$query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning(
					'select clients COUNT({hashed_user_id}) - rowCount is 0',
					[
						'hashed_user_id' => $hashed_user_id,
					],
				);
				return 0;
			}

			return (int) $result['count'];
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

	public function selectOne(
		string $hashed_user_id,
		UuidInterface $client_id,
	): ?DbClientInfo {
		$this->logger->debug(
			'select ClientTable (hashed_user_id: "{hashed_user_id}", client_id: "{client_id}")',
			[
				'hashed_user_id' => $hashed_user_id,
				'client_id' => $client_id,
			],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				SELECT
					`app_id`,
					`name`,
					`created_at`
				FROM
					`clients`
				WHERE
					`user_id` = :hashed_user_id
					AND `client_id` = :client_id
					AND `deleted_at` IS NULL
				SQL,
			);

			$query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
			$query->bindValue(':client_id', $client_id->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning(
					'select clients({hashed_user_id}.{client_id}) - rowCount is 0',
					[
						'hashed_user_id' => $hashed_user_id,
						'client_id' => $client_id,
					],
				);
				return null;
			}

			return new DbClientInfo(
				$client_id,
				Uuid::fromBytes($result['app_id']),
				$result['name'],
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

	public function selectOneRefreshToken(
		string $hashed_user_id,
		UuidInterface $client_id,
	): ?string {
		$this->logger->debug(
			'select ClientTable REFRESH_TOKEN (hashed_user_id: "{hashed_user_id}", client_id: "{client_id}")',
			[
				'hashed_user_id' => $hashed_user_id,
				'client_id' => $client_id,
			],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				SELECT
					`refresh_token`
				FROM
					`clients`
				WHERE
					`user_id` = :hashed_user_id
					AND `client_id` = :client_id
					AND `deleted_at` IS NULL
				SQL,
			);

			$query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
			$query->bindValue(':client_id', $client_id->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning(
					'select clients REFRESH_TOKEN({hashed_user_id}.{client_id}) - rowCount is 0',
					[
						'hashed_user_id' => $hashed_user_id,
						'client_id' => $client_id,
					],
				);
				return null;
			}

			return $result['refresh_token'];
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
	 * select all clients
	 *
	 * @param string $hashed_user_id
	 * @return DbClientInfo[]
	 */
	public function selectAll(
		string $hashed_user_id,
		int $offset,
		int $limit,
	): array {
		$this->logger->debug(
			'select ClientTable ALL (hashed_user_id: "{hashed_user_id}", offset: "{offset}", limit: "{limit}")',
			[
				'hashed_user_id' => $hashed_user_id,
				'offset' => $offset,
				'limit' => $limit,
			],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				SELECT
					`client_id`,
					`app_id`,
					`name`,
					`created_at`
				FROM
					`clients`
				WHERE
					`user_id` = :hashed_user_id
					AND `deleted_at` IS NULL
				ORDER BY
					`created_at` DESC
				LIMIT
					:offset, :limit
				SQL,
			);

			$query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
			$query->bindValue(':offset', $offset, PDO::PARAM_INT);
			$query->bindValue(':limit', $limit, PDO::PARAM_INT);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning(
					'select clients ALL({hashed_user_id}) - rowCount is 0',
					[
						'hashed_user_id' => $hashed_user_id,
					],
				);
				return [];
			}

			$clients = [];
			do {
				$clients[] = new DbClientInfo(
					Uuid::fromBytes($result['client_id']),
					Uuid::fromBytes($result['app_id']),
					$result['name'],
					Utils::dbDateStrToDateTime($result['created_at']),
				);
			} while ($result = $query->fetch(PDO::FETCH_ASSOC));
			return $clients;
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

	public function createNewClient(
		string $hashed_user_id,
		UuidInterface $app_id,
		UuidInterface $client_id,
		string $name,
		string $hashed_refresh_token,
	): int {
		$this->logger->debug(
			'insert ClientTable (hashed_user_id: "{hashed_user_id}", app_id: "{app_id}", client_id: "{client_id}", name: "{name}")',
			[
				'hashed_user_id' => $hashed_user_id,
				'app_id' => $app_id,
				'client_id' => $client_id,
				'name' => $name,
			],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				INSERT INTO
					`clients` (
						`user_id`,
						`client_id`,
						`app_id`,
						`name`,
						`refresh_token`
					)
				VALUES (
					:hashed_user_id,
					:client_id,
					:app_id,
					:name,
					:refresh_token
				)
				SQL,
			);

			$query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
			$query->bindValue(':client_id', $client_id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':app_id', $app_id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':name', $name, PDO::PARAM_STR);
			$query->bindValue(':refresh_token', $hashed_refresh_token, PDO::PARAM_STR);

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
		string $hashed_user_id,
		UuidInterface $client_id,
	): int {
		$this->logger->debug(
			'delete ClientTable (hashed_user_id: "{hashed_user_id}", client_id: "{client_id}")',
			[
				'hashed_user_id' => $hashed_user_id,
				'client_id' => $client_id,
			],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				UPDATE
					`clients`
				SET
					`deleted_at` = NOW()
				WHERE
					`user_id` = :hashed_user_id
					AND `client_id` = :client_id
					AND `deleted_at` IS NULL
				SQL,
			);

			$query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
			$query->bindValue(':client_id', $client_id->getBytes(), PDO::PARAM_STR);

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
