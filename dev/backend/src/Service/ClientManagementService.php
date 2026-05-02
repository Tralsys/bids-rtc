<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Service;

use BidsRtc\Backend\Model\ClientInfo;
use BidsRtc\Backend\Model\ClientInfoWithToken;
use BidsRtc\Backend\Model\ClientTokenPair;
use BidsRtc\Backend\Repository\ClientTableRepository;
use BidsRtc\Backend\RetValueOrError;
use BidsRtc\Backend\Service\Auth\MyJwtClaims;
use BidsRtc\Backend\Utils;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * クライアント管理サービス
 */
class ClientManagementService
{
  private const int LIST_LIMIT = 100;
  private const int MAX_CLIENT_COUNT_PER_USER = 10;

  private readonly ClientTableRepository $repo;
  private string $rawUserId = '';
  private string $hashedUserId = '';

  public function __construct(
    private readonly PDO $db,
    private readonly LoggerInterface $logger,
    private readonly MyJwtUtil $jwtUtil,
  ) {
    $this->repo = new ClientTableRepository($this->db, $this->logger);
  }

  /**
   * リクエストからユーザーIDを設定
   * 認証ミドルウェアで設定された属性から取得
   */
  public function setUserId(
    ServerRequestInterface $request,
    ResponseInterface $response,
  ): ?ResponseInterface {
    // 認証ミドルウェアから設定される想定
    $userId = $request->getAttribute('uid');

    if ($userId === null) {
      return Utils::withUnauthorizedError($response);
    }

    $this->setUserIdInternal($userId);
    return null;
  }

  /**
   * 内部用: ユーザーIDを設定
   */
  private function setUserIdInternal(string $userId): void
  {
    $this->rawUserId = $userId;
    $this->hashedUserId = Utils::getHashedUserId($userId);
  }

  /**
   * リフレッシュトークンの SHA-256 ハッシュを返す。
   * bcrypt は 72 バイト打ち切りがあり JWT には不適切なため SHA-256 を使用する。
   */
  private static function hashToken(string $token): string
  {
    return hash('sha256', $token);
  }

  /**
   * クライアント情報を削除
   */
  public function deleteClientInfo(UuidInterface $clientId): bool
  {
    try {
      $result = $this->repo->delete($this->hashedUserId, $clientId);
      return $result === 1;
    } catch (\PDOException $e) {
      throw new RetValueOrError(500, "Database error: " . $e->getMessage());
    }
  }

  /**
   * リフレッシュトークンを検証して新しいアクセストークンを発行する
   *
   * @throws RetValueOrError 400/401/404 のいずれか
   */
  public function getClientAccessToken(string $unverifiedRawRefreshToken): string
  {
    // JWT パース + 署名/issuer 検証
    $claims = $this->jwtUtil->parseAndValidate($unverifiedRawRefreshToken);

    // typ クレームがリフレッシュトークンであること
    if ($claims->keyType !== MyJwtClaims::KEY_TYPE_REFRESH) {
      throw new RetValueOrError(401, 'Token type mismatch');
    }

    // DB からハッシュ済みリフレッシュトークンを取得
    $hashedUid = Utils::getHashedUserId($claims->uid);

    try {
      $storedHash = $this->repo->selectOneRefreshToken($hashedUid, $claims->clientId);
    } catch (\PDOException $e) {
      throw new RetValueOrError(500, 'Database error: ' . $e->getMessage());
    }

    if ($storedHash === null) {
      throw new RetValueOrError(404, 'Client not found');
    }

    // SHA-256 でリフレッシュトークンの正当性を確認
    // (bcrypt は 72 バイト打ち切りがあるため JWT には不適切)
    if (!hash_equals(self::hashToken($unverifiedRawRefreshToken), $storedHash)) {
      throw new RetValueOrError(401, 'Invalid refresh token');
    }

    // 新しいアクセストークンを発行して返す
    return $this->jwtUtil->issueAccessToken($claims->uid, $claims->appId, $claims->clientId);
  }

  /**
   * リフレッシュトークンをローテーションし、新しいリフレッシュトークンとアクセストークンを返す。
   * CAS UPDATE により同一トークンの同時ローテーションを防ぐ。
   *
   * @throws RetValueOrError 400/401/404 のいずれか
   */
  public function rotateRefreshToken(string $unverifiedRawRefreshToken): ClientTokenPair
  {
    $claims = $this->jwtUtil->parseAndValidate($unverifiedRawRefreshToken);

    if ($claims->keyType !== MyJwtClaims::KEY_TYPE_REFRESH) {
      throw new RetValueOrError(401, 'Token type mismatch');
    }

    $hashedUid = Utils::getHashedUserId($claims->uid);

    try {
      $storedHash = $this->repo->selectOneRefreshToken($hashedUid, $claims->clientId);
    } catch (\PDOException $e) {
      throw new RetValueOrError(500, 'Database error: ' . $e->getMessage());
    }

    if ($storedHash === null) {
      throw new RetValueOrError(404, 'Client not found');
    }

    if (!hash_equals(self::hashToken($unverifiedRawRefreshToken), $storedHash)) {
      throw new RetValueOrError(401, 'Invalid refresh token');
    }

    $newRefreshToken = $this->jwtUtil->issueRefreshToken($claims->uid, $claims->appId, $claims->clientId);
    $newAccessToken  = $this->jwtUtil->issueAccessToken($claims->uid, $claims->appId, $claims->clientId);
    $newHash         = self::hashToken($newRefreshToken);

    try {
      $updated = $this->repo->updateRefreshTokenCas($hashedUid, $claims->clientId, $storedHash, $newHash);
    } catch (\PDOException $e) {
      throw new RetValueOrError(500, 'Database error: ' . $e->getMessage());
    }

    if ($updated === 0) {
      // 別リクエストがすでにローテーション済み
      throw new RetValueOrError(401, 'Refresh token already rotated');
    }

    return new ClientTokenPair($newRefreshToken, $newAccessToken);
  }

  /**
   * クライアント情報を取得
   */
  public function getClientInfo(UuidInterface $clientId): ClientInfo
  {
    try {
      $clientInfo = $this->repo->selectOne($this->hashedUserId, $clientId);

      if ($clientInfo === null) {
        throw new RetValueOrError(404, "Not Found: $clientId");
      }

      return $clientInfo->toApiClientInfo();
    } catch (\PDOException $e) {
      throw new RetValueOrError(500, "Database error: " . $e->getMessage());
    }
  }

  /**
   * クライアント情報をリストで返す
   *
   * @return ClientInfo[]
   */
  public function getClientInfoList(): array
  {
    try {
      $clientInfoList = $this->repo->selectAll($this->hashedUserId, 0, self::LIST_LIMIT);

      return array_map(
        fn($clientInfo) => $clientInfo->toApiClientInfo(),
        $clientInfoList,
      );
    } catch (\PDOException $e) {
      throw new RetValueOrError(500, "Database error: " . $e->getMessage());
    }
  }

  /**
   * 新しいクライアントを登録
   */
  public function registerClientInfo(
    UuidInterface $appId,
    string $name,
  ): ClientInfoWithToken {
    try {
      // クライアント数の上限チェック
      $clientCount = $this->repo->count($this->hashedUserId);
      if ($clientCount >= self::MAX_CLIENT_COUNT_PER_USER) {
        throw new RetValueOrError(400, 'Client count limit exceeded');
      }

      $clientId = Uuid::uuid7();

      // JWT リフレッシュトークンの生成
      $refreshToken = $this->jwtUtil->issueRefreshToken($this->rawUserId, $appId, $clientId);
      $refreshTokenHash = self::hashToken($refreshToken);

      $this->db->beginTransaction();

      $insertResult = $this->repo->createNewClient(
        $this->hashedUserId,
        $clientId,
        $appId,
        $name,
        $refreshTokenHash,
      );

      if ($insertResult === 0) {
        throw new RetValueOrError(500, "Database error: insert failed");
      }

      $clientInfo = $this->repo->selectOne($this->hashedUserId, $clientId);

      if ($clientInfo === null) {
        throw new RetValueOrError(500, "Database error: selectOne failed");
      }

      $this->db->commit();

      return $clientInfo->toApiClientInfoWithToken($refreshToken);
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
