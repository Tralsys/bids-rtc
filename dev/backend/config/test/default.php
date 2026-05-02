<?php

declare(strict_types=1);

/**
 * デフォルト設定（テスト環境）
 */
return [
    'mode' => 'test',

    // Slim設定
    'slim.displayErrorDetails' => true,
    'slim.logErrors' => true,
    'slim.logErrorDetails' => true,

    // アプリケーション情報
    'app.name' => 'bids-rtc',
    'app.version' => '1.0.0',

    // ログ設定
    'logger.name' => 'bids-rtc',
    'logger.path' => '/tmp/app-test.log',
    'logger.level' => \Monolog\Level::Debug,
    'logger.options' => [],

    // データベース設定
    'pdo.dsn' => 'mysql:host=' . (getenv('TEST_DB_HOST') ?: 'test-mysql') . ';dbname=' . (getenv('TEST_DB_NAME') ?: 'signaling') . ';charset=utf8mb4',
    'pdo.username' => getenv('TEST_DB_USER') ?: 'signaling',
    'pdo.password' => getenv('TEST_DB_PASSWORD') ?: 'signaling',
    'pdo.options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],

    // Firebase設定（エミュレーターモード: FIREBASE_AUTH_EMULATOR_HOST 環境変数で制御）
    'firebase.project_id' => getenv('FIREBASE_PROJECT_ID') ?: '',
    'firebase.sa_file' => null,
    'firebase.api_token_cache_dir' => '/tmp/firebase-api-token',
    'firebase.auth.pubkey_cache_dir' => '/tmp/firebase-auth-pubkey',

    // JWT設定（テスト専用キー）
    'my-auth.private_key' => __DIR__ . '/jwt-private.pem',
    'my-auth.public_key' => __DIR__ . '/jwt-public.pem',
    'my-auth.issuer' => 'bids-rtc',

    // CORS設定
    'cors.settings' => [
        \Neomerx\Cors\Contracts\Constants\CorsResponseHeaders::ALLOW_ORIGIN => [
            '*',
        ],
        \Neomerx\Cors\Contracts\Constants\CorsResponseHeaders::ALLOW_METHODS => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        \Neomerx\Cors\Contracts\Constants\CorsResponseHeaders::ALLOW_HEADERS => ['Content-Type', 'Authorization'],
        \Neomerx\Cors\Contracts\Constants\CorsResponseHeaders::ALLOW_CREDENTIALS => 'true',
        \Neomerx\Cors\Contracts\Constants\CorsResponseHeaders::MAX_AGE => 3600,
    ],

    // 管理者ログディレクトリ
    'admin.logs_dir' => '/tmp',
];
