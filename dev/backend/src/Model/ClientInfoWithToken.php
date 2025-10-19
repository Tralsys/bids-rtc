<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use OpenApi\Attributes as OA;

/**
 * トークン付きクライアント情報
 */
#[OA\Schema(
  schema: 'ClientInfoWithToken',
  title: 'ClientInfoWithToken',
  description: 'トークン付きクライアント情報',
  type: 'object',
  required: ['client_info', 'client_token']
)]
class ClientInfoWithToken implements \JsonSerializable
{
  public function __construct(
    #[OA\Property(
      property: 'client_info',
      ref: '#/components/schemas/ClientInfo',
      description: 'クライアント情報'
    )]
    public readonly ClientInfo $client_info,

    #[OA\Property(
      property: 'client_token',
      description: 'クライアントアクセストークン',
      type: 'string',
      example: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'
    )]
    public readonly string $client_token,
  ) {
  }

  public function jsonSerialize(): array
  {
    return [
      'client_info' => $this->client_info,
      'client_token' => $this->client_token,
    ];
  }
}
