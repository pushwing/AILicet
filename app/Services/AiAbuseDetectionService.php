<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditEventType;
use App\Integrations\AiClient;
use App\Integrations\AiModelTier;
use App\Models\AuditLogModel;
use App\Models\LicenseHistoryModel;
use Throwable;

/**
 * AI 부정사용 이상 탐지(보강) — 규칙 기반 AbuseDetectionService 가 못 잡는 행동 패턴 이상을 탐지한다.
 *
 * 사용 로그를 라이선스별로 일일 집계(고유 호스트·IP 수, 사용 빈도, 시간대)해 추론 등급 AI 로 판단하고,
 * 이상으로 판정된 건을 audit_logs 에 ai_anomaly 로 기록한다(초안 — 실제 정지는 사람 확정).
 * ANTHROPIC_API_KEY/GROQ_API_KEY 미설정 시 안전한 no-op.
 */
final class AiAbuseDetectionService
{
    /** 한 배치에서 AI 로 판단할 최대 라이선스 수(비용·부하 보호). */
    private const int MAX_LICENSES = 200;

    public function __construct(
        private readonly ?AiClient $ai = null,
    ) {
    }

    /**
     * 사용 로그 엔트리를 라이선스별로 집계·판단해 이상 건을 audit_logs 에 기록한다.
     *
     * @param list<array{license_key?:string, host_id?:string, ip?:string, logged_at?:string}> $entries
     * @param int                                                                              $maxLicenses 이 배치에서 판단할 최대 라이선스 수
     *
     * @return list<array{license_key:string, severity:string, reason:string}> 새로 감지된 이상 초안
     */
    public function detect(array $entries, int $maxLicenses = self::MAX_LICENSES): array
    {
        $ai = $this->ai ?? service('aiClient');
        if (! $ai->isConfigured()) {
            return []; // AI 미설정 — no-op
        }

        $audit    = model(AuditLogModel::class);
        $since    = date('Y-m-d 00:00:00'); // 같은 날 재실행 시 중복 기록 방지
        $detected = [];
        $analyzed = 0;

        foreach ($this->aggregate($entries) as $key => $stats) {
            if ($analyzed >= $maxLicenses) {
                break;
            }

            $licenseId = model(LicenseHistoryModel::class)->licenseIdByKey($key);
            if ($licenseId === null) {
                continue; // 알 수 없는 키는 규칙 기반 탐지 소관
            }
            if ($audit->existsSince(AuditEventType::AiAnomaly->value, $key, $since)) {
                continue; // 오늘 이미 기록됨
            }

            $analyzed++;

            try {
                $verdict = $this->judge($ai, $stats);
            } catch (Throwable $e) {
                log_message('error', sprintf('AI 이상 탐지 실패(license_key=%s): %s', $key, $e->getMessage()));

                continue;
            }

            if ($verdict === null || $verdict['anomalous'] !== true) {
                continue;
            }

            $audit->insert([
                'license_id'  => $licenseId,
                'event_type'  => AuditEventType::AiAnomaly->value,
                'license_key' => $key,
                'detail'      => json_encode([
                    'severity' => $verdict['severity'],
                    'reason'   => $verdict['reason'],
                    'stats'    => $stats,
                    'source'   => 'ai',
                ], JSON_UNESCAPED_UNICODE) ?: null,
            ]);

            $detected[] = ['license_key' => $key, 'severity' => $verdict['severity'], 'reason' => $verdict['reason']];
        }

        return $detected;
    }

    /**
     * 사용 로그를 license_key 단위로 집계한다.
     *
     * @param list<array{license_key?:string, host_id?:string, ip?:string, logged_at?:string}> $entries
     *
     * @return array<string, array{total:int, distinct_hosts:int, distinct_ips:int, hosts:list<string>, ips:list<string>, hours:list<int>, first_seen:?string, last_seen:?string}>
     */
    private function aggregate(array $entries): array
    {
        /** @var array<string, array{total:int, hosts:array<string,true>, ips:array<string,true>, hours:array<int,true>, first_seen:?string, last_seen:?string}> $acc */
        $acc = [];

        foreach ($entries as $e) {
            $key = trim((string) ($e['license_key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $acc[$key] ??= ['total' => 0, 'hosts' => [], 'ips' => [], 'hours' => [], 'first_seen' => null, 'last_seen' => null];
            $acc[$key]['total']++;

            $host = trim((string) ($e['host_id'] ?? ''));
            if ($host !== '') {
                $acc[$key]['hosts'][$host] = true;
            }
            $ip = trim((string) ($e['ip'] ?? ''));
            if ($ip !== '') {
                $acc[$key]['ips'][$ip] = true;
            }

            $loggedAt = trim((string) ($e['logged_at'] ?? ''));
            if ($loggedAt !== '') {
                $ts = strtotime($loggedAt);
                if ($ts !== false) {
                    $acc[$key]['hours'][(int) date('G', $ts)] = true;
                }
                if ($acc[$key]['first_seen'] === null || $loggedAt < $acc[$key]['first_seen']) {
                    $acc[$key]['first_seen'] = $loggedAt;
                }
                if ($acc[$key]['last_seen'] === null || $loggedAt > $acc[$key]['last_seen']) {
                    $acc[$key]['last_seen'] = $loggedAt;
                }
            }
        }

        $out = [];
        foreach ($acc as $key => $a) {
            $hosts        = array_keys($a['hosts']);
            $ips          = array_keys($a['ips']);
            $hours        = array_keys($a['hours']);
            sort($hours);
            $out[$key] = [
                'total'          => $a['total'],
                'distinct_hosts' => count($hosts),
                'distinct_ips'   => count($ips),
                'hosts'          => array_slice($hosts, 0, 10),
                'ips'            => array_slice($ips, 0, 10),
                'hours'          => array_map('intval', $hours),
                'first_seen'     => $a['first_seen'],
                'last_seen'      => $a['last_seen'],
            ];
        }

        return $out;
    }

    /**
     * 집계 통계를 AI(추론 등급)에 넘겨 이상 여부를 판단받는다.
     *
     * @param array<string, mixed> $stats
     *
     * @return array{anomalous:bool, severity:string, reason:string}|null 파싱 불가 시 null
     */
    private function judge(AiClient $ai, array $stats): ?array
    {
        $raw = $ai->complete(AiModelTier::Reasoning, $this->systemPrompt(), json_encode($stats, JSON_UNESCAPED_UNICODE) ?: '{}');

        if (preg_match('/\{.*\}/s', $raw, $m) !== 1) {
            return null;
        }
        $decoded = json_decode($m[0], true);
        if (! is_array($decoded)) {
            return null;
        }

        $severity = strtolower(trim((string) ($decoded['severity'] ?? 'low')));
        if (! in_array($severity, ['low', 'medium', 'high'], true)) {
            $severity = 'low';
        }

        return [
            'anomalous' => ($decoded['anomalous'] ?? false) === true,
            'severity'  => $severity,
            'reason'    => mb_substr(trim((string) ($decoded['reason'] ?? '')), 0, 500),
        ];
    }

    private function systemPrompt(): string
    {
        return '너는 소프트웨어 라이선스 부정사용 분석기다. '
            . '한 라이선스 키의 하루 사용 통계(사용 횟수, 고유 호스트 수, 고유 IP 수, 사용 시간대 등)를 보고 '
            . '부정사용(키 공유·다중 기기 남용·비정상 사용 패턴)이 의심되는지 판단하라. '
            . '반드시 {"anomalous":true|false,"severity":"low|medium|high","reason":"<한국어 근거 한 문장>"} '
            . '형식의 JSON 만 출력하라. 정상 범위면 anomalous 는 false 로 한다.';
    }
}
