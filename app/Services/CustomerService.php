<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\CustomerRequest;
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
