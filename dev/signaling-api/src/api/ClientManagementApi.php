<?php

namespace dev_t0r\bids_rtc\signaling\api;

use dev_t0r\bids_rtc\signaling\auth\MyAuthUtil;
use dev_t0r\bids_rtc\signaling\model\ClientInfo;
use dev_t0r\bids_rtc\signaling\RetValueOrError;
use dev_t0r\bids_rtc\signaling\service\ClientManagementService;
use dev_t0r\bids_rtc\signaling\Utils;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Slim\Exception\HttpNotImplementedException;

class ClientManagementApi extends AbstractClientManagementApi
{
	private const int MAX_CLIENT_NAME_LENGTH = 200;
	private readonly ClientManagementService $service;

	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
		MyAuthUtil $authUtil,
	) {
		$this->service = new ClientManagementService(
			$db,
			$this->logger,
			$authUtil,
		);
	}

	public function deleteClientInfo(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $clientId,
	): ResponseInterface {
		$prepareResponse = $this->service->setUserId($request, $response);
		if ($prepareResponse != null) {
			return $prepareResponse;
		}

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

	public function getClientAccessToken(
		ServerRequestInterface $request,
		ResponseInterface $response,
	): ResponseInterface {
		$unverifiedRawRefreshToken = $request->getParsedBody();

		try {
			$accessToken = $this->service->getClientAccessToken($unverifiedRawRefreshToken);
			if ($accessToken === null) {
				return Utils::withError($response, 500, 'Internal server error');
			}
			$response = $response->withHeader('Content-Type', 'application/jose')->withStatus(200);
			$response->getBody()->write($accessToken);
			return $response;
		} catch (RetValueOrError $e) {
			return $e->getResponseWithJson($response);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			return Utils::withError($response, 500, $e->getMessage());
		}
	}

	public function getClientInfo(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $clientId,
	): ResponseInterface {
		$prepareResponse = $this->service->setUserId($request, $response);
		if ($prepareResponse != null) {
			return $prepareResponse;
		}

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

	public function getClientInfoList(
		ServerRequestInterface $request,
		ResponseInterface $response,
	): ResponseInterface {
		$preparingResponse = $this->service->setUserId($request, $response);
		if ($preparingResponse != null) {
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

	public function registerClientInfo(
		ServerRequestInterface $request,
		ResponseInterface $response,
	): ResponseInterface {
		$preparingResponse = $this->service->setUserId($request, $response);
		if ($preparingResponse != null) {
			return $preparingResponse;
		}

		$body = $request->getParsedBody();
		try {
			$requestedClientInfo = new ClientInfo();
			$requestedClientInfo->setData($body);
			$name = $requestedClientInfo->name;
			$appId = Uuid::fromString($requestedClientInfo->app_id);
			if (self::MAX_CLIENT_NAME_LENGTH < strlen($name)) {
				return Utils::withError($response, 400, 'Client name is too long');
			}

			if (!Uuid::isValid($appId)) {
				return Utils::withUuidError($response);
			}

			$clientInfo = $this->service->registerClientInfo($appId, $name);
			return Utils::withJson($response, $clientInfo);
		} catch (RetValueOrError $e) {
			return $e->getResponseWithJson($response);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			return Utils::withError($response, 500, $e->getMessage());
		}
	}
}
