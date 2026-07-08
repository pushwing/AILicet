<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\InvalidTokenException;
use RuntimeException;

/**
 * JWT(HMAC-SHA256) 인코더/디코더 — 외부 라이브러리 없이 직접 구현.
 *
 * AILicet 이 **자체 발급**하는 단기 토큰(예: 플로팅 라이센스 활성화 토큰)의
 * 서명·검증에 사용한다. 발급·검증을 한 서버가 모두 담당하므로 대칭키(HS256)가 적합하다.
 *
 * > AITessera 가 발급한 토큰의 검증은 비대칭키(RS256)를 지원하는 {@see JwtVerifier} 가 담당한다.
 *
 * 보안:
 * - 헤더 `alg` 를 HS256 으로 고정 → `alg:none`·알고리즘 혼동 공격 차단
 * - 서명 비교는 `hash_equals` (상수 시간)
 * - `exp` 만료·`nbf` 유효시작 검증
 */
final class JwtLibrary
{
    use JwtCodec;

    private const string ALGORITHM = 'HS256';

    private string $secret;

    /**
     * @param string|null $secret 검증·서명 시크릿. 미지정 시 env('JWT_SECRET').
     */
    public function __construct(?string $secret = null)
    {
        $secret ??= (string) env('JWT_SECRET');

        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT 시크릿은 32자 이상이어야 합니다.');
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
     * @throws \App\Exceptions\TokenExpiredException 만료
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
        $this->assertTimeClaims($claims);

        return $claims;
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
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
}
