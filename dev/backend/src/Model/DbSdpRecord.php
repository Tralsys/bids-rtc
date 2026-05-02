<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use BidsRtc\Backend\RetValueOrError;
use BidsRtc\Backend\Service\SDPEncryptAndDecrypt;
use Ramsey\Uuid\UuidInterface;

/**
 * SDP テーブルの 1 行を保持する内部 DTO
 *
 * API 出力用ではないため OpenAPI 属性は付与しない。
 */
class DbSdpRecord
{
  public function __construct(
    public readonly UuidInterface $sdp_id,
    public readonly string $user_id_hash,
    public readonly UuidInterface $offer_client_id,
    public readonly string $role,
    public readonly ?UuidInterface $answer_client_id,
    /** 暗号化済みの生バイト列 (IV + 暗号文) */
    public readonly string $protected_offer,
    /** 暗号化済みの生バイト列 (IV + 暗号文)、未登録の場合 null */
    public readonly ?string $protected_answer,
    public readonly \DateTime $created_at,
    public readonly \DateTime $updated_at,
  ) {
  }

  /**
   * API レスポンス用の SDPOfferInfo に変換する
   *
   * protected_offer を復号して Base64 エンコードし SDPOfferInfo を生成する。
   * 復号に失敗した場合は RetValueOrError(500) をスローする。
   */
  public function toApiOfferInfo(
    SDPEncryptAndDecrypt $encDec,
    string $rawUserId,
  ): SDPOfferInfo {
    $plainOffer = $encDec->decrypt($rawUserId, $this->protected_offer);
    if ($plainOffer === null) {
      throw new RetValueOrError(500, 'Failed to decrypt SDP offer');
    }

    return new SDPOfferInfo(
      sdp_id: $this->sdp_id->toString(),
      offer_client_id: $this->offer_client_id->toString(),
      offer_client_role: $this->role,
      created_at: $this->created_at,
      offer: base64_encode($plainOffer),
    );
  }
}
