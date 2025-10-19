<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use OpenApi\Attributes as OA;

/**
 * SDP Offer登録レスポンス
 */
#[OA\Schema(
  schema: 'PostSDPOfferInfoResponse',
  title: 'PostSDPOfferInfoResponse',
  description: 'SDP Offer登録レスポンス',
  type: 'object',
  required: ['sdp_id']
)]
class PostSDPOfferInfoResponse implements \JsonSerializable
{
  public function __construct(
    #[OA\Property(
      property: 'sdp_id',
      description: 'SDP交換ID',
      type: 'string',
      format: 'uuid'
    )]
    public readonly string $sdp_id,
  ) {
  }

  public function jsonSerialize(): array
  {
    return [
      'sdp_id' => $this->sdp_id,
    ];
  }
}
