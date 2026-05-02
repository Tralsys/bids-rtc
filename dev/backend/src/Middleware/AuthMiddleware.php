<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Middleware;

use BidsRtc\Backend\Constants;
use Kreait\Firebase\Contract\Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
	public function __construct(
		private readonly Auth $firebaseAuth,
		private readonly LoggerInterface $logger,
	) {
	}

	public function process(
		ServerRequestInterface $request,
		RequestHandlerInterface $handler,
	): ResponseInterface {
		// Get Authorization header
		$authHeader = $request->getHeaderLine('Authorization');

		if (empty($authHeader)) {
			$this->logger->debug('No Authorization header present');
			return $this->unauthorizedResponse('Authorization required');
		}

		// Extract Bearer token
		if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
			$this->logger->debug('Invalid Authorization header format');
			return $this->unauthorizedResponse('Invalid Authorization header format');
		}

		$idToken = $matches[1];

		try {
			// For Firebase Emulator, check if it's an unsigned token (alg: none)
			$isEmulator = !empty(getenv('FIREBASE_AUTH_EMULATOR_HOST'));

			if ($isEmulator) {
				// Parse JWT without verification for emulator
				$parts = explode('.', $idToken);
				if (count($parts) !== 3) {
					throw new \Exception('Invalid token format');
				}

				$payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
				$claims = json_decode($payload, true);

				if (!$claims || !isset($claims['user_id'])) {
					throw new \Exception('Invalid token payload');
				}

				$uid = $claims['user_id'];
				$this->logger->debug('Token parsed (emulator mode)', [
					'uid' => $uid,
					'claims' => $claims,
				]);
			} else {
				// Verify the ID token for production
				$verifiedIdToken = $this->firebaseAuth->verifyIdToken($idToken);
				$claims = $verifiedIdToken->claims()->all();
				$uid = $verifiedIdToken->claims()->get('sub');

				$this->logger->debug('Token verified', [
					'uid' => $uid,
					'claims' => $claims,
				]);
			}
		} catch (\Kreait\Firebase\Exception\Auth\FailedToVerifyToken $e) {
			$this->logger->warning('Failed to verify Firebase token', [
				'error' => $e->getMessage(),
			]);
			return $this->unauthorizedResponse('Invalid or expired token');
		} catch (\Exception $e) {
			$this->logger->error('Error processing authentication', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);
			return $this->unauthorizedResponse('Authentication error');
		}

		// Add user info to request attributes
		$request = $request->withAttribute(Constants::ATTR_NAME_UID, $uid);

		// Extract role from custom claims
		// Firebase stores custom claims at the root level of the token
		$role = $claims['role'] ?? null;

		if ($role !== null) {
			$request = $request->withAttribute(Constants::ATTR_NAME_USER_ROLE, $role);
			$this->logger->debug('User role set', ['role' => $role]);
		} else {
			$this->logger->debug('No role claim found in token');
		}

		return $handler->handle($request);
	}

	private function unauthorizedResponse(string $message): ResponseInterface
	{
		$response = new Response();
		$response->getBody()->write(json_encode([
			'error' => [
				'code' => 401,
				'message' => $message,
			],
		]));
		return $response
			->withStatus(401)
			->withHeader('Content-Type', 'application/json');
	}
}
