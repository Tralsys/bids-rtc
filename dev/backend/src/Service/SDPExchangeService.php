<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Service;

use BidsRtc\Backend\Constants;
use BidsRtc\Backend\Model\PostSDPOfferInfoResponse;
use BidsRtc\Backend\Model\SDPAnswerInfo;
use BidsRtc\Backend\Model\SdpIdAndAnswer;
use BidsRtc\Backend\Model\SdpRoles;
use BidsRtc\Backend\Repository\SdpTableRepository;
use BidsRtc\Backend\RetValueOrError;
use BidsRtc\Backend\Utils;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * SDP 交換サービス
 */
class SDPExchangeService
{
  private const int MAX_EXEC_TIME_SEC = 15;
  private const int SLEEP_US = 1_000_000;
  private const int OFFER_CHECK_WITHIN_MINUTE = 60;

  private string $rawUserId = '';
  private string $hashedUserId = '';
  private ?UuidInterface $clientId = null;

  public function __construct(
    private readonly PDO $pdo,
    private readonly SdpTableRepository $sdpRepo,
    private readonly SDPEncryptAndDecrypt $encDec,
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * リクエストからユーザーIDとクライアントIDを設定する
   *
   * 成功時 null、失敗時はエラーレスポンスを返す。
   */
  public function setUserIdAndClientId(
    ServerRequestInterface $request,
    ResponseInterface $response,
  ): ?ResponseInterface {
    $userId = Utils::getUserIdOrNull($request);
    if ($userId === null) {
      return Utils::withUnauthorizedError($response);
    }

    $clientId = Utils::getClientIdFromHeaderOrNull($request);
    if ($clientId === null) {
      return Utils::withHeaderClientIdError($response);
    }

    // アクセストークン内の client_id claim とヘッダの一致を検証
    $clientIdFromToken = $request->getAttribute(Constants::ATTR_NAME_CLIENT_ID_FROM_TOKEN);
    if ($clientIdFromToken !== null && $clientId->toString() !== $clientIdFromToken) {
      return Utils::withError($response, 403, 'Client ID mismatch with token');
    }

    // tokenType が 'access' であることを確認
    $tokenType = $request->getAttribute('tokenType');
    if ($tokenType !== null && $tokenType !== 'access') {
      return Utils::withError($response, 401, 'Access token required');
    }

    $this->rawUserId = $userId;
    $this->hashedUserId = Utils::getHashedUserId($userId);
    $this->clientId = $clientId;

    return null;
  }

  /**
   * Offer を登録し、Answer 可能な Offer を取得する
   *
   * @param string $role offer のロール ('provider' | 'subscriber')
   * @param string $rawOffer Base64 エンコード済み offer 文字列
   * @param string[] $establishedClients 既に接続済みクライアントの UUID 文字列配列
   * @throws RetValueOrError
   */
  public function registerOfferAndGetAnswerableOffers(
    string $role,
    string $rawOffer,
    array $establishedClients,
  ): PostSDPOfferInfoResponse {
    if (!SdpRoles::isValid($role)) {
      throw new RetValueOrError(400, 'Invalid role');
    }

    // established_clients を UuidInterface[] に変換
    $establishedClientUuids = [];
    foreach ($establishedClients as $v) {
      if (!Uuid::isValid((string) $v)) {
        throw new RetValueOrError(400, 'Invalid UUID in established_clients');
      }
      $establishedClientUuids[] = Uuid::fromString((string) $v);
    }

    if (!$this->pdo->beginTransaction()) {
      $this->logger->error('SDPExchangeService: beginTransaction failed');
      throw new RetValueOrError(500, 'Database error: beginTransaction failed');
    }

    try {
      $registeredOffer = null;

      // 既存の自分の offer を削除してから新しい offer を登録する (旧コードと同じ流れ)
      if ($rawOffer !== '') {
        $this->sdpRepo->deleteRecordByClientId(
          $this->hashedUserId,
          $this->clientId,
        );

        $rawOfferBytes = base64_decode($rawOffer, true);
        if ($rawOfferBytes === false) {
          $this->pdo->rollBack();
          throw new RetValueOrError(400, 'Invalid base64 format for offer');
        }

        $protectedOffer = $this->encDec->encrypt($this->rawUserId, $rawOfferBytes);

        $sdpId = $this->sdpRepo->insertOffer(
          $this->hashedUserId,
          $this->clientId,
          $role,
          $protectedOffer,
        );
        if ($sdpId === null) {
          $this->pdo->rollBack();
          throw new RetValueOrError(500, 'Database error: insertOffer failed');
        }

        $dbRecord = $this->sdpRepo->selectOne(
          $sdpId,
          $this->hashedUserId,
          $this->clientId,
        );
        if ($dbRecord === null) {
          $this->pdo->rollBack();
          throw new RetValueOrError(500, 'Database error: selectOne failed');
        }

        $registeredOffer = $dbRecord->toApiOfferInfo($this->encDec, $this->rawUserId);
      } else {
        $this->logger->info('SDPExchangeService::registerOfferAndGetAnswerableOffers: offer is empty');
      }

      $targetRole = SdpRoles::targetOf($role);
      $count = $this->sdpRepo->setOfferAsProcessing(
        $this->hashedUserId,
        $targetRole,
        $this->clientId,
        $establishedClientUuids,
        self::OFFER_CHECK_WITHIN_MINUTE,
      );

      $receivedOffers = [];
      if ($count > 0) {
        $offerList = $this->sdpRepo->getOfferListWithAnswerId(
          $this->hashedUserId,
          $this->clientId,
        );
        if ($offerList === null) {
          $this->pdo->rollBack();
          throw new RetValueOrError(500, 'Database error: getOfferListWithAnswerId failed');
        }

        foreach ($offerList as $dbRec) {
          $receivedOffers[] = $dbRec->toApiOfferInfo($this->encDec, $this->rawUserId);
        }
      }

      if (!$this->pdo->commit()) {
        $this->logger->error('SDPExchangeService: commit failed');
        throw new RetValueOrError(500, 'Database error: commit failed');
      }

      $this->logger->debug('SDPExchangeService::registerOfferAndGetAnswerableOffers: success');

      return new PostSDPOfferInfoResponse(
        registered_offer: $registeredOffer,
        received_offers: count($receivedOffers) > 0 ? $receivedOffers : null,
      );
    } catch (\PDOException $e) {
      throw new RetValueOrError(500, 'Database error: ' . $e->getMessage());
    } finally {
      if ($this->pdo->inTransaction()) {
        $this->logger->error('SDPExchangeService: rolling back transaction');
        $this->pdo->rollBack();
      }
    }
  }

  /**
   * Answer を登録する
   *
   * @param SdpIdAndAnswer[] $answers
   * @throws RetValueOrError
   */
  public function registerAnswer(array $answers): void
  {
    if (!$this->pdo->beginTransaction()) {
      $this->logger->error('SDPExchangeService: beginTransaction failed');
      throw new RetValueOrError(500, 'Database error: beginTransaction failed');
    }

    try {
      foreach ($answers as $answer) {
        if (!Uuid::isValid($answer->sdp_id)) {
          $this->pdo->rollBack();
          throw new RetValueOrError(400, 'Invalid UUID for sdp_id: ' . $answer->sdp_id);
        }
        $sdpId = Uuid::fromString($answer->sdp_id);

        $rawAnswer = base64_decode($answer->answer, true);
        if ($rawAnswer === false) {
          $this->pdo->rollBack();
          throw new RetValueOrError(400, 'Invalid base64 format for answer');
        }

        $protectedAnswer = $this->encDec->encrypt($this->rawUserId, $rawAnswer);

        $result = $this->sdpRepo->setAnswer(
          $this->hashedUserId,
          $sdpId,
          $this->clientId,
          $protectedAnswer,
        );

        if ($result !== 1) {
          $this->pdo->rollBack();
          throw new RetValueOrError(404, 'Not Found: ' . $answer->sdp_id);
        }
      }

      $this->pdo->commit();
    } catch (\PDOException $e) {
      throw new RetValueOrError(500, 'Database error: ' . $e->getMessage());
    } finally {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }

      // processing 状態を解除する (成功・失敗問わず)
      try {
        $this->sdpRepo->unsetOfferAsProcessing(
          $this->hashedUserId,
          $this->clientId,
        );
      } catch (\PDOException $e) {
        $this->logger->error('SDPExchangeService: unsetOfferAsProcessing failed: ' . $e->getMessage());
      }
    }
  }

  /**
   * Answer を取得する (ポーリング型)
   *
   * タイムアウト時は RetValueOrError(204) をスローする。
   *
   * @throws RetValueOrError
   */
  public function getAnswer(UuidInterface $sdpId): SDPAnswerInfo
  {
    try {
      $startTime = time();
      do {
        $record = $this->sdpRepo->getAnswer(
          $sdpId,
          $this->hashedUserId,
          $this->clientId,
        );

        if ($record !== null) {
          return $record->toApiAnswerInfo($this->encDec, $this->rawUserId);
        }

        if (self::MAX_EXEC_TIME_SEC <= (time() - $startTime)) {
          break;
        }

        $this->logger->debug('SDPExchangeService::getAnswer: sleep');
        usleep(self::SLEEP_US);
      } while (true);
    } catch (\PDOException $e) {
      throw new RetValueOrError(500, 'Database error: ' . $e->getMessage());
    }

    throw new RetValueOrError(204, 'Timeout: ' . $sdpId->toString());
  }

  /**
   * SDP 交換レコードを削除する
   *
   * @throws RetValueOrError
   */
  public function deleteSDPExchange(UuidInterface $sdpId): bool
  {
    try {
      $result = $this->sdpRepo->deleteRecord(
        $this->hashedUserId,
        $sdpId,
        $this->clientId,
      );
      return $result === 1;
    } catch (\PDOException $e) {
      throw new RetValueOrError(500, 'Database error: ' . $e->getMessage());
    }
  }
}
