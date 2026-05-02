<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Tests\Integration\Repository;

use BidsRtc\Backend\Repository\AppTableRepository;
use BidsRtc\Backend\Repository\ClientTableRepository;
use BidsRtc\Backend\Tests\Integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Integration tests for ClientTableRepository.
 */
class ClientTableRepositoryTest extends IntegrationTestCase
{
    private const TEST_USER_ID = 'test-user-id';

    // Inserts a test application row (required for the FK on clients.app_id) and returns its UUID.
    private function insertTestApp(): UuidInterface
    {
        $appId = Uuid::uuid7();
        $repo  = new AppTableRepository(self::$pdo, new \Psr\Log\NullLogger());
        $repo->createNewApp($appId, 'Test App', 'Desc', 'owner');
        return $appId;
    }

    private function hashedUserId(): string
    {
        return hash('sha256', self::TEST_USER_ID);
    }

    // -------------------------------------------------------------------------
    // Test 1: createNewClient + selectOne returns the inserted DbClientInfo
    // -------------------------------------------------------------------------

    public function testCreateAndSelectOne(): void
    {
        $appId    = $this->insertTestApp();
        $clientId = Uuid::uuid7();
        $userId   = $this->hashedUserId();
        $repo     = new ClientTableRepository(self::$pdo, $this->createNullLogger());

        $rowCount = $repo->createNewClient($userId, $clientId, $appId, 'My Client', 'hash_abc');
        $this->assertSame(1, $rowCount);

        $info = $repo->selectOne($userId, $clientId);
        $this->assertNotNull($info);
        $this->assertSame($clientId->toString(), $info->client_id->toString());
        $this->assertSame($appId->toString(), $info->app_id->toString());
        $this->assertSame('My Client', $info->name);
    }

    // -------------------------------------------------------------------------
    // Test 2: selectOne with an unknown client_id returns null
    // -------------------------------------------------------------------------

    public function testSelectOneNotFound(): void
    {
        $repo     = new ClientTableRepository(self::$pdo, $this->createNullLogger());
        $userId   = $this->hashedUserId();
        $unknown  = Uuid::uuid7();

        $result = $repo->selectOne($userId, $unknown);
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Test 3: count returns 2 after inserting 2 clients for the same user
    // -------------------------------------------------------------------------

    public function testCount(): void
    {
        $appId  = $this->insertTestApp();
        $userId = $this->hashedUserId();
        $repo   = new ClientTableRepository(self::$pdo, $this->createNullLogger());

        $repo->createNewClient($userId, Uuid::uuid7(), $appId, 'Client A', 'hash_a');
        $repo->createNewClient($userId, Uuid::uuid7(), $appId, 'Client B', 'hash_b');

        $this->assertSame(2, $repo->count($userId));
    }

    // -------------------------------------------------------------------------
    // Test 4: selectAll returns all clients for the user
    // -------------------------------------------------------------------------

    public function testSelectAll(): void
    {
        $appId  = $this->insertTestApp();
        $userId = $this->hashedUserId();
        $repo   = new ClientTableRepository(self::$pdo, $this->createNullLogger());

        $repo->createNewClient($userId, Uuid::uuid7(), $appId, 'Client A', 'hash_a');
        $repo->createNewClient($userId, Uuid::uuid7(), $appId, 'Client B', 'hash_b');

        $results = $repo->selectAll($userId, 0, 100);
        $this->assertCount(2, $results);
    }

    // -------------------------------------------------------------------------
    // Test 5: selectOneRefreshToken returns the stored hash
    // -------------------------------------------------------------------------

    public function testSelectOneRefreshToken(): void
    {
        $appId    = $this->insertTestApp();
        $clientId = Uuid::uuid7();
        $userId   = $this->hashedUserId();
        $hash     = 'my_refresh_token_hash_xyz';
        $repo     = new ClientTableRepository(self::$pdo, $this->createNullLogger());

        $repo->createNewClient($userId, $clientId, $appId, 'Token Client', $hash);

        $retrieved = $repo->selectOneRefreshToken($userId, $clientId);
        $this->assertSame($hash, $retrieved);
    }

    // -------------------------------------------------------------------------
    // Test 6: delete soft-deletes the client and returns 1; selectOne returns null
    // -------------------------------------------------------------------------

    public function testDelete(): void
    {
        $appId    = $this->insertTestApp();
        $clientId = Uuid::uuid7();
        $userId   = $this->hashedUserId();
        $repo     = new ClientTableRepository(self::$pdo, $this->createNullLogger());

        $repo->createNewClient($userId, $clientId, $appId, 'Delete Me', 'hash_del');

        $rowCount = $repo->delete($userId, $clientId);
        $this->assertSame(1, $rowCount);

        $result = $repo->selectOne($userId, $clientId);
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Test 7: delete on an unknown client returns 0
    // -------------------------------------------------------------------------

    public function testDeleteReturnsZeroForUnknown(): void
    {
        $repo    = new ClientTableRepository(self::$pdo, $this->createNullLogger());
        $userId  = $this->hashedUserId();
        $unknown = Uuid::uuid7();

        $rowCount = $repo->delete($userId, $unknown);
        $this->assertSame(0, $rowCount);
    }
}
