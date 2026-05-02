<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use OpenApi\Attributes as OA;

#[OA\Schema(
  schema: 'ClientTokenPair',
  title: 'ClientTokenPair',
  description: 'リフレッシュトークンローテーション後のトークンペア',
  type: 'object',
  required: ['refresh_token', 'access_token']
)]
class ClientTokenPair implements \JsonSerializable
{
  public function __construct(
    #[OA\Property(
      property: 'refresh_token',
      description: '新しいリフレッシュトークン',
      type: 'string',
    )]
    public readonly string $refresh_token,

    #[OA\Property(
      property: 'access_token',
      description: '新しいアクセストークン',
      type: 'string',
    )]
    public readonly string $access_token,
  ) {
  }

  public function jsonSerialize(): array
  {
    return [
      'refresh_token' => $this->refresh_token,
      'access_token'  => $this->access_token,
    ];
  }
}
