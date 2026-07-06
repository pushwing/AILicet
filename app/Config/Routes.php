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

    // 회원관리 — 운영자 전용
    $routes->group('members', ['filter' => 'adminAuth:operator'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Admin\CustomerController::index');
        $routes->get('data', 'Admin\CustomerController::data');
        $routes->get('new', 'Admin\CustomerController::new');
        $routes->post('/', 'Admin\CustomerController::create');
        $routes->get('(:num)/edit', 'Admin\CustomerController::edit/$1');
        $routes->post('(:num)', 'Admin\CustomerController::update/$1');
        $routes->post('(:num)/delete', 'Admin\CustomerController::delete/$1');
    });

    // 상품·모듈 관리 — 운영자 전용
    $routes->group('products', ['filter' => 'adminAuth:operator'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Admin\ProductController::index');
        $routes->get('new', 'Admin\ProductController::new');
        $routes->post('/', 'Admin\ProductController::create');
        $routes->get('(:num)/edit', 'Admin\ProductController::edit/$1');
        $routes->post('(:num)', 'Admin\ProductController::update/$1');
        $routes->post('(:num)/delete', 'Admin\ProductController::delete/$1');
    });
});
