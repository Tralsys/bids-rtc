<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Repository;

use BidsRtc\Backend\Model\DbSdpAnswer;
use BidsRtc\Backend\Model\DbSdpRecord;
use BidsRtc\Backend\Utils;
use PDO;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * SDP テーブルのリポジトリ
 */
class SdpTableRepository
{
  public function __construct(
    private readonly PDO $pdo,
  ) {
  }

  /**
   * SDP レコードを1件取得
   */
  public function selectOne(
    UuidInterface $sdpId,
    string $hashedUserId,
    UuidInterface $offerClientId,
  ): ?DbSdpRecord {
    try {
      $query = $this->pdo->prepare(<<<SQL
        SELECT
          `role`,
          `answer_client_id`,
          `offer`,
          `answer`,
          `created_at`,
          `updated_at`
        FROM
          `sdp`
        WHERE
          `sdp_id` = :sdp_id
          AND `user_id` = :hashed_user_id
          AND `offer_client_id` = :offer_client_id
          AND `deleted_at` IS NULL
        LIMIT 1
        SQL,
      );

      $query->bindValue(':sdp_id', $sdpId->getBytes(), PDO::PARAM_STR);
      $query->bindValue(':hashed_user_id', $hashedUserId, PDO::PARAM_STR);
      $query->bindValue(':offer_client_id', $offerClientId->getBytes(), PDO::PARAM_STR);

      $query->execute();
      $result = $query->fetch(PDO::FETCH_ASSOC);
      if ($result === false) {
        return null;
      }

      return new DbSdpRecord(
        sdp_id: $sdpId,
        user_id_hash: $hashedUserId,
        offer_client_id: $offerClientId,
        role: $result['role'],
        answer_client_id: Utils::uuidFromBytesOrNull($result['answer_client_id']),
        protected_offer: $result['offer'],
        protected_answer: $result['answer'],
        created_at: Utils::dbDateStrToDateTime($result['created_at']),
        updated_at: Utils::dbDateStrToDateTime($result['updated_at']),
      );
    } catch (\PDOException $ex) {
      throw $ex;
    }
  }

  /**
   * SDP Answer を取得
   */
  public function getAnswer(
    UuidInterface $sdpId,
    string $hashedUserId,
    UuidInterface $offerClientId,
  ): ?DbSdpAnswer {
    try {
      $query = $this->pdo->prepare(<<<SQL
        SELECT
          `sdp_id`,
          `answer_client_id`,
          `answer`
        FROM
          `sdp`
        WHERE
          `sdp_id` = :sdp_id
          AND `user_id` = :hashed_user_id
          AND `offer_client_id` = :offer_client_id
          AND `answer` IS NOT NULL
          AND `answer_client_id` IS NOT NULL
          AND `deleted_at` IS NULL
        LIMIT 1
        SQL,
      );

      $query->bindValue(':sdp_id', $sdpId->getBytes(), PDO::PARAM_STR);
      $query->bindValue(':hashed_user_id', $hashedUserId, PDO::PARAM_STR);
      $query->bindValue(':offer_client_id', $offerClientId->getBytes(), PDO::PARAM_STR);

      $query->execute();
      $result = $query->fetch(PDO::FETCH_ASSOC);
      if ($result === false) {
        return null;
      }

      $answerClientId = Utils::uuidFromBytesOrNull($result['answer_client_id']);
      if ($answerClientId === null) {
        return null;
      }

      return new DbSdpAnswer(
        sdp_id: $sdpId,
        answer_client_id: $answerClientId,
        protected_answer: $result['answer'],
      );
    } catch (\PDOException $ex) {
      throw $ex;
    }
  }

  /**
   * Offer を INSERT して sdp_id を返す
   */
  public function insertOffer(
    string $hashedUserId,
    UuidInterface $offerClientId,
    string $role,
    string $protectedOffer,
  ): ?UuidInterface {
    $sdpId = Uuid::uuid7();
    $now = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

    try {
      $query = $this->pdo->prepare(<<<SQL
        INSERT INTO `sdp` (
          `sdp_id`,
          `user_id`,
          `offer_client_id`,
          `role`,
          `offer`,
          `created_at`,
          `updated_at`
        ) VALUES (
          :sdp_id,
          :hashed_user_id,
          :offer_client_id,
          :role,
          :protected_offer,
          :created_at,
          :updated_at
        )
        SQL,
      );

      $query->bindValue(':sdp_id', $sdpId->getBytes(), PDO::PARAM_STR);
      $query->bindValue(':hashed_user_id', $hashedUserId, PDO::PARAM_STR);
      $query->bindValue(':offer_client_id', $offerClientId->getBytes(), PDO::PARAM_STR);
      $query->bindValue(':role', $role, PDO::PARAM_STR);
      $query->bindValue(':protected_offer', $protectedOffer, PDO::PARAM_STR);
      $query->bindValue(':created_at', $now, PDO::PARAM_STR);
      $query->bindValue(':updated_at', $now, PDO::PARAM_STR);

      $query->execute();
      if ($query->rowCount() === 0) {
        return null;
      }

      return $sdpId;
    } catch (\PDOException $ex) {
      throw $ex;
    }
  }

  /**
   * Offer を processing 状態に更新し影響行数を返す
   *
   * 1 クライアントが同時に 1 つの offer しか processing にできないよう
   * recent_answers サブクエリで制御する。
   *
   * @param UuidInterface[] $excludeOfferClientIds
   */
  public function setOfferAsProcessing(
    string $hashedUserId,
    string $targetRole,
    UuidInterface $answerClientId,
    array $excludeOfferClientIds,
    int $checkMinutes,
  ): int {
    $nowUtc = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    $recentCutoff = (new \DateTime('now', new \DateTimeZone('UTC')))
      ->modify('-1 minute')
      ->format('Y-m-d H:i:s.u');
    $offerCutoff = (new \DateTime('now', new \DateTimeZone('UTC')))
      ->modify("-{$checkMinutes} minutes")
      ->format('Y-m-d H:i:s.u');

    $excludeCount = count($excludeOfferClientIds);
    $queryStr = <<<SQL
      UPDATE `sdp` AS `sdp1`
      LEFT JOIN (
        SELECT
          `offer_client_id`
        FROM
          `sdp`
        WHERE
          `user_id` = :hashed_user_id
          AND `answer_client_id` IS NOT NULL
          AND `updated_at` >= :recent_cutoff
      ) AS `recent_answers`
      ON
        `sdp1`.`offer_client_id` = `recent_answers`.`offer_client_id`
      SET
        `sdp1`.`answer_client_id` = :answer_client_id
      WHERE
        `sdp1`.`user_id` = :hashed_user_id2
        AND `recent_answers`.`offer_client_id` IS NULL
        AND `sdp1`.`role` = :target_role
        AND `sdp1`.`answer_client_id` IS NULL
        AND `sdp1`.`deleted_at` IS NULL
        AND `sdp1`.`created_at` >= :offer_cutoff
      SQL;

    if ($excludeCount > 0) {
      $placeholders = implode(',', array_map(
        fn($i) => ":exclude_offer_client_id_{$i}",
        range(0, $excludeCount - 1),
      ));
      $queryStr .= " AND `sdp1`.`offer_client_id` NOT IN ({$placeholders})";
    }

    try {
      $query = $this->pdo->prepare($queryStr);

      $query->bindValue(':answer_client_id', $answerClientId->getBytes(), PDO::PARAM_STR);
      $query->bindValue(':hashed_user_id', $hashedUserId, PDO::PARAM_STR);
      $query->bindValue(':hashed_user_id2', $hashedUserId, PDO::PARAM_STR);
      $query->bindValue(':target_role', $targetRole, PDO::PARAM_STR);
      $query->bindValue(':recent_cutoff', $recentCutoff, PDO::PARAM_STR);
      $query->bindValue(':offer_cutoff', $offerCutoff, PDO::PARAM_STR);

      for ($i = 0; $i < $excludeCount; $i++) {
        $query->bindValue(":exclude_offer_client_id_{$i}", $excludeOfferClientIds[$i]->getBytes(), PDO::PARAM_STR);
      }

      $query->execute();
      return $query->rowCount();
    } catch (\PDOException $ex) {
      throw $ex;
    }
  }

  /**
   * Offer の processing 状態を解除し影響行数を返す
   */
  public function unsetOfferAsProcessing(
    string $hashedUserId,
    UuidInterface $answerClientId,
  ): int {
    try {
      $query = $this->pdo->prepare(<<<SQL
        UPDATE
          `sdp`
        SET
          `answer_client_id` = NULL
        WHERE
          `user_id` = :hashed_user_id
          AND `answer_client_id` = :answer_client_id
          AND `answer` IS NULL
          AND `deleted_at` IS NULL
        SQL,
      );

      $query->bindValue(':hashed_user_id', $hashedUserId, PDO::PARAM_STR);
      $query->bindValue(':answer_client_id', $answerClientId->getBytes(), PDO::PARAM_STR);

      $query->execute();
      return $query->rowCount();
    } catch (\PDOException $ex) {
      throw $ex;
    }
  }

  /**
   * 指定 answer_client_id がセット済みで answer 未登録の offer 一覧を取得
   *
   * @return DbSdpRecord[]
   */
  public function getOfferListWithAnswerId(
    string $hashedUserId,
    UuidInterface $answerClientId,
  ): array {
    try {
      $query = $this->pdo->prepare(<<<SQL
        SELECT
          `sdp_id`,
          `offer_client_id`,
          `role`,
          `offer`,
          `created_at`,
          `updated_at`
        FROM
          `sdp`
        WHERE
          `user_id` = :hashed_user_id
          AND `answer_client_id` = :answer_client_id
          AND `answer` IS NULL
          AND `deleted_at` IS NULL
        SQL,
      );

      $query->bindValue(':hashed_user_id', $hashedUserId, PDO::PARAM_STR);
      $query->bindValue(':answer_client_id', $answerClientId->getBytes(), PDO::PARAM_STR);

      $query->execute();

      $offerList = [];
      while ($result = $query->fetch(PDO::FETCH_ASSOC)) {
        $offerList[] = new DbSdpRecord(
          sdp_id: Uuid::fromBytes($result['sdp_id']),
          user_id_hash: $hashedUserId,
          offer_client_id: Uuid::fromBytes($result['offer_client_id']),
          role: $result['role'],
          answer_client_id: $answerClientId,
          protected_offer: $result['offer'],
          protected_answer: null,
          created_at: Utils::dbDateStrToDateTime($result['created_at']),
          updated_at: Utils::dbDateStrToDateTime($result['updated_at']),
        );
      }

      return $offerList;
    } catch (\PDOException $ex) {
      throw $ex;
    }
  }

  /**
   * Answer をセットし影響行数を返す
   */
  public function setAnswer(
    string $hashedUserId,
    UuidInterface $sdpId,
    UuidInterface $answerClientId,
    string $protectedAnswer,
  ): int {
    $now = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

    try {
      $query = $this->pdo->prepare(<<<SQL
        UPDATE
          `sdp`
        SET
          `answer` = :protected_answer,
          `updated_at` = :updated_at
        WHERE
          `sdp_id` = :sdp_id
          AND `user_id` = :hashed_user_id
          AND `answer_client_id` = :answer_client_id
          AND `deleted_at` IS NULL
        SQL,
      );

      $query->bindValue(':protected_answer', $protectedAnswer, PDO::PARAM_STR);
      $query->bindValue(':updated_at', $now, PDO::PARAM_STR);
      $query->bindValue(':sdp_id', $sdpId->getBytes(), PDO::PARAM_STR);
      $query->bindValue(':hashed_user_id', $hashedUserId, PDO::PARAM_STR);
      $query->bindValue(':answer_client_id', $answerClientId->getBytes(), PDO::PARAM_STR);

      $query->execute();
      return $query->rowCount();
    } catch (\PDOException $ex) {
      throw $ex;
    }
  }

  /**
   * SDP レコードを論理削除し影響行数を返す
   *
   * offer_client_id または answer_client_id が一致するレコードを削除する。
   */
  public function deleteRecord(
    string $hashedUserId,
    UuidInterface $sdpId,
    UuidInterface $clientId,
  ): int {
    $now = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

    try {
      $query = $this->pdo->prepare(<<<SQL
        UPDATE
          `sdp`
        SET
          `deleted_at` = :deleted_at
        WHERE
          `sdp_id` = :sdp_id
          AND `user_id` = :hashed_user_id
          AND (`offer_client_id` = :client_id OR `answer_client_id` = :client_id2)
          AND `deleted_at` IS NULL
        SQL,
      );

      $query->bindValue(':deleted_at', $now, PDO::PARAM_STR);
      $query->bindValue(':sdp_id', $sdpId->getBytes(), PDO::PARAM_STR);
      $query->bindValue(':hashed_user_id', $hashedUserId, PDO::PARAM_STR);
      $query->bindValue(':client_id', $clientId->getBytes(), PDO::PARAM_STR);
      $query->bindValue(':client_id2', $clientId->getBytes(), PDO::PARAM_STR);

      $query->execute();
      return $query->rowCount();
    } catch (\PDOException $ex) {
      throw $ex;
    }
  }

  /**
   * client_id に紐付く SDP レコードを全件論理削除し影響行数を返す
   *
   * クライアントが削除されたときに呼ばれる。
   * offer_client_id または answer_client_id が一致するレコードを削除する。
   */
  public function deleteRecordByClientId(
    string $hashedUserId,
    UuidInterface $clientId,
  ): int {
    $now = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

    try {
      $query = $this->pdo->prepare(<<<SQL
        UPDATE
          `sdp`
        SET
          `deleted_at` = :deleted_at
        WHERE
          `user_id` = :hashed_user_id
          AND (`offer_client_id` = :client_id OR `answer_client_id` = :client_id2)
          AND `deleted_at` IS NULL
        SQL,
      );

      $query->bindValue(':deleted_at', $now, PDO::PARAM_STR);
      $query->bindValue(':hashed_user_id', $hashedUserId, PDO::PARAM_STR);
      $query->bindValue(':client_id', $clientId->getBytes(), PDO::PARAM_STR);
      $query->bindValue(':client_id2', $clientId->getBytes(), PDO::PARAM_STR);

      $query->execute();
      return $query->rowCount();
    } catch (\PDOException $ex) {
      throw $ex;
    }
  }
}
