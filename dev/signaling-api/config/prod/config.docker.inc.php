<?php

putenv('FIREBASE_AUTH_EMULATOR_HOST=firebase:9099');

return [
	// PDO
	'pdo.dsn' => 'mysql:host=mysql;dbname=test;charset=utf8mb4',
	'pdo.username' => 'test',
	'pdo.password' => 'test',

	// logger
	'logger.name' => 'App',
	'logger.path' => '/var/log/apache2/slim-app',
	'logger.level' => \Monolog\Logger::DEBUG,
	'logger.options' => [],
];
