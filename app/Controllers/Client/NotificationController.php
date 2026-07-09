<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Enums\UserRole;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * 회원 수신함 — 본인(user_id) 스코프 인앱 메시지 조회·읽음 처리.
 */
final class NotificationController extends BaseClientController
{
    private const string BASE_URL = '/client/notifications';

    /** GET /client/notifications */
    public function index(): string
    {
        return $this->render('notifications/index', [
            'title'         => '알림',
            'activeMenu'    => 'notifications',
            'baseUrl'       => self::BASE_URL,
            'notifications' => service('notificationService')->inbox($this->role(), $this->authUserId()),
        ]);
    }

    /** POST /client/notifications/(:num)/read */
    public function read(int $id): RedirectResponse
    {
        service('notificationService')->markRead($id, $this->role(), $this->authUserId());

        return redirect()->to(self::BASE_URL);
    }

    /** POST /client/notifications/read-all */
    public function readAll(): RedirectResponse
    {
        service('notificationService')->markAllRead($this->role(), $this->authUserId());

        return redirect()->to(self::BASE_URL);
    }

    private function role(): int
    {
        return UserRole::Member->value;
    }
}
