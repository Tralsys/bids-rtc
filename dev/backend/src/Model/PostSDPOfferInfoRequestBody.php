<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use OpenApi\Attributes as OA;

/**
 * SDP Offer登録リクエストボディ
 */
#[OA\Schema(
  schema: 'PostSDPOfferInfoRequestBody',
  title: 'SDP OfferInfo POST Request Body',
  description: 'SDP Offer登録リクエストボディ',
  type: 'object',
  required: ['role', 'offer']
)]
class PostSDPOfferInfoRequestBody implements \JsonSerializable
{
  public function __construct(
    #[OA\Property(
      property: 'role',
      description: 'Offerしたクライアントのロール',
      type: 'string',
      enum: ['provider', 'subscriber']
    )]
    public readonly string $role,

    #[OA\Property(
      property: 'offer',
      description: 'Offer SDP (Base64エンコード済み)',
      type: 'string',
      format: 'byte'
    )]
    public readonly string $offer,

    #[OA\Property(
      property: 'established_clients',
      description: '既に接続が確立されているクライアントのIDのリスト',
      type: 'array',
      items: new OA\Items(type: 'string', format: 'uuid'),
      nullable: true
    )]
    public readonly ?array $established_clients = null,
  ) {
  }

  public function jsonSerialize(): array
  {
    $data = [
      'role' => $this->role,
      'offer' => $this->offer,
    ];
    if ($this->established_clients !== null) {
      $data['established_clients'] = $this->established_clients;
    }
    return $data;
  }
}
