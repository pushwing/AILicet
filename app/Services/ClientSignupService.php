<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CustomerType;
use App\Models\CustomerModel;
use RuntimeException;

/**
 * 고객 자가가입 + 이메일 인증.
 *
 * 로그인 인증 자체는 AITessera 가 담당하므로 여기서는 고객 프로필을 만들고 이메일 인증만 처리한다.
 * 가입 직후 is_active=0(미인증), 인증 링크(verify_token) 확인 시 활성화된다.
 */
final class ClientSignupService
{
    /**
     * 가입 처리. 성공 시 [고객 id, 인증 토큰] 반환(토큰은 인증 메일로 발송 대상).
     *
     * @param array<string, mixed> $data
     *
     * @return array{id:int, token:string}
     *
     * @throws RuntimeException 유효성 실패
     */
    public function register(array $data): array
    {
        $model = model(CustomerModel::class);
        $token = bin2hex(random_bytes(16));

        $id = (int) ($model->insert([
            'customer_type' => CustomerType::Client->value,
            'company_name'  => trim((string) ($data['company_name'] ?? '')),
            'name'          => trim((string) ($data['name'] ?? '')),
            'email'         => trim((string) ($data['email'] ?? '')),
            'phone'         => self::nullable($data['phone'] ?? null),
            'is_active'     => 0,
            'verify_token'  => $token,
        ], true) ?: 0);

        if ($id === 0) {
            throw new RuntimeException($model->errors() === [] ? '가입에 실패했습니다.' : (string) array_values($model->errors())[0]);
        }

        return ['id' => $id, 'token' => $token];
    }

    /** 이메일 인증 토큰 확인 → 계정 활성화. 성공 여부 반환. */
    public function verify(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $model = model(CustomerModel::class);
        /** @var array<string, mixed>|null $row */
        $row = $model->where('verify_token', $token)->first();
        if ($row === null) {
            return false;
        }

        $model->update((int) $row['id'], [
            'is_active'         => 1,
            'verify_token'      => null,
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
