<?php
/**
 * 인증 셸 마스터 레이아웃 (Admin/대행사/고객 공용).
 *
 * @var array{id:int, name?:string, role:int, aff?:string}|null $authUser
 * @var string $title
 * @var string $activeMenu
 */
use App\Enums\UserRole;

$authUser   = $authUser ?? null;
$roleValue  = is_array($authUser) ? (int) ($authUser['role'] ?? 0) : 0;
$role       = UserRole::tryFrom($roleValue);
$roleLabel  = $role?->label() ?? '게스트';
$userName   = is_array($authUser) ? (string) ($authUser['name'] ?? '사용자') : '게스트';
$activeMenu = $activeMenu ?? '';
$title      = $title ?? 'AILicet';

// 권한별 메뉴 정의
$menus = match ($role) {
    UserRole::Operator => [
        ['key' => 'dashboard', 'label' => '대시보드', 'icon' => '▦', 'url' => '/admin'],
        ['key' => 'members',   'label' => '회원관리', 'icon' => '👤', 'url' => '/admin/members'],
        ['key' => 'licenses',  'label' => '라이센스', 'icon' => '🔑', 'url' => '/admin/licenses'],
        ['key' => 'products',  'label' => '상품·모듈', 'icon' => '📦', 'url' => '/admin/products'],
        ['key' => 'audit',     'label' => '감사로그', 'icon' => '🛡', 'url' => '/admin/audit-logs'],
    ],
    UserRole::Agency => [
        ['key' => 'dashboard', 'label' => '대시보드', 'icon' => '▦', 'url' => '/admin'],
        ['key' => 'customers', 'label' => '고객관리', 'icon' => '👤', 'url' => '/admin/customers'],
        ['key' => 'licenses',  'label' => '라이센스', 'icon' => '🔑', 'url' => '/admin/licenses'],
    ],
    UserRole::Member => [
        ['key' => 'dashboard', 'label' => '대시보드',  'icon' => '▦', 'url' => '/admin'],
        ['key' => 'licenses',  'label' => '내 라이센스', 'icon' => '🔑', 'url' => '/admin/licenses'],
        ['key' => 'support',   'label' => '고객센터',  'icon' => '💬', 'url' => '/admin/support'],
    ],
    default => [
        ['key' => 'dashboard', 'label' => '대시보드', 'icon' => '▦', 'url' => '/admin'],
    ],
};
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> · AILicet</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/aicura.css') ?>">
    <?= $this->renderSection('head') ?>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar__brand">
            <span class="sidebar__brand-mark">A</span> AILicet
        </div>
        <div class="sidebar__role"><?= esc($roleLabel) ?> 콘솔</div>
        <nav class="sidebar__nav">
            <?php foreach ($menus as $menu): ?>
                <a class="nav-item <?= $activeMenu === $menu['key'] ? 'is-active' : '' ?>"
                   href="<?= esc($menu['url']) ?>">
                    <span class="nav-item__icon"><?= $menu['icon'] ?></span>
                    <span><?= esc($menu['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="topbar__title"><?= esc($title) ?></div>
            <div class="topbar__user">
                <div class="topbar__meta">
                    <div class="topbar__name"><?= esc($userName) ?></div>
                    <div class="topbar__role"><?= esc($roleLabel) ?></div>
                </div>
                <div class="topbar__avatar"><?= esc(mb_substr($userName, 0, 1)) ?></div>
                <a class="btn btn--ghost" href="/admin/logout">로그아웃</a>
            </div>
        </header>

        <main class="content">
            <?= $this->renderSection('content') ?>
        </main>
    </div>
</div>
<?= $this->renderSection('scripts') ?>
</body>
</html>
