<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Model;

use OpenApi\Attributes as OA;

/**
 * エラーレスポンス
 */
#[OA\Schema(
  schema: 'ErrorResponse',
  title: 'ErrorResponse',
  description: 'エラーレスポンス',
  type: 'object',
  required: ['error']
)]
class ErrorResponse implements \JsonSerializable
{
  public function __construct(
    #[OA\Property(
      property: 'error',
      type: 'object',
      required: ['code', 'message'],
      properties: [
        new OA\Property(property: 'code', type: 'integer', example: 400),
        new OA\Property(property: 'message', type: 'string', example: 'Bad Request'),
      ]
    )]
    public readonly array $error,
  ) {
  }

  public function jsonSerialize(): array
  {
    return [
      'error' => $this->error,
    ];
  }
}
