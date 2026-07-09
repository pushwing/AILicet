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
     * 지정한 상태코드·본문을 반환하는 가짜 curlrequest 를 주입한다.
     */
    protected function fakeCurl(int $status, string $body): void
    {
        $response = (new Response(Factories::config(App::class)))
            ->setStatusCode($status)
            ->setBody($body);

        $fake = new class ($response) {
            public function __construct(private readonly Response $response)
            {
            }

            /**
             * @param array<string, mixed> $options
             */
            public function request(string $method, string $url, array $options = []): Response
            {
                return $this->response;
            }
        };

        Services::injectMock('curlrequest', $fake);
    }
}
