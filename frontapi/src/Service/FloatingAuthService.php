<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\FloatingRepository;
use App\Support\Jwt;

/**
 * 플로팅 라이센스 온라인 인증 유스케이스.
 *
 * 활성화(호스트 점유·이중활성화 차단·캐시토큰) → 유효성(잔여 카운트/크레딧) → 분석 차감(중복 방지).
 */
final class FloatingAuthService
{
    private const string TOKEN_SCOPE = 'floating_activation';

    public function __construct(
        private readonly FloatingRepository $repo,
        private readonly Jwt $jwt,
    ) {
    }

    /**
     * 활성화 — 호스트 등록 + 이중 활성화 차단 + 캐시 토큰 발급.
     *
     * @return array{activated:bool, reason:string, token?:string, check_term?:int, activate_term?:int}
     */
    public function activation(string $licenseKey, string $hostId): array
    {
        $license = $this->resolve($licenseKey);
        if ($license === null) {
            return ['activated' => false, 'reason' => 'INVALID_LICENSE_KEY'];
        }
        if (($reason = $this->invalidReason($license)) !== null) {
            return ['activated' => false, 'reason' => $reason];
        }

        $licenseId = (int) $license['id'];
        $now       = gmdate('Y-m-d H:i:s');

        // 이중 활성화 차단: 활성 구간 내 다른 호스트가 점유 중이면 거부
        $active = $this->repo->activeActivation($licenseId, $now);
        if ($active !== null && (string) $active['host_id'] !== $hostId) {
            return ['activated' => false, 'reason' => 'ALREADY_ACTIVE'];
        }

        $activateTerm = (int) ($license['activate_term'] ?? 24);
        $checkTerm    = (int) ($license['check_term'] ?? 30);
        $expiresAt    = gmdate('Y-m-d H:i:s', time() + $activateTerm * 3600);

        $this->repo->insertActivation($licenseId, $hostId, $now, $expiresAt);

        $token = $this->jwt->encode([
            'scope' => self::TOKEN_SCOPE,
            'lic'   => $licenseId,
            'host'  => $hostId,
        ], max(60, $checkTerm * 60));

        return ['activated' => true, 'reason' => 'OK', 'token' => $token, 'check_term' => $checkTerm, 'activate_term' => $activateTerm];
    }

    /**
     * 유효성 + 잔여 카운트/크레딧.
     *
     * @return array{valid:bool, reason:string, remaining:array<string,int>, status:?string, expire_date:?string}
     */
    public function effectiveness(string $licenseKey): array
    {
        $license = $this->resolve($licenseKey);
        if ($license === null) {
            return $this->invalid('INVALID_LICENSE_KEY');
        }
        if (($reason = $this->invalidReason($license)) !== null) {
            return $this->invalid($reason, $license);
        }

        $remaining = $this->remaining($license);
        $reason    = 'OK';
        $valid     = true;
        foreach ($remaining as $left) {
            if ($left <= 0) {
                $valid  = false;
                $reason = 'USAGE_EXCEEDED';
            }
        }

        return [
            'valid'       => $valid,
            'reason'      => $reason,
            'remaining'   => $remaining,
            'status'      => (string) $license['status'],
            'expire_date' => $license['expire_date'] !== null ? (string) $license['expire_date'] : null,
        ];
    }

    /**
     * 분석 시작 — analysis_key 발급.
     *
     * @return array{started:bool, reason:string, analysis_key?:string}
     */
    public function analysisStart(string $licenseKey, string $hostId): array
    {
        $license = $this->resolve($licenseKey);
        if ($license === null) {
            return ['started' => false, 'reason' => 'INVALID_LICENSE_KEY'];
        }
        if (($reason = $this->invalidReason($license)) !== null) {
            return ['started' => false, 'reason' => $reason];
        }
        foreach ($this->remaining($license) as $left) {
            if ($left <= 0) {
                return ['started' => false, 'reason' => 'USAGE_EXCEEDED'];
            }
        }

        $analysisKey = bin2hex(random_bytes(16));
        $this->repo->insertAnalysis((int) $license['id'], $analysisKey, $hostId, gmdate('Y-m-d H:i:s'));

        return ['started' => true, 'reason' => 'OK', 'analysis_key' => $analysisKey];
    }

    /**
     * 분석 종료 — 성공 시 사용량 차감(중복 방지: analysis_key 이미 종료면 무시).
     *
     * @return array{deducted:bool, reason:string, amount:int}
     */
    public function analysisEnd(string $analysisKey, bool $success, int $amount): array
    {
        $row = $this->repo->findAnalysis($analysisKey);
        if ($row === null) {
            return ['deducted' => false, 'reason' => 'NOT_FOUND', 'amount' => 0];
        }
        if ((string) $row['status'] !== 'started') {
            return ['deducted' => false, 'reason' => 'ALREADY_PROCESSED', 'amount' => 0]; // 멱등
        }

        $now = gmdate('Y-m-d H:i:s');
        if (! $success) {
            $this->repo->completeAnalysis((int) $row['id'], 'failed', 0, $now);

            return ['deducted' => false, 'reason' => 'FAILED_NO_DEDUCT', 'amount' => 0];
        }

        $amount = max(1, $amount); // 카운트제=1, 크레딧제=요청량
        $this->repo->completeAnalysis((int) $row['id'], 'completed', $amount, $now);

        return ['deducted' => true, 'reason' => 'OK', 'amount' => $amount];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolve(string $licenseKey): ?array
    {
        $id = $this->repo->licenseIdByKey($licenseKey);

        return $id === null ? null : $this->repo->find($id);
    }

    /**
     * @param array<string, mixed> $license
     */
    private function invalidReason(array $license): ?string
    {
        if ((string) $license['status'] !== 'active') {
            return 'INACTIVE';
        }
        $expire = $license['expire_date'] !== null ? (string) $license['expire_date'] : null;
        if ($expire !== null && $expire < gmdate('Y-m-d')) {
            return 'EXPIRED';
        }

        return null;
    }

    /**
     * 잔여 사용량(설정된 한도만).
     *
     * @param array<string, mixed> $license
     *
     * @return array<string, int>
     */
    private function remaining(array $license): array
    {
        $config = json_decode((string) ($license['config'] ?? '{}'), true);
        $limits = is_array($config) && isset($config['limits']) && is_array($config['limits']) ? $config['limits'] : [];
        $id     = (int) $license['id'];

        $remaining = [];
        if (isset($limits['count'])) {
            $remaining['count'] = max(0, (int) $limits['count'] - $this->repo->usedCount($id));
        }
        if (isset($limits['credit'])) {
            $remaining['credit'] = max(0, (int) $limits['credit'] - $this->repo->usedCredit($id));
        }

        return $remaining;
    }

    /**
     * @param array<string, mixed>|null $license
     *
     * @return array{valid:bool, reason:string, remaining:array<string,int>, status:?string, expire_date:?string}
     */
    private function invalid(string $reason, ?array $license = null): array
    {
        return [
            'valid'       => false,
            'reason'      => $reason,
            'remaining'   => [],
            'status'      => $license !== null ? (string) $license['status'] : null,
            'expire_date' => $license !== null && $license['expire_date'] !== null ? (string) $license['expire_date'] : null,
        ];
    }
}
