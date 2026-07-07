<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\BaseAdminController;
use RuntimeException;

/**
 * 고객 자가가입 + 이메일 인증 (공개).
 */
final class SignupController extends BaseAdminController
{
    /** GET /signup — 가입 폼. */
    public function showSignup(): string
    {
        return $this->render('client/auth/signup', ['title' => '회원가입']);
    }

    /** POST /signup — 가입 처리. */
    public function signup(): string
    {
        $rules = [
            'company_name' => 'required|max_length[100]',
            'name'         => 'required|max_length[50]',
            'email'        => 'required|valid_email|is_unique[customers.email]',
        ];
        if (! $this->validate($rules)) {
            return $this->render('client/auth/signup', [
                'title'  => '회원가입',
                'errors' => $this->validator->getErrors(),
                'old'    => $this->request->getPost(),
            ]);
        }

        try {
            $result = service('clientSignupService')->register($this->request->getPost());
        } catch (RuntimeException $e) {
            return $this->render('client/auth/signup', ['title' => '회원가입', 'error' => $e->getMessage()]);
        }

        // 실제 운영에서는 인증 메일 발송. 개발에서는 링크를 화면에 노출.
        $verifyUrl = site_url('verify?token=' . $result['token']);

        return $this->render('client/auth/signup_done', [
            'title'     => '가입 확인',
            'verifyUrl' => ENVIRONMENT === 'development' ? $verifyUrl : null,
        ]);
    }

    /** GET /verify — 이메일 인증. */
    public function verify(): string
    {
        $ok = service('clientSignupService')->verify((string) $this->request->getGet('token'));

        return $this->render('client/auth/verify', ['title' => '이메일 인증', 'ok' => $ok]);
    }
}
