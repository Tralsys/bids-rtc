<?php

namespace dev_t0r\bids_rtc\signaling\service;

use dev_t0r\bids_rtc\signaling\auth\MyAuthCheckResult;
use dev_t0r\bids_rtc\signaling\auth\MyAuthMiddleware;
use dev_t0r\bids_rtc\signaling\auth\MyAuthUtil;
use dev_t0r\bids_rtc\signaling\model\ClientInfo;
use dev_t0r\bids_rtc\signaling\model\ClientInfoWithToken;
use dev_t0r\bids_rtc\signaling\repo\ClientTableRepo;
use dev_t0r\bids_rtc\signaling\RetValueOrError;
use dev_t0r\bids_rtc\signaling\Utils;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class ClientManagementService
{
	const int LIST_LIMIT = 100;

	private readonly ClientTableRepo $repo;
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
		private readonly MyAuthUtil $authUtil,
	) {
		$this->repo = new ClientTableRepo($this->db, $this->logger);
	}

	private string $rawUserId;
	private string $hashedUserId;
	private SDPEncryptAndDecrypt $encryptAndDecrypt;
	public function setUserId(
		ServerRequestInterface $request,
		ResponseInterface $response,
	): ?ResponseInterface {
		$userId = MyAuthMiddleware::getUserIdOrNull($request);
		if ($userId == null) {
			return Utils::withUnauthorizedError($response);
		}

		$this->setUserIdInternal($userId);

		return null;
	}

	private function setUserIdInternal(
		string $userId,
	): void {
		$this->rawUserId = $userId;
		$this->hashedUserId = Utils::getHashedUserId($userId);
	}

	public function deleteClientInfo(
		UuidInterface $clientId,
	): bool {
		try {
			$result = $this->repo->delete(
				$this->hashedUserId,
				$clientId,
			);
			return $result == 1;
		} catch (\PDOException $e) {
			throw RetValueOrError::withError(500, "Database error: " . $e->getMessage());
		}
	}

	public function getClientAccessToken(
		string $unverifiedRawRefreshToken,
	): string {
		try {
			$refreshToken = $this->authUtil->parseMyToken($unverifiedRawRefreshToken);
			if ($refreshToken == null) {
				throw RetValueOrError::withError(400, "Invalid token");
			}

			$refreshTokenInfo = $this->authUtil->validateMyToken($refreshToken);
			if ($refreshTokenInfo->error != null) {
				throw $refreshTokenInfo->error;
			} else if ($refreshTokenInfo->keyType !== MyAuthCheckResult::KEY_TYPE_REFRESH) {
				throw RetValueOrError::withError(400, "Invalid token type");
			}

			$this->setUserIdInternal($refreshTokenInfo->uid);
			$appId = $refreshTokenInfo->appId;
			$clientId = $refreshTokenInfo->clientId;

			// 得られたclientIdを元に、DBからrefresh tokenのhashを取得
			$refreshTokenHash = $this->repo->selectOneRefreshToken(
				$this->hashedUserId,
				$clientId,
			);
			if ($refreshTokenHash == null) {
				throw RetValueOrError::withError(404, "Not Found: $clientId");
			}
			if (!password_verify($unverifiedRawRefreshToken, $refreshTokenHash)) {
				throw RetValueOrError::withError(401, "Invalid token");
			}

			return $this->authUtil->generateAccessToken(
				$this->rawUserId,
				$appId,
				$clientId,
			);
		} catch (\PDOException $e) {
			throw RetValueOrError::withError(500, "Database error: " . $e->getMessage());
		}
	}

	public function getClientInfo(
		UuidInterface $clientId,
	): ClientInfo {
		try {
			$clientInfo = $this->repo->selectOne(
				$this->hashedUserId,
				$clientId,
			);
			if ($clientInfo == null) {
				throw RetValueOrError::withError(404, "Not Found: $clientId");
			}

			return $clientInfo->toApiClientInfo();
		} catch (\PDOException $e) {
			throw RetValueOrError::withError(500, "Database error: " . $e->getMessage());
		}
	}

	/**
	 * クライアント情報をリストで返す
	 * @return array<ClientInfo>
	 */
	public function getClientInfoList(
	): array {
		try {
			$clientInfoList = $this->repo->selectAll(
				$this->hashedUserId,
				0,
				self::LIST_LIMIT,
			);
			$apiClientInfoList = array_map(
				fn($clientInfo) => $clientInfo->toApiClientInfo(),
				$clientInfoList,
			);

			return $apiClientInfoList;
		} catch (\PDOException $e) {
			throw RetValueOrError::withError(500, "Database error: " . $e->getMessage());
		}
	}

	public function registerClientInfo(
		UuidInterface $appId,
		string $name,
	): ClientInfoWithToken {
		try {
			$count = $this->repo->count(
				$this->hashedUserId,
			);
			if (self::LIST_LIMIT <= $count) {
				throw RetValueOrError::withError(400, "Too many clients");
			}

			$clientId = Uuid::uuid7();
			$refreshToken = $this->authUtil->generateRefreshToken(
				$this->rawUserId,
				$appId,
				$clientId,
			);
			$hashedRefreshToken = password_hash($refreshToken, PASSWORD_DEFAULT);

			$this->db->beginTransaction();
			$insertResult = $this->repo->createNewClient(
				$this->hashedUserId,
				$appId,
				$clientId,
				$name,
				$hashedRefreshToken,
			);
			if ($insertResult == 0) {
				throw RetValueOrError::withError(500, "Database error: insert failed");
			}

			$clientInfo = $this->repo->selectOne(
				$this->hashedUserId,
				$clientId,
			);
			if ($clientInfo == null) {
				throw RetValueOrError::withError(500, "Database error: selectOne failed");
			}

			$this->db->commit();
			return $clientInfo->toApiClientInfoWithToken($refreshToken);
		} catch (\PDOException $e) {
			throw RetValueOrError::withError(500, "Database error: " . $e->getMessage());
		} finally {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
		}
	}
}
