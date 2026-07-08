<?php

declare(strict_types=1);

use App\DTO\NodeLockIssueRequest;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * 노드락 호스트ID 입력 정규화·검증 단위 테스트 (DB 불필요). 이슈 #74.
 *
 * tools/hostid 유틸리티가 산출하는 표준 형식(XXXX-XXXX-XXXX-XXXX)과 맞물린다.
 *
 * @internal
 */
final class NodeLockHostIdTest extends CIUnitTestCase
{
    public function testValidHostIdPassesUnchanged(): void
    {
        $this->assertSame('9F3A-1C7B-E204-8DD6', NodeLockIssueRequest::normalizeHostId('9F3A-1C7B-E204-8DD6'));
    }

    public function testLowercaseIsUppercased(): void
    {
        $this->assertSame('9F3A-1C7B-E204-8DD6', NodeLockIssueRequest::normalizeHostId('9f3a-1c7b-e204-8dd6'));
    }

    public function testSurroundingWhitespaceTrimmed(): void
    {
        $this->assertSame('9F3A-1C7B-E204-8DD6', NodeLockIssueRequest::normalizeHostId('  9F3A-1C7B-E204-8DD6 '));
    }

    /**
     * @dataProvider invalidHostIds
     */
    public function testInvalidFormatRejected(string $raw): void
    {
        $this->assertNull(NodeLockIssueRequest::normalizeHostId($raw));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHostIds(): array
    {
        return [
            '빈 값'          => [''],
            '하이픈 없음'    => ['9F3A1C7BE2048DD6'],
            '자릿수 부족'    => ['9F3A-1C7B-E204'],
            'hex 아님(G)'    => ['9F3A-1C7B-E204-8DZ6'],
            '그룹당 길이 초과' => ['9F3A-1C7B-E204-8DD6A'],
            '공백 포함'      => ['9F3A 1C7B E204 8DD6'],
        ];
    }
}
