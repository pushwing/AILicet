<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Exceptions\AitesseraException;

/**
 * AITessera 운영자 회원관리 API 클라이언트.
 *
 * 운영자 액세스 토큰(Bearer)으로 회원 목록·상세·수정 및 계정 생성 API를 호출한다.
 * 모든 호출은 로그인한 운영자의 AITessera 토큰을 사용한다.
 */
class AitesseraClient
{
    /** AILicet 제품군(affiliation) 식별자 — AITessera 로그인·계정생성 요청에 함께 보낸다. */
    public const string AFFILIATION = 'ailicet';

    public function __construct(private readonly string $baseUrl)
    {
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '';
    }

    /**
     * 이메일·비밀번호·소속(affiliation)으로 로그인해 Access/Refresh 토큰을 발급받는다
     * (공개 엔드포인트, 인증 헤더 불필요).
     *
     * @return array{access_token: string, refresh_token: ?string}|null 자격증명 불일치 등 비2xx 응답이면 null
     *
     * @throws AitesseraException AITessera 가 설정되지 않음
     */
    public function login(string $email, string $password, string $affiliation): ?array
    {
        if (! $this->isConfigured()) {
            throw new AitesseraException('AITessera 가 설정되지 않았습니다.', 'AITESSERA_NOT_CONFIGURED', 503);
        }

        $response = service('curlrequest')->request('POST', rtrim($this->baseUrl, '/') . '/api/v1/tokens', [
            'json'        => ['email' => $email, 'password' => $password, 'affiliation' => $affiliation],
            'timeout'     => 5,
            'http_errors' => false,
        ]);

        // AITessera 토큰 발급은 201(Created)을 반환한다. 2xx 를 성공으로 처리.
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return null;
        }

        $body  = json_decode((string) $response->getBody(), true);
        $token = is_array($body) ? $this->extractToken($body) : null;
        if ($token === null) {
            return null;
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;

        return [
            'access_token'  => $token,
            'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
        ];
    }

    /**
     * 회원 목록(운영자). meta 표준 포함.
     *
     * @param array<string, mixed> $query page/per_page/role/is_active/q/sort
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listUsers(string $token, array $query): array
    {
        $body = $this->request($token, 'get', '/api/v1/users', ['query' => $query]);

        /** @var list<array<string, mixed>> $items */
        $items = is_array($body['data'] ?? null) ? $body['data'] : [];
        /** @var array<string, mixed> $meta */
        $meta = is_array($body['meta'] ?? null) ? $body['meta'] : [];

        return ['items' => $items, 'meta' => $meta];
    }

    /**
     * 회원 상세.
     *
     * @return array<string, mixed>
     */
    public function getUser(string $token, int $id): array
    {
        $body = $this->request($token, 'get', "/api/v1/users/{$id}");

        /** @var array<string, mixed> $data */
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        return $data;
    }

    /**
     * 회원 정보 수정(부분 갱신, PATCH).
     *
     * @param array<string, mixed> $data
     */
    public function updateUser(string $token, int $id, array $data): void
    {
        $this->request($token, 'patch', "/api/v1/users/{$id}", ['json' => $data]);
    }

    /**
     * 운영자/대행사/일반회원 계정 생성.
     *
     * @param array<string, mixed> $data email/password/role/name/contact/company/affiliation
     *
     * @return array<string, mixed>
     */
    public function createAccount(string $token, array $data): array
    {
        $body = $this->request($token, 'post', '/api/v1/operators', ['json' => $data]);

        /** @var array<string, mixed> $result */
        $result = is_array($body['data'] ?? null) ? $body['data'] : [];

        return $result;
    }

    /**
     * Refresh 토큰으로 새 액세스·리프레시 토큰을 발급받는다(공개 엔드포인트, 인증 헤더 불필요).
     *
     * @return array{access_token:string, refresh_token:?string}
     *
     * @throws AitesseraException 비2xx 응답·통신 실패
     */
    public function refresh(string $refreshToken): array
    {
        if (! $this->isConfigured()) {
            throw new AitesseraException('AITessera 가 설정되지 않았습니다.', 'AITESSERA_NOT_CONFIGURED', 503);
        }

        $response = service('curlrequest')->request('POST', rtrim($this->baseUrl, '/') . '/api/v1/tokens/refresh', [
            'json'        => ['refresh_token' => $refreshToken],
            'timeout'     => 5,
            'http_errors' => false,
        ]);

        $status = $response->getStatusCode();
        $body   = json_decode((string) $response->getBody(), true);
        $body   = is_array($body) ? $body : [];
        if ($status < 200 || $status >= 300) {
            throw new AitesseraException(
                (string) ($body['message'] ?? '토큰 갱신에 실패했습니다.'),
                (string) ($body['code'] ?? 'TOKEN_REFRESH_FAILED'),
                $status,
            );
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;

        return [
            'access_token'  => (string) ($data['access_token'] ?? ''),
            'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     *
     * @throws AitesseraException 비2xx 응답·통신 실패
     */
    private function request(string $token, string $method, string $path, array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw new AitesseraException('AITessera 가 설정되지 않았습니다.', 'AITESSERA_NOT_CONFIGURED', 503);
        }

        // HTTP 메서드는 반드시 대문자로 전송한다. 소문자면 엄격한 서버(PHP 내장 서버 등)가
        // "Malformed HTTP request" 로 거부해 빈 응답(curl 52)이 된다.
        $response = service('curlrequest')->request(strtoupper($method), rtrim($this->baseUrl, '/') . $path, array_merge([
            'headers'     => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
            'timeout'     => 5,
            'http_errors' => false,
        ], $options));

        $status = $response->getStatusCode();
        $body   = json_decode((string) $response->getBody(), true);
        $body   = is_array($body) ? $body : [];

        if ($status < 200 || $status >= 300) {
            throw new AitesseraException(
                (string) ($body['message'] ?? 'AITessera 호출에 실패했습니다.'),
                (string) ($body['code'] ?? 'AITESSERA_ERROR'),
                $status,
            );
        }

        return $body;
    }

    /**
     * 응답 본문에서 Access Token 문자열을 찾는다(래핑 형태 방어).
     *
     * @param array<string, mixed> $body
     */
    private function extractToken(array $body): ?string
    {
        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
        foreach (['access_token', 'accessToken', 'token'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return null;
    }
}
