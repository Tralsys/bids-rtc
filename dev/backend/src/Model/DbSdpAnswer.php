<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use BidsRtc\Backend\RetValueOrError;
use BidsRtc\Backend\Service\SDPEncryptAndDecrypt;
use Ramsey\Uuid\UuidInterface;

/**
 * SDP Answer の内部 DTO
 *
 * API 出力用ではないため OpenAPI 属性は付与しない。
 */
class DbSdpAnswer
{
  public function __construct(
    public readonly UuidInterface $sdp_id,
    public readonly UuidInterface $answer_client_id,
    /** 暗号化済みの生バイト列 (IV + 暗号文) */
    public readonly string $protected_answer,
  ) {
  }

  /**
   * API レスポンス用の SDPAnswerInfo に変換する
   *
   * protected_answer を復号して Base64 エンコードし SDPAnswerInfo を生成する。
   * 復号に失敗した場合は RetValueOrError(500) をスローする。
   */
  public function toApiAnswerInfo(
    SDPEncryptAndDecrypt $encDec,
    string $rawUserId,
  ): SDPAnswerInfo {
    $plainAnswer = $encDec->decrypt($rawUserId, $this->protected_answer);
    if ($plainAnswer === null) {
      throw new RetValueOrError(500, 'Failed to decrypt SDP answer');
    }

    return SDPAnswerInfo::fromDb(
      sdpId: $this->sdp_id->toString(),
      answerClientId: $this->answer_client_id->toString(),
      answer: base64_encode($plainAnswer),
    );
  }
}
