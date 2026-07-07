<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Enums\AuditEventType;
use App\Models\AuditLogModel;
use App\Models\LicenseModel;
use App\Models\ProductModel;
use App\Models\ProductModuleModel;
use CodeIgniter\Database\Seeder;

/**
 * 데모·QA용 대량 샘플 데이터 — 상품 5 / 모듈 20 / 감사로그 50 (이슈 #45).
 *
 * 감사로그는 상품명·연관 라이센스 화면이 정상 동작하도록 백업용 샘플 라이센스에 연결한다.
 *
 *   php spark db:seed SampleDataSeeder   (재실행 안전 — 샘플 레코드를 지우고 다시 만든다)
 */
final class SampleDataSeeder extends Seeder
{
    /** clear·삽입 식별용 샘플 상품 코드(5개). */
    private const array PRODUCT_CODES = ['PT101', 'PT102', 'PT201', 'PT301', 'PT401'];

    /** 샘플 라이센스·감사로그 키 접두어(clear 식별자). */
    private const string LICENSE_KEY_PREFIX = 'SAMPLE-';

    public function run(): void
    {
        $this->clear();

        $productIds = $this->seedProducts();          // 5개 → [code => id]
        $this->seedModules($productIds);              // 20개
        $licenses = $this->seedLicenses($productIds); // 백업용 → [['id'=>, 'key'=>, 'host'=>]]
        $this->seedAuditLogs($licenses);              // 50개

        echo "SampleDataSeeder 완료 — 상품 5 / 모듈 20 / 라이센스 " . count($licenses) . " / 감사로그 50\n";
    }

    /**
     * 상품 5개(현실적 제품군).
     *
     * @return array<string, int> product_code => id
     */
    private function seedProducts(): array
    {
        $products = model(ProductModel::class);

        $rows = [
            ['product_code' => 'PT101', 'name' => 'TESLAB Pro',    'product_family' => 'teslab', 'license_type' => 'nodelock', 'version' => '3.2', 'period_code' => 'perpetual'],
            ['product_code' => 'PT102', 'name' => 'TESLAB Cloud',  'product_family' => 'teslab', 'license_type' => 'floating', 'version' => '3.2', 'period_code' => 'perpetual_credit'],
            ['product_code' => 'PT201', 'name' => 'AQUA Analyzer', 'product_family' => 'aqua',   'license_type' => 'floating', 'version' => '2.5', 'period_code' => 'period_count'],
            ['product_code' => 'PT301', 'name' => 'TMS LAB',       'product_family' => 'tms',    'license_type' => 'nodelock', 'version' => '1.8', 'period_code' => 'perpetual_count'],
            ['product_code' => 'PT401', 'name' => 'DermaScan AI',  'product_family' => 'derma',  'license_type' => 'floating', 'version' => '1.0', 'period_code' => 'perpetual_credit'],
        ];

        $ids = [];
        foreach ($rows as $row) {
            $row['is_active'] = 1;
            $ids[$row['product_code']] = (int) $products->insert($row, true);
        }

        return $ids;
    }

    /**
     * 모듈 20개 — 상품별 4개, 제품군에 맞는 이름. (product_id, code) 유니크.
     *
     * @param array<string, int> $productIds
     */
    private function seedModules(array $productIds): void
    {
        $modules = model(ProductModuleModel::class);

        // 상품 코드 => 모듈명 4개(제품군 맥락)
        $byProduct = [
            'PT101' => ['안면윤곽 분석', '피부 진단', '리프팅 시뮬레이션', '3D 스캔'],
            'PT102' => ['클라우드 동기화', '원격 협진', 'AI 리포트', '이미지 아카이브'],
            'PT201' => ['수분 측정', '유분 분석', '색소 침착 진단', '모공 분석'],
            'PT301' => ['근육 자극 매핑', '통증 분석', '재활 트래킹', '세션 리포트'],
            'PT401' => ['병변 검출', '흑색종 스크리닝', '경과 비교', '위험도 스코어링'],
        ];

        foreach ($byProduct as $code => $names) {
            $productId = $productIds[$code];
            foreach ($names as $i => $name) {
                $modules->insert([
                    'product_id' => $productId,
                    'code'       => sprintf('MD%03d', $i + 1),
                    'name'       => $name,
                ]);
            }
        }
    }

    /**
     * 감사로그 연결용 백업 라이센스(상품당 1~2개, 상태 혼합).
     *
     * @param array<string, int> $productIds
     * @return list<array{id:int, key:string, host:string}>
     */
    private function seedLicenses(array $productIds): array
    {
        $licenses = model(LicenseModel::class);

        // [상품코드, 상태, 만료일(offset일 · null=영구), config]
        $specs = [
            ['PT101', 'active',     null, ['modules' => ['MD001', 'MD002'], 'limits' => ['count' => 100]]],
            ['PT102', 'active',     null, ['modules' => ['MD001', 'MD003'], 'limits' => ['credit' => 1000]]],
            ['PT201', 'active',      90, ['modules' => ['MD001'], 'limits' => ['count' => 50]]],
            ['PT201', 'suspended',  -10, ['modules' => ['MD002'], 'limits' => ['count' => 50]]],
            ['PT301', 'active',     null, ['modules' => ['MD001', 'MD004'], 'limits' => ['count' => 200]]],
            ['PT301', 'terminated', -30, ['modules' => ['MD003'], 'limits' => ['count' => 200]]],
            ['PT401', 'active',     null, ['modules' => ['MD001', 'MD002'], 'limits' => ['credit' => 500]]],
            ['PT401', 'suspended',  null, ['modules' => ['MD004'], 'limits' => ['credit' => 500]]],
        ];

        $result = [];
        foreach ($specs as $seq => [$code, $status, $expireOffset, $config]) {
            $productId   = $productIds[$code];
            $licenseType = in_array($code, ['PT101', 'PT301'], true) ? 'nodelock' : 'floating';
            $isNodeLock  = $licenseType === 'nodelock';
            $hostId      = hash('sha256', self::LICENSE_KEY_PREFIX . $code . '-' . $seq);
            $hasExpire   = is_int($expireOffset);
            $expireDate  = $hasExpire ? date('Y-m-d', strtotime("{$expireOffset} days")) : null;

            $id = (int) $licenses->insert([
                'product_id'   => $productId,
                'license_type' => $licenseType,
                'period_code'  => $hasExpire ? 'period_count' : 'perpetual_credit',
                'status'       => $status,
                'version'      => '1.0',
                'host_id'      => $isNodeLock ? $hostId : null,
                'expire_date'  => $expireDate,
                'activate_term' => $isNodeLock ? null : 24,
                'check_term'    => $isNodeLock ? null : 30,
                'config'       => json_encode($config, JSON_UNESCAPED_UNICODE),
                'issued_by'    => 1,
                'issue_date'   => date('Y-m-d', strtotime('-60 days')),
            ], true);

            $result[] = [
                'id'   => $id,
                'key'  => sprintf('%s%s-%04d', self::LICENSE_KEY_PREFIX, $code, $seq + 1),
                'host' => $hostId,
            ];
        }

        return $result;
    }

    /**
     * 감사로그 50개 — 이벤트 5종 순환, 대부분 샘플 라이센스 연결·일부 NULL, 최근 30일 분산.
     *
     * @param list<array{id:int, key:string, host:string}> $licenses
     */
    private function seedAuditLogs(array $licenses): void
    {
        $audit  = model(AuditLogModel::class);
        $events = AuditEventType::cases();
        $count  = count($licenses);

        for ($i = 0; $i < 50; $i++) {
            $event = $events[$i % count($events)];

            // 5개 중 1개는 라이센스 미연결(삭제·미연결 상황 재현)
            $linked  = ($i % 5 !== 0);
            $license = $linked ? $licenses[$i % $count] : null;

            $registeredHost = $license !== null ? $license['host'] : hash('sha256', "orphan-host-{$i}");
            $clientHost     = $event === AuditEventType::IllegalHost
                ? hash('sha256', "intruder-{$i}")   // 불일치 재현
                : $registeredHost;

            // 발생일시 — 최근 30일에 걸쳐 등간격 분산(내림차순), 시각은 인덱스 기반
            $minutesAgo = (int) ($i * (30 * 24 * 60 / 50)) + ($i * 7 % 60);
            $createdAt  = date('Y-m-d H:i:s', strtotime("-{$minutesAgo} minutes"));

            $audit->insert([
                'license_id'     => $license !== null ? $license['id'] : null,
                'event_type'     => $event->value,
                'host_id'        => $registeredHost,
                'client_host_id' => $clientHost,
                'license_key'    => $license !== null ? $license['key'] : sprintf('%sORPHAN-%04d', self::LICENSE_KEY_PREFIX, $i),
                'ip'             => $this->sampleIp($i),
                'detail'         => json_encode($this->eventDetail($event, $i), JSON_UNESCAPED_UNICODE),
                'created_at'     => $createdAt,
            ]);
        }
    }

    /**
     * 이벤트 유형별 detail(JSON) 맥락.
     *
     * @return array<string, mixed>
     */
    private function eventDetail(AuditEventType $event, int $i): array
    {
        return match ($event) {
            AuditEventType::IllegalHost         => ['message' => '등록 호스트와 사용 호스트 불일치', 'attempt' => $i % 3 + 1],
            AuditEventType::ExpiredUse          => ['message' => '만료된 라이센스 사용 시도', 'expired_at' => date('Y-m-d', strtotime('-' . ($i % 20 + 1) . ' days'))],
            AuditEventType::RevokedKeyUse       => ['message' => '재발급 후 폐기된 키 사용', 'revoked_key' => sprintf('OLD-%04d', $i)],
            AuditEventType::DuplicateActivation => ['message' => '플로팅 이중 활성화 시도', 'active_sessions' => $i % 4 + 2],
            AuditEventType::UsageOverLimit      => ['message' => '사용량 한도 초과', 'limit' => 100, 'used' => 100 + ($i % 50 + 1)],
        };
    }

    /** 사설/공인 IP 혼합 샘플. */
    private function sampleIp(int $i): string
    {
        return $i % 2 === 0
            ? sprintf('192.168.%d.%d', $i % 255, ($i * 3) % 255)
            : sprintf('203.0.%d.%d', $i % 255, ($i * 7) % 255);
    }

    /** 기존 샘플 레코드 제거(재실행 안전). FK 순서: 감사로그 → 라이센스 → 상품(모듈 CASCADE). */
    private function clear(): void
    {
        $db    = db_connect();
        $codes = "'" . implode("','", self::PRODUCT_CODES) . "'";
        // 접두어 상수는 LIKE 와일드카드(%,_)를 포함하지 않으므로 그대로 사용
        $prefix = self::LICENSE_KEY_PREFIX;

        // 감사로그 — 샘플 키 접두어 기준(고아 로그 포함)
        $db->query("DELETE FROM audit_logs WHERE license_key LIKE '{$prefix}%'");
        // 라이센스 — 샘플 상품 소속
        $db->query("DELETE l FROM licenses l JOIN products p ON p.id = l.product_id WHERE p.product_code IN ({$codes})");
        // 상품 — product_modules 는 FK CASCADE 로 함께 삭제
        $db->query("DELETE FROM products WHERE product_code IN ({$codes})");
    }
}
