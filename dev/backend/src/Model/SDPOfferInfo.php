<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use OpenApi\Attributes as OA;

/**
 * SDP Offer情報
 */
#[OA\Schema(
  schema: 'SDPOfferInfo',
  title: 'SDP OfferInfo',
  description: 'SDP Offer情報',
  type: 'object',
  required: ['sdp_id', 'offer_client_id', 'offer_client_role', 'created_at', 'offer']
)]
class SDPOfferInfo implements \JsonSerializable
{
  public function __construct(
    #[OA\Property(
      property: 'sdp_id',
      description: 'SDP ID',
      type: 'string',
      format: 'uuid',
      readOnly: true
    )]
    public readonly string $sdp_id,

    #[OA\Property(
      property: 'offer_client_id',
      description: 'Offerを送信したクライアントID',
      type: 'string',
      format: 'uuid',
      readOnly: true
    )]
    public readonly string $offer_client_id,

    #[OA\Property(
      property: 'offer_client_role',
      description: 'Offerしたクライアントのロール',
      type: 'string',
      enum: ['provider', 'subscriber']
    )]
    public readonly string $offer_client_role,

    #[OA\Property(
      property: 'created_at',
      description: '作成日時',
      type: 'string',
      format: 'date-time',
      readOnly: true
    )]
    public readonly \DateTime $created_at,

    #[OA\Property(
      property: 'offer',
      description: 'Offer SDP (Base64エンコード済み)',
      type: 'string',
      format: 'byte'
    )]
    public readonly string $offer,
  ) {
  }

  public function jsonSerialize(): array
  {
    return [
      'sdp_id' => $this->sdp_id,
      'offer_client_id' => $this->offer_client_id,
      'offer_client_role' => $this->offer_client_role,
      'created_at' => $this->created_at->format(\DateTimeInterface::ATOM),
      'offer' => $this->offer,
    ];
  }
}
