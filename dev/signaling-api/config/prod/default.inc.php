<?php

/**
 * App configuration defaults for production.
 * This file used when 'APP_ENV' variable set to 'prod' or 'production'. Check public/.htaccess file
 */

// Disable error reporting
if (getenv('APP_IS_DOCKER') === 'true') {
	error_reporting(E_ALL);
	ini_set("log_errors", 1);
	ini_set("display_errors", 1);
	ini_set('error_log', '/var/log/apache2/php-error.log');
} else {
	error_reporting(0);
	ini_set("display_errors", 0);
}

/**
 * Each environment(dev, prod) should contain two files default.inc.php and config.inc.php.
 * This is the first file with production defaults. It contains all data which can be safely committed
 * to VCS(version control system). For sensitive values(passwords, api keys, emails) use config.inc.php
 * and make sure it's excluded from VCS by .gitignore.
 * do not add dependencies here, use dev_t0r\bids_rtc\signaling\App\RegisterDependencies class
 * @see https://php-di.org/doc/php-definitions.html#values
 */
return [
	'mode' => 'production',

	// Returns a detailed HTML page with error details and
	// a stack trace. Should be disabled in production.
	'slim.displayErrorDetails' => false,

	// Whether to display errors on the internal PHP log or not.
	'slim.logErrors' => true,

	// If true, display full errors with message and stack trace on the PHP log.
	// If false, display only "Slim Application Error" on the PHP log.
	// Doesn't do anything when 'logErrors' is false.
	'slim.logErrorDetails' => true,

	// CORS settings
	// https://github.com/neomerx/cors-psr7/blob/master/src/Strategies/Settings.php
	'cors.settings' => [
		isset($_SERVER['HTTPS']) ? 'https' : 'http', // serverOriginScheme
		$_SERVER['SERVER_NAME'], // serverOriginHost
		null, // serverOriginPort
		true, // isPreFlightCanBeCached
		86400, // preFlightCacheMaxAge
		true, // isForceAddMethods
		true, // isForceAddHeaders
		true, // isUseCredentials
		true, // areAllOriginsAllowed
		[], // allowedOrigins
		true, // areAllMethodsAllowed
		['put', 'options', 'post', 'delete', 'head', 'get'], // allowedLcMethods
		'PUT,OPTIONS,POST,DELETE,HEAD,GET', // allowedMethodsList
		true, // areAllHeadersAllowed
		['content-type', 'authorization', 'x-client-id'], // allowedLcHeaders
		'Content-Type,Authorization,X-Client-Id', // allowedHeadersList
		'X-Total-Count', // exposedHeadersList
		true, // isCheckHost
	],

	// PDO
	'pdo.dsn' => 'mysql:host=localhost;charset=utf8mb4',
	'pdo.username' => 'root',
	'pdo.password' => 'root',
	'pdo.options' => [
		\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET time_zone = \'+00:00\';',
	],

	// logger
	'logger.name' => 'App',
	'logger.path' => \realpath(__DIR__ . '/../../logs') . '/app',
	'logger.level' => 300, // equals WARNING level
	'logger.options' => [],

	// App Settings
	'app.name' => 'bids-rtc',
	'app.version' => '1.0.0',

	'firebase.sa_file' => \realpath(__DIR__) . '/firebase-service-account.json',
	'firebase.project_id' => 'bids-rtc',

	'firebase.api_token_cache_dir' => \realpath(__DIR__ . '/../../cache') . '/firebase/ApiToken',
	'firebase.auth.pubkey_cache_dir' => \realpath(__DIR__ . '/../../cache') . '/firebase/AuthPubKey',

	'my-auth.private_key' => __DIR__ . '/my-auth-private.pem',
	'my-auth.public_key' => __DIR__ . '/my-auth-public.pem',
];
