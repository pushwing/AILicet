<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * 고객 — 내 정보 보기/수정.
 */
final class ProfileController extends BaseClientController
{
    /** GET /client/profile */
    public function show(): string
    {
        if ($this->customerId === 0) {
            return $this->noCustomerView();
        }

        return $this->render('client/profile', [
            'title'      => '내 정보',
            'activeMenu' => 'profile',
            'customer'   => service('clientService')->profile($this->customerId),
        ]);
    }

    /** POST /client/profile */
    public function update(): RedirectResponse
    {
        if ($this->customerId === 0) {
            return redirect()->to('/client/licenses');
        }

        try {
            service('clientService')->updateProfile($this->customerId, $this->request->getPost());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/client/profile')->with('message', '정보가 수정되었습니다.');
    }
}
