<?php

namespace dev_t0r\bids_rtc\signaling\service;

use dev_t0r\bids_rtc\signaling\auth\MyAuthMiddleware;
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
	) {
		$this->repo = new ClientTableRepo($this->db, $this->logger);
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

		$this->rawUserId = $userId;
		$this->hashedUserId = Utils::getHashedUserId($userId);

		return null;
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
		// JWT検証
		// JWT形式エラーは400
		// JWT署名エラーは401
		// JWT有効期限切れは401
		// RefreshTokenではないものだったら400

		// RefreshTokenからclientIdを取得
		// clientIdが存在しない場合は400

		// 得られたclientIdを元に、DBからrefresh tokenのhashを取得
		// hashが存在しない場合は404
		// hashが一致しない場合は401

		// hashが一致したら、新しいAccessTokenを生成して返す
		// 生成に失敗したら500
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

	public function getClientInfoList(
	): array {
		try {
			$clientInfoList = $this->repo->selectAll(
				$this->hashedUserId,
				0,
				self::LIST_LIMIT,
			);
			$clientInfoList = array_map(
				fn($clientInfo) => $clientInfo->toApiClientInfo(),
				$clientInfoList,
			);

			return $clientInfoList;
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
			// TODO: リフレッシュトークン生成
			$refreshToken = "dummy";
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
