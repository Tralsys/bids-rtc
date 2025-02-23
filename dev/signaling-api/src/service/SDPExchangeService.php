<?php

namespace dev_t0r\bids_rtc\signaling\service;

use dev_t0r\bids_rtc\signaling\auth\MyAuthMiddleware;
use dev_t0r\bids_rtc\signaling\model\DecryptedSdpAnswer;
use dev_t0r\bids_rtc\signaling\repo\SdpTableRepo;
use dev_t0r\bids_rtc\signaling\RetValueOrError;
use dev_t0r\bids_rtc\signaling\Utils;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use dev_t0r\bids_rtc\signaling\model\RegisterOfferAndGetAnswerableOffersResult;

class SDPExchangeService
{
	public const string ROLE_PROVIDER = 'provider';
	public const string ROLE_SUBSCRIBER = 'subscriber';
	private const int MAX_EXEC_TIME_SEC = 15;
	private const int SLEEP_US = 1000 * 1000;
	private const int OFFER_CHECK_WITHIN_MINUTE = 60;

	private readonly SdpTableRepo $repo;
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->repo = new SdpTableRepo($this->db, $this->logger);
	}

	private string $rawUserId;
	private string $hashedUserId;
	private UuidInterface $clientId;
	private SDPEncryptAndDecrypt $encryptAndDecrypt;
	public function setUserIdAndClientId(
		ServerRequestInterface $request,
		ResponseInterface $response,
	): ?ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId == null) {
			return Utils::withUnauthorizedError($response);
		}

		$clientId = Utils::getClientIdOrNull($request);
		if ($clientId == null) {
			return Utils::withHeaderClientIdError($response);
		}

		$this->rawUserId = $userId;
		$this->hashedUserId = Utils::getHashedUserId($userId);
		$this->clientId = $clientId;
		$this->encryptAndDecrypt = new SDPEncryptAndDecrypt($userId);

		return null;
	}

	public function deleteSDPExchange(
		UuidInterface $sdpId,
	): bool {
		try {
			$result = $this->repo->deleteRecord(
				$this->hashedUserId,
				$sdpId,
				$this->clientId,
			);
			return $result === 1;
		} catch (\PDOException $e) {
			throw RetValueOrError::withError(500, "Database error: " . $e->getMessage());
		}
	}

	public function getAnswer(
		UuidInterface $sdpId,
	): ?DecryptedSdpAnswer {
		try {
			$startTime = time();
			do {
				$record = $this->repo->getAnswer(
					$sdpId,
					$this->hashedUserId,
					$this->clientId,
				);
				if ($record == null) {
					throw RetValueOrError::withError(404, "Not Found: $sdpId");
				}
				if ($record->protected_answer == null) {
					if (self::MAX_EXEC_TIME_SEC <= (time() - $startTime)) {
						break;
					}
					$this->logger->debug("getAnswer: sleep");
					usleep(self::SLEEP_US);
					continue;
				}

				return $record->decrypt($this->encryptAndDecrypt);
			} while (true);
		} catch (\PDOException $e) {
			throw RetValueOrError::withError(500, "Database error: " . $e->getMessage());
		}
		throw RetValueOrError::withError(204, "Timeout: $sdpId");
	}

	/**
	 * Answerを登録する
	 *
	 * @param array<\dev_t0r\bids_rtc\signaling\model\SdpIdAndAnswer> $answerArray Answerの配列
	 * @return array<string> 結果メッセージの配列
	 */
	public function registerAnswer(
		array $answerArray,
	): void {
		if (!$this->db->beginTransaction()) {
			$this->logger->error("Database error: beginTransaction failed");
			throw RetValueOrError::withError(500, "Database error: beginTransaction failed");
		}
		try {
			foreach ($answerArray as $answer) {
				$result = $this->repo->setAnswer(
					$this->hashedUserId,
					$answer->sdpId,
					$this->clientId,
					$answer->getProtectedAnswer($this->encryptAndDecrypt),
				);
				if ($result !== 1) {
					$this->db->rollBack();
					throw RetValueOrError::withError(404, "Not Found: $answer->sdpId");
				}
			}
			$this->db->commit();
		} catch (\PDOException $e) {
			throw RetValueOrError::withError(500, "Database error: " . $e->getMessage());
		} finally {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}

			try {
				$this->repo->unsetOfferAsProcessing(
					$this->hashedUserId,
					$this->clientId,
				);
			} catch (\PDOException $e) {
				throw RetValueOrError::withError(500, "Database error: " . $e->getMessage());
			}
		}
	}

	/**
	 * Offerを登録し、Answer可能なOfferを取得する
	 *
	 * @param string $role
	 * @param ?string $rawOffer
	 * @param array<\Ramsey\Uuid\UuidInterface> $establishedClients
	 * @return \dev_t0r\bids_rtc\signaling\model\RegisterOfferAndGetAnswerableOffersResult
	 */
	public function registerOfferAndGetAnswerableOffers(
		string $role,
		?string $rawOffer,
		array $establishedClients,
	): RegisterOfferAndGetAnswerableOffersResult {
		if (!$this->db->beginTransaction()) {
			$this->logger->error("Database error: beginTransaction failed");
			throw RetValueOrError::withError(500, "Database error: beginTransaction failed");
		}
		try {
			if ($rawOffer == null || $rawOffer === "") {
				$this->logger->info("registerOfferAndGetAnswerableOffers: offer is empty");
			} else {
				$this->repo->deleteRecordByClientId(
					$this->hashedUserId,
					$this->clientId,
				);
				$sdpId = $this->repo->insertOffer(
					$this->hashedUserId,
					$this->clientId,
					$role,
					$this->encryptAndDecrypt->encrypt($rawOffer),
				);
				if ($sdpId == null) {
					$this->db->rollBack();
					throw RetValueOrError::withError(500, "Database error: insertOffer failed");
				}

				$registeredOffer = $this->repo->selectOne(
					$sdpId,
					$this->hashedUserId,
					$this->clientId,
				);
				if ($registeredOffer == null) {
					$this->db->rollBack();
					throw RetValueOrError::withError(500, "Database error: selectOne failed");
				}
			}

			$targetRole = $role === self::ROLE_PROVIDER ? self::ROLE_SUBSCRIBER : self::ROLE_PROVIDER;
			$count = $this->repo->setOfferAsProcessing(
				$this->hashedUserId,
				$targetRole,
				$this->clientId,
				$establishedClients,
				self::OFFER_CHECK_WITHIN_MINUTE,
			);
			$answerableOffers = [];
			if (0 < $count) {
				$answerableOffers = $this->repo->getOfferListWithAnswerId(
					$this->hashedUserId,
					$this->clientId,
				);
				if ($answerableOffers == null || count($answerableOffers) !== $count) {
					$this->db->rollBack();
					throw RetValueOrError::withError(500, "Database error: getOfferListWithAnswerId failed");
				}
			}

			$resObj = new RegisterOfferAndGetAnswerableOffersResult(
				$registeredOffer->decrypt($this->rawUserId, $this->encryptAndDecrypt),
				array_map(
					fn($v) => $v->decrypt($this->rawUserId, $this->encryptAndDecrypt),
					$answerableOffers,
				),
			);

			if (!$this->db->commit()) {
				$this->logger->error("Database error: commit failed");
				throw RetValueOrError::withError(500, "Database error: commit failed");
			}
			$this->logger->debug("registerOfferAndGetAnswerableOffers: success");

			return $resObj;
		} catch (\PDOException $e) {
			throw RetValueOrError::withError(500, "Database error: " . $e->getMessage());
		} finally {
			if ($this->db->inTransaction()) {
				$this->logger->error("Database error: rollback");
				$this->db->rollBack();
			}
		}
	}
}
