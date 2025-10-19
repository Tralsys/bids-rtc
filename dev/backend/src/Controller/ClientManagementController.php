<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Controller;

use BidsRtc\Backend\Model\ClientInfo;
use BidsRtc\Backend\Model\ClientInfoWithToken;
use BidsRtc\Backend\Model\ErrorResponse;
use BidsRtc\Backend\Service\ClientManagementService;
use BidsRtc\Backend\RetValueOrError;
use BidsRtc\Backend\Utils;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * クライアント管理コントローラー
 */
class ClientManagementController
{
  private const int MAX_CLIENT_NAME_LENGTH = 200;

  public function __construct(
    private readonly ClientManagementService $service,
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * クライアントアクセストークンを取得
   */
  #[OA\Put(
    path: '/client_token',
    operationId: 'getClientAccessToken',
    summary: 'クライアントアクセストークンを取得',
    tags: ['Client Management']
  )]
  #[OA\RequestBody(
    required: true,
    content: new OA\MediaType(
      mediaType: 'application/jose',
      schema: new OA\Schema(type: 'string', description: 'リフレッシュトークン'),
    )
  )]
  #[OA\Response(
    response: 200,
    description: 'アクセストークン',
    content: new OA\MediaType(
      mediaType: 'application/jose',
      schema: new OA\Schema(type: 'string'),
    )
  )]
  #[OA\Response(
    response: 401,
    description: 'エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  public function getClientAccessToken(
    ServerRequestInterface $request,
    ResponseInterface $response,
  ): ResponseInterface {
    try {
      $unverifiedRawRefreshToken = $request->getBody()->getContents();

      if ($unverifiedRawRefreshToken === '') {
        return Utils::withError($response, 400, 'Empty request body');
      }

      $accessToken = $this->service->getClientAccessToken($unverifiedRawRefreshToken);

      $response = $response
        ->withHeader('Content-Type', 'application/jose')
        ->withStatus(200);
      $response->getBody()->write($accessToken);

      return $response;
    } catch (RetValueOrError $e) {
      return $e->getResponseWithJson($response);
    } catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return Utils::withError($response, 500, $e->getMessage());
    }
  }

  /**
   * クライアント情報一覧を取得
   */
  #[OA\Get(
    path: '/clients',
    operationId: 'getClientInfoList',
    summary: 'クライアント情報一覧を取得',
    security: [['bearerAuth' => []]],
    tags: ['Client Management']
  )]
  #[OA\Response(
    response: 200,
    description: 'クライアントの情報一覧',
    content: new OA\JsonContent(
      type: 'array',
      items: new OA\Items(ref: '#/components/schemas/ClientInfo'),
    )
  )]
  #[OA\Response(
    response: 401,
    description: 'エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  public function getClientInfoList(
    ServerRequestInterface $request,
    ResponseInterface $response,
  ): ResponseInterface {
    $preparingResponse = $this->service->setUserId($request, $response);
    if ($preparingResponse !== null) {
      return $preparingResponse;
    }

    try {
      $clientInfoList = $this->service->getClientInfoList();
      return Utils::withJson($response, $clientInfoList);
    } catch (RetValueOrError $e) {
      return $e->getResponseWithJson($response);
    } catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return Utils::withError($response, 500, $e->getMessage());
    }
  }

  /**
   * クライアント情報を登録
   */
  #[OA\Post(
    path: '/clients',
    operationId: 'registerClientInfo',
    summary: 'クライアント情報を登録',
    security: [['bearerAuth' => []]],
    tags: ['Client Management']
  )]
  #[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
      required: ['app_id', 'name'],
      properties: [
        new OA\Property(property: 'app_id', type: 'string', format: 'uuid', description: 'アプリケーションID'),
        new OA\Property(property: 'name', type: 'string', description: 'クライアント名'),
      ],
    )
  )]
  #[OA\Response(
    response: 201,
    description: 'クライアントの情報',
    content: new OA\JsonContent(ref: '#/components/schemas/ClientInfoWithToken')
  )]
  #[OA\Response(
    response: 401,
    description: 'エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  public function registerClientInfo(
    ServerRequestInterface $request,
    ResponseInterface $response,
  ): ResponseInterface {
    $preparingResponse = $this->service->setUserId($request, $response);
    if ($preparingResponse !== null) {
      return $preparingResponse;
    }

    try {
      $body = $request->getParsedBody();
      $name = $body['name'] ?? '';
      $appIdStr = $body['app_id'] ?? '';

      if (!Uuid::isValid($appIdStr)) {
        return Utils::withError($response, 400, 'Invalid app_id format');
      }

      if (mb_strlen($name) > self::MAX_CLIENT_NAME_LENGTH) {
        return Utils::withError($response, 400, 'Client name is too long');
      }

      $appId = Uuid::fromString($appIdStr);
      $clientInfoWithToken = $this->service->registerClientInfo($appId, $name);

      return Utils::withJson($response, $clientInfoWithToken, 201);
    } catch (RetValueOrError $e) {
      return $e->getResponseWithJson($response);
    } catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return Utils::withError($response, 500, $e->getMessage());
    }
  }

  /**
   * クライアント情報を削除
   */
  #[OA\Delete(
    path: '/clients/{clientId}',
    operationId: 'deleteClientInfo',
    summary: 'クライアント情報を削除',
    security: [['bearerAuth' => []]],
    tags: ['Client Management']
  )]
  #[OA\Parameter(
    name: 'clientId',
    in: 'path',
    required: true,
    description: 'クライアントID',
    schema: new OA\Schema(type: 'string', format: 'uuid')
  )]
  #[OA\Response(
    response: 200,
    description: '削除成功'
  )]
  #[OA\Response(
    response: 401,
    description: 'エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  #[OA\Response(
    response: 404,
    description: 'エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  public function deleteClientInfo(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args,
  ): ResponseInterface {
    $preparingResponse = $this->service->setUserId($request, $response);
    if ($preparingResponse !== null) {
      return $preparingResponse;
    }

    $clientId = $args['clientId'] ?? '';

    if (!Uuid::isValid($clientId)) {
      return Utils::withUuidError($response);
    }

    $clientUuid = Uuid::fromString($clientId);

    try {
      if ($this->service->deleteClientInfo($clientUuid)) {
        return $response->withStatus(204);
      } else {
        return Utils::withError($response, 404, 'Not found');
      }
    } catch (RetValueOrError $e) {
      return $e->getResponseWithJson($response);
    } catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return Utils::withError($response, 500, $e->getMessage());
    }
  }

  /**
   * クライアント情報を取得
   */
  #[OA\Get(
    path: '/clients/{clientId}',
    operationId: 'getClientInfo',
    summary: 'クライアント情報を取得',
    security: [['bearerAuth' => []]],
    tags: ['Client Management']
  )]
  #[OA\Parameter(
    name: 'clientId',
    in: 'path',
    required: true,
    description: 'クライアントID',
    schema: new OA\Schema(type: 'string', format: 'uuid')
  )]
  #[OA\Response(
    response: 200,
    description: 'クライアントの情報',
    content: new OA\JsonContent(ref: '#/components/schemas/ClientInfo')
  )]
  #[OA\Response(
    response: 401,
    description: 'エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  #[OA\Response(
    response: 404,
    description: 'エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  public function getClientInfo(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args,
  ): ResponseInterface {
    $preparingResponse = $this->service->setUserId($request, $response);
    if ($preparingResponse !== null) {
      return $preparingResponse;
    }

    $clientId = $args['clientId'] ?? '';

    if (!Uuid::isValid($clientId)) {
      return Utils::withUuidError($response);
    }

    $clientUuid = Uuid::fromString($clientId);

    try {
      $clientInfo = $this->service->getClientInfo($clientUuid);
      return Utils::withJson($response, $clientInfo);
    } catch (RetValueOrError $e) {
      return $e->getResponseWithJson($response);
    } catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return Utils::withError($response, 500, $e->getMessage());
    }
  }
}
