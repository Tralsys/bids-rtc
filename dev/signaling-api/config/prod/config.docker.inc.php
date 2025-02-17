<?php

putenv('FIREBASE_AUTH_EMULATOR_HOST=localhost:9099');

return [
	// PDO
	'pdo.dsn' => 'mysql:host=mysql;dbname=test;charset=utf8mb4',
	'pdo.username' => 'test',
	'pdo.password' => 'test',
	'pdo.options' => [
		\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
	],

	// logger
	'logger.name' => 'App',
	'logger.path' => '/var/log/apache2/slim-app',
	'logger.level' => \Monolog\Logger::DEBUG,
	'logger.options' => [],
];
