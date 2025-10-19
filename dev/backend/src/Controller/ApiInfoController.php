<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Controller;

use BidsRtc\Backend\Model\ApiInfo;
use BidsRtc\Backend\Utils;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API情報コントローラー
 */
#[OA\Info(
  version: '1.0.0',
  title: 'BIDS WebRTC Signaling API',
  description: 'WebRTC Signaling API for BIDS'
)]
#[OA\Server(
  url: '/api',
  description: 'Signaling API Server'
)]
#[OA\SecurityScheme(
  securityScheme: 'bearerAuth',
  type: 'http',
  scheme: 'bearer',
  bearerFormat: 'JWT'
)]
class ApiInfoController
{
  public function __construct(
    private readonly string $serverName,
    private readonly string $appVersion,
  ) {
  }

  /**
   * APIの基本情報を取得
   */
  #[OA\Get(
    path: '/',
    operationId: 'getApiInfo',
    summary: 'APIの基本情報を取得',
    tags: ['API Info']
  )]
  #[OA\Response(
    response: 200,
    description: 'APIの情報',
    content: new OA\JsonContent(ref: '#/components/schemas/ApiInfo')
  )]
  public function getApiInfo(
    ServerRequestInterface $request,
    ResponseInterface $response,
  ): ResponseInterface {
    $apiInfo = new ApiInfo(
      server_name: $this->serverName,
      version: $this->appVersion,
    );

    return Utils::withJson($response, $apiInfo);
  }
}
