<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use OpenApi\Attributes as OA;

/**
 * SDP Offer情報
 */
#[OA\Schema(
  schema: 'SDPOfferInfo',
  title: 'SDPOfferInfo',
  description: 'SDP Offer情報',
  type: 'object',
  required: ['offer_client_id', 'offer']
)]
class SDPOfferInfo implements \JsonSerializable
{
  public function __construct(
    #[OA\Property(
      property: 'offer_client_id',
      description: 'Offerを送信するクライアントID',
      type: 'string',
      format: 'uuid'
    )]
    public readonly string $offer_client_id,

    #[OA\Property(
      property: 'offer',
      description: 'SDP Offer（Base64エンコード済み）',
      type: 'string'
    )]
    public readonly string $offer,
  ) {
  }

  public function jsonSerialize(): array
  {
    return [
      'offer_client_id' => $this->offer_client_id,
      'offer' => $this->offer,
    ];
  }
}
