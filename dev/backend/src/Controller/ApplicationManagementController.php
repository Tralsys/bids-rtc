<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Controller;

use BidsRtc\Backend\Model\ApplicationInfo;
use BidsRtc\Backend\Model\ErrorResponse;
use BidsRtc\Backend\Service\AppManagementService;
use BidsRtc\Backend\RetValueOrError;
use BidsRtc\Backend\Utils;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * アプリケーション管理コントローラー
 */
class ApplicationManagementController
{
	private const int MAX_APP_NAME_LENGTH = 250;
	private const int MAX_APP_DESCRIPTION_LENGTH = 65000;
	private const int MAX_APP_OWNER_LENGTH = 250;

	public function __construct(
		private readonly AppManagementService $service,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * アプリケーション情報を取得
	 */
	#[OA\Get(
		path: '/apps/{appId}',
		operationId: 'getApplicationInfo',
		summary: 'アプリケーション情報を取得',
		tags: ['Application Management']
	)]
	#[OA\Parameter(
		name: 'appId',
		in: 'path',
		required: true,
		description: 'アプリケーションID',
		schema: new OA\Schema(type: 'string', format: 'uuid')
	)]
	#[OA\Response(
		response: 200,
		description: 'そのアプリケーションの情報',
		content: new OA\JsonContent(ref: '#/components/schemas/ApplicationInfo')
	)]
	#[OA\Response(
		response: 400,
		description: '不正なリクエスト',
		content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
	)]
	#[OA\Response(
		response: 404,
		description: 'アプリケーションが見つからない',
		content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
	)]
	public function getApplicationInfo(
		ServerRequestInterface $request,
		ResponseInterface $response,
		array $args,
	): ResponseInterface {
		$appId = $args['appId'] ?? '';

		if (!Uuid::isValid($appId)) {
			return Utils::withUuidError($response);
		}

		$appUuid = Uuid::fromString($appId);

		try {
			$appInfo = $this->service->getAppInfo($appUuid);
			return Utils::withJson($response, $appInfo);
		} catch (RetValueOrError $e) {
			return $e->getResponseWithJson($response);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			return Utils::withError($response, 500, $e->getMessage());
		}
	}

	/**
	 * 新しいアプリケーションを作成
	 */
	#[OA\Post(
		path: '/apps',
		operationId: 'postApplicationInfo',
		summary: '新しいアプリケーションを作成',
		security: [['bearerAuth' => []]],
		tags: ['Application Management']
	)]
	#[OA\RequestBody(
		required: true,
		content: new OA\JsonContent(
			required: ['name', 'description', 'owner'],
			properties: [
				new OA\Property(property: 'name', type: 'string', description: 'アプリケーション名'),
				new OA\Property(property: 'description', type: 'string', description: 'アプリケーションの説明'),
				new OA\Property(property: 'owner', type: 'string', description: 'オーナー'),
			],
		)
	)]
	#[OA\Response(
		response: 200,
		description: '作成結果',
		content: new OA\JsonContent(ref: '#/components/schemas/ApplicationInfo')
	)]
	#[OA\Response(
		response: 400,
		description: '不正なリクエスト',
		content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
	)]
	#[OA\Response(
		response: 403,
		description: '権限がありません',
		content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
	)]
	public function postApplicationInfo(
		ServerRequestInterface $request,
		ResponseInterface $response,
	): ResponseInterface {
		if (!Utils::getIsAdminRole($request)) {
			return Utils::withError($response, 403, 'Forbidden');
		}

		try {
			$data = $request->getParsedBody();
			$name = $data['name'] ?? null;
			$description = $data['description'] ?? null;
			$owner = $data['owner'] ?? null;

			if ($name === null || $description === null || $owner === null || $name === '' || $owner === '') {
				return Utils::withError($response, 400, 'name, description, owner are required');
			}

			if (mb_strlen($name) > self::MAX_APP_NAME_LENGTH) {
				return Utils::withError($response, 400, 'name is too long');
			}
			if (mb_strlen($description) > self::MAX_APP_DESCRIPTION_LENGTH) {
				return Utils::withError($response, 400, 'description is too long');
			}
			if (mb_strlen($owner) > self::MAX_APP_OWNER_LENGTH) {
				return Utils::withError($response, 400, 'owner is too long');
			}

			$appInfo = $this->service->createApp($name, $description, $owner);
			return Utils::withJson($response, $appInfo);
		} catch (RetValueOrError $e) {
			return $e->getResponseWithJson($response);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			return Utils::withError($response, 500, $e->getMessage());
		}
	}
}
