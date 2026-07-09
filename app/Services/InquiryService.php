<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InquiryStatus;
use App\Models\InquiryModel;
use RuntimeException;

/**
 * 고객 문의 운영(Admin) 유스케이스 — 목록 조회·상세·답변 확정 발송.
 *
 * AI 초안(ai_draft_reply)은 참고용이고, 실제 발송(reply 저장 + status=answered)은
 * 운영자가 초안을 검토·수정해 확정할 때만 일어난다(human-in-the-loop).
 */
final class InquiryService
{
    private const int MAX_PER_PAGE = 100;

    /**
     * 검색·필터·페이징 목록. meta 표준 반환.
     *
     * @return array{items: list<array<string, mixed>>, meta: array{page:int, per_page:int, total:int, last_page:int}}
     */
    public function paginate(
        string $search = '',
        string $status = '',
        string $category = '',
        int $page = 1,
        int $perPage = 20,
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        $model = model(InquiryModel::class);
        $model->orderBy('id', 'DESC');

        if ($status !== '') {
            $model->where('status', $status);
        }
        if ($category !== '') {
            $model->where('ai_category', $category);
        }
        if ($search !== '') {
            $model->groupStart()
                ->like('subject', $search)
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
     * 단건 상세.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        /** @var array<string, mixed>|null $row */
        $row = model(InquiryModel::class)->find($id);

        return $row;
    }

    /**
     * 운영자가 답변을 확정 발송한다 — reply 저장 + status=answered + replied_at 기록.
     *
     * 초안(ai_draft_reply)을 그대로 쓰든 수정하든, 발송되는 값은 운영자가 넘긴 $reply 뿐이다.
     *
     * @throws RuntimeException 문의 없음 또는 빈 답변
     */
    public function sendReply(int $id, string $reply): void
    {
        $reply = trim($reply);
        if ($reply === '') {
            throw new RuntimeException('답변 내용을 입력하세요.');
        }

        $model = model(InquiryModel::class);
        if ($model->find($id) === null) {
            throw new RuntimeException('문의를 찾을 수 없습니다.');
        }

        $model->update($id, [
            'reply'      => $reply,
            'status'     => InquiryStatus::Answered->value,
            'replied_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
