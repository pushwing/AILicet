<?php

declare(strict_types=1);

use App\Enums\AuditEventType;
use App\Enums\UserRole;
use App\Models\AuditLogModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * 감사로그 관리 UI(컨트롤러) feature 테스트.
 *
 * @internal
 */
final class AuditLogAdminTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    /**
     * @return array<string, array<string, mixed>>
     */
    private function operator(): array
    {
        return ['authUser' => ['id' => 1, 'name' => '관리자', 'role' => UserRole::Operator->value]];
    }

    private function seedLog(string $eventType = AuditEventType::IllegalHost->value, string $key = 'KEY-A', ?string $host = 'HOST-X'): int
    {
        return (int) model(AuditLogModel::class)->insert([
            'license_id'     => null,
            'event_type'     => $eventType,
            'client_host_id' => $host,
            'license_key'    => $key,
            'ip'             => '10.0.0.1',
            'detail'         => json_encode(['reason' => '테스트'], JSON_UNESCAPED_UNICODE),
        ], true);
    }

    public function testIndexRendersForOperator(): void
    {
        $result = $this->withSession($this->operator())->get('admin/audit-logs');
        $result->assertStatus(200);
        $result->assertSee('감사로그');
        $result->assertSeeElement('#auditGrid');
    }

    public function testNonOperatorForbidden(): void
    {
        $result = $this->withSession(['authUser' => ['id' => 9, 'role' => UserRole::Member->value]])->get('admin/audit-logs');
        $result->assertStatus(403);
    }

    public function testDataEndpointReturnsRows(): void
    {
        $this->seedLog();
        $result = $this->withSession($this->operator())->get('admin/audit-logs/data');

        $json = json_decode($result->getJSON() ?? '', true);
        $this->assertSame('success', $json['status']);
        $this->assertSame(1, $json['meta']['total']);
        $this->assertSame('KEY-A', $json['data'][0]['license_key']);
    }

    public function testDataEndpointFiltersByEventType(): void
    {
        $this->seedLog(AuditEventType::IllegalHost->value, 'KEY-A');
        $this->seedLog(AuditEventType::ExpiredUse->value, 'KEY-B');

        $result = $this->withSession($this->operator())
            ->get('admin/audit-logs/data?event_type=' . AuditEventType::ExpiredUse->value);

        $json = json_decode($result->getJSON() ?? '', true);
        $this->assertSame(1, $json['meta']['total']);
        $this->assertSame('KEY-B', $json['data'][0]['license_key']);
    }

    public function testDataEndpointSearch(): void
    {
        $this->seedLog(AuditEventType::IllegalHost->value, 'ALPHA-KEY');
        $this->seedLog(AuditEventType::IllegalHost->value, 'BETA-KEY');

        $result = $this->withSession($this->operator())->get('admin/audit-logs/data?search=ALPHA');

        $json = json_decode($result->getJSON() ?? '', true);
        $this->assertSame(1, $json['meta']['total']);
        $this->assertSame('ALPHA-KEY', $json['data'][0]['license_key']);
    }

    public function testShowRenders(): void
    {
        $id     = $this->seedLog();
        $result = $this->withSession($this->operator())->get("admin/audit-logs/{$id}");

        $result->assertStatus(200);
        $result->assertSee('이벤트 정보');
        $result->assertSee('호스트 불일치');
    }

    public function testShowNotFoundRedirects(): void
    {
        $result = $this->withSession($this->operator())->get('admin/audit-logs/9999');
        $result->assertRedirectTo('/admin/audit-logs');
    }
}
