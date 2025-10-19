<?php

declare(strict_types=1);

/**
 * デフォルト設定（開発環境）
 */
return [
	'mode' => 'dev',

	// Slim設定
	'slim.displayErrorDetails' => true,
	'slim.logErrors' => true,
	'slim.logErrorDetails' => true,

	// アプリケーション情報
	'app.name' => 'bids-rtc',
	'app.version' => '1.0.0',

	// ログ設定
	'logger.name' => 'bids-rtc',
	'logger.path' => __DIR__ . '/../../logs/app.log',
	'logger.level' => \Monolog\Level::Debug,
	'logger.options' => [],

	// データベース設定
	'pdo.dsn' => 'mysql:host=mysql;dbname=signaling;charset=utf8mb4',
	'pdo.username' => 'signaling',
	'pdo.password' => 'signaling',
	'pdo.options' => [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	],

	// Firebase設定
	'firebase.project_id' => getenv('FIREBASE_PROJECT_ID') ?: '',
	'firebase.sa_file' => __DIR__ . '/../../config/dev/firebase-sa.json',
	'firebase.api_token_cache_dir' => __DIR__ . '/../../cache/firebase/api_token',
	'firebase.auth.pubkey_cache_dir' => __DIR__ . '/../../cache/firebase/auth_pubkey',

	// JWT設定
	'my-auth.private_key' => __DIR__ . '/../../config/dev/jwt-private.pem',
	'my-auth.public_key' => __DIR__ . '/../../config/dev/jwt-public.pem',
	'my-auth.issuer' => 'bids-rtc',

	// CORS設定
	'cors.settings' => [
		\Neomerx\Cors\Contracts\Constants\CorsResponseHeaders::ALLOW_ORIGIN => [
			'http://localhost',
			'http://127.0.0.1',
			'*',
		],
		\Neomerx\Cors\Contracts\Constants\CorsResponseHeaders::ALLOW_METHODS => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
		\Neomerx\Cors\Contracts\Constants\CorsResponseHeaders::ALLOW_HEADERS => ['Content-Type', 'Authorization'],
		\Neomerx\Cors\Contracts\Constants\CorsResponseHeaders::ALLOW_CREDENTIALS => 'true',
		\Neomerx\Cors\Contracts\Constants\CorsResponseHeaders::MAX_AGE => 3600,
	],

	// 管理者ログディレクトリ
	'admin.logs_dir' => __DIR__ . '/../../logs',
];
