<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Tests\Integration\Repository;

use BidsRtc\Backend\Repository\AppTableRepository;
use BidsRtc\Backend\Tests\Integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;

/**
 * Integration tests for AppTableRepository.
 */
class AppTableRepositoryTest extends IntegrationTestCase
{
    // -------------------------------------------------------------------------
    // Test 1: createNewApp + selectOne returns the inserted record
    // -------------------------------------------------------------------------

    public function testCreateAndSelectOne(): void
    {
        $repo  = new AppTableRepository(self::$pdo, $this->createNullLogger());
        $appId = Uuid::uuid7();

        $rowCount = $repo->createNewApp($appId, 'My App', 'A description', 'owner@example.com');
        $this->assertSame(1, $rowCount);

        $info = $repo->selectOne($appId);
        $this->assertNotNull($info);
        $this->assertSame('My App', $info->name);
        $this->assertSame('A description', $info->description);
        $this->assertSame('owner@example.com', $info->owner);
    }

    // -------------------------------------------------------------------------
    // Test 2: selectOne with an unknown UUID returns null
    // -------------------------------------------------------------------------

    public function testSelectOneNotFound(): void
    {
        $repo    = new AppTableRepository(self::$pdo, $this->createNullLogger());
        $unknown = Uuid::uuid7();

        $result = $repo->selectOne($unknown);
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Test 3: selectAll returns all inserted apps
    // -------------------------------------------------------------------------

    public function testSelectAll(): void
    {
        $repo = new AppTableRepository(self::$pdo, $this->createNullLogger());
        $repo->createNewApp(Uuid::uuid7(), 'App 1', 'Desc 1', 'owner1');
        $repo->createNewApp(Uuid::uuid7(), 'App 2', 'Desc 2', 'owner2');

        $results = $repo->selectAll(0, 100);
        $this->assertCount(2, $results);
    }

    // -------------------------------------------------------------------------
    // Test 4: selectAll on an empty table returns empty array
    // -------------------------------------------------------------------------

    public function testSelectAllEmpty(): void
    {
        $repo    = new AppTableRepository(self::$pdo, $this->createNullLogger());
        $results = $repo->selectAll(0, 100);
        $this->assertSame([], $results);
    }

    // -------------------------------------------------------------------------
    // Test 5: createNewApp returns int 1
    // -------------------------------------------------------------------------

    public function testCreateNewAppReturnsOne(): void
    {
        $repo     = new AppTableRepository(self::$pdo, $this->createNullLogger());
        $rowCount = $repo->createNewApp(Uuid::uuid7(), 'Test App', 'Test Desc', 'tester');
        $this->assertSame(1, $rowCount);
    }

    // -------------------------------------------------------------------------
    // Test 6: soft-deleted app is not returned by selectOne
    // -------------------------------------------------------------------------

    public function testSoftDeletedAppNotReturned(): void
    {
        $repo  = new AppTableRepository(self::$pdo, $this->createNullLogger());
        $appId = Uuid::uuid7();

        $repo->createNewApp($appId, 'Deleted App', 'Desc', 'owner');

        // Soft-delete the record directly via PDO (app_id is BINARY(16))
        $stmt = self::$pdo->prepare('UPDATE `applications` SET `deleted_at` = NOW() WHERE `app_id` = ?');
        $stmt->execute([$appId->getBytes()]);

        $result = $repo->selectOne($appId);
        $this->assertNull($result);
    }
}
