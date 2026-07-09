<?php

declare(strict_types=1);

namespace App\Controllers\Agency;

use App\Enums\UserRole;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * 대행사 수신함 — 본인(user_id) 스코프 인앱 메시지 조회·읽음 처리.
 */
final class NotificationController extends BaseAgencyController
{
    private const string BASE_URL = '/agency/notifications';

    /** GET /agency/notifications */
    public function index(): string
    {
        return $this->render('notifications/index', [
            'title'         => '알림',
            'activeMenu'    => 'notifications',
            'baseUrl'       => self::BASE_URL,
            'notifications' => service('notificationService')->inbox($this->role(), $this->authUserId()),
        ]);
    }

    /** POST /agency/notifications/(:num)/read */
    public function read(int $id): RedirectResponse
    {
        service('notificationService')->markRead($id, $this->role(), $this->authUserId());

        return redirect()->to(self::BASE_URL);
    }

    /** POST /agency/notifications/read-all */
    public function readAll(): RedirectResponse
    {
        service('notificationService')->markAllRead($this->role(), $this->authUserId());

        return redirect()->to(self::BASE_URL);
    }

    private function role(): int
    {
        return UserRole::Agency->value;
    }
}
