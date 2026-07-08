<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseAdminController;
use App\Enums\UserRole;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * 운영자 수신함 — 공용(broadcast) 인앱 메시지 조회·읽음 처리.
 */
final class NotificationController extends BaseAdminController
{
    private const string BASE_URL = '/admin/notifications';

    /** GET /admin/notifications */
    public function index(): string
    {
        return $this->render('notifications/index', [
            'title'         => '알림',
            'activeMenu'    => 'notifications',
            'baseUrl'       => self::BASE_URL,
            'notifications' => service('notificationService')->inbox($this->role(), $this->authUserId()),
        ]);
    }

    /** POST /admin/notifications/(:num)/read */
    public function read(int $id): RedirectResponse
    {
        service('notificationService')->markRead($id, $this->role(), $this->authUserId());

        return redirect()->to(self::BASE_URL);
    }

    /** POST /admin/notifications/read-all */
    public function readAll(): RedirectResponse
    {
        service('notificationService')->markAllRead($this->role(), $this->authUserId());

        return redirect()->to(self::BASE_URL);
    }

    private function role(): int
    {
        return UserRole::Operator->value;
    }
}
