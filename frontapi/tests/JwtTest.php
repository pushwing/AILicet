<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\InvalidTokenException;
use App\Exception\TokenExpiredException;
use App\Support\Jwt;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class JwtTest extends TestCase
{
    private const string SECRET = 'test-secret-0123456789-abcdefghijkl';

    /**
     * @param array<string, mixed> $claims
     */
    private function encode(array $claims, string $secret = self::SECRET): string
    {
        $b64 = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $h   = $b64((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $p   = $b64((string) json_encode($claims));
        $s   = $b64(hash_hmac('sha256', $h . '.' . $p, $secret, true));

        return "{$h}.{$p}.{$s}";
    }

    public function testDecodeValidToken(): void
    {
        $jwt    = new Jwt(self::SECRET);
        $claims = $jwt->decode($this->encode(['sub' => 42, 'role' => 3, 'exp' => time() + 60]));

        $this->assertSame(42, $claims['sub']);
        $this->assertSame(3, $claims['role']);
    }

    public function testExpiredTokenThrows(): void
    {
        $this->expectException(TokenExpiredException::class);
        (new Jwt(self::SECRET))->decode($this->encode(['sub' => 1, 'exp' => time() - 10]));
    }

    public function testTamperedSignatureThrows(): void
    {
        $token = $this->encode(['sub' => 1]);
        $this->expectException(InvalidTokenException::class);
        (new Jwt(self::SECRET))->decode($token . 'x');
    }

    public function testWrongSecretThrows(): void
    {
        $token = $this->encode(['sub' => 1], 'another-secret-9876543210-zyxwvuts');
        $this->expectException(InvalidTokenException::class);
        (new Jwt(self::SECRET))->decode($token);
    }

    public function testAlgNoneRejected(): void
    {
        $b64 = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $token = $b64('{"typ":"JWT","alg":"none"}') . '.' . $b64('{"sub":1}') . '.';

        $this->expectException(InvalidTokenException::class);
        (new Jwt(self::SECRET))->decode($token);
    }
}
