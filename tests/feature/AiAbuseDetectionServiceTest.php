<?php

declare(strict_types=1);

use App\Enums\AuditEventType;
use App\Integrations\AiClient;
use App\Integrations\AiModelTier;
use App\Integrations\NullAiClient;
use App\Models\AuditLogModel;
use App\Models\LicenseHistoryModel;
use App\Models\LicenseModel;
use App\Models\ProductModel;
use App\Services\AiAbuseDetectionService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * AI 부정사용 이상 탐지 — 집계·판단·audit_logs 기록 / 미설정 no-op / 중복 방지.
 *
 * @internal
 */
final class AiAbuseDetectionServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    /** 지정 응답을 돌려주는 가짜 AiClient. */
    private function fakeAi(string $response, bool $configured = true): AiClient
    {
        return new class ($response, $configured) implements AiClient {
            public function __construct(
                private readonly string $response,
                private readonly bool $configured,
            ) {
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function complete(AiModelTier $tier, string $system, string $prompt, int $maxTokens = 1024): string
            {
                return $this->response;
            }
        };
    }

    /**
     * 상품·라이선스를 만들고 발급 이력을 넣어 license_key → license_id 역추적이 되게 한다.
     * FK(license_history→licenses) 때문에 실제 licenses 행이 필요하다.
     *
     * @return int 생성된 license_id
     */
    private function seedIssuedKey(string $key): int
    {
        $pid = (int) model(ProductModel::class)->insert([
            'product_code' => 'PT-' . substr(md5($key), 0, 6), 'name' => '상품', 'license_type' => 'nodelock', 'is_active' => 1,
        ], true);
        $licenseId = (int) model(LicenseModel::class)->insert([
            'product_id' => $pid, 'license_type' => 'nodelock', 'period_code' => 'perpetual', 'status' => 'active',
        ], true);
        model(LicenseHistoryModel::class)->insert([
            'license_id'  => $licenseId,
            'type'        => 'issue',
            'license_key' => $key,
        ]);

        return $licenseId;
    }

    /**
     * @return list<array{license_key:string, host_id:string, ip:string, logged_at:string}>
     */
    private function usageEntries(string $key): array
    {
        return [
            ['license_key' => $key, 'host_id' => 'H1', 'ip' => '1.1.1.1', 'logged_at' => '2026-07-08 02:00:00'],
            ['license_key' => $key, 'host_id' => 'H2', 'ip' => '2.2.2.2', 'logged_at' => '2026-07-08 03:00:00'],
            ['license_key' => $key, 'host_id' => 'H3', 'ip' => '3.3.3.3', 'logged_at' => '2026-07-08 04:00:00'],
        ];
    }

    public function testRecordsAnomalyWhenAiFlagsIt(): void
    {
        $id = $this->seedIssuedKey('KEY-ABUSE');

        $service = new AiAbuseDetectionService($this->fakeAi('{"anomalous":true,"severity":"high","reason":"단일 키가 다수 호스트에서 사용됨"}'));
        $result  = $service->detect($this->usageEntries('KEY-ABUSE'));

        $this->assertCount(1, $result);
        $this->assertSame('high', $result[0]['severity']);
        $this->assertSame('KEY-ABUSE', $result[0]['license_key']);

        $this->seeInDatabase('audit_logs', [
            'event_type'  => AuditEventType::AiAnomaly->value,
            'license_key' => 'KEY-ABUSE',
            'license_id'  => $id,
        ]);
    }

    public function testDoesNotRecordWhenAiSaysNormal(): void
    {
        $this->seedIssuedKey('KEY-OK');

        $result = (new AiAbuseDetectionService($this->fakeAi('{"anomalous":false,"severity":"low","reason":"정상"}')))
            ->detect($this->usageEntries('KEY-OK'));

        $this->assertSame([], $result);
        $this->dontSeeInDatabase('audit_logs', ['event_type' => AuditEventType::AiAnomaly->value, 'license_key' => 'KEY-OK']);
    }

    public function testUnknownKeyIsSkipped(): void
    {
        // license_history 에 없는 키 → 규칙 기반 소관, AI 이상탐지는 건너뜀
        $result = (new AiAbuseDetectionService($this->fakeAi('{"anomalous":true,"severity":"high","reason":"x"}')))
            ->detect($this->usageEntries('KEY-UNKNOWN'));

        $this->assertSame([], $result);
    }

    public function testAlreadyRecordedTodayIsNotDuplicated(): void
    {
        $id = $this->seedIssuedKey('KEY-DUP');
        model(AuditLogModel::class)->insert([
            'license_id'  => $id,
            'event_type'  => AuditEventType::AiAnomaly->value,
            'license_key' => 'KEY-DUP',
            'detail'      => json_encode(['source' => 'ai'], JSON_UNESCAPED_UNICODE),
        ]);

        $result = (new AiAbuseDetectionService($this->fakeAi('{"anomalous":true,"severity":"high","reason":"x"}')))
            ->detect($this->usageEntries('KEY-DUP'));

        $this->assertSame([], $result);
        // 여전히 1건만 존재
        $this->assertSame(1, model(AuditLogModel::class)
            ->where('event_type', AuditEventType::AiAnomaly->value)
            ->where('license_key', 'KEY-DUP')
            ->countAllResults());
    }

    public function testUnconfiguredIsNoop(): void
    {
        $this->seedIssuedKey('KEY-NOOP');

        $result = (new AiAbuseDetectionService(new NullAiClient()))->detect($this->usageEntries('KEY-NOOP'));

        $this->assertSame([], $result);
        $this->dontSeeInDatabase('audit_logs', ['event_type' => AuditEventType::AiAnomaly->value, 'license_key' => 'KEY-NOOP']);
    }

    public function testMalformedVerdictIsSkipped(): void
    {
        $this->seedIssuedKey('KEY-BAD');

        $result = (new AiAbuseDetectionService($this->fakeAi('JSON 아님')))->detect($this->usageEntries('KEY-BAD'));

        $this->assertSame([], $result);
        $this->dontSeeInDatabase('audit_logs', ['event_type' => AuditEventType::AiAnomaly->value, 'license_key' => 'KEY-BAD']);
    }
}
