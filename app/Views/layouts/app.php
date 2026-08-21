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
$unread     = (int) ($unreadNotifications ?? 0);

// 권한별 메뉴 정의
$menus = match ($role) {
    UserRole::Operator => [
        ['key' => 'dashboard',     'label' => '대시보드', 'icon' => '▦', 'url' => '/admin'],
        ['key' => 'accounts',      'label' => '운영자 관리', 'icon' => '🪪', 'url' => '/admin/accounts'],
        ['key' => 'members',       'label' => '회원관리', 'icon' => '👤', 'url' => '/admin/members'],
        ['key' => 'licenses',      'label' => '라이센스', 'icon' => '🔑', 'url' => '/admin/licenses'],
        ['key' => 'products',      'label' => '상품·모듈', 'icon' => '📦', 'url' => '/admin/products'],
        ['key' => 'audit',         'label' => '감사로그', 'icon' => '🛡', 'url' => '/admin/audit-logs'],
        ['key' => 'inquiries',     'label' => '문의관리', 'icon' => '💬', 'url' => '/admin/inquiries'],
        ['key' => 'notifications', 'label' => '알림', 'icon' => '🔔', 'url' => '/admin/notifications'],
    ],
    UserRole::Agency => [
        ['key' => 'customers',     'label' => '고객관리', 'icon' => '👤', 'url' => '/agency/customers'],
        ['key' => 'licenses',      'label' => '라이센스', 'icon' => '🔑', 'url' => '/agency/licenses'],
        ['key' => 'notifications', 'label' => '알림', 'icon' => '🔔', 'url' => '/agency/notifications'],
    ],
    UserRole::Member => [
        ['key' => 'licenses',      'label' => '내 라이센스', 'icon' => '🔑', 'url' => '/client/licenses'],
        ['key' => 'support',       'label' => '고객센터',   'icon' => '💬', 'url' => '/client/support'],
        ['key' => 'profile',       'label' => '내 정보',    'icon' => '👤', 'url' => '/client/profile'],
        ['key' => 'notifications', 'label' => '알림', 'icon' => '🔔', 'url' => '/client/notifications'],
    ],
    default => [
        ['key' => 'dashboard', 'label' => '대시보드', 'icon' => '▦', 'url' => '/admin'],
    ],
};

// 알림 메뉴 URL(토프바 벨 링크)
$notifUrl = '';
foreach ($menus as $m) {
    if ($m['key'] === 'notifications') {
        $notifUrl = $m['url'];
        break;
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= esc($title) ?> · AILicet</title>
    <link rel="icon" type="image/svg+xml" href="<?= base_url('favicon.svg') ?>">
    <link rel="alternate icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('apple-touch-icon.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/aicura.css') ?>">
    <?= $this->renderSection('head') ?>
</head>
<body>
<div class="app-shell">
    <button class="sidebar-backdrop" type="button" aria-label="메뉴 닫기" tabindex="-1"></button>
    <aside class="sidebar" id="primary-navigation">
        <div class="sidebar__brand">
            <img class="sidebar__brand-mark" src="<?= base_url('assets/img/ailicet-mark-on-dark.svg') ?>" width="28" height="28" alt=""> AILicet
        </div>
        <div class="sidebar__role"><?= esc($roleLabel) ?> 콘솔</div>
        <nav class="sidebar__nav">
            <?php foreach ($menus as $menu): ?>
                <a class="nav-item <?= $activeMenu === $menu['key'] ? 'is-active' : '' ?>"
                   href="<?= esc($menu['url']) ?>" <?= $activeMenu === $menu['key'] ? 'aria-current="page"' : '' ?>>
                    <span class="nav-item__icon"><?= $menu['icon'] ?></span>
                    <span><?= esc($menu['label']) ?></span>
                    <?php if ($menu['key'] === 'notifications' && $unread > 0): ?>
                        <span class="badge badge--danger" style="margin-left:auto;"><?= esc((string) $unread) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="topbar__context">
                <button class="nav-toggle" type="button" aria-controls="primary-navigation" aria-expanded="false">
                    <span class="nav-toggle__icon" aria-hidden="true"></span>
                    <span>메뉴</span>
                </button>
                <div class="topbar__title"><?= esc($title) ?></div>
            </div>
            <div class="topbar__user">
                <?php if ($notifUrl !== ''): ?>
                    <a class="topbar__bell" href="<?= esc($notifUrl) ?>" aria-label="<?= $unread > 0 ? '읽지 않은 알림 ' . $unread . '건' : '알림' ?>">
                        🔔
                        <?php if ($unread > 0): ?>
                            <span class="badge badge--danger topbar__notification-count" aria-hidden="true"><?= esc((string) $unread) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
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
<script>
    (() => {
        const toggle = document.querySelector('.nav-toggle');
        const backdrop = document.querySelector('.sidebar-backdrop');
        const sidebar = document.querySelector('.sidebar');
        if (!(toggle instanceof HTMLButtonElement) || !(backdrop instanceof HTMLButtonElement) || !(sidebar instanceof HTMLElement)) return;

        const closeNavigation = () => {
            document.body.classList.remove('is-navigation-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        };

        toggle.addEventListener('click', () => {
            const isOpen = document.body.classList.toggle('is-navigation-open');
            toggle.setAttribute('aria-expanded', String(isOpen));
            if (isOpen) sidebar.querySelector('a')?.focus();
        });
        backdrop.addEventListener('click', closeNavigation);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && document.body.classList.contains('is-navigation-open')) closeNavigation();
        });
    })();
</script>
</body>
</html>
