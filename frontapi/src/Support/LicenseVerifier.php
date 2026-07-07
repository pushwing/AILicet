<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 노드락 라이센스 파일(Ed25519 서명) 검증기 — 공개키만 사용.
 *
 * CI4 발급 엔진(App\Libraries\LicenseSigner)과 동일한 파일 포맷을 검증한다.
 *   { "v":1, "alg":"Ed25519", "data":"<base64(payload)>", "sig":"<base64(sig)>" }
 * 서명 대상은 data 문자열이며, 개인키 없이 공개키로만 위조 여부를 판별한다.
 */
final class LicenseVerifier
{
    private string $publicKey;

    /**
     * @param string $publicKeyBase64 base64 인코딩된 32바이트 Ed25519 공개키
     */
    public function __construct(string $publicKeyBase64)
    {
        $decoded         = base64_decode($publicKeyBase64, true);
        $this->publicKey = $decoded !== false ? $decoded : '';
    }

    public function isConfigured(): bool
    {
        return strlen($this->publicKey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES;
    }

    /**
     * 라이센스 파일을 검증하고 페이로드를 반환한다. 위조·손상·미설정 시 null.
     *
     * @return array<string, mixed>|null
     */
    public function verify(string $file): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $envelope = json_decode($file, true);
        if (! is_array($envelope) || ($envelope['alg'] ?? null) !== 'Ed25519') {
            return null;
        }

        $data = $envelope['data'] ?? null;
        $sig  = isset($envelope['sig']) ? base64_decode((string) $envelope['sig'], true) : false;
        if (! is_string($data) || $sig === false) {
            return null;
        }

        if (! sodium_crypto_sign_verify_detached($sig, $data, $this->publicKey)) {
            return null;
        }

        $json = base64_decode($data, true);
        if ($json === false) {
            return null;
        }

        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : null;
    }
}
