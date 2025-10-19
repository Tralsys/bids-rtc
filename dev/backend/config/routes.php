<?php

declare(strict_types=1);

use BidsRtc\Backend\Controller;
use Psr\Container\ContainerInterface;
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * ルート定義
 *
 * @var App $app
 * @var ContainerInterface $container
 */

// このファイルはindex.phpからrequireされ、$appと$containerが利用可能

/** @var ContainerInterface $container */
$container = $app->getContainer();

// OPTIONSリクエスト対応（CORS）
$app->options('/{routes:.*}', function (Request $request, Response $response) {
	$response = $response->withStatus(204);
	$response = $response->withHeader('Access-Control-Allow-Origin', '*');
	$response = $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
	$response = $response->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
	$response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
	$response = $response->withHeader('Access-Control-Max-Age', '3600');
	return $response;
});

// API情報取得
$app->get('/', function (Request $request, Response $response) use ($container) {
	$controller = new Controller\ApiInfoController(
		serverName: $container->get('app.name'),
		appVersion: $container->get('app.version'),
	);
	return $controller->getApiInfo($request, $response);
});

// アプリケーション管理
$app->group('/apps', function ($group) use ($container) {
	// アプリケーション作成
	$group->post('', function (Request $request, Response $response) use ($container) {
		$controller = new Controller\ApplicationManagementController(
			service: $container->get(\BidsRtc\Backend\Service\AppManagementService::class),
			logger: $container->get(\Psr\Log\LoggerInterface::class),
		);
		return $controller->postApplicationInfo($request, $response);
	});

	// アプリケーション情報取得
	$group->get('/{appId}', function (Request $request, Response $response, array $args) use ($container) {
		$controller = new Controller\ApplicationManagementController(
			service: $container->get(\BidsRtc\Backend\Service\AppManagementService::class),
			logger: $container->get(\Psr\Log\LoggerInterface::class),
		);
		return $controller->getApplicationInfo($request, $response, $args);
	});
});

// クライアント管理
$app->group('/client', function ($group) use ($container) {
	// クライアントアクセストークン取得
	$group->put('_token', function (Request $request, Response $response) use ($container) {
		$controller = new Controller\ClientManagementController(
			service: $container->get(\BidsRtc\Backend\Service\ClientManagementService::class),
			logger: $container->get(\Psr\Log\LoggerInterface::class),
		);
		return $controller->getClientAccessToken($request, $response);
	});
});

$app->group('/clients', function ($group) use ($container) {
	// クライアント一覧取得
	$group->get('', function (Request $request, Response $response) use ($container) {
		$controller = new Controller\ClientManagementController(
			service: $container->get(\BidsRtc\Backend\Service\ClientManagementService::class),
			logger: $container->get(\Psr\Log\LoggerInterface::class),
		);
		return $controller->getClientInfoList($request, $response);
	});

	// クライアント登録
	$group->post('', function (Request $request, Response $response) use ($container) {
		$controller = new Controller\ClientManagementController(
			service: $container->get(\BidsRtc\Backend\Service\ClientManagementService::class),
			logger: $container->get(\Psr\Log\LoggerInterface::class),
		);
		return $controller->registerClientInfo($request, $response);
	});

	// クライアント情報取得
	$group->get('/{clientId}', function (Request $request, Response $response, array $args) use ($container) {
		$controller = new Controller\ClientManagementController(
			service: $container->get(\BidsRtc\Backend\Service\ClientManagementService::class),
			logger: $container->get(\Psr\Log\LoggerInterface::class),
		);
		return $controller->getClientInfo($request, $response, $args);
	});

	// クライアント削除
	$group->delete('/{clientId}', function (Request $request, Response $response, array $args) use ($container) {
		$controller = new Controller\ClientManagementController(
			service: $container->get(\BidsRtc\Backend\Service\ClientManagementService::class),
			logger: $container->get(\Psr\Log\LoggerInterface::class),
		);
		return $controller->deleteClientInfo($request, $response, $args);
	});
});

// SDP交換
$app->post('/offer', function (Request $request, Response $response) use ($container) {
	$controller = new Controller\SDPExchangeController(
		logger: $container->get(\Psr\Log\LoggerInterface::class),
	);
	return $controller->registerOffer($request, $response);
});

$app->post('/answer', function (Request $request, Response $response) use ($container) {
	$controller = new Controller\SDPExchangeController(
		logger: $container->get(\Psr\Log\LoggerInterface::class),
	);
	return $controller->registerAnswer($request, $response);
});

$app->get('/answer/{sdpId}', function (Request $request, Response $response, array $args) use ($container) {
	$controller = new Controller\SDPExchangeController(
		logger: $container->get(\Psr\Log\LoggerInterface::class),
	);
	return $controller->getAnswer($request, $response, $args);
});

$app->delete('/exchange/{sdpId}', function (Request $request, Response $response, array $args) use ($container) {
	$controller = new Controller\SDPExchangeController(
		logger: $container->get(\Psr\Log\LoggerInterface::class),
	);
	return $controller->deleteSDPExchange($request, $response, $args);
});

// 管理者API
$app->group('/admin', function ($group) use ($container) {
	// ログファイル一覧取得
	$group->get('/logs', function (Request $request, Response $response) use ($container) {
		$controller = new Controller\AdminController(
			logger: $container->get(\Psr\Log\LoggerInterface::class),
			config: [
				'admin.logs_dir' => $container->get('admin.logs_dir'),
			],
		);
		return $controller->getLogsList($request, $response);
	});

	// ログファイル内容取得
	$group->get('/logs/{filename}', function (Request $request, Response $response) use ($container) {
		$controller = new Controller\AdminController(
			logger: $container->get(\Psr\Log\LoggerInterface::class),
			config: [
				'admin.logs_dir' => $container->get('admin.logs_dir'),
			],
		);
		// Get filename from route arguments
		$filename = $request->getAttribute('filename');
		return $controller->getLogContent($request, $response, ['filename' => $filename]);
	});
});
