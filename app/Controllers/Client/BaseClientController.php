<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\BaseAdminController;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * 고객 컨트롤러 기반 클래스.
 *
 * 로그인 고객(user_id)에 매핑된 회원 id(customerId)를 해석해 소유권 스코프 기준으로 삼는다.
 */
abstract class BaseClientController extends BaseAdminController
{
    protected int $customerId = 0;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        $userId           = (int) (session()->get('authUser')['id'] ?? 0);
        $this->customerId = (int) (service('clientService')->resolveCustomerId($userId) ?? 0);
    }

    protected function noCustomerView(): string
    {
        return $this->render('client/no_customer', ['title' => '고객 정보 없음', 'activeMenu' => '']);
    }
}
