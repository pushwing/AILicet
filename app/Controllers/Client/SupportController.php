<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * 고객센터 — 이메일 문의 등록 + 내 문의내역.
 */
final class SupportController extends BaseClientController
{
    /** GET /client/support */
    public function index(): string
    {
        if ($this->customerId === 0) {
            return $this->noCustomerView();
        }

        return $this->render('client/support/index', [
            'title'      => '고객센터',
            'activeMenu' => 'support',
            'inquiries'  => service('clientService')->myInquiries($this->customerId),
        ]);
    }

    /** POST /client/support — 문의 등록. */
    public function create(): RedirectResponse
    {
        if ($this->customerId === 0) {
            return redirect()->to('/client/licenses');
        }

        $email = (string) (service('clientService')->profile($this->customerId)['email'] ?? '');

        try {
            service('clientService')->createInquiry($this->customerId, $email, $this->request->getPost());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/client/support')->with('message', '문의가 등록되었습니다.');
    }
}
