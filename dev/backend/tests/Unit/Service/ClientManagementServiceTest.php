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
 * Unit tests for ClientManagementService
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
    // Helper: configure PDO so first prepare() returns a SELECT stmt
    //         (returning $fetchReturn) and second returns an UPDATE stmt
    //         (returning $updateRowCount via rowCount()).
    // ------------------------------------------------------------------

    private function configurePdoFetchThenUpdate(mixed $fetchReturn, int $updateRowCount): void
    {
        $selectStmt = $this->createMock(PDOStatement::class);
        $selectStmt->method('bindValue')->willReturn(true);
        $selectStmt->method('execute')->willReturn(true);
        $selectStmt->method('fetch')->willReturn($fetchReturn);

        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->method('bindValue')->willReturn(true);
        $updateStmt->method('execute')->willReturn(true);
        $updateStmt->method('rowCount')->willReturn($updateRowCount);

        $this->mockPdo->method('prepare')
            ->willReturnOnConsecutiveCalls($selectStmt, $updateStmt);
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

        $storedHash = hash('sha256', 'some-other-token');
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

        $storedHash = hash('sha256', $refreshTokenStr);
        $this->configurePdoFetch(['refresh_token_hash' => $storedHash]);

        $this->mockJwtUtil
            ->method('issueAccessToken')
            ->willReturn('access-token-string');

        $result = $this->service->getClientAccessToken($refreshTokenStr);

        $this->assertSame('access-token-string', $result);
    }

    // ==================================================================
    // rotateRefreshToken tests
    // ==================================================================

    // ------------------------------------------------------------------
    // Test 6: parseAndValidate throws → propagates without touching DB
    // ------------------------------------------------------------------

    public function testRotateRefreshTokenJwtValidationError(): void
    {
        $this->mockPdo
            ->expects($this->never())
            ->method('prepare');

        $this->mockJwtUtil
            ->method('parseAndValidate')
            ->willThrowException(new RetValueOrError(401, 'Invalid token'));

        $this->expectException(RetValueOrError::class);
        $this->expectExceptionCode(401);

        $this->service->rotateRefreshToken('bad-token');
    }

    // ------------------------------------------------------------------
    // Test 7: access token (typ=access) used instead of refresh → 401
    // ------------------------------------------------------------------

    public function testRotateRefreshTokenAccessTokenRejected(): void
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

        $this->service->rotateRefreshToken('some-access-token');
    }

    // ------------------------------------------------------------------
    // Test 8: DB returns null (client not found) → 404
    // ------------------------------------------------------------------

    public function testRotateRefreshTokenClientNotFound(): void
    {
        $claims = $this->buildRefreshClaims();

        $this->mockJwtUtil
            ->method('parseAndValidate')
            ->willReturn($claims);

        $this->configurePdoFetch(false);

        $this->expectException(RetValueOrError::class);
        $this->expectExceptionCode(404);

        $this->service->rotateRefreshToken('valid-refresh-token');
    }

    // ------------------------------------------------------------------
    // Test 9: stored hash does not match → password_verify fails → 401
    // ------------------------------------------------------------------

    public function testRotateRefreshTokenHashMismatch(): void
    {
        $claims = $this->buildRefreshClaims();

        $this->mockJwtUtil
            ->method('parseAndValidate')
            ->willReturn($claims);

        $storedHash = hash('sha256', 'different-token');
        $this->configurePdoFetch(['refresh_token_hash' => $storedHash]);

        $this->expectException(RetValueOrError::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Invalid refresh token');

        $this->service->rotateRefreshToken('valid-refresh-token');
    }

    // ------------------------------------------------------------------
    // Test 10: CAS UPDATE returns 0 (already rotated by concurrent req) → 401
    // ------------------------------------------------------------------

    public function testRotateRefreshTokenAlreadyRotated(): void
    {
        $refreshTokenStr = 'concurrent-token';
        $claims          = $this->buildRefreshClaims();

        $this->mockJwtUtil
            ->method('parseAndValidate')
            ->willReturn($claims);

        $this->mockJwtUtil
            ->method('issueRefreshToken')
            ->willReturn('new-refresh-token');

        $this->mockJwtUtil
            ->method('issueAccessToken')
            ->willReturn('new-access-token');

        $storedHash = hash('sha256', $refreshTokenStr);
        // SELECT returns the hash, UPDATE returns 0 rows (race)
        $this->configurePdoFetchThenUpdate(['refresh_token_hash' => $storedHash], 0);

        $this->expectException(RetValueOrError::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Refresh token already rotated');

        $this->service->rotateRefreshToken($refreshTokenStr);
    }

    // ------------------------------------------------------------------
    // Test 11: happy path → returns ClientTokenPair with new tokens
    // ------------------------------------------------------------------

    public function testRotateRefreshTokenSuccess(): void
    {
        $refreshTokenStr   = 'current-valid-refresh-token';
        $newRefreshToken   = 'new-refresh-token';
        $newAccessToken    = 'new-access-token';
        $claims            = $this->buildRefreshClaims();

        $this->mockJwtUtil
            ->method('parseAndValidate')
            ->willReturn($claims);

        $this->mockJwtUtil
            ->method('issueRefreshToken')
            ->willReturn($newRefreshToken);

        $this->mockJwtUtil
            ->method('issueAccessToken')
            ->willReturn($newAccessToken);

        $storedHash = hash('sha256', $refreshTokenStr);
        $this->configurePdoFetchThenUpdate(['refresh_token_hash' => $storedHash], 1);

        $result = $this->service->rotateRefreshToken($refreshTokenStr);

        $this->assertSame($newRefreshToken, $result->refresh_token);
        $this->assertSame($newAccessToken, $result->access_token);
    }
}
