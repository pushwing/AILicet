<?php

declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

/**
 * 노드락 라이센스 서명·검증기 (Ed25519, libsodium).
 *
 * 레거시 C++ 의 하드코딩 대칭키 방식을 폐기하고 비대칭 서명으로 교체한다.
 * - 서버는 **개인키**로 라이센스 페이로드에 서명한다(개인키는 env/KMS 보관, 하드코딩 금지).
 * - 클라이언트/frontApi 는 내장 **공개키**로 검증한다(개인키 없음 → 위조 불가).
 *
 * 라이센스 파일 포맷(JSON):
 *   { "v":1, "alg":"Ed25519", "data":"<base64(payload json)>", "sig":"<base64(detached sig)>" }
 * 서명 대상은 `data` 문자열 그 자체이므로 정규화 문제에서 자유롭다.
 */
final class LicenseSigner
{
    private const int VERSION = 1;
    private const string ALG  = 'Ed25519';

    /**
     * @param string $secretKey 64바이트 Ed25519 비밀키(바이너리)
     * @param string $publicKey 32바이트 Ed25519 공개키(바이너리)
     */
    public function __construct(
        private readonly string $secretKey,
        private readonly string $publicKey,
    ) {
        if (strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('Ed25519 비밀키 길이가 올바르지 않습니다.');
        }
        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('Ed25519 공개키 길이가 올바르지 않습니다.');
        }
    }

    /**
     * env(license.ed25519SecretKey/PublicKey, base64)에서 키를 로드한다.
     * 개인키는 운영에서 KMS/Secrets Manager 가 주입한다(코드 하드코딩 금지).
     */
    public static function fromConfig(): self
    {
        $secret = base64_decode((string) env('license.ed25519SecretKey'), true);
        $public = base64_decode((string) env('license.ed25519PublicKey'), true);

        if ($secret === false || $public === false || $secret === '' || $public === '') {
            throw new RuntimeException('라이센스 서명 키(license.ed25519*)가 설정되지 않았습니다.');
        }

        return new self($secret, $public);
    }

    /**
     * 신규 키페어를 base64 로 생성한다(초기 셋업·키 로테이션용).
     *
     * @return array{secretKey:string, publicKey:string}
     */
    public static function generateKeypair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'secretKey' => base64_encode(sodium_crypto_sign_secretkey($pair)),
            'publicKey' => base64_encode(sodium_crypto_sign_publickey($pair)),
        ];
    }

    /**
     * 페이로드에 서명해 라이센스 파일(JSON 문자열)을 만든다.
     *
     * @param array<string, mixed> $payload
     */
    public function sign(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('라이센스 페이로드 직렬화 실패: ' . json_last_error_msg());
        }

        $data = base64_encode($json);
        $sig  = sodium_crypto_sign_detached($data, $this->secretKey);

        $file = json_encode([
            'v'    => self::VERSION,
            'alg'  => self::ALG,
            'data' => $data,
            'sig'  => base64_encode($sig),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($file === false) {
            throw new RuntimeException('라이센스 파일 직렬화 실패: ' . json_last_error_msg());
        }

        return $file;
    }

    /**
     * 라이센스 파일을 검증하고 페이로드를 반환한다. 위조·손상 시 null.
     *
     * @return array<string, mixed>|null
     */
    public function verify(string $file): ?array
    {
        $envelope = json_decode($file, true);
        if (! is_array($envelope) || ($envelope['alg'] ?? null) !== self::ALG) {
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
