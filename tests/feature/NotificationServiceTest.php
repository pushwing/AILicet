<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\CustomerLicenseModel;
use App\Models\CustomerModel;
use App\Models\LicenseModel;
use App\Models\NotificationModel;
use App\Models\ProductModel;
use App\Services\NotificationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * 인앱 메시지 서비스 DB 통합 테스트 — 수신자 해석·dedup·수신함 스코프.
 *
 * @internal
 */
final class NotificationServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private NotificationService $service;
    private int $productId;
    private int $agencyId;
    private int $clientId;
    private int $agencyUserId = 201;
    private int $clientUserId = 301;
    private int $licenseId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService();

        $this->productId = (int) model(ProductModel::class)->insert([
            'product_code' => 'PT001', 'name' => '테스트상품', 'license_type' => 'nodelock', 'is_active' => 1,
        ], true);

        $this->agencyId = (int) model(CustomerModel::class)->insert([
            'customer_type' => 'agency', 'user_id' => $this->agencyUserId,
            'company_name' => '대행사A', 'name' => '대행담당', 'email' => 'agency@test.com',
        ], true);

        $this->clientId = (int) model(CustomerModel::class)->insert([
            'customer_type' => 'client', 'user_id' => $this->clientUserId, 'parent_id' => $this->agencyId,
            'company_name' => '고객B', 'name' => '고객담당', 'email' => 'client@test.com',
        ], true);

        $this->licenseId = (int) model(LicenseModel::class)->insert([
            'product_id' => $this->productId, 'license_type' => 'nodelock', 'period_code' => 'period',
            'status' => 'active', 'host_id' => 'H', 'expire_date' => date('Y-m-d', strtotime('+30 days')),
        ], true);

        // 라이센스 소유 = 고객B
        model(CustomerLicenseModel::class)->insert([
            'customer_id' => $this->clientId, 'license_id' => $this->licenseId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function license(): array
    {
        return [
            'id'           => $this->licenseId,
            'product_id'   => $this->productId,
            'product_name' => '테스트상품',
            'expire_date'  => date('Y-m-d', strtotime('+30 days')),
        ];
    }

    public function testExpiringNotifiesMemberAndAgency(): void
    {
        $this->service->notifyLicenseExpiring($this->license(), 30);

        // 회원 본인(role=Member, user_id=고객)
        $this->seeInDatabase('notifications', [
            'recipient_role'    => UserRole::Member->value,
            'recipient_user_id' => $this->clientUserId,
            'license_id'        => $this->licenseId,
            'type'              => NotificationType::LicenseExpiring->value,
        ]);
        // 소속 대행사(role=Agency, user_id=대행사)
        $this->seeInDatabase('notifications', [
            'recipient_role'    => UserRole::Agency->value,
            'recipient_user_id' => $this->agencyUserId,
            'license_id'        => $this->licenseId,
            'type'              => NotificationType::LicenseExpiring->value,
        ]);
    }

    public function testExpiringIsIdempotent(): void
    {
        $this->service->notifyLicenseExpiring($this->license(), 30);
        $this->service->notifyLicenseExpiring($this->license(), 30);

        $count = model(NotificationModel::class)
            ->where('license_id', $this->licenseId)
            ->where('type', NotificationType::LicenseExpiring->value)
            ->countAllResults();

        // 회원 1 + 대행사 1 = 2건(중복 발송 없음)
        $this->assertSame(2, $count);
    }

    public function testOperatorSummaryBroadcast(): void
    {
        $this->service->notifyExpiringSummaryToOperators(30, [$this->license()]);

        $this->seeInDatabase('notifications', [
            'recipient_role'    => UserRole::Operator->value,
            'recipient_user_id' => null,
            'type'              => NotificationType::LicenseExpiring->value,
        ]);
    }

    public function testExpiredNotifiesOwnersAndOperator(): void
    {
        $this->service->notifyLicenseExpired([$this->licenseId]);

        // 운영자 요약(공용)
        $this->seeInDatabase('notifications', [
            'recipient_role'    => UserRole::Operator->value,
            'recipient_user_id' => null,
            'type'              => NotificationType::LicenseExpired->value,
        ]);
        // 회원 종료 안내
        $this->seeInDatabase('notifications', [
            'recipient_role'    => UserRole::Member->value,
            'recipient_user_id' => $this->clientUserId,
            'type'              => NotificationType::LicenseExpired->value,
        ]);
    }

    public function testInboxScopeAndUnreadCount(): void
    {
        $this->service->notifyLicenseExpiring($this->license(), 30);

        // 회원 수신함엔 본인 메시지만
        $memberInbox = $this->service->inbox(UserRole::Member->value, $this->clientUserId);
        $this->assertCount(1, $memberInbox);
        $this->assertSame(1, $this->service->unreadCount(UserRole::Member->value, $this->clientUserId));

        // 타 사용자(다른 user_id)는 조회되지 않음
        $this->assertSame(0, $this->service->unreadCount(UserRole::Member->value, 99999));

        // 읽음 처리 후 미읽음 0
        $id = (int) $memberInbox[0]['id'];
        $this->assertTrue($this->service->markRead($id, UserRole::Member->value, $this->clientUserId));
        $this->assertSame(0, $this->service->unreadCount(UserRole::Member->value, $this->clientUserId));
    }

    public function testMarkAllReadScopedToRecipient(): void
    {
        $this->service->notifyLicenseExpiring($this->license(), 30); // 회원 + 대행사 각 1건

        $this->service->markAllRead(UserRole::Member->value, $this->clientUserId);

        // 회원 것만 읽음 처리되고 대행사 것은 그대로
        $this->assertSame(0, $this->service->unreadCount(UserRole::Member->value, $this->clientUserId));
        $this->assertSame(1, $this->service->unreadCount(UserRole::Agency->value, $this->agencyUserId));
    }

    public function testMarkAllReadOperatorBroadcast(): void
    {
        $this->service->notifyExpiringSummaryToOperators(30, [$this->license()]);
        $this->assertSame(1, $this->service->unreadCount(UserRole::Operator->value, 0));

        $this->service->markAllRead(UserRole::Operator->value, 0);
        $this->assertSame(0, $this->service->unreadCount(UserRole::Operator->value, 0));
    }
}
