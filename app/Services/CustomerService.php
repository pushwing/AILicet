<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\CustomerRequest;
use App\Exceptions\AitesseraException;
use App\Models\CustomerModel;
use RuntimeException;

/**
 * 회원(대행사/고객) 관리 유스케이스.
 */
final class CustomerService
{
    private const int MAX_PER_PAGE = 100;

    /**
     * 검색·페이징 목록. meta 표준(page/per_page/total/last_page) 반환.
     *
     * @return array{items: list<array<string, mixed>>, meta: array{page:int, per_page:int, total:int, last_page:int}}
     */
    public function paginate(string $search = '', string $type = '', int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        $model = model(CustomerModel::class);
        $model->orderBy('created_at', 'DESC');

        if ($type !== '') {
            $model->where('customer_type', $type);
        }
        if ($search !== '') {
            $model->groupStart()
                ->like('company_name', $search)
                ->orLike('name', $search)
                ->orLike('email', $search)
                ->groupEnd();
        }

        $total = $model->countAllResults(false);
        /** @var list<array<string, mixed>> $items */
        $items = $model->limit($perPage, ($page - 1) * $perPage)->find();

        return [
            'items' => $items,
            'meta'  => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        /** @var array<string, mixed>|null $row */
        $row = model(CustomerModel::class)->find($id);

        return $row;
    }

    /**
     * 대행사에 소속된 하위 고객 목록(대행사 상세용).
     *
     * @return list<array<string, mixed>>
     */
    public function childClients(int $agencyId): array
    {
        return model(CustomerModel::class)->clientsOf($agencyId);
    }

    /**
     * 회원 레코드의 AITessera 연동 계정 정보(이메일·이름 병기용).
     *
     * 표시 전용 베스트에포트 — 조회를 시도하지 않는 경우(토큰·user_id 없음)는 null 을 반환한다.
     * 조회를 시도한 경우는 결과를 상태로 구분한다:
     * - found  : 계정 정보 조회 성공
     * - missing: AITessera 에 해당 user_id 가 없음(404) — 연동 값이 잘못됨(조치 필요)
     * - error  : 그 외 통신·인증 오류(일시적)
     *
     * @return array{status:'found', id:int, email:?string, name:?string, is_active:?bool}
     *              |array{status:'missing'|'error', id:int}
     *              |null
     */
    public function linkedAccount(?int $userId, ?string $token): ?array
    {
        if ($userId === null || $userId <= 0 || $token === null) {
            return null;
        }

        try {
            $user = service('aitesseraClient')->getUser($token, $userId);
        } catch (AitesseraException $e) {
            // 표시 전용이라 화면은 깨지 않지만, 원인 파악을 위해 최소 로깅한다.
            log_message('warning', 'linkedAccount getUser 실패 [user_id={id}] {code}({status}): {msg}', [
                'id'     => $userId,
                'code'   => $e->errorCode(),
                'status' => $e->httpStatusCode(),
                'msg'    => $e->getMessage(),
            ]);

            return ['status' => $e->httpStatusCode() === 404 ? 'missing' : 'error', 'id' => $userId];
        }

        if ($user === []) {
            log_message('warning', 'linkedAccount getUser 응답 비어있음 [user_id={id}]', ['id' => $userId]);

            return ['status' => 'missing', 'id' => $userId];
        }

        return [
            'status'    => 'found',
            'id'        => $userId,
            'email'     => isset($user['email']) ? (string) $user['email'] : null,
            'name'      => isset($user['name']) ? (string) $user['name'] : null,
            'is_active' => isset($user['is_active']) ? (bool) $user['is_active'] : null,
        ];
    }

    /**
     * @throws RuntimeException 유효성 실패
     */
    public function create(CustomerRequest $dto): int
    {
        $model = model(CustomerModel::class);
        $id    = (int) ($model->insert($dto->toRow(), true) ?: 0);
        if ($id === 0) {
            throw new RuntimeException($this->firstError($model->errors()));
        }

        return $id;
    }

    /**
     * @throws RuntimeException 유효성 실패
     */
    public function update(int $id, CustomerRequest $dto): void
    {
        $model      = model(CustomerModel::class);
        $row        = $dto->toRow();
        $row['id']  = $id; // is_unique {id} 플레이스홀더
        if ($model->update($id, $row) === false) {
            throw new RuntimeException($this->firstError($model->errors()));
        }
    }

    public function delete(int $id): void
    {
        model(CustomerModel::class)->delete($id);
    }

    /**
     * @param array<string, string> $errors
     */
    private function firstError(array $errors): string
    {
        return $errors === [] ? '회원 저장에 실패했습니다.' : (string) array_values($errors)[0];
    }
}
