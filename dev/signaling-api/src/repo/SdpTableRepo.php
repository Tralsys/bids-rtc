<?php

namespace dev_t0r\bids_rtc\signaling\repo;

use dev_t0r\bids_rtc\signaling\model\SdpRecord;
use dev_t0r\bids_rtc\signaling\Utils;
use PDO;
use Psr\Log\LoggerInterface;
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
}
