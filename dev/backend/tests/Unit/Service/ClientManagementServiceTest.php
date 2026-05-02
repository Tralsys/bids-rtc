<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Tests\Unit\Service;

use BidsRtc\Backend\RetValueOrError;
use BidsRtc\Backend\Service\Auth\MyJwtClaims;
use BidsRtc\Backend\Service\ClientManagementService;
use BidsRtc\Backend\Service\MyJwtUtil;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Unit tests for ClientManagementService::getClientAccessToken
 *
 * ClientTableRepository is created internally via `new` in the constructor,
 * so PDO and PDOStatement are mocked to control repository behaviour.
 */
class ClientManagementServiceTest extends TestCase
{
    private PDO $mockPdo;
    private LoggerInterface $mockLogger;
    private MyJwtUtil $mockJwtUtil;
    private ClientManagementService $service;

    protected function setUp(): void
    {
        $this->mockPdo     = $this->createMock(PDO::class);
        $this->mockLogger  = $this->createMock(LoggerInterface::class);
        $this->mockJwtUtil = $this->createMock(MyJwtUtil::class);

        $this->service = new ClientManagementService(
            $this->mockPdo,
            $this->mockLogger,
            $this->mockJwtUtil,
        );
    }

    // ------------------------------------------------------------------
    // Helper: build a MyJwtClaims value object
    // ------------------------------------------------------------------

    private function buildRefreshClaims(string $uid = 'test-uid'): MyJwtClaims
    {
        return new MyJwtClaims(
            uid: $uid,
            appId: Uuid::uuid4(),
            clientId: Uuid::uuid4(),
            keyType: MyJwtClaims::KEY_TYPE_REFRESH,
            issuedAt: new \DateTimeImmutable(),
        );
    }

    // ------------------------------------------------------------------
    // Helper: configure the PDO mock so that selectOneRefreshToken returns
    //         whatever $fetchReturn the caller specifies.
    // ------------------------------------------------------------------

    private function configurePdoFetch(mixed $fetchReturn): void
    {
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('bindValue')->willReturn(true);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetch')->willReturn($fetchReturn);

        $this->mockPdo->method('prepare')->willReturn($mockStmt);
    }

    // ------------------------------------------------------------------
    // Test 1: parseAndValidate throws → service propagates, DB never touched
    // ------------------------------------------------------------------

    public function testGetClientAccessTokenJwtValidationError(): void
    {
        $this->mockPdo
            ->expects($this->never())
            ->method('prepare');

        $this->mockJwtUtil
            ->method('parseAndValidate')
            ->willThrowException(new RetValueOrError(400, 'Invalid token format'));

        $this->expectException(RetValueOrError::class);
        $this->expectExceptionCode(400);

        $this->service->getClientAccessToken('bad-token');
    }

    // ------------------------------------------------------------------
    // Test 2: token keyType is 'access' → 401, DB never touched
    // ------------------------------------------------------------------

    public function testGetClientAccessTokenAccessTokenRejected(): void
    {
        $this->mockPdo
            ->expects($this->never())
            ->method('prepare');

        $accessClaims = new MyJwtClaims(
            uid: 'test-uid',
            appId: Uuid::uuid4(),
            clientId: Uuid::uuid4(),
            keyType: MyJwtClaims::KEY_TYPE_ACCESS,
            issuedAt: new \DateTimeImmutable(),
        );

        $this->mockJwtUtil
            ->method('parseAndValidate')
            ->willReturn($accessClaims);

        $this->expectException(RetValueOrError::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Token type mismatch');

        $this->service->getClientAccessToken('some-access-token');
    }

    // ------------------------------------------------------------------
    // Test 3: valid refresh claims, DB returns false (not found) → 404
    // ------------------------------------------------------------------

    public function testGetClientAccessTokenClientNotFound(): void
    {
        $claims = $this->buildRefreshClaims();

        $this->mockJwtUtil
            ->method('parseAndValidate')
            ->willReturn($claims);

        // fetch returns false → selectOneRefreshToken returns null
        $this->configurePdoFetch(false);

        $this->expectException(RetValueOrError::class);
        $this->expectExceptionCode(404);

        $this->service->getClientAccessToken('valid-refresh-token');
    }

    // ------------------------------------------------------------------
    // Test 4: stored hash belongs to a *different* token → password_verify
    //         fails → 401
    // ------------------------------------------------------------------

    public function testGetClientAccessTokenInvalidRefreshToken(): void
    {
        $claims = $this->buildRefreshClaims();

        $this->mockJwtUtil
            ->method('parseAndValidate')
            ->willReturn($claims);

        // Hash of a completely different token string
        $storedHash = password_hash('some-other-token', PASSWORD_DEFAULT);
        $this->configurePdoFetch(['refresh_token_hash' => $storedHash]);

        $this->expectException(RetValueOrError::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Invalid refresh token');

        $this->service->getClientAccessToken('valid-refresh-token');
    }

    // ------------------------------------------------------------------
    // Test 5: everything matches → returns the newly issued access token
    // ------------------------------------------------------------------

    public function testGetClientAccessTokenSuccess(): void
    {
        $refreshTokenStr = 'my-valid-refresh-token-string';
        $claims          = $this->buildRefreshClaims();

        $this->mockJwtUtil
            ->method('parseAndValidate')
            ->willReturn($claims);

        // Hash of the same token that will be passed to the service
        $storedHash = password_hash($refreshTokenStr, PASSWORD_DEFAULT);
        $this->configurePdoFetch(['refresh_token_hash' => $storedHash]);

        $this->mockJwtUtil
            ->method('issueAccessToken')
            ->willReturn('access-token-string');

        $result = $this->service->getClientAccessToken($refreshTokenStr);

        $this->assertSame('access-token-string', $result);
    }
}
