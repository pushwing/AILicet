<?php

declare(strict_types=1);

use App\Enums\InquiryStatus;
use App\Models\InquiryModel;
use App\Services\InquiryService;
use Tests\Support\DatabaseTestCase;

/**
 * 문의 운영 서비스 — 답변 확정 발송(상태 전이) / 목록 페이징·필터.
 *
 * @internal
 */
final class InquiryServiceTest extends DatabaseTestCase
{
    private function insertInquiry(InquiryModel $model, array $extra = []): int
    {
        return (int) $model->insert(array_merge([
            'email'   => 'user@example.com',
            'subject' => '테스트 문의',
            'content' => '문의 내용입니다.',
            'status'  => 'open',
        ], $extra), true);
    }

    public function testSendReplyStoresReplyAndMarksAnswered(): void
    {
        $model = model(InquiryModel::class);
        $id    = $this->insertInquiry($model);

        (new InquiryService())->sendReply($id, '  안녕하세요. 처리해 드렸습니다.  ');

        $row = $model->find($id);
        $this->assertSame('안녕하세요. 처리해 드렸습니다.', $row['reply']);
        $this->assertSame(InquiryStatus::Answered->value, $row['status']);
        $this->assertNotNull($row['replied_at']);
    }

    public function testSendReplyRejectsEmptyBody(): void
    {
        $model = model(InquiryModel::class);
        $id    = $this->insertInquiry($model);

        $this->expectException(RuntimeException::class);

        try {
            (new InquiryService())->sendReply($id, '   ');
        } finally {
            // 빈 답변은 아무것도 바꾸지 않는다.
            $this->assertSame('open', $model->find($id)['status']);
        }
    }

    public function testSendReplyThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        (new InquiryService())->sendReply(999999, '답변');
    }

    public function testPaginateFiltersByStatusAndCategory(): void
    {
        $model = model(InquiryModel::class);
        $this->insertInquiry($model, ['status' => 'open', 'ai_category' => 'billing']);
        $this->insertInquiry($model, ['status' => 'answered', 'ai_category' => 'license']);
        $this->insertInquiry($model, ['status' => 'open', 'ai_category' => 'license']);

        $service = new InquiryService();

        $byStatus = $service->paginate('', 'open');
        $this->assertSame(2, $byStatus['meta']['total']);

        $byCategory = $service->paginate('', '', 'license');
        $this->assertSame(2, $byCategory['meta']['total']);

        $combined = $service->paginate('', 'open', 'license');
        $this->assertSame(1, $combined['meta']['total']);
    }

    public function testPaginateSearchMatchesSubject(): void
    {
        $model = model(InquiryModel::class);
        $this->insertInquiry($model, ['subject' => '환불 요청합니다']);
        $this->insertInquiry($model, ['subject' => '사용법 질문']);

        $result = (new InquiryService())->paginate('환불');
        $this->assertSame(1, $result['meta']['total']);
    }
}
