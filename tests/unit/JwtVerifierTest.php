<?php

declare(strict_types=1);

use App\Exceptions\InvalidTokenException;
use App\Exceptions\TokenExpiredException;
use App\Libraries\JwtLibrary;
use App\Libraries\JwtVerifier;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * JwtVerifier(RS256/HS256 검증 전용) 단위 테스트 (DB 불필요).
 *
 * 무중단 전환(HS256+RS256 동시 허용)과 alg 혼동 공격 차단을 검증한다.
 *
 * @internal
 */
final class JwtVerifierTest extends CIUnitTestCase
{
    private const string HMAC_SECRET = 'verifier-test-secret-0123456789-ab';

    private OpenSSLAsymmetricKey $privateKey;
    private string $publicPem;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
        $this->privateKey = $key;

        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);
        $this->publicPem = (string) $details['key'];
    }

    // --- RS256 검증 ---------------------------------------------------------

    public function testRs256RoundTrip(): void
    {
        $verifier = new JwtVerifier(['RS256'], null, $this->publicPem);
        $token    = $this->makeRs256(['sub' => 42, 'role' => 1, 'aff' => 'ailicet', 'exp' => time() + 3600]);

        $claims = $verifier->decode($token);

        $this->assertSame(42, $claims['sub']);
        $this->assertSame(1, $claims['role']);
        $this->assertSame('ailicet', $claims['aff']);
    }

    public function testRs256WrongKeyRejected(): void
    {
        // 다른 키페어로 검증 → 서명 불일치.
        $other        = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $otherDetails = openssl_pkey_get_details($other);
        $verifier     = new JwtVerifier(['RS256'], null, (string) $otherDetails['key']);

        $this->expectException(InvalidTokenException::class);
        $verifier->decode($this->makeRs256(['sub' => 1, 'exp' => time() + 3600]));
    }

    // --- 무중단 전환(HS256+RS256 동시 허용) --------------------------------

    public function testTransitionAcceptsBothHmacAndRsa(): void
    {
        $verifier = new JwtVerifier(['HS256', 'RS256'], self::HMAC_SECRET, $this->publicPem);

        $hmacToken = (new JwtLibrary(self::HMAC_SECRET))->encode(['sub' => 1, 'role' => 3], 3600);
        $rsaToken  = $this->makeRs256(['sub' => 2, 'role' => 1, 'exp' => time() + 3600]);

        $this->assertSame(1, $verifier->decode($hmacToken)['sub']);
        $this->assertSame(2, $verifier->decode($rsaToken)['sub']);
    }

    // --- alg 혼동 공격 차단 -------------------------------------------------

    public function testAlgConfusionAttackRejectedWhenRsaOnly(): void
    {
        // 공격자: 공개키 PEM 을 HMAC 시크릿처럼 사용해 HS256 토큰을 위조.
        // RS256 전용 검증기는 헤더 alg=HS256 을 허용 목록에서 거부해야 한다.
        $forged   = $this->makeForgedHs256WithSecret($this->publicPem, ['sub' => 999, 'role' => 1]);
        $verifier = new JwtVerifier(['RS256'], null, $this->publicPem);

        $this->expectException(InvalidTokenException::class);
        $verifier->decode($forged);
    }

    public function testAlgNoneRejected(): void
    {
        $header  = $this->b64($this->json(['typ' => 'JWT', 'alg' => 'none']));
        $payload = $this->b64($this->json(['sub' => 1]));
        $token   = $header . '.' . $payload . '.';

        $verifier = new JwtVerifier(['RS256'], null, $this->publicPem);

        $this->expectException(InvalidTokenException::class);
        $verifier->decode($token);
    }

    // --- 시간 클레임·형식 ---------------------------------------------------

    public function testExpiredRsaTokenThrows(): void
    {
        $verifier = new JwtVerifier(['RS256'], null, $this->publicPem);

        $this->expectException(TokenExpiredException::class);
        $verifier->decode($this->makeRs256(['sub' => 1, 'exp' => time() - 10]));
    }

    public function testTamperedRsaPayloadThrows(): void
    {
        $verifier      = new JwtVerifier(['RS256'], null, $this->publicPem);
        $token         = $this->makeRs256(['sub' => 1, 'exp' => time() + 3600]);
        [$h, , $s]     = explode('.', $token);
        $forgedPayload = $this->b64('{"sub":999}');

        $this->expectException(InvalidTokenException::class);
        $verifier->decode($h . '.' . $forgedPayload . '.' . $s);
    }

    public function testMalformedTokenThrows(): void
    {
        $verifier = new JwtVerifier(['RS256'], null, $this->publicPem);

        $this->expectException(InvalidTokenException::class);
        $verifier->decode('not-a-jwt');
    }

    // --- 생성자 가드 --------------------------------------------------------

    public function testEmptyAlgorithmsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        new JwtVerifier([], self::HMAC_SECRET, $this->publicPem);
    }

    public function testRsaAllowedWithoutPublicKeyRejected(): void
    {
        $this->expectException(RuntimeException::class);
        new JwtVerifier(['RS256'], self::HMAC_SECRET, null);
    }

    public function testHmacAllowedWithShortSecretRejected(): void
    {
        $this->expectException(RuntimeException::class);
        new JwtVerifier(['HS256'], 'too-short', $this->publicPem);
    }

    public function testUnsupportedAlgorithmRejected(): void
    {
        $this->expectException(RuntimeException::class);
        new JwtVerifier(['ES256'], self::HMAC_SECRET, $this->publicPem);
    }

    // --- 헬퍼 ---------------------------------------------------------------

    /**
     * @param array<string, mixed> $claims
     */
    private function makeRs256(array $claims): string
    {
        $input = $this->b64($this->json(['typ' => 'JWT', 'alg' => 'RS256']))
            . '.' . $this->b64($this->json($claims));

        openssl_sign($input, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return $input . '.' . $this->b64($signature);
    }

    /**
     * 임의 시크릿으로 HS256 서명을 위조한다(alg 혼동 공격 재현용).
     *
     * @param array<string, mixed> $claims
     */
    private function makeForgedHs256WithSecret(string $secret, array $claims): string
    {
        $input = $this->b64($this->json(['typ' => 'JWT', 'alg' => 'HS256']))
            . '.' . $this->b64($this->json($claims));
        $sig   = $this->b64(hash_hmac('sha256', $input, $secret, true));

        return $input . '.' . $sig;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
