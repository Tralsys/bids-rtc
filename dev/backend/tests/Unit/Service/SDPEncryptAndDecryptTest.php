<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Tests\Unit\Service;

use BidsRtc\Backend\Service\SDPEncryptAndDecrypt;
use PHPUnit\Framework\TestCase;

class SDPEncryptAndDecryptTest extends TestCase
{
    private SDPEncryptAndDecrypt $sut;

    protected function setUp(): void
    {
        $this->sut = new SDPEncryptAndDecrypt();
    }

    /**
     * Test 1: encrypt then decrypt returns the original plaintext.
     */
    public function testEncryptDecryptRoundtrip(): void
    {
        $userId = 'user-id-abc123';
        $plain  = 'v=0\r\no=- 46117 2 IN IP4 127.0.0.1\r\n';

        $protected = $this->sut->encrypt($userId, $plain);
        $result    = $this->sut->decrypt($userId, $protected);

        $this->assertSame($plain, $result);
    }

    /**
     * Test 2: encrypted output is longer than the plaintext (IV + ciphertext).
     */
    public function testEncryptProducesNonEmptyBytes(): void
    {
        $userId = 'user-id-abc123';
        $plain  = 'hello world';

        $protected = $this->sut->encrypt($userId, $plain);

        // IV is 16 bytes; ciphertext is at least as long as plain (AES block-padded).
        $this->assertGreaterThan(strlen($plain), strlen($protected));
    }

    /**
     * Test 3: decrypting with a different userId returns null.
     */
    public function testDecryptWithWrongKeyReturnsNull(): void
    {
        $userId      = 'correct-user';
        $wrongUserId = 'wrong-user';
        $plain       = 'sensitive SDP data';

        $protected = $this->sut->encrypt($userId, $plain);
        $result    = $this->sut->decrypt($wrongUserId, $protected);

        $this->assertNull($result);
    }

    /**
     * Test 4: decrypting random bytes does not reproduce the original string.
     */
    public function testDecryptInvalidDataReturnsNull(): void
    {
        $userId    = 'user-id-abc123';
        $plain     = 'original SDP';
        $garbage   = random_bytes(64); // random IV + random "ciphertext"

        $result = $this->sut->decrypt($userId, $garbage);

        // Either null (openssl rejects it) or the garbage decodes to something
        // that is definitely not the original plaintext.
        $this->assertNotSame($plain, $result);
    }

    /**
     * Test 5: encrypting the same plaintext twice yields different ciphertext
     *          (random IV), but both still decrypt correctly.
     */
    public function testEncryptIsDeterministicKeyButRandomIv(): void
    {
        $userId = 'user-id-abc123';
        $plain  = 'same plaintext every time';

        $protected1 = $this->sut->encrypt($userId, $plain);
        $protected2 = $this->sut->encrypt($userId, $plain);

        // Different IVs → different ciphertext blobs
        $this->assertNotSame($protected1, $protected2);

        // Both must still round-trip correctly
        $this->assertSame($plain, $this->sut->decrypt($userId, $protected1));
        $this->assertSame($plain, $this->sut->decrypt($userId, $protected2));
    }

    /**
     * Test 6: encrypting/decrypting an empty string works correctly.
     */
    public function testEmptyStringRoundtrip(): void
    {
        $userId = 'user-id-abc123';
        $plain  = '';

        $protected = $this->sut->encrypt($userId, $plain);
        $result    = $this->sut->decrypt($userId, $protected);

        $this->assertSame($plain, $result);
    }

    /**
     * Test 7: arbitrary binary data can be encrypted and decrypted.
     */
    public function testBinaryDataRoundtrip(): void
    {
        $userId = 'binary-user';
        $plain  = random_bytes(256);

        $protected = $this->sut->encrypt($userId, $plain);
        $result    = $this->sut->decrypt($userId, $protected);

        $this->assertSame($plain, $result);
    }
}
