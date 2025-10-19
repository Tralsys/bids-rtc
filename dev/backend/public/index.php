<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use BidsRtc\Backend\App;
use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Slim\Factory\ServerRequestCreatorFactory;

// 環境判定
$env = match (strtolower($_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'prod')) {
	'development', 'dev' => 'dev',
	default => 'prod',
};

// PHP-DI ContainerBuilderのインスタンス化
$builder = new ContainerBuilder();

// 設定の読み込み
$builder->addDefinitions(__DIR__ . "/../config/{$env}/default.php");

$userConfig = __DIR__ . "/../config/{$env}/config.php";
if (file_exists($userConfig)) {
	$builder->addDefinitions($userConfig);
}

// 依存関係の設定
$builder->addDefinitions(__DIR__ . '/../config/dependencies.php');

// コンテナのビルド
$container = $builder->build();

// Slimアプリケーションのインスタンス化
$app = Bridge::create($container);

// ミドルウェアの登録
$app->addBodyParsingMiddleware();
// CORS middleware (must be before routing)
$app->add(new \BidsRtc\Backend\Middleware\CorsMiddleware($container->get('cors.settings')));
$app->add(function ($request, $handler) use ($container) {
	if (strtoupper($request->getMethod()) === 'OPTIONS') {
		$settings = $container->get('cors.settings');
		$origin = $request->getHeaderLine('Origin');
		$allowedOrigins = $settings['Access-Control-Allow-Origin'] ?? '*';
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
		$response = new \Slim\Psr7\Response(204);
		return $response
			->withHeader('Access-Control-Allow-Origin', $allowOrigin)
			->withHeader('Access-Control-Allow-Methods', implode(', ', $settings['Access-Control-Allow-Methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']))
			->withHeader('Access-Control-Allow-Headers', implode(', ', $settings['Access-Control-Allow-Headers'] ?? ['Content-Type', 'Authorization']))
			->withHeader('Access-Control-Allow-Credentials', (string) ($settings['Access-Control-Allow-Credentials'] ?? 'true'))
			->withHeader('Access-Control-Max-Age', (string) ($settings['Access-Control-Max-Age'] ?? '3600'));
	}
	return $handler->handle($request);
});
$app->addRoutingMiddleware();

// エラーミドルウェア
$errorMiddleware = $app->addErrorMiddleware(
	$container->get('slim.displayErrorDetails'),
	$container->get('slim.logErrors'),
	$container->get('slim.logErrorDetails'),
);

$errorMiddleware->setDefaultErrorHandler(function ($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails) use ($container) {
	$settings = $container->get('cors.settings');
	$origin = $request->getHeaderLine('Origin');
	$allowedOrigins = $settings['Access-Control-Allow-Origin'] ?? '*';
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
	$response = new \Slim\Psr7\Response();
	$response = $response
		->withHeader('Access-Control-Allow-Origin', $allowOrigin)
		->withHeader('Access-Control-Allow-Methods', implode(', ', $settings['Access-Control-Allow-Methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']))
		->withHeader('Access-Control-Allow-Headers', implode(', ', $settings['Access-Control-Allow-Headers'] ?? ['Content-Type', 'Authorization']))
		->withHeader('Access-Control-Allow-Credentials', (string) ($settings['Access-Control-Allow-Credentials'] ?? 'true'))
		->withHeader('Access-Control-Max-Age', (string) ($settings['Access-Control-Max-Age'] ?? '3600'));
	if (strtoupper($request->getMethod()) === 'OPTIONS') {
		return $response->withStatus(204);
	}
	// Default: 500 error
	return $response->withStatus(500);
});

// Custom NotFound and NotAllowed handlers for CORS
$app->setBasePath("");

// 認証ミドルウェア（必要に応じて）
// $app->add(\BidsRtc\Backend\Middleware\AuthMiddleware::class);

// ルートの登録
require __DIR__ . '/../config/routes.php';

// リクエストの処理
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();
$response = $app->handle($request);

// レスポンスの送信
$responseEmitter = new \Slim\ResponseEmitter();
$responseEmitter->emit($response);
