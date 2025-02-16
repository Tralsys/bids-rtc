<?php

namespace dev_t0r\bids_rtc\signaling\repo;

use dev_t0r\bids_rtc\signaling\model\SdpRecord;
use dev_t0r\bids_rtc\signaling\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class SdpTableRepo
{
	public function __construct(
		protected readonly PDO $db,
		protected readonly LoggerInterface $logger,
	) {
	}

	public function selectOne(
		UuidInterface $sdp_id,
		string $hashed_user_id,
		UuidInterface $offer_client_id,
	): ?SdpRecord {
		$this->logger->debug(
			'select SdpTable (sdp_id: "{sdp_id}", hashed_user_id: "{hashed_user_id}, offer_client_id: "{offer_client_id}")',
			[
				'sdp_id' => $sdp_id,
				'hashed_user_id' => $hashed_user_id,
				'offer_client_id' => $offer_client_id,
			],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				SELECT
					`role`,
					`answer_client_id`,
					`answer_process_id`,
					`offer`,
					`answer`,
					`error_message`,
					`created_at`
				FROM
					`sdp`
				WHERE
					`sdp_id` = :sdp_id
					AND `user_id` = :hashed_user_id
					AND `offer_client_id` = :offer_client_id
					AND `deleted_at` IS NULL
				SQL,
			);

			$query->bindValue(':sdp_id', $sdp_id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
			$query->bindValue(':offer_client_id', $offer_client_id->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning(
					'select sdp({sdp_id}) - rowCount is 0',
					[
						'sdp_id' => $sdp_id,
					],
				);
				return null;
			}

			return new SdpRecord(
				$sdp_id,
				$hashed_user_id,
				$offer_client_id,
				$result['role'],
				Utils::uuidFromBytesOrNull($result['answer_client_id']),
				$result['answer_process_id'],
				$result['offer'],
				$result['answer'],
				$result['error_message'],
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

	public function isOfferExistsInRange(
		string $hashed_user_id,
		string $offer_client_id,
		int $check_minutes,
	): bool {
		$this->logger->debug(
			'isOfferExistsInRange (hashed_user_id: "{hashed_user_id}, offer_client_id: "{offer_client_id}, check_minutes: "{check_minutes}")',
			[
				'hashed_user_id' => $hashed_user_id,
				'offer_client_id' => $offer_client_id,
				'check_minutes' => $check_minutes,
			],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				SELECT EXISTS(
					*
				FROM
					`sdp`
				WHERE
					`user_id` = :hashed_user_id
					AND `offer_client_id` = :offer_client_id
					AND `created_at` >= DATE_SUB(NOW(), INTERVAL :check_minutes MINUTE)
				) AS `exists`
				SQL,
			);

			$query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
			$query->bindValue(':offer_client_id', $offer_client_id, PDO::PARAM_STR);
			$query->bindValue(':check_minutes', $check_minutes, PDO::PARAM_INT);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			return $result && $result['exists'] === '1';
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


	public function insertOffer(
		string $hashed_user_id,
		UuidInterface $offer_client_id,
		string $role,
		string $protected_offer,
	): ?UuidInterface {
		$this->logger->debug(
			'insertOffer (hashed_user_id: "{hashed_user_id}, offer_client_id: "{offer_client_id}")',
			[
				'hashed_user_id' => $hashed_user_id,
				'offer_client_id' => $offer_client_id,
			],
		);

		$sdp_id = Uuid::uuid7();
		try {
			$query = $this->db->prepare(<<<SQL
				INSERT INTO `sdp` (
					`sdp_id`,
					`user_id`,
					`offer_client_id`,
					`role`,
					`offer`
				)
				VALUES (
					:sdp_id,
					:hashed_user_id,
					:offer_client_id,
					:role,
					NULL,
					NULL,
					:protected_offer
				)
				SQL,
			);

			$query->bindValue(':sdp_id', $sdp_id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
			$query->bindValue(':offer_client_id', $offer_client_id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':role', $role, PDO::PARAM_STR);
			$query->bindValue(':protected_offer', $protected_offer, PDO::PARAM_STR);

			$query->execute();
			$rowCount = $query->rowCount();
			if ($rowCount === 0) {
				$this->logger->warning(
					'insertOffer - rowCount is 0',
				);
				return null;
			}
			$this->logger->debug(
				'insertOffer - rowCount: {rowCount}',
				[
					'rowCount' => $rowCount,
				],
			);
			return $sdp_id;
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

	public function setAnswerProcessId(
		string $hashed_user_id,
		string $target_role,
		UuidInterface $answer_client_id,
		UuidInterface $answer_process_id,
		array $exclude_offer_client_ids,
		int $check_minutes,
	): int {
		$this->logger->debug(
			'setAnswerProcessId (hashed_user_id: "{hashed_user_id}, target_role: "{target_role}", answer_client_id:{answer_client_id}, answer_process_id: "{answer_process_id}", check_minutes: {check_minutes})',
			[
				'hashed_user_id' => $hashed_user_id,
				'target_role' => $target_role,
				'answer_client_id' => $answer_client_id,
				'answer_process_id' => $answer_process_id,
				'check_minutes' => $check_minutes,
			],
		);

		$excludeOfferClientIdListLength = count($exclude_offer_client_ids);
		$queryStr = <<<SQL
			UPDATE
				`sdp`
			SET
				`answer_client_id` = :answer_client_id,
				`answer_process_id` = :answer_process_id
			WHERE
				`user_id` = :hashed_user_id
				AND `role` = :target_role
				AND `answer_process_id` IS NULL
				AND `deleted_at` IS NULL
				AND `created_at` >= DATE_SUB(NOW(), INTERVAL :check_minutes MINUTE)
			SQL;
		if (0 < count($exclude_offer_client_ids)) {
			$queryStr .= ' AND `offer_client_id` NOT IN (';
			$queryStr .= implode(',', array_map(
				fn($i) => ":exclude_offer_client_id_$i",
				range(0, $excludeOfferClientIdListLength - 1),
			));
			$queryStr .= ')';
		}

		try {
			$query = $this->db->prepare($queryStr);

			$query->bindValue(':answer_client_id', $answer_client_id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':answer_process_id', $answer_process_id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
			$query->bindValue(':target_role', $target_role, PDO::PARAM_STR);
			for ($i = 0; $i < $excludeOfferClientIdListLength; $i++) {
				$query->bindValue(":exclude_offer_client_id_$i", $exclude_offer_client_ids[$i]->getBytes(), PDO::PARAM_STR);
			}

			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				'setAnswerProcessId - rowCount: {rowCount}',
				[
					'rowCount' => $rowCount,
				],
			);
			return $rowCount;
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

	public function getOfferListWithAnswerId(
		string $hashed_user_id,
		UuidInterface $answer_client_id,
		UuidInterface $answer_process_id,
	): array {
		$this->logger->debug(
			'getOfferWithAnswerId (hashed_user_id: "{hashed_user_id}, answer_client_id: "{answer_client_id}", answer_process_id: "{answer_process_id}")',
			[
				'hashed_user_id' => $hashed_user_id,
				'answer_client_id' => $answer_client_id,
				'answer_process_id' => $answer_process_id,
			],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				SELECT
					`sdp_id`,
					`offer_client_id`,
					`role`,
					`offer`
				FROM
					`sdp`
				WHERE
					`user_id` = :hashed_user_id
					AND `answer_client_id` = :answer_client_id
					AND `answer_process_id` = :answer_process_id
					AND `deleted_at` IS NULL
				SQL,
			);

			$query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
			$query->bindValue(':answer_client_id', $answer_client_id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':answer_process_id', $answer_process_id->getBytes(), PDO::PARAM_STR);

			$query->execute();

			$offerList = [];
			while ($result = $query->fetch(PDO::FETCH_ASSOC)) {
				$offerList[] = new SdpRecord(
					Uuid::fromBytes($result['sdp_id']),
					$hashed_user_id,
					Uuid::fromBytes($result['offer_client_id']),
					$result['role'],
					$answer_client_id,
					$answer_process_id,
					$result['offer'],
					null,
					null,
					Utils::dbDateStrToDateTime($result['created_at']),
				);
			}

			$this->logger->debug(
				'getOfferWithAnswerId - rowCount: {rowCount}',
				[
					'rowCount' => count($offerList),
				],
			);

			return $offerList;
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

	public function setAnswer(
		string $hashed_user_id,
		UuidInterface $sdp_id,
		UuidInterface $answer_client_id,
		UuidInterface $answer_process_id,
		string $protected_answer,
	): int {
		$this->logger->debug(
			'setAnswer (hashed_user_id: "{hashed_user_id}, sdp_id: "{sdp_id}, answer_client_id: "{answer_client_id}, answer_process_id: "{answer_process_id}")',
			[
				'hashed_user_id' => $hashed_user_id,
				'sdp_id' => $sdp_id,
				'answer_client_id' => $answer_client_id,
				'answer_process_id' => $answer_process_id,
			],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				UPDATE
					`sdp`
				SET
					`answer` = :protected_answer
				WHERE
					`sdo_id` = :offer_client_id
					AND `user_id` = :hashed_user_id
					AND `answer_client_id` = :answer_client_id
					AND `answer_process_id` = :answer_process_id
					AND `deleted_at` IS NULL
				SQL,
			);

			$query->bindValue(':protected_answer', $protected_answer, PDO::PARAM_STR);
			$query->bindValue(':sdp_id', $sdp_id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
			$query->bindValue(':answer_client_id', $answer_client_id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':answer_process_id', $answer_process_id->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				'setAnswer - rowCount: {rowCount}',
				[
					'rowCount' => $rowCount,
				],
			);
			return $rowCount;
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

	public function deleteRecord(
		string $hashed_user_id,
		UuidInterface $sdp_id,
		UuidInterface $client_id,
	) {
		$this->logger->debug(
			'deleteRecord (hashed_user_id: "{hashed_user_id}, sdp_id: "{sdp_id}, client_id: "{client_id}")',
			[
				'hashed_user_id' => $hashed_user_id,
				'sdp_id' => $sdp_id,
				'client_id' => $client_id,
			],
		);

		try {
			$query = $this->db->prepare(<<<SQL
				UPDATE
					`sdp`
				SET
					`deleted_at` = NOW()
				WHERE
					`sdp_id` = :sdp_id
					AND `user_id` = :hashed_user_id
					AND `offer_client_id` = :client_id
					AND `deleted_at` IS NULL
				SQL,
			);

			$query->bindValue(':sdp_id', $sdp_id->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
			$query->bindValue(':client_id', $client_id->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				'deleteRecord - rowCount: {rowCount}',
				[
					'rowCount' => $rowCount,
				],
			);
			return $rowCount;
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
