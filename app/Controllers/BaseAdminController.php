<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * 서버렌더링(Admin/대행사/고객) 컨트롤러 기반 클래스.
 *
 * render() 는 세션의 authUser 등 공통 데이터를 뷰 데이터에 자동 병합한다.
 * CI4 기본 view() 를 직접 호출하면 공통 데이터가 누락되므로 반드시 render() 를 사용한다.
 *
 * @property array<string, mixed> $viewData
 */
abstract class BaseAdminController extends Controller
{
    /** @var array<string, mixed> 모든 뷰에 병합되는 공통 데이터. */
    protected array $viewData = [];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        $authUser = session()->get('authUser');
        $this->viewData['authUser'] = is_array($authUser) ? $authUser : null;
    }

    /**
     * 공통 데이터를 병합해 뷰를 렌더링한다.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): string
    {
        return view($view, array_merge($this->viewData, $data));
    }
}
