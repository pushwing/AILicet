<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\InvalidTokenException;
use App\Exceptions\TokenExpiredException;

/**
 * JWT 인코딩·검증 공통 프리미티브.
 *
 * base64url 변환·JSON 파싱·시간 클레임(exp/nbf) 검증을 담당한다.
 * HS256 서명기({@see JwtLibrary})와 비대칭키 검증기({@see JwtVerifier})가 함께 사용해
 * 로직 중복을 제거한다.
 */
trait JwtCodec
{
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @throws InvalidTokenException 잘못된 base64url 인코딩
     */
    private function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidTokenException('잘못된 인코딩입니다.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidTokenException 본문 해석 불가
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

    /**
     * exp(만료)·nbf(유효 시작) 클레임을 현재 시각 기준으로 검증한다.
     *
     * @param array<string, mixed> $claims
     *
     * @throws TokenExpiredException 만료
     * @throws InvalidTokenException 아직 사용 불가(nbf)
     */
    private function assertTimeClaims(array $claims): void
    {
        $now = time();
        if (isset($claims['exp']) && $now >= (int) $claims['exp']) {
            throw new TokenExpiredException();
        }
        if (isset($claims['nbf']) && $now < (int) $claims['nbf']) {
            throw new InvalidTokenException('아직 사용할 수 없는 토큰입니다.');
        }
    }
}
