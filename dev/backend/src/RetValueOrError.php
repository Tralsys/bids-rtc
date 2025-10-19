<?php

declare(strict_types=1);

namespace BidsRtc\Backend;

use Psr\Http\Message\ResponseInterface;

/**
 * エラーまたは値を返すための例外クラス
 */
class RetValueOrError extends \Exception
{
  public function __construct(
    private readonly int $statusCode,
    private readonly string $errorMessage,
    private readonly ?int $errorCode = null,
  ) {
    parent::__construct($errorMessage, $errorCode ?? $statusCode);
  }

  public function getResponseWithJson(ResponseInterface $response): ResponseInterface
  {
    return Utils::withError($response, $this->statusCode, $this->errorMessage, $this->errorCode);
  }
}
