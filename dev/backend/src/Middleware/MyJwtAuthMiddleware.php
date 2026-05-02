<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Middleware;

use BidsRtc\Backend\Constants;
use BidsRtc\Backend\RetValueOrError;
use BidsRtc\Backend\Service\MyJwtUtil;
use BidsRtc\Backend\Utils;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class MyJwtAuthMiddleware implements MiddlewareInterface
{
	public function __construct(
		private readonly MyJwtUtil $jwtUtil,
		private readonly LoggerInterface $logger,
		private readonly ResponseFactoryInterface $responseFactory,
	) {
	}

	public function process(
		ServerRequestInterface $request,
		RequestHandlerInterface $handler,
	): ResponseInterface {
		// Get Authorization header
		$authHeader = $request->getHeaderLine('Authorization');

		// Check for Bearer token
		if (!str_starts_with($authHeader, 'Bearer ')) {
			$this->logger->debug('Missing or malformed Authorization header');
			return Utils::withError(
				$this->responseFactory->createResponse(),
				401,
				'Missing or malformed Authorization header',
			);
		}

		// Extract raw token (everything after "Bearer ")
		$rawToken = substr($authHeader, 7);

		try {
			$claims = $this->jwtUtil->parseAndValidate($rawToken);
		} catch (RetValueOrError $e) {
			$this->logger->debug('JWT validation failed', ['error' => $e->getMessage()]);
			return $e->getResponseWithJson($this->responseFactory->createResponse());
		}

		$this->logger->debug('JWT validated', [
			'uid' => $claims->uid,
			'keyType' => $claims->keyType,
		]);

		// Set request attributes from claims
		$request = $request
			->withAttribute(Constants::ATTR_NAME_UID, $claims->uid)
			->withAttribute(Constants::ATTR_NAME_CLIENT_ID_FROM_TOKEN, $claims->clientId->toString())
			->withAttribute('appId', $claims->appId->toString())
			->withAttribute('tokenType', $claims->keyType);

		return $handler->handle($request);
	}
}
