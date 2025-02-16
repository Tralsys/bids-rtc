<?php

namespace dev_t0r\bids_rtc\signaling\service;

use dev_t0r\bids_rtc\signaling\auth\MyAuthMiddleware;
use dev_t0r\bids_rtc\signaling\model\DecryptedSdpRecord;
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
		throw RetValueOrError::withError(501, 'Not implemented');
	}

	public function getAnswer(
		UuidInterface $sdpId,
	): ?DecryptedSdpRecord {
		throw RetValueOrError::withError(501, 'Not implemented');
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
		throw RetValueOrError::withError(501, 'Not implemented');
	}

	/**
	 * Offerを登録し、Answer可能なOfferを取得する
	 *
	 * @param string $role
	 * @param string $rawOffer
	 * @param array<\Ramsey\Uuid\UuidInterface> $establishedClients
	 * @return \dev_t0r\bids_rtc\signaling\model\RegisterOfferAndGetAnswerableOffersResult
	 */
	public function registerOfferAndGetAnswerableOffers(
		string $role,
		string $rawOffer,
		array $establishedClients,
	): RegisterOfferAndGetAnswerableOffersResult {
		throw RetValueOrError::withError(501, 'Not implemented');
	}
}
