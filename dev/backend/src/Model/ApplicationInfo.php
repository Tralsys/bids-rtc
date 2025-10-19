<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use OpenApi\Attributes as OA;

/**
 * アプリケーション情報
 */
#[OA\Schema(
  schema: 'ApplicationInfo',
  title: 'ApplicationInfo',
  description: 'アプリケーション情報',
  type: 'object',
  required: ['app_id', 'name', 'description', 'owner', 'created_at']
)]
class ApplicationInfo implements \JsonSerializable
{
  public function __construct(
    #[OA\Property(
      property: 'app_id',
      description: 'アプリケーションID',
      type: 'string',
      format: 'uuid',
      readOnly: true,
      example: '550e8400-e29b-41d4-a716-446655440000'
    )]
    public readonly string $app_id,

    #[OA\Property(
      property: 'name',
      description: 'アプリケーション名',
      type: 'string',
      example: 'My Application'
    )]
    public readonly string $name,

    #[OA\Property(
      property: 'description',
      description: 'アプリケーションの説明',
      type: 'string',
      example: 'This is my application'
    )]
    public readonly string $description,

    #[OA\Property(
      property: 'owner',
      description: 'オーナー',
      type: 'string',
      example: 'user@example.com'
    )]
    public readonly string $owner,

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
      'name' => $this->name,
      'description' => $this->description,
      'owner' => $this->owner,
      'created_at' => $this->created_at,
    ];
  }
}
