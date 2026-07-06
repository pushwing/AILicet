<?php

declare(strict_types=1);

namespace App\Controllers\Agency;

use App\Controllers\BaseAdminController;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * 대행사 컨트롤러 기반 클래스.
 *
 * 로그인한 대행사의 회원 id(agencyId)를 해석해 이후 모든 동작의 소유권 스코프 기준으로 삼는다.
 * 매핑된 대행사 레코드가 없으면 agencyId 는 0 이며, 각 컨트롤러가 안내 화면으로 처리한다.
 */
abstract class BaseAgencyController extends BaseAdminController
{
    protected int $agencyId = 0;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        $userId         = (int) (session()->get('authUser')['id'] ?? 0);
        $this->agencyId = (int) (service('agencyService')->resolveAgencyId($userId) ?? 0);
    }

    /** 대행사 계정 매핑이 없을 때 안내 화면. */
    protected function noAgencyView(): string
    {
        return $this->render('agency/no_agency', [
            'title'      => '대행사 정보 없음',
            'activeMenu' => '',
        ]);
    }
}
