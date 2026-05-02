<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Tests\Unit\Service;

use BidsRtc\Backend\Repository\SdpTableRepository;
use BidsRtc\Backend\Service\SDPEncryptAndDecrypt;
use BidsRtc\Backend\Service\SDPExchangeService;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * Unit tests for SDPExchangeService::setUserIdAndClientId
 *
 * Only the validation logic in setUserIdAndClientId is tested here.
 * Methods requiring DB interaction are covered by integration tests.
 */
class SDPExchangeServiceTest extends TestCase
{
    private PDO $mockPdo;
    private SdpTableRepository $mockSdpRepo;
    private SDPEncryptAndDecrypt $mockEncDec;
    private LoggerInterface $mockLogger;
    private SDPExchangeService $service;

    protected function setUp(): void
    {
        $this->mockPdo     = $this->createMock(PDO::class);
        $this->mockSdpRepo = $this->createMock(SdpTableRepository::class);
        $this->mockEncDec  = $this->createMock(SDPEncryptAndDecrypt::class);
        $this->mockLogger  = $this->createMock(LoggerInterface::class);

        $this->service = new SDPExchangeService(
            $this->mockPdo,
            $this->mockSdpRepo,
            $this->mockEncDec,
            $this->mockLogger,
        );
    }

    // ------------------------------------------------------------------
    // Helper: build a minimal Slim PSR-7 server request
    // ------------------------------------------------------------------

    private function buildRequest(
        ?string $uid,
        ?string $clientIdHeader,
        ?string $clientIdFromToken = null,
        ?string $tokenType = null,
    ): \Psr\Http\Message\ServerRequestInterface {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/offer');

        if ($uid !== null) {
            $request = $request->withAttribute('uid', $uid);
        }

        if ($clientIdHeader !== null) {
            $request = $request->withHeader('X-Client-Id', $clientIdHeader);
        }

        if ($clientIdFromToken !== null) {
            $request = $request->withAttribute('clientIdFromToken', $clientIdFromToken);
        }

        if ($tokenType !== null) {
            $request = $request->withAttribute('tokenType', $tokenType);
        }

        return $request;
    }

    // ------------------------------------------------------------------
    // Test 1: valid uid + valid X-Client-Id → returns null (success)
    // ------------------------------------------------------------------

    public function testSetUserIdAndClientIdSuccess(): void
    {
        $clientId = Uuid::uuid4();
        $request  = $this->buildRequest(
            uid: 'test-user-id',
            clientIdHeader: $clientId->toString(),
        );
        $response = new Response();

        $result = $this->service->setUserIdAndClientId($request, $response);

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // Test 2: no uid attribute → returns 401 response
    // ------------------------------------------------------------------

    public function testSetUserIdAndClientIdMissingUid(): void
    {
        $request  = $this->buildRequest(uid: null, clientIdHeader: Uuid::uuid4()->toString());
        $response = new Response();

        $result = $this->service->setUserIdAndClientId($request, $response);

        $this->assertNotNull($result);
        $this->assertSame(401, $result->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Test 3: uid set but no X-Client-Id header → returns 400 response
    // ------------------------------------------------------------------

    public function testSetUserIdAndClientIdMissingClientId(): void
    {
        $request  = $this->buildRequest(uid: 'test-user-id', clientIdHeader: null);
        $response = new Response();

        $result = $this->service->setUserIdAndClientId($request, $response);

        $this->assertNotNull($result);
        $this->assertSame(400, $result->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Test 4: X-Client-Id is not a valid UUID → returns 400 response
    // ------------------------------------------------------------------

    public function testSetUserIdAndClientIdInvalidClientIdUuid(): void
    {
        $request  = $this->buildRequest(uid: 'test-user-id', clientIdHeader: 'not-a-valid-uuid');
        $response = new Response();

        $result = $this->service->setUserIdAndClientId($request, $response);

        $this->assertNotNull($result);
        $this->assertSame(400, $result->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Test 5: X-Client-Id differs from token's clientIdFromToken → 403
    // ------------------------------------------------------------------

    public function testSetUserIdAndClientIdMismatchWithToken(): void
    {
        $clientIdInHeader = Uuid::uuid4();
        $clientIdInToken  = Uuid::uuid4(); // deliberately different

        $request = $this->buildRequest(
            uid: 'test-user-id',
            clientIdHeader: $clientIdInHeader->toString(),
            clientIdFromToken: $clientIdInToken->toString(),
        );
        $response = new Response();

        $result = $this->service->setUserIdAndClientId($request, $response);

        $this->assertNotNull($result);
        $this->assertSame(403, $result->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Test 6: tokenType='refresh' → returns 401 response
    // ------------------------------------------------------------------

    public function testSetUserIdAndClientIdRefreshTokenRejected(): void
    {
        $clientId = Uuid::uuid4();

        $request = $this->buildRequest(
            uid: 'test-user-id',
            clientIdHeader: $clientId->toString(),
            // clientIdFromToken null → no mismatch check
            tokenType: 'refresh',
        );
        $response = new Response();

        $result = $this->service->setUserIdAndClientId($request, $response);

        $this->assertNotNull($result);
        $this->assertSame(401, $result->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Test 7: tokenType='access' with matching clientIdFromToken → null
    // ------------------------------------------------------------------

    public function testSetUserIdAndClientIdAccessTokenAccepted(): void
    {
        $clientId = Uuid::uuid4();

        $request = $this->buildRequest(
            uid: 'test-user-id',
            clientIdHeader: $clientId->toString(),
            clientIdFromToken: $clientId->toString(), // matches header
            tokenType: 'access',
        );
        $response = new Response();

        $result = $this->service->setUserIdAndClientId($request, $response);

        $this->assertNull($result);
    }
}
