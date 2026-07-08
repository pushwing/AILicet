<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
// 루트 접속 시 로그인 페이지로(로그인돼 있으면 로그인 화면이 다시 /admin 으로 보냄)
$routes->get('/', static fn () => redirect()->to('/admin/login'));

// ── 고객 자가가입 (공개) ──
$routes->get('signup', 'Client\SignupController::showSignup');
$routes->post('signup', 'Client\SignupController::signup');
$routes->get('verify', 'Client\SignupController::verify');

// ── 고객 영역 (소유권 스코프) ──
$routes->group('client', ['filter' => 'adminAuth:member'], static function (RouteCollection $routes): void {
    $routes->get('licenses', 'Client\LicenseController::index');
    $routes->get('licenses/data', 'Client\LicenseController::data');
    $routes->get('licenses/(:num)', 'Client\LicenseController::show/$1');
    $routes->get('profile', 'Client\ProfileController::show');
    $routes->post('profile', 'Client\ProfileController::update');
    $routes->get('support', 'Client\SupportController::index');
    $routes->post('support', 'Client\SupportController::create');
});

// ── 대행사 영역 (소유권 스코프) ──
$routes->group('agency', ['filter' => 'adminAuth:agency'], static function (RouteCollection $routes): void {
    // 고객 관리
    $routes->group('customers', static function (RouteCollection $routes): void {
        $routes->get('/', 'Agency\CustomerController::index');
        $routes->get('data', 'Agency\CustomerController::data');
        $routes->get('new', 'Agency\CustomerController::new');
        $routes->post('/', 'Agency\CustomerController::create');
        $routes->get('(:num)/edit', 'Agency\CustomerController::edit/$1');
        $routes->post('(:num)', 'Agency\CustomerController::update/$1');
        $routes->post('(:num)/delete', 'Agency\CustomerController::delete/$1');
    });
    // 라이센스 발급·조회
    $routes->group('licenses', static function (RouteCollection $routes): void {
        $routes->get('/', 'Agency\LicenseController::index');
        $routes->get('data', 'Agency\LicenseController::data');
        $routes->get('new', 'Agency\LicenseController::new');
        $routes->post('/', 'Agency\LicenseController::create');
        $routes->get('product-modules/(:num)', 'Agency\LicenseController::productModules/$1');
        $routes->get('product-versions/(:num)', 'Agency\LicenseController::productVersions/$1');
        $routes->get('(:num)', 'Agency\LicenseController::show/$1');
    });
});

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

    // 운영자 관리(AITessera) — 운영자 전용
    $routes->group('accounts', ['filter' => 'adminAuth:operator'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Admin\AccountController::index');
        $routes->get('data', 'Admin\AccountController::data');
        $routes->get('new', 'Admin\AccountController::new');
        $routes->post('/', 'Admin\AccountController::create');
        $routes->get('(:num)/edit', 'Admin\AccountController::edit/$1');
        $routes->post('(:num)', 'Admin\AccountController::update/$1');
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

    // 라이센스 관리 — 운영자 전용
    $routes->group('licenses', ['filter' => 'adminAuth:operator'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Admin\LicenseController::index');
        $routes->get('data', 'Admin\LicenseController::data');
        $routes->get('new', 'Admin\LicenseController::new');
        $routes->post('/', 'Admin\LicenseController::create');
        $routes->get('product-modules/(:num)', 'Admin\LicenseController::productModules/$1');
        $routes->get('product-versions/(:num)', 'Admin\LicenseController::productVersions/$1');
        $routes->get('(:num)', 'Admin\LicenseController::show/$1');
        $routes->get('(:num)/download', 'Admin\LicenseController::download/$1');
        $routes->post('(:num)/suspend', 'Admin\LicenseController::suspend/$1');
        $routes->post('(:num)/resume', 'Admin\LicenseController::resume/$1');
        $routes->post('(:num)/terminate', 'Admin\LicenseController::terminate/$1');
        $routes->post('(:num)/extend', 'Admin\LicenseController::extend/$1');
        $routes->post('(:num)/reissue', 'Admin\LicenseController::reissue/$1');
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

    // 모듈 마스터 관리 — 운영자 전용 (목록은 상품 화면의 '모듈 관리' 탭)
    $routes->group('modules', ['filter' => 'adminAuth:operator'], static function (RouteCollection $routes): void {
        $routes->post('/', 'Admin\ModuleController::create');
        $routes->post('(:num)', 'Admin\ModuleController::update/$1');
        $routes->post('(:num)/delete', 'Admin\ModuleController::delete/$1');
    });

    // 감사로그 — 운영자 전용 (읽기 전용)
    $routes->group('audit-logs', ['filter' => 'adminAuth:operator'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Admin\AuditLogController::index');
        $routes->get('data', 'Admin\AuditLogController::data');
        $routes->get('(:num)', 'Admin\AuditLogController::show/$1');
    });
});
