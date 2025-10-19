<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Repository;

use BidsRtc\Backend\Model\DbClientInfo;
use BidsRtc\Backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * クライアントテーブルのリポジトリ
 */
class ClientTableRepository
{
  public function __construct(
    protected readonly PDO $db,
    protected readonly LoggerInterface $logger,
  ) {
  }

  /**
   * ユーザーのクライアント数をカウント
   */
  public function count(string $hashed_user_id): int
  {
    $this->logger->debug('select ClientTable COUNT (hashed_user_id: "{hashed_user_id}")', [
      'hashed_user_id' => $hashed_user_id,
    ]);

    try {
      $query = $this->db->prepare(<<<SQL
                SELECT COUNT(*) AS `count`
                FROM `clients`
                WHERE `user_id` = :hashed_user_id
                SQL,
      );

      $query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
      $query->execute();

      $result = $query->fetch(PDO::FETCH_ASSOC);
      if ($result === false) {
        return 0;
      }

      return (int) $result['count'];
    } catch (\PDOException $ex) {
      $this->logger->error("Failed to execute SQL ({errorCode} -> {errorInfo})", [
        'errorCode' => $ex->getCode(),
        'errorInfo' => $ex->getMessage(),
      ]);
      throw $ex;
    }
  }

  /**
   * クライアント情報を1件取得
   */
  public function selectOne(string $hashed_user_id, UuidInterface $client_id): ?DbClientInfo
  {
    $this->logger->debug('select ClientTable (hashed_user_id: "{hashed_user_id}", client_id: "{client_id}")', [
      'hashed_user_id' => $hashed_user_id,
      'client_id' => $client_id,
    ]);

    try {
      $query = $this->db->prepare(<<<SQL
                SELECT `app_id`, `name`, `created_at`
                FROM `clients`
                WHERE `user_id` = :hashed_user_id
                    AND `client_id` = :client_id
                    AND `deleted_at` IS NULL
                SQL,
      );

      $query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
      $query->bindValue(':client_id', $client_id->getBytes(), PDO::PARAM_STR);
      $query->execute();

      $result = $query->fetch(PDO::FETCH_ASSOC);
      if ($result === false) {
        return null;
      }

      return new DbClientInfo(
        client_id: $client_id,
        app_id: \Ramsey\Uuid\Uuid::fromBytes($result['app_id']),
        name: $result['name'],
        created_at: $this->dbDateStrToDateTime($result['created_at']),
      );
    } catch (\PDOException $ex) {
      $this->logger->error("Failed to execute SQL", ['error' => $ex->getMessage()]);
      throw $ex;
    }
  }

  /**
   * すべてのクライアント情報を取得
   *
   * @return DbClientInfo[]
   */
  public function selectAll(string $hashed_user_id, int $offset, int $limit): array
  {
    $this->logger->debug('select ClientTable ALL', [
      'hashed_user_id' => $hashed_user_id,
      'offset' => $offset,
      'limit' => $limit,
    ]);

    try {
      $query = $this->db->prepare(<<<SQL
                SELECT `client_id`, `app_id`, `name`, `created_at`
                FROM `clients`
                WHERE `user_id` = :hashed_user_id
                    AND `deleted_at` IS NULL
                ORDER BY `created_at` DESC
                LIMIT :limit OFFSET :offset
                SQL,
      );

      $query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
      $query->bindValue(':limit', $limit, PDO::PARAM_INT);
      $query->bindValue(':offset', $offset, PDO::PARAM_INT);
      $query->execute();

      $results = $query->fetchAll(PDO::FETCH_ASSOC);

      return array_map(function ($row) {
        return new DbClientInfo(
          client_id: \Ramsey\Uuid\Uuid::fromBytes($row['client_id']),
          app_id: \Ramsey\Uuid\Uuid::fromBytes($row['app_id']),
          name: $row['name'],
          created_at: $this->dbDateStrToDateTime($row['created_at']),
        );
      }, $results);
    } catch (\PDOException $ex) {
      $this->logger->error("Failed to execute SQL", ['error' => $ex->getMessage()]);
      throw $ex;
    }
  }

  /**
   * リフレッシュトークンのハッシュを取得
   */
  public function selectOneRefreshToken(string $hashed_user_id, UuidInterface $client_id): ?string
  {
    try {
      $query = $this->db->prepare(<<<SQL
                SELECT `refresh_token_hash`
                FROM `clients`
                WHERE `user_id` = :hashed_user_id
                    AND `client_id` = :client_id
                    AND `deleted_at` IS NULL
                SQL,
      );

      $query->bindValue(':hashed_user_id', $hashed_user_id, PDO::PARAM_STR);
      $query->bindValue(':client_id', $client_id->getBytes(), PDO::PARAM_STR);
      $query->execute();

      $result = $query->fetch(PDO::FETCH_ASSOC);
      if ($result === false) {
        return null;
      }

      return $result['refresh_token_hash'];
    } catch (\PDOException $ex) {
      $this->logger->error("Failed to execute SQL", ['error' => $ex->getMessage()]);
      throw $ex;
    }
  }

  /**
   * 新しいクライアントを作成
   */
  public function createNewClient(
    string $hashed_user_id,
    UuidInterface $client_id,
    UuidInterface $app_id,
    string $name,
    string $refreshTokenHash,
  ): int {
    try {
      $query = $this->db->prepare(<<<SQL
                INSERT INTO `clients` (
                    `user_id`, `client_id`, `app_id`, `name`,
                    `refresh_token_hash`, `created_at`
                ) VALUES (
                    :user_id, :client_id, :app_id, :name,
                    :refresh_token_hash, :created_at
                )
                SQL,
      );

      $query->bindValue(':user_id', $hashed_user_id, PDO::PARAM_STR);
      $query->bindValue(':client_id', $client_id->getBytes(), PDO::PARAM_STR);
      $query->bindValue(':app_id', $app_id->getBytes(), PDO::PARAM_STR);
      $query->bindValue(':name', $name, PDO::PARAM_STR);
      $query->bindValue(':refresh_token_hash', $refreshTokenHash, PDO::PARAM_STR);
      $query->bindValue(':created_at', Utils::getUtcNow()->format('Y-m-d H:i:s'), PDO::PARAM_STR);

      $query->execute();
      return $query->rowCount();
    } catch (\PDOException $ex) {
      $this->logger->error("Failed to execute SQL", ['error' => $ex->getMessage()]);
      throw $ex;
    }
  }

  /**
   * クライアントを削除（論理削除）
   */
  public function delete(string $hashed_user_id, UuidInterface $client_id): int
  {
    try {
      $query = $this->db->prepare(<<<SQL
                UPDATE `clients`
                SET `deleted_at` = :deleted_at
                WHERE `user_id` = :user_id
                    AND `client_id` = :client_id
                    AND `deleted_at` IS NULL
                SQL,
      );

      $query->bindValue(':user_id', $hashed_user_id, PDO::PARAM_STR);
      $query->bindValue(':client_id', $client_id->getBytes(), PDO::PARAM_STR);
      $query->bindValue(':deleted_at', Utils::getUtcNow()->format('Y-m-d H:i:s'), PDO::PARAM_STR);

      $query->execute();
      return $query->rowCount();
    } catch (\PDOException $ex) {
      $this->logger->error("Failed to execute SQL", ['error' => $ex->getMessage()]);
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
