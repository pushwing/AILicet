<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\Response;
use Config\App;

/**
 * 외부 HTTP 클라이언트(curlrequest) 서비스를 지정 상태코드·본문으로 응답하는 가짜로 대체한다.
 *
 * AitesseraClient·AnthropicAiClient·GroqAiClient 등 curlrequest 기반 통합 클라이언트 단위 테스트 공용.
 */
trait FakeCurlRequestTrait
{
    /**
     * 마지막 request() 호출의 method/url/options — 요청 바디(affiliation 등) 검증용.
     *
     * @var array{method: string, url: string, options: array<string, mixed>}|null
     */
    protected ?array $lastCurlRequest = null;

    /**
     * 지정한 상태코드·본문을 반환하는 가짜 curlrequest 를 주입한다.
     */
    protected function fakeCurl(int $status, string $body): void
    {
        $response = (new Response(Factories::config(App::class)))
            ->setStatusCode($status)
            ->setBody($body);

        $capture = function (string $method, string $url, array $options): void {
            $this->lastCurlRequest = ['method' => $method, 'url' => $url, 'options' => $options];
        };

        $fake = new class ($response, $capture) {
            public function __construct(private readonly Response $response, private readonly \Closure $capture)
            {
            }

            /**
             * @param array<string, mixed> $options
             */
            public function request(string $method, string $url, array $options = []): Response
            {
                ($this->capture)($method, $url, $options);

                return $this->response;
            }
        };

        Services::injectMock('curlrequest', $fake);
    }
}
