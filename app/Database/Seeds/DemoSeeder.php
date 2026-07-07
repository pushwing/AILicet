<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Models\CustomerLicenseModel;
use App\Models\CustomerModel;
use App\Models\LicenseHistoryModel;
use App\Models\LicenseModel;
use App\Models\ProductModel;
use App\Models\ProductModuleModel;
use CodeIgniter\Database\Seeder;

/**
 * 개발용 데모 데이터 — 빠른 로그인(대행사 user_id=2 / 일반회원 user_id=3)에 맞춘 회원·라이센스.
 *
 *   php spark db:seed DemoSeeder   (재실행 안전 — 데모 레코드를 지우고 다시 만든다)
 */
final class DemoSeeder extends Seeder
{
    private const int AGENCY_USER = 2; // 대행사 데모 로그인 user_id
    private const int MEMBER_USER = 3; // 일반회원 데모 로그인 user_id

    public function run(): void
    {
        $this->clear();

        $products = model(ProductModel::class);
        $customers = model(CustomerModel::class);
        $licenses = model(LicenseModel::class);

        // 상품 + 모듈
        $productId = (int) $products->insert([
            'product_code' => 'DEMO01', 'name' => '데모 제품', 'product_family' => 'demo',
            'license_type' => 'floating', 'version' => '1.0', 'period_code' => 'perpetual_credit', 'is_active' => 1,
        ], true);
        model(ProductModuleModel::class)->insert(['product_id' => $productId, 'code' => 'MD001', 'name' => '데모 모듈']);

        // 대행사(로그인 user_id=2)
        $agencyId = (int) $customers->insert([
            'customer_type' => 'agency', 'user_id' => self::AGENCY_USER, 'company_name' => '데모 대행사',
            'name' => '김대행', 'email' => 'demo-agency@ailicet.test', 'phone' => '02-000-0000', 'is_active' => 1,
        ], true);

        // 대행사 하위 고객 A (로그인 없음)
        $customers->insert([
            'customer_type' => 'client', 'parent_id' => $agencyId, 'company_name' => '데모 고객사 A',
            'name' => '이고객', 'email' => 'demo-c1@ailicet.test', 'is_active' => 1,
        ]);

        // 일반회원(로그인 user_id=3) — 대행사 소속이자 본인 계정
        $memberId = (int) $customers->insert([
            'customer_type' => 'client', 'parent_id' => $agencyId, 'user_id' => self::MEMBER_USER,
            'company_name' => '데모 고객(내 회사)', 'name' => '박회원', 'email' => 'demo-member@ailicet.test', 'is_active' => 1,
        ], true);

        // 일반회원에게 발급된 플로팅 라이센스
        $licenseId = (int) $licenses->insert([
            'product_id' => $productId, 'license_type' => 'floating', 'period_code' => 'perpetual_credit',
            'status' => 'active', 'version' => '1.0', 'activate_term' => 24, 'check_term' => 30,
            'config' => json_encode(['modules' => ['MD001'], 'limits' => ['credit' => 500]], JSON_UNESCAPED_UNICODE),
            'issued_by' => 1, 'issue_date' => date('Y-m-d'),
        ], true);
        model(LicenseHistoryModel::class)->insert([
            'license_id' => $licenseId, 'type' => 'issue', 'license_key' => 'DEMO-FLOATING-0001',
            'contents' => '데모 발급', 'created_by' => 1,
        ]);
        model(CustomerLicenseModel::class)->insert(['customer_id' => $memberId, 'license_id' => $licenseId, 'created_by' => 1]);

        echo "DemoSeeder 완료 — 대행사(user_id=2)·일반회원(user_id=3) + 라이센스 생성\n";
    }

    /** 기존 데모 레코드 제거(재실행 안전). */
    private function clear(): void
    {
        $db = db_connect();
        $db->query("DELETE cl FROM customer_license cl JOIN customers c ON c.id = cl.customer_id WHERE c.email LIKE 'demo-%@ailicet.test'");
        $db->query("DELETE lh FROM license_history lh WHERE lh.license_key = 'DEMO-FLOATING-0001'");
        $db->query("DELETE l FROM licenses l JOIN products p ON p.id = l.product_id WHERE p.product_code = 'DEMO01'");
        $db->query("DELETE FROM customers WHERE email LIKE 'demo-%@ailicet.test'");
        $db->query("DELETE pm FROM product_modules pm JOIN products p ON p.id = pm.product_id WHERE p.product_code = 'DEMO01'");
        $db->query("DELETE FROM products WHERE product_code = 'DEMO01'");
    }
}
