<?php

declare(strict_types=1);

namespace Tests;

use App\Support\LicenseVerifier;
use PHPUnit\Framework\TestCase;

/**
 * 노드락 공개키 검증기 — CI4 발급 엔진(P2 LicenseSigner)과 파일 포맷 정합성 검증.
 *
 * @internal
 */
final class LicenseVerifierTest extends TestCase
{
    /**
     * CI4 App\Libraries\LicenseSigner::sign() 과 동일한 방식으로 서명 파일을 만든다.
     *
     * @param array<string, mixed> $payload
     */
    private function signLikeP2(array $payload, string $secretKeyBinary): string
    {
        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $data = base64_encode($json);
        $sig  = sodium_crypto_sign_detached($data, $secretKeyBinary);

        return (string) json_encode([
            'v'    => 1,
            'alg'  => 'Ed25519',
            'data' => $data,
            'sig'  => base64_encode($sig),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function testVerifiesP2SignedFile(): void
    {
        $pair     = sodium_crypto_sign_keypair();
        $secret   = sodium_crypto_sign_secretkey($pair);
        $public   = base64_encode(sodium_crypto_sign_publickey($pair));
        $verifier = new LicenseVerifier($public);

        $file    = $this->signLikeP2(['host_id' => 'HOST-A', 'product_code' => 'PT001', 'modules' => ['MD001']], $secret);
        $payload = $verifier->verify($file);

        $this->assertNotNull($payload);
        $this->assertSame('HOST-A', $payload['host_id']);
        $this->assertSame(['MD001'], $payload['modules']);
    }

    public function testRejectsTamperedFile(): void
    {
        $pair     = sodium_crypto_sign_keypair();
        $verifier = new LicenseVerifier(base64_encode(sodium_crypto_sign_publickey($pair)));

        $file            = $this->signLikeP2(['host_id' => 'HOST-A'], sodium_crypto_sign_secretkey($pair));
        $env             = json_decode($file, true);
        $env['data']     = base64_encode('{"host_id":"HACKED"}');

        $this->assertNull($verifier->verify((string) json_encode($env)));
    }

    public function testRejectsWrongPublicKey(): void
    {
        $signer   = sodium_crypto_sign_keypair();
        $other    = sodium_crypto_sign_keypair();
        $verifier = new LicenseVerifier(base64_encode(sodium_crypto_sign_publickey($other)));

        $file = $this->signLikeP2(['host_id' => 'HOST-A'], sodium_crypto_sign_secretkey($signer));
        $this->assertNull($verifier->verify($file));
    }

    public function testUnconfiguredVerifierReturnsNull(): void
    {
        $this->assertFalse((new LicenseVerifier(''))->isConfigured());
        $this->assertNull((new LicenseVerifier(''))->verify('{}'));
    }
}
