<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use OpenApi\Attributes as OA;

/**
 * APIの基本情報
 */
#[OA\Schema(
  schema: 'ApiInfo',
  title: 'ApiInfo',
  description: 'APIの基本情報',
  type: 'object',
  required: ['server_name', 'version']
)]
class ApiInfo implements \JsonSerializable
{
  public function __construct(
    #[OA\Property(
      property: 'server_name',
      description: 'サーバの名前',
      type: 'string',
      example: 'bids-rtc'
    )]
    public readonly string $server_name,

    #[OA\Property(
      property: 'version',
      description: 'APIのバージョン',
      type: 'string',
      example: '1.0.0'
    )]
    public readonly string $version,
  ) {
  }

  public function jsonSerialize(): array
  {
    return [
      'server_name' => $this->server_name,
      'version' => $this->version,
    ];
  }
}
