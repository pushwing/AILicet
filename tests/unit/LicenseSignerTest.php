<?php

declare(strict_types=1);

use App\Libraries\LicenseSigner;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Ed25519 라이센스 서명·검증 단위 테스트 (DB 불필요).
 *
 * @internal
 */
final class LicenseSignerTest extends CIUnitTestCase
{
    private LicenseSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $pair = LicenseSigner::generateKeypair();
        $this->signer = new LicenseSigner(
            base64_decode($pair['secretKey'], true) ?: '',
            base64_decode($pair['publicKey'], true) ?: '',
        );
    }

    public function testSignVerifyRoundTrip(): void
    {
        $payload = ['host_id' => 'ABC123', 'product_code' => 'PT001', 'modules' => ['MD001']];
        $file    = $this->signer->sign($payload);

        $verified = $this->signer->verify($file);
        $this->assertNotNull($verified);
        $this->assertSame('ABC123', $verified['host_id']);
        $this->assertSame(['MD001'], $verified['modules']);
    }

    public function testFileIsSelfDescribing(): void
    {
        $envelope = json_decode($this->signer->sign(['a' => 1]), true);
        $this->assertSame('Ed25519', $envelope['alg']);
        $this->assertArrayHasKey('data', $envelope);
        $this->assertArrayHasKey('sig', $envelope);
    }

    public function testTamperedPayloadFailsVerification(): void
    {
        $file     = $this->signer->sign(['host_id' => 'ABC123']);
        $envelope  = json_decode($file, true);
        // data 를 다른 페이로드로 교체(서명은 그대로) → 검증 실패해야 함
        $envelope['data'] = base64_encode('{"host_id":"HACKED"}');
        $forged           = json_encode($envelope);

        $this->assertNull($this->signer->verify((string) $forged));
    }

    public function testWrongPublicKeyFailsVerification(): void
    {
        $file  = $this->signer->sign(['host_id' => 'ABC123']);
        $other = LicenseSigner::generateKeypair();
        $otherSigner = new LicenseSigner(
            base64_decode($other['secretKey'], true) ?: '',
            base64_decode($other['publicKey'], true) ?: '',
        );

        $this->assertNull($otherSigner->verify($file));
    }

    public function testGarbageInputReturnsNull(): void
    {
        $this->assertNull($this->signer->verify('not-json'));
        $this->assertNull($this->signer->verify('{"alg":"none"}'));
    }

    public function testInvalidKeyLengthThrows(): void
    {
        $this->expectException(RuntimeException::class);
        new LicenseSigner('short', 'short');
    }
}
