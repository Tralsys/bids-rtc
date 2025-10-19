<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Controller;

use BidsRtc\Backend\Model\SDPAnswerInfo;
use BidsRtc\Backend\Model\PostSDPOfferInfoResponse;
use BidsRtc\Backend\Model\ErrorResponse;
use BidsRtc\Backend\RetValueOrError;
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

  public function __construct(
    // private readonly SDPExchangeService $service,
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
  #[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
      required: ['offer_client_id', 'offer'],
      properties: [
        new OA\Property(property: 'offer_client_id', type: 'string', format: 'uuid', description: 'Offerを送信するクライアントID'),
        new OA\Property(property: 'offer', type: 'string', description: 'SDP Offer（Base64エンコード済み）'),
      ],
    )
  )]
  #[OA\Response(
    response: 201,
    description: 'Offer登録成功',
    content: new OA\JsonContent(ref: '#/components/schemas/PostSDPOfferInfoResponse')
  )]
  #[OA\Response(
    response: 401,
    description: 'エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  public function registerOffer(
    ServerRequestInterface $request,
    ResponseInterface $response,
  ): ResponseInterface {
    // TODO: 実装
    return Utils::withError($response, 501, 'Not implemented yet');
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
  #[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
      required: ['sdp_id', 'answer_client_id', 'answer'],
      properties: [
        new OA\Property(property: 'sdp_id', type: 'string', format: 'uuid', description: 'SDP交換ID'),
        new OA\Property(property: 'answer_client_id', type: 'string', format: 'uuid', description: 'Answerを送信するクライアントID'),
        new OA\Property(property: 'answer', type: 'string', description: 'SDP Answer（Base64エンコード済み）'),
      ],
    )
  )]
  #[OA\Response(
    response: 201,
    description: 'Answer登録成功'
  )]
  #[OA\Response(
    response: 401,
    description: 'エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  public function registerAnswer(
    ServerRequestInterface $request,
    ResponseInterface $response,
  ): ResponseInterface {
    // TODO: 実装
    return Utils::withError($response, 501, 'Not implemented yet');
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
    description: 'Answer未登録'
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
  public function getAnswer(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args,
  ): ResponseInterface {
    // TODO: 実装
    return Utils::withError($response, 501, 'Not implemented yet');
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
  #[OA\Response(
    response: 204,
    description: '削除成功'
  )]
  #[OA\Response(
    response: 401,
    description: 'エラー',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
  )]
  public function deleteSDPExchange(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args,
  ): ResponseInterface {
    // TODO: 実装
    return Utils::withError($response, 501, 'Not implemented yet');
  }
}
