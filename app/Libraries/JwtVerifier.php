<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\InvalidTokenException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * JWT 검증 전용기 — AITessera 발급 토큰을 검증한다(발급 기능 없음).
 *
 * 대칭키(HS256)에서 비대칭키(RS256)로의 무중단 전환을 위해 두 알고리즘을 동시에
 * 허용할 수 있다. 전환 완료 후에는 허용 목록을 `['RS256']` 로만 좁히면 된다.
 *
 * 보안:
 * - **alg 혼동 공격 차단**: 검증 알고리즘을 토큰 헤더값에 맡기지 않고, 서버가 설정한
 *   허용 목록(`$allowedAlgorithms`)으로 강제한다. HS256 은 비공개 공유 시크릿으로,
 *   RS256 은 공개키로 **서로 다른 키**를 사용하므로 공개키를 HMAC 키로 악용하는
 *   고전적 취약점이 발생하지 않는다.
 * - 서명 검증 실패·미허용 알고리즘·만료는 예외로 구분한다.
 */
final class JwtVerifier
{
    use JwtCodec;

    private const string ALGO_HMAC = 'HS256';
    private const string ALGO_RSA  = 'RS256';

    /** @var list<string> */
    private array $allowedAlgorithms;

    private ?string $hmacSecret;

    private ?OpenSSLAsymmetricKey $publicKey;

    /**
     * @param list<string> $allowedAlgorithms 허용 서명 알고리즘. 예: ['HS256','RS256'] 또는 ['RS256']
     * @param string|null  $hmacSecret        HS256 검증용 공유 시크릿(전환기). HS256 미허용 시 불필요
     * @param string|null  $publicKeyPem      RS256 검증용 공개키(PEM). RS256 미허용 시 불필요
     *
     * @throws RuntimeException 설정 오류(빈 목록·키 누락·미지원 알고리즘)
     */
    public function __construct(array $allowedAlgorithms, ?string $hmacSecret = null, ?string $publicKeyPem = null)
    {
        if ($allowedAlgorithms === []) {
            throw new RuntimeException('허용 서명 알고리즘이 최소 하나 필요합니다.');
        }

        $publicKey = null;
        foreach ($allowedAlgorithms as $algo) {
            match ($algo) {
                self::ALGO_HMAC => $hmacSecret !== null && strlen($hmacSecret) >= 32
                    ? null
                    : throw new RuntimeException('HS256 허용 시 32자 이상의 시크릿이 필요합니다.'),
                self::ALGO_RSA => $publicKey = $this->loadPublicKey($publicKeyPem),
                default => throw new RuntimeException('지원하지 않는 서명 알고리즘입니다: ' . $algo),
            };
        }

        $this->allowedAlgorithms = $allowedAlgorithms;
        $this->hmacSecret        = $hmacSecret;
        $this->publicKey         = $publicKey;
    }

    /**
     * env 설정에서 검증기를 조립한다.
     *
     * - `JWT_VERIFY_ALGOS`: 허용 알고리즘 CSV (기본 `HS256,RS256`)
     * - `JWT_SECRET`: HS256 검증용 공유 시크릿 (HS256 허용 시)
     * - `JWT_PUBLIC_KEY_PATH`: RS256 검증용 공개키 PEM 경로 (RS256 허용 시)
     *
     * @throws RuntimeException 설정 오류
     */
    public static function fromConfig(): self
    {
        $algorithms = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) (env('JWT_VERIFY_ALGOS') ?: 'HS256,RS256')),
        )));

        $hmacSecret = in_array(self::ALGO_HMAC, $algorithms, true) ? (string) env('JWT_SECRET') : null;

        $publicKeyPem = in_array(self::ALGO_RSA, $algorithms, true)
            ? self::readPublicKeyFile((string) env('JWT_PUBLIC_KEY_PATH'))
            : null;

        return new self($algorithms, $hmacSecret, $publicKeyPem);
    }

    /**
     * RS256 공개키 PEM 파일을 읽는다(`@` 억제 없이 존재·가독성 사전 확인).
     *
     * @throws RuntimeException 경로 미설정·파일 없음·읽기 실패
     */
    private static function readPublicKeyFile(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('RS256 검증에는 JWT_PUBLIC_KEY_PATH 설정이 필요합니다.');
        }
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('공개키 파일을 읽을 수 없습니다: ' . $path);
        }

        $pem = file_get_contents($path);
        if ($pem === false) {
            throw new RuntimeException('공개키 파일 읽기에 실패했습니다: ' . $path);
        }

        return $pem;
    }

    /**
     * 토큰을 검증하고 클레임 배열을 반환한다.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidTokenException 형식·서명·알고리즘 오류
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
        $alg    = $header['alg'] ?? null;
        if (! is_string($alg) || ! in_array($alg, $this->allowedAlgorithms, true)) {
            throw new InvalidTokenException('지원하지 않는 서명 알고리즘입니다.');
        }

        if (! $this->verifySignature($alg, $header64 . '.' . $payload64, $signature64)) {
            throw new InvalidTokenException('서명이 일치하지 않습니다.');
        }

        $claims = $this->jsonDecode($this->base64UrlDecode($payload64));
        $this->assertTimeClaims($claims);

        return $claims;
    }

    /**
     * 서명을 알고리즘별 전용 키로 검증한다.
     * (알고리즘은 decode 에서 허용 목록으로 이미 강제된 값이다.)
     */
    private function verifySignature(string $alg, string $signingInput, string $signature64): bool
    {
        return match ($alg) {
            self::ALGO_HMAC => $this->verifyHmac($signingInput, $signature64),
            self::ALGO_RSA  => $this->verifyRsa($signingInput, $signature64),
            default         => false,
        };
    }

    private function verifyHmac(string $signingInput, string $signature64): bool
    {
        // hmacSecret 은 HS256 허용 시 생성자에서 non-null 보장.
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $signingInput, (string) $this->hmacSecret, true));

        return hash_equals($expected, $signature64);
    }

    private function verifyRsa(string $signingInput, string $signature64): bool
    {
        // publicKey 는 RS256 허용 시 생성자에서 non-null 보장.
        if ($this->publicKey === null) {
            return false;
        }

        $signature = $this->base64UrlDecode($signature64);
        $result    = openssl_verify($signingInput, $signature, $this->publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    /**
     * 공개키 PEM 을 파싱해 검증용 키 객체로 로드한다.
     *
     * @throws RuntimeException 키 누락·파싱 실패
     */
    private function loadPublicKey(?string $publicKeyPem): OpenSSLAsymmetricKey
    {
        if ($publicKeyPem === null || trim($publicKeyPem) === '') {
            throw new RuntimeException('RS256 허용 시 공개키(PEM)가 필요합니다.');
        }

        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) {
            throw new RuntimeException('공개키(PEM)를 파싱할 수 없습니다.');
        }

        return $key;
    }
}
