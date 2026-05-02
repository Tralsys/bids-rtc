<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Base class for all integration tests.
 *
 * Manages a shared PDO connection and truncates tables before each test.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        $host     = getenv('TEST_DB_HOST')     ?: 'test-mysql';
        $dbname   = getenv('TEST_DB_NAME')     ?: 'signaling';
        $user     = getenv('TEST_DB_USER')     ?: 'signaling';
        $password = getenv('TEST_DB_PASSWORD') ?: 'signaling';

        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

        self::$pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    protected function setUp(): void
    {
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
        self::$pdo->exec('TRUNCATE TABLE `sdp`;');
        self::$pdo->exec('TRUNCATE TABLE `clients`;');
        self::$pdo->exec('TRUNCATE TABLE `applications`;');
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
    }

    protected function createNullLogger(): NullLogger
    {
        return new NullLogger();
    }
}
