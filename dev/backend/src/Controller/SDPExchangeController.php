<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Controller;

use BidsRtc\Backend\Model\SDPAnswerInfo;
use BidsRtc\Backend\Model\SdpIdAndAnswer;
use BidsRtc\Backend\Model\SdpRoles;
use BidsRtc\Backend\Model\PostSDPOfferInfoRequestBody;
use BidsRtc\Backend\Model\PostSDPOfferInfoResponse;
use BidsRtc\Backend\Model\ErrorResponse;
use BidsRtc\Backend\RetValueOrError;
use BidsRtc\Backend\Service\SDPExchangeService;
use BidsRtc\Backend\Utils;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * SDP交換コントローラー
 */
class SDPExchangeController
{
  private const int MAX_SDP_BASE64_LENGTH = 12000;
  private const int MAX_CLIENT_COUNT = 100;

  public function __construct(
    private readonly SDPExchangeService $service,
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Offerを登録
   */
  #[OA\Post(
    path: '/offer',
    operationId: 'registerOffer',
    summary: 'SDP Offerを登録',
    security: [['bearerAuth' => []]],
    tags: ['SDP Exchange']
  )]
  #[OA\Parameter(
    name: 'X-Client-Id',
    in: 'header',
    required: true,
    description: 'クライアントID',
    schema: new OA\Schema(type: 'string', format: 'uuid')
  )]
  #[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(ref: '#/components/schemas/PostSDPOfferInfoRequestBody')
  )]
  #[OA\Response(
    response: 201,
    description: 'Offer登録成功',
    content: new OA\JsonContent(ref: '#/components/schemas/PostSDPOfferInfoResponse')
  )]
  #[OA\Response(
    response: 400,
    description: 'リクエストエラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  #[OA\Response(
    response: 401,
    description: '認証エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  #[OA\Response(
    response: 403,
    description: '権限エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  public function registerOffer(
    ServerRequestInterface $request,
    ResponseInterface $response,
  ): ResponseInterface {
    $preparingResponse = $this->service->setUserIdAndClientId($request, $response);
    if ($preparingResponse !== null) {
      return $preparingResponse;
    }

    try {
      $body = $request->getParsedBody();
      if (!is_array($body)) {
        return Utils::withError($response, 400, 'Invalid request body');
      }

      $role = (string) ($body['role'] ?? '');
      $offer = (string) ($body['offer'] ?? '');
      $establishedClients = $body['established_clients'] ?? null;

      if (!SdpRoles::isValid($role)) {
        return Utils::withError($response, 400, 'Invalid role');
      }

      if (strlen($offer) > self::MAX_SDP_BASE64_LENGTH) {
        return Utils::withError($response, 400, 'Too long base64 format');
      }

      if ($establishedClients !== null && !is_array($establishedClients)) {
        return Utils::withError($response, 400, 'established_clients must be an array');
      }

      if (is_array($establishedClients) && count($establishedClients) > self::MAX_CLIENT_COUNT) {
        return Utils::withError($response, 400, 'Too many clients');
      }

      $result = $this->service->registerOfferAndGetAnswerableOffers(
        $role,
        $offer,
        $establishedClients ?? [],
      );

      return Utils::withJson($response, $result, 201);
    } catch (RetValueOrError $e) {
      return $e->getResponseWithJson($response);
    } catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return Utils::withError($response, 500, $e->getMessage());
    }
  }

  /**
   * Answerを登録
   */
  #[OA\Post(
    path: '/answer',
    operationId: 'registerAnswer',
    summary: 'SDP Answerを登録',
    security: [['bearerAuth' => []]],
    tags: ['SDP Exchange']
  )]
  #[OA\Parameter(
    name: 'X-Client-Id',
    in: 'header',
    required: true,
    description: 'クライアントID',
    schema: new OA\Schema(type: 'string', format: 'uuid')
  )]
  #[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
      type: 'array',
      items: new OA\Items(ref: '#/components/schemas/SDPAnswerInfo')
    )
  )]
  #[OA\Response(
    response: 201,
    description: 'Answer登録成功'
  )]
  #[OA\Response(
    response: 400,
    description: 'リクエストエラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  #[OA\Response(
    response: 401,
    description: '認証エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  #[OA\Response(
    response: 403,
    description: '権限エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  #[OA\Response(
    response: 404,
    description: 'SDP IDが見つからない',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  public function registerAnswer(
    ServerRequestInterface $request,
    ResponseInterface $response,
  ): ResponseInterface {
    $preparingResponse = $this->service->setUserIdAndClientId($request, $response);
    if ($preparingResponse !== null) {
      return $preparingResponse;
    }

    try {
      $body = $request->getParsedBody();
      if (!is_array($body)) {
        $body = [];
      }

      // 配列形式であることを確認 (連想配列は reject)
      if (!array_is_list($body) && count($body) > 0) {
        return Utils::withError($response, 400, 'Request body must be an array');
      }

      if (count($body) > self::MAX_CLIENT_COUNT) {
        return Utils::withError($response, 400, 'Too many clients');
      }

      /** @var SdpIdAndAnswer[] $answerArray */
      $answerArray = [];
      foreach ($body as $v) {
        if (!is_array($v)) {
          return Utils::withError($response, 400, 'Invalid format request');
        }

        $sdpIdStr = (string) ($v['sdp_id'] ?? '');
        $base64Answer = (string) ($v['answer'] ?? '');

        if (!Uuid::isValid($sdpIdStr)) {
          return Utils::withUuidError($response);
        }

        if (strlen($base64Answer) > self::MAX_SDP_BASE64_LENGTH) {
          return Utils::withError($response, 400, 'Too long base64 format');
        }

        $answerArray[] = SdpIdAndAnswer::fromArray($v);
      }

      $this->service->registerAnswer($answerArray);

      return $response->withStatus(201);
    } catch (\InvalidArgumentException $e) {
      return Utils::withError($response, 400, 'Invalid format request');
    } catch (RetValueOrError $e) {
      return $e->getResponseWithJson($response);
    } catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return Utils::withError($response, 500, $e->getMessage());
    }
  }

  /**
   * Answerを取得
   */
  #[OA\Get(
    path: '/answer/{sdpId}',
    operationId: 'getAnswer',
    summary: 'SDP Answerを取得',
    security: [['bearerAuth' => []]],
    tags: ['SDP Exchange']
  )]
  #[OA\Parameter(
    name: 'X-Client-Id',
    in: 'header',
    required: true,
    description: 'クライアントID',
    schema: new OA\Schema(type: 'string', format: 'uuid')
  )]
  #[OA\Parameter(
    name: 'sdpId',
    in: 'path',
    required: true,
    description: 'SDP交換ID',
    schema: new OA\Schema(type: 'string', format: 'uuid')
  )]
  #[OA\Response(
    response: 200,
    description: 'Answer取得成功',
    content: new OA\JsonContent(ref: '#/components/schemas/SDPAnswerInfo')
  )]
  #[OA\Response(
    response: 204,
    description: 'Answer未登録 (タイムアウト)'
  )]
  #[OA\Response(
    response: 400,
    description: 'リクエストエラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  #[OA\Response(
    response: 401,
    description: '認証エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  #[OA\Response(
    response: 403,
    description: '権限エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  #[OA\Response(
    response: 404,
    description: 'エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  public function getAnswer(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args,
  ): ResponseInterface {
    $preparingResponse = $this->service->setUserIdAndClientId($request, $response);
    if ($preparingResponse !== null) {
      return $preparingResponse;
    }

    $sdpIdStr = $args['sdpId'] ?? '';
    if (!Uuid::isValid($sdpIdStr)) {
      return Utils::withUuidError($response);
    }
    $sdpId = Uuid::fromString($sdpIdStr);

    try {
      $answer = $this->service->getAnswer($sdpId);
      return Utils::withJson($response, $answer, 200);
    } catch (RetValueOrError $e) {
      if ($e->getCode() === 204) {
        return $response->withStatus(204);
      }
      return $e->getResponseWithJson($response);
    } catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return Utils::withError($response, 500, $e->getMessage());
    }
  }

  /**
   * SDP交換を削除
   */
  #[OA\Delete(
    path: '/exchange/{sdpId}',
    operationId: 'deleteSDPExchange',
    summary: 'SDP交換を削除',
    security: [['bearerAuth' => []]],
    tags: ['SDP Exchange']
  )]
  #[OA\Parameter(
    name: 'sdpId',
    in: 'path',
    required: true,
    description: 'SDP交換ID',
    schema: new OA\Schema(type: 'string', format: 'uuid')
  )]
  #[OA\Parameter(
    name: 'X-Client-Id',
    in: 'header',
    required: true,
    description: 'クライアントID',
    schema: new OA\Schema(type: 'string', format: 'uuid')
  )]
  #[OA\Response(
    response: 204,
    description: '削除成功'
  )]
  #[OA\Response(
    response: 400,
    description: 'リクエストエラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  #[OA\Response(
    response: 401,
    description: '認証エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  #[OA\Response(
    response: 403,
    description: '権限エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  #[OA\Response(
    response: 404,
    description: 'エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  public function deleteSDPExchange(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args,
  ): ResponseInterface {
    $preparingResponse = $this->service->setUserIdAndClientId($request, $response);
    if ($preparingResponse !== null) {
      return $preparingResponse;
    }

    $sdpIdStr = $args['sdpId'] ?? '';
    if (!Uuid::isValid($sdpIdStr)) {
      return Utils::withUuidError($response);
    }
    $sdpId = Uuid::fromString($sdpIdStr);

    try {
      if ($this->service->deleteSDPExchange($sdpId)) {
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
}
