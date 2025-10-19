<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
	private array $settings;

	public function __construct(array $settings)
	{
		$this->settings = $settings;
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$origin = $request->getHeaderLine('Origin');
		$allowedOrigins = $this->settings['Access-Control-Allow-Origin'] ?? '*';

		// Allow all if '*' or match
		$allowOrigin = '*';
		if (is_array($allowedOrigins)) {
			if (in_array($origin, $allowedOrigins, true)) {
				$allowOrigin = $origin;
			} elseif (in_array('*', $allowedOrigins, true)) {
				$allowOrigin = '*';
			}
		} elseif ($allowedOrigins === '*' || $allowedOrigins === $origin) {
			$allowOrigin = $allowedOrigins;
		}

		// Intercept OPTIONS before handler so CORS headers are always returned
		if (strtoupper($request->getMethod()) === 'OPTIONS') {
			$response = new \Slim\Psr7\Response(204);
			return $response
				->withHeader('Access-Control-Allow-Origin', $allowOrigin)
				->withHeader('Access-Control-Allow-Methods', implode(', ', $this->settings['Access-Control-Allow-Methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']))
				->withHeader('Access-Control-Allow-Headers', implode(', ', $this->settings['Access-Control-Allow-Headers'] ?? ['Content-Type', 'Authorization']))
				->withHeader('Access-Control-Allow-Credentials', (string) ($this->settings['Access-Control-Allow-Credentials'] ?? 'true'))
				->withHeader('Access-Control-Max-Age', (string) ($this->settings['Access-Control-Max-Age'] ?? '3600'));
		}

		$response = $handler->handle($request);
		return $response
			->withHeader('Access-Control-Allow-Origin', $allowOrigin)
			->withHeader('Access-Control-Allow-Methods', implode(', ', $this->settings['Access-Control-Allow-Methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']))
			->withHeader('Access-Control-Allow-Headers', implode(', ', $this->settings['Access-Control-Allow-Headers'] ?? ['Content-Type', 'Authorization']))
			->withHeader('Access-Control-Allow-Credentials', (string) ($this->settings['Access-Control-Allow-Credentials'] ?? 'true'))
			->withHeader('Access-Control-Max-Age', (string) ($this->settings['Access-Control-Max-Age'] ?? '3600'));
	}
}
