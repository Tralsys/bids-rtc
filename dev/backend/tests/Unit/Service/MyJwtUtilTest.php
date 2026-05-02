<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Tests\Unit\Service;

use BidsRtc\Backend\RetValueOrError;
use BidsRtc\Backend\Service\Auth\MyJwtClaims;
use BidsRtc\Backend\Service\MyJwtUtil;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\RegisteredClaims;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class MyJwtUtilTest extends TestCase
{
    // RSA key pair generated once per test run.
    private static string $privateKeyPem;
    private static string $publicKeyPem;

    // Shared Lcobucci Configuration and MyJwtUtil instances.
    private static Configuration $jwtConfig;
    private static MyJwtUtil $jwtUtil;

    private static string $issuer = 'https://test.example.com';

    // Fixed UUIDs used across tests.
    private static UuidInterface $appId;
    private static UuidInterface $clientId;

    public static function setUpBeforeClass(): void
    {
        // Generate an in-memory RSA-2048 key pair.
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($res, 'openssl_pkey_new() failed');

        openssl_pkey_export($res, $privateKeyPem);
        self::$privateKeyPem = $privateKeyPem;

        $details = openssl_pkey_get_details($res);
        self::assertIsArray($details);
        self::$publicKeyPem = $details['key'];

        // Build Lcobucci v5 asymmetric configuration.
        self::$jwtConfig = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText(self::$privateKeyPem),
            InMemory::plainText(self::$publicKeyPem),
        );

        self::$jwtUtil = new MyJwtUtil(self::$jwtConfig, self::$issuer);

        // Fixed UUIDs for claim-matching assertions.
        self::$appId    = Uuid::fromString('11111111-1111-1111-1111-111111111111');
        self::$clientId = Uuid::fromString('22222222-2222-2222-2222-222222222222');
    }

    // -------------------------------------------------------------------------
    // Test 1: issueAccessToken returns a non-empty string
    // -------------------------------------------------------------------------

    public function testIssueAccessTokenReturnsString(): void
    {
        $token = self::$jwtUtil->issueAccessToken('uid-test', self::$appId, self::$clientId);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    // -------------------------------------------------------------------------
    // Test 2: issueRefreshToken returns a non-empty string
    // -------------------------------------------------------------------------

    public function testIssueRefreshTokenReturnsString(): void
    {
        $token = self::$jwtUtil->issueRefreshToken('uid-test', self::$appId, self::$clientId);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    // -------------------------------------------------------------------------
    // Test 3: parseAndValidate access token returns correct MyJwtClaims
    // -------------------------------------------------------------------------

    public function testParseAndValidateAccessToken(): void
    {
        $uid   = 'user-abc';
        $token = self::$jwtUtil->issueAccessToken($uid, self::$appId, self::$clientId);

        $claims = self::$jwtUtil->parseAndValidate($token);

        $this->assertInstanceOf(MyJwtClaims::class, $claims);
        $this->assertSame($uid, $claims->uid);
        $this->assertSame(self::$appId->toString(), $claims->appId->toString());
        $this->assertSame(self::$clientId->toString(), $claims->clientId->toString());
        $this->assertSame(MyJwtClaims::KEY_TYPE_ACCESS, $claims->keyType);
    }

    // -------------------------------------------------------------------------
    // Test 4: parseAndValidate refresh token returns keyType = 'refresh'
    // -------------------------------------------------------------------------

    public function testParseAndValidateRefreshToken(): void
    {
        $uid   = 'user-refresh';
        $token = self::$jwtUtil->issueRefreshToken($uid, self::$appId, self::$clientId);

        $claims = self::$jwtUtil->parseAndValidate($token);

        $this->assertSame(MyJwtClaims::KEY_TYPE_REFRESH, $claims->keyType);
        $this->assertSame($uid, $claims->uid);
    }

    // -------------------------------------------------------------------------
    // Test 5: passing garbage string throws RetValueOrError with code 400
    // -------------------------------------------------------------------------

    public function testParseAndValidateInvalidFormatThrows400(): void
    {
        $this->expectException(RetValueOrError::class);
        $this->expectExceptionCode(400);

        self::$jwtUtil->parseAndValidate('this.is.not.a.valid.jwt');
    }

    // -------------------------------------------------------------------------
    // Test 6: token signed by a different issuer throws RetValueOrError 401
    // -------------------------------------------------------------------------

    public function testParseAndValidateWrongIssuerThrows401(): void
    {
        // Create a second MyJwtUtil that signs with a different issuer.
        $wrongIssuerUtil = new MyJwtUtil(self::$jwtConfig, 'https://evil.example.com');
        $token = $wrongIssuerUtil->issueAccessToken('uid-x', self::$appId, self::$clientId);

        $this->expectException(RetValueOrError::class);
        $this->expectExceptionCode(401);

        // Validate with the original util (which expects self::$issuer).
        self::$jwtUtil->parseAndValidate($token);
    }

    // -------------------------------------------------------------------------
    // Test 7: access token with exp in the past throws RetValueOrError 401
    // -------------------------------------------------------------------------

    public function testParseAndValidateExpiredAccessTokenThrows401(): void
    {
        // Build a token directly using the Lcobucci builder with exp = -1 hour.
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiredToken = self::$jwtConfig->builder()
            ->issuedBy(self::$issuer)
            ->issuedAt($now->modify('-2 hours'))
            ->expiresAt($now->modify('-1 hour'))
            ->relatedTo('uid-expired')
            ->withClaim('app_id', self::$appId->toString())
            ->withClaim('client_id', self::$clientId->toString())
            ->withClaim('typ', MyJwtClaims::KEY_TYPE_ACCESS)
            ->getToken(self::$jwtConfig->signer(), self::$jwtConfig->signingKey())
            ->toString();

        $this->expectException(RetValueOrError::class);
        $this->expectExceptionCode(401);

        self::$jwtUtil->parseAndValidate($expiredToken);
    }

    // -------------------------------------------------------------------------
    // Test 8: after issue+parse, uid / appId / clientId all match
    // -------------------------------------------------------------------------

    public function testAccessTokenContainsCorrectClaims(): void
    {
        $uid   = 'specific-uid-999';
        $appId = Uuid::fromString('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $cid   = Uuid::fromString('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');

        $token  = self::$jwtUtil->issueAccessToken($uid, $appId, $cid);
        $claims = self::$jwtUtil->parseAndValidate($token);

        $this->assertSame($uid, $claims->uid);
        $this->assertSame($appId->toString(), $claims->appId->toString());
        $this->assertSame($cid->toString(), $claims->clientId->toString());
        $this->assertSame(MyJwtClaims::KEY_TYPE_ACCESS, $claims->keyType);
        $this->assertInstanceOf(\DateTimeImmutable::class, $claims->issuedAt);
    }

    // -------------------------------------------------------------------------
    // Test 9: refresh token can be parsed without triggering an expiry error
    // -------------------------------------------------------------------------

    public function testRefreshTokenHasNoExpiry(): void
    {
        $uid   = 'uid-refresh-noexp';
        $token = self::$jwtUtil->issueRefreshToken($uid, self::$appId, self::$clientId);

        // No exception expected — refresh tokens have no exp claim.
        $claims = self::$jwtUtil->parseAndValidate($token);

        $this->assertSame(MyJwtClaims::KEY_TYPE_REFRESH, $claims->keyType);
        $this->assertSame($uid, $claims->uid);
    }
}
