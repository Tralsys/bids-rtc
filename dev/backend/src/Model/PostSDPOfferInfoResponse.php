<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use OpenApi\Attributes as OA;

/**
 * SDP Offer登録レスポンス
 */
#[OA\Schema(
  schema: 'PostSDPOfferInfoResponse',
  title: 'SDP OfferInfo POST Response',
  description: 'SDP Offer登録レスポンス',
  type: 'object'
)]
class PostSDPOfferInfoResponse implements \JsonSerializable
{
  public function __construct(
    #[OA\Property(
      property: 'registered_offer',
      ref: '#/components/schemas/SDPOfferInfo',
      nullable: true
    )]
    public readonly ?SDPOfferInfo $registered_offer = null,

    #[OA\Property(
      property: 'received_offers',
      type: 'array',
      items: new OA\Items(ref: '#/components/schemas/SDPOfferInfo'),
      nullable: true
    )]
    public readonly ?array $received_offers = null,
  ) {
  }

  public function jsonSerialize(): array
  {
    $data = [];
    if ($this->registered_offer !== null) {
      $data['registered_offer'] = $this->registered_offer;
    }
    if ($this->received_offers !== null) {
      $data['received_offers'] = $this->received_offers;
    }
    return $data;
  }
}
