<?php

declare(strict_types=1);

use App\Enums\LogCategory;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * 로그 분류 카테고리 Enum — 폴백·라벨·허용값 목록.
 *
 * @internal
 */
final class LogCategoryTest extends CIUnitTestCase
{
    public function testFromStringMatchesKnownValue(): void
    {
        $this->assertSame(LogCategory::Security, LogCategory::fromString('security'));
        $this->assertSame(LogCategory::UserAction, LogCategory::fromString('  USER_ACTION '));
    }

    public function testFromStringFallsBackToOther(): void
    {
        $this->assertSame(LogCategory::Other, LogCategory::fromString('없는값'));
        $this->assertSame(LogCategory::Other, LogCategory::fromString(null));
        $this->assertSame(LogCategory::Other, LogCategory::fromString(''));
    }

    public function testLabelsAreKorean(): void
    {
        $this->assertSame('보안', LogCategory::Security->label());
        $this->assertSame('기타', LogCategory::Other->label());
    }

    public function testAllowedValuesCsvContainsAllCases(): void
    {
        $csv = LogCategory::allowedValuesCsv();
        foreach (LogCategory::cases() as $case) {
            $this->assertStringContainsString($case->value, $csv);
        }
    }
}
