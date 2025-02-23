<?php

namespace dev_t0r\bids_rtc\signaling\api;

use dev_t0r\bids_rtc\signaling\model\PostSDPOfferInfoRequestBody;
use dev_t0r\bids_rtc\signaling\model\SDPAnswerInfo;
use dev_t0r\bids_rtc\signaling\RetValueOrError;
use dev_t0r\bids_rtc\signaling\service\SDPExchangeService;
use dev_t0r\bids_rtc\signaling\Utils;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class SDPExchangeApi extends AbstractSDPExchangeApi
{
	private const int MAX_SDP_BASE64_LENGTH = 12000;
	private const int MAX_CLIENT_COUNT = 100;

	private readonly SDPExchangeService $service;

	public function __construct(
		PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->service = new SDPExchangeService(
			$db,
			$this->logger,
		);
	}

	public function deleteSDPExchange(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $sdpId,
	): ResponseInterface {
		$preparingResponse = $this->service->setUserIdAndClientId($request, $response);
		if ($preparingResponse != null) {
			return $preparingResponse;
		}

		if (!Uuid::isValid($sdpId)) {
			return Utils::withUuidError($response);
		}
		$sdpUuid = Uuid::fromString($sdpId);

		try {
			if ($this->service->deleteSDPExchange($sdpUuid)) {
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

	public function getAnswer(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $sdpId,
	): ResponseInterface {
		$preparingResponse = $this->service->setUserIdAndClientId($request, $response);
		if ($preparingResponse != null) {
			return $preparingResponse;
		}

		if (!Uuid::isValid($sdpId)) {
			return Utils::withUuidError($response);
		}
		$sdpUuid = Uuid::fromString($sdpId);

		try {
			$answer = $this->service->getAnswer($sdpUuid);
			if ($answer == null) {
				return Utils::withError($response, 404, 'Not found');
			} else if ($answer->raw_answer == null) {
				return Utils::withError($response, 204, 'Answer is not yet registered');
			}

			$resObj = new SDPAnswerInfo();
			$resObj->setData([
				'sdp_id' => $answer->sdp_id->toString(),
				'answer_client_id' => $answer->answer_client_id->toString(),
				'answer' => base64_encode($answer->raw_answer),
			]);
			return Utils::withJson($response, $resObj);
		} catch (RetValueOrError $e) {
			return $e->getResponseWithJson($response);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			return Utils::withError($response, 500, $e->getMessage());
		}
	}

	public function registerAnswer(
		ServerRequestInterface $request,
		ResponseInterface $response,
	): ResponseInterface {
		$preparingResponse = $this->service->setUserIdAndClientId($request, $response);
		if ($preparingResponse != null) {
			return $preparingResponse;
		}

		$body = $request->getParsedBody();
		if (!is_array($body)) {
			$body = [$body];
		}
		if (self::MAX_CLIENT_COUNT < count($body)) {
			return Utils::withError($response, 400, 'Too many clients');
		}

		/** @var array<\dev_t0r\bids_rtc\signaling\model\SdpIdAndAnswer> $answerArray */
		$answerArray = [];
		try {
			foreach ($body as $v) {
				$model = new SDPAnswerInfo();
				$model->setData($v);
				$sdpIdStr = $model->sdp_id;
				$base64Answer = $model->answer;
				if (!Uuid::isValid($sdpIdStr)) {
					return Utils::withUuidError($response);
				}
				if (self::MAX_SDP_BASE64_LENGTH < strlen($base64Answer)) {
					return Utils::withError($response, 400, 'Too long base64 format');
				}
				$rawAnswer = base64_decode($base64Answer, true);
				if ($rawAnswer === false) {
					return Utils::withError($response, 400, 'Invalid base64 format');
				}
				$answerArray[] = new \dev_t0r\bids_rtc\signaling\model\SdpIdAndAnswer(
					Uuid::fromString($sdpIdStr),
					$rawAnswer,
				);
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

	public function registerOffer(
		ServerRequestInterface $request,
		ResponseInterface $response,
	): ResponseInterface {
		$preparingResponse = $this->service->setUserIdAndClientId($request, $response);
		if ($preparingResponse != null) {
			return $preparingResponse;
		}

		try {
			$reqBody = new PostSDPOfferInfoRequestBody();
			$reqBody->setData($request->getParsedBody());
			$role = $reqBody->role;
			if ($role !== SDPExchangeService::ROLE_PROVIDER && $role !== SDPExchangeService::ROLE_SUBSCRIBER) {
				return Utils::withError($response, 400, 'Invalid role');
			}
			if (self::MAX_SDP_BASE64_LENGTH < strlen($reqBody->offer)) {
				return Utils::withError($response, 400, 'Too long base64 format');
			}
			if ($reqBody->established_clients != null && self::MAX_CLIENT_COUNT < count($reqBody->established_clients)) {
				return Utils::withError($response, 400, 'Too many clients');
			}

			$rawOffer = base64_decode($reqBody->offer, true);
			if ($rawOffer === false) {
				return Utils::withError($response, 400, 'Invalid base64 format');
			}

			$establishedClients = [];
			if ($reqBody->established_clients != null) {
				foreach ($reqBody->established_clients as $v) {
					if (!Uuid::isValid($v)) {
						return Utils::withUuidError($response);
					}
					$establishedClients[] = Uuid::fromString($v);
				}
			}

			$result = $this->service->registerOfferAndGetAnswerableOffers(
				$role,
				$rawOffer,
				$establishedClients,
			);
			return Utils::withJson($response, $result->toResObj());
		} catch (\InvalidArgumentException $e) {
			return Utils::withError($response, 400, 'Invalid format request');
		} catch (RetValueOrError $e) {
			return $e->getResponseWithJson($response);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			return Utils::withError($response, 500, $e->getMessage());
		}
	}
}
