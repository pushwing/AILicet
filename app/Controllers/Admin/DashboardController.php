<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseAdminController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 대시보드 — 라이선스 발급·사용 현황 요약.
 *
 * 집계는 DashboardService(집계 쿼리·캐시)에 위임하고, 컨트롤러는 렌더만 담당한다.
 */
final class DashboardController extends BaseAdminController
{
    /** 자연어 질의 Rate Limit — 사용자별 분당 허용 횟수. */
    private const int QUERY_RATE_LIMIT = 20;

    public function index(): string
    {
        $summary = service('dashboardService')->summary();

        return $this->render('admin/dashboard', [
            'title'      => '대시보드',
            'activeMenu' => 'dashboard',
            'stats'      => $summary['stats'],
            'chart'      => $summary['chart'],
            'rows'       => $summary['rows'],
        ]);
    }

    /**
     * 자연어 질의 — 화이트리스트 집계 + AI 인사이트(JSON).
     *
     * 읽기 전용(상태 변경 없음)이라 POST 본문으로 질문을 받되, AI 호출 비용·부하 방어를 위해
     * 사용자별 Rate Limit 을 적용한다. AI 미설정 시 서비스가 no-op 결과를 돌려준다.
     */
    public function query(): ResponseInterface
    {
        $throttler = service('throttler');
        $bucket    = 'dash-query-' . ($this->authUserId() ?: $this->request->getIPAddress());
        if ($throttler->check(md5($bucket), self::QUERY_RATE_LIMIT, MINUTE) === false) {
            return $this->response->setStatusCode(429)->setJSON([
                'ok'      => false,
                'error'   => 'RATE_LIMITED',
                'insight' => '요청이 많습니다. 잠시 후 다시 시도해 주세요.',
            ]);
        }

        $body     = $this->request->getJSON(true);
        $question = is_array($body) && isset($body['question'])
            ? (string) $body['question']
            : (string) ($this->request->getPost('question') ?? '');

        $result = service('dashboardService')->queryInsight($question);

        $status = match ($result['error']) {
            null                 => 200,
            'AI_NOT_CONFIGURED'  => 503,
            'AI_ERROR'           => 502,
            default              => 200, // EMPTY_QUESTION·UNRECOGNIZED 는 정상 응답으로 안내
        };

        return $this->response->setStatusCode($status)->setJSON($result);
    }
}
