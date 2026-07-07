<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\InvalidTokenException;
use App\Exceptions\TokenExpiredException;
use RuntimeException;

/**
 * JWT(HMAC-SHA256) 인코더/디코더 — 외부 라이브러리 없이 직접 구현.
 *
 * AITessera 가 발급한 Access Token(HS256)을 AILicet 이 공유 시크릿으로 검증한다.
 * 보안:
 * - 헤더 `alg` 를 HS256 으로 고정 → `alg:none`·알고리즘 혼동 공격 차단
 * - 서명 비교는 `hash_equals` (상수 시간)
 * - `exp` 만료·`nbf` 유효시작 검증
 */
final class JwtLibrary
{
    private const string ALGORITHM = 'HS256';

    private string $secret;

    /**
     * @param string|null $secret 검증·서명 시크릿. 미지정 시 env('JWT_SECRET').
     */
    public function __construct(?string $secret = null)
    {
        $secret ??= (string) env('JWT_SECRET');

        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET 은 32자 이상이어야 합니다.');
        }

        $this->secret = $secret;
    }

    /**
     * 클레임을 HS256 토큰으로 인코딩한다.
     *
     * @param array<string, mixed> $claims
     * @param int|null             $ttl    유효기간(초). 지정 시 exp 클레임 추가.
     */
    public function encode(array $claims, ?int $ttl = null): string
    {
        $now = time();
        $claims['iat'] ??= $now;
        if ($ttl !== null) {
            $claims['exp'] = $now + $ttl;
        }

        $header  = $this->base64UrlEncode($this->jsonEncode(['typ' => 'JWT', 'alg' => self::ALGORITHM]));
        $payload = $this->base64UrlEncode($this->jsonEncode($claims));
        $sig     = $this->sign($header . '.' . $payload);

        return $header . '.' . $payload . '.' . $sig;
    }

    /**
     * 토큰을 검증하고 클레임 배열을 반환한다.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidTokenException 형식·서명 오류
     * @throws TokenExpiredException 만료
     */
    public function decode(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new InvalidTokenException();
        }
        [$header64, $payload64, $signature64] = $parts;

        $header = $this->jsonDecode($this->base64UrlDecode($header64));
        if (($header['alg'] ?? null) !== self::ALGORITHM) {
            throw new InvalidTokenException('지원하지 않는 서명 알고리즘입니다.');
        }

        $expected = $this->sign($header64 . '.' . $payload64);
        if (! hash_equals($expected, $signature64)) {
            throw new InvalidTokenException('서명이 일치하지 않습니다.');
        }

        $claims = $this->jsonDecode($this->base64UrlDecode($payload64));

        $now = time();
        if (isset($claims['exp']) && $now >= (int) $claims['exp']) {
            throw new TokenExpiredException();
        }
        if (isset($claims['nbf']) && $now < (int) $claims['nbf']) {
            throw new InvalidTokenException('아직 사용할 수 없는 토큰입니다.');
        }

        return $claims;
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidTokenException('잘못된 인코딩입니다.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonEncode(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('JSON 인코딩 실패: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonDecode(string $json): array
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new InvalidTokenException('토큰 본문을 해석할 수 없습니다.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
