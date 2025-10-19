<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use OpenApi\Attributes as OA;

/**
 * クライアント情報
 */
#[OA\Schema(
  schema: 'ClientInfo',
  title: 'ClientInfo',
  description: 'クライアント情報',
  type: 'object',
  required: ['app_id', 'client_id', 'name', 'created_at']
)]
class ClientInfo implements \JsonSerializable
{
  public function __construct(
    #[OA\Property(
      property: 'app_id',
      description: 'アプリケーションID',
      type: 'string',
      format: 'uuid',
      example: '550e8400-e29b-41d4-a716-446655440000'
    )]
    public readonly string $app_id,

    #[OA\Property(
      property: 'client_id',
      description: 'クライアントID',
      type: 'string',
      format: 'uuid',
      readOnly: true,
      example: '660e8400-e29b-41d4-a716-446655440001'
    )]
    public readonly string $client_id,

    #[OA\Property(
      property: 'name',
      description: 'クライアント名',
      type: 'string',
      example: 'My Client'
    )]
    public readonly string $name,

    #[OA\Property(
      property: 'created_at',
      description: '作成日時',
      type: 'string',
      format: 'date-time',
      readOnly: true,
      example: '2025-10-19T12:00:00Z'
    )]
    public readonly string $created_at,
  ) {
  }

  public function jsonSerialize(): array
  {
    return [
      'app_id' => $this->app_id,
      'client_id' => $this->client_id,
      'name' => $this->name,
      'created_at' => $this->created_at,
    ];
  }
}
