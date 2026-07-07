<?php

declare(strict_types=1);

namespace App\Support;

use App\Exception\InvalidTokenException;
use App\Exception\TokenExpiredException;

/**
 * JWT(HS256) 검증기 — AITessera 발급 토큰을 공유 시크릿으로 검증.
 *
 * 보안: alg HS256 고정(alg:none 차단), hash_equals 상수시간 비교, exp 만료 검증.
 */
final class Jwt
{
    public function __construct(private readonly string $secret)
    {
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidTokenException|TokenExpiredException
     */
    public function decode(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new InvalidTokenException();
        }
        [$h64, $p64, $s64] = $parts;

        $header = json_decode($this->b64d($h64), true);
        if (! is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            throw new InvalidTokenException('지원하지 않는 서명 알고리즘입니다.');
        }

        $expected = $this->b64e(hash_hmac('sha256', $h64 . '.' . $p64, $this->secret, true));
        if (! hash_equals($expected, $s64)) {
            throw new InvalidTokenException('서명이 일치하지 않습니다.');
        }

        $claims = json_decode($this->b64d($p64), true);
        if (! is_array($claims)) {
            throw new InvalidTokenException();
        }

        if (isset($claims['exp']) && time() >= (int) $claims['exp']) {
            throw new TokenExpiredException();
        }

        /** @var array<string, mixed> $claims */
        return $claims;
    }

    private function b64e(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function b64d(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidTokenException('잘못된 인코딩입니다.');
        }

        return $decoded;
    }
}
