<?php

namespace dev_t0r\bids_rtc\signaling\api;

use dev_t0r\bids_rtc\signaling\auth\MyAuthMiddleware;
use dev_t0r\bids_rtc\signaling\model\ApplicationInfo;
use dev_t0r\bids_rtc\signaling\RetValueOrError;
use dev_t0r\bids_rtc\signaling\service\AppManagementService;
use dev_t0r\bids_rtc\signaling\Utils;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class ApplicationManagementApi extends AbstractApplicationManagementApi
{
	private const int MAX_APP_NAME_LENGTH = 250;
	private const int MAX_APP_DESCRIPTION_LENGTH = 65000;
	private const int MAX_APP_OWNER_LENGTH = 250;

	private readonly AppManagementService $service;

	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->service = new AppManagementService(
			$db,
			$this->logger,
		);
	}

	public function getApplicationInfo(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $appId,
	): ResponseInterface {
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

	public function postApplicationInfo(
		ServerRequestInterface $request,
		ResponseInterface $response,
	): ResponseInterface {
		if (!MyAuthMiddleware::getIsAdminRole($request)) {
			return Utils::withError($response, 403, 'Forbidden');
		}

		try {
			$data = new ApplicationInfo();
			$data->setData($request->getParsedBody());
			$name = $data->name;
			$description = $data->description;
			$owner = $data->owner;
			if ($name === null || $description === null || $owner === null || $name === '' || $owner === '') {
				// (descriptionは空文字でもOK)
				return Utils::withError($response, 400, 'name, description, owner are required');
			}
			if (self::MAX_APP_NAME_LENGTH < mb_strlen($name)) {
				return Utils::withError($response, 400, 'name is too long');
			}
			if (self::MAX_APP_DESCRIPTION_LENGTH < mb_strlen($description)) {
				return Utils::withError($response, 400, 'description is too long');
			}
			if (self::MAX_APP_OWNER_LENGTH < mb_strlen($owner)) {
				return Utils::withError($response, 400, 'owner is too long');
			}

			$appInfo = $this->service->createApp(
				$name,
				$description,
				$owner,
			);
			return Utils::withJson($response, $appInfo);
		} catch (RetValueOrError $e) {
			return $e->getResponseWithJson($response);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			return Utils::withError($response, 500, $e->getMessage());
		}
	}
}
