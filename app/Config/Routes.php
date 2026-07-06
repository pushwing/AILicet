<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// ── 서버렌더링(Admin/대행사/고객) ──
$routes->group('admin', static function (RouteCollection $routes): void {
    // 인증(비보호)
    $routes->get('login', 'Admin\AuthController::showLogin');
    $routes->post('login', 'Admin\AuthController::login');
    $routes->get('logout', 'Admin\AuthController::logout');

    // 보호 영역 — 세션 인증 필요
    $routes->group('', ['filter' => 'adminAuth'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Admin\DashboardController::index');
    });
});
