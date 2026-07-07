<?php

declare(strict_types=1);

use App\Exceptions\InvalidTokenException;
use App\Exceptions\TokenExpiredException;
use App\Libraries\JwtLibrary;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * JwtLibrary(HS256) 검증 단위 테스트 (DB 불필요).
 *
 * @internal
 */
final class JwtLibraryTest extends CIUnitTestCase
{
    private const string SECRET       = 'test-secret-key-0123456789-abcdefgh';
    private const string OTHER_SECRET = 'another-secret-key-9876543210-zyxwvu';

    private JwtLibrary $jwt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = new JwtLibrary(self::SECRET);
    }

    public function testEncodeDecodeRoundTrip(): void
    {
        $token  = $this->jwt->encode(['sub' => 42, 'role' => 1, 'aff' => 'ailicet'], 3600);
        $claims = $this->jwt->decode($token);

        $this->assertSame(42, $claims['sub']);
        $this->assertSame(1, $claims['role']);
        $this->assertSame('ailicet', $claims['aff']);
        $this->assertArrayHasKey('iat', $claims);
        $this->assertArrayHasKey('exp', $claims);
    }

    public function testShortSecretIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        new JwtLibrary('too-short');
    }

    public function testExpiredTokenThrows(): void
    {
        // exp 를 과거로: ttl 음수
        $token = $this->jwt->encode(['sub' => 1, 'role' => 3], -10);

        $this->expectException(TokenExpiredException::class);
        $this->jwt->decode($token);
    }

    public function testTamperedPayloadThrows(): void
    {
        $token          = $this->jwt->encode(['sub' => 1, 'role' => 3], 3600);
        [$h, , $s]      = explode('.', $token);
        $forgedPayload  = rtrim(strtr(base64_encode('{"sub":999,"role":1}'), '+/', '-_'), '=');
        $forgedToken    = $h . '.' . $forgedPayload . '.' . $s;

        $this->expectException(InvalidTokenException::class);
        $this->jwt->decode($forgedToken);
    }

    public function testWrongSecretThrows(): void
    {
        $token = (new JwtLibrary(self::OTHER_SECRET))->encode(['sub' => 1, 'role' => 1], 3600);

        $this->expectException(InvalidTokenException::class);
        $this->jwt->decode($token);
    }

    public function testAlgNoneIsRejected(): void
    {
        $enc     = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $header  = $enc('{"typ":"JWT","alg":"none"}');
        $payload = $enc('{"sub":1,"role":1}');
        $token   = $header . '.' . $payload . '.';

        $this->expectException(InvalidTokenException::class);
        $this->jwt->decode($token);
    }

    public function testMalformedTokenThrows(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->jwt->decode('only.two');
    }
}
