<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 원시 사용정보 로그 기록기 — 날짜별 파일에 JSON 라인 append.
 *
 * 설계(로그 수집 파이프라인): bypass 는 즉시 원시 로그로 적재하고, DB 가공은 큐 소비자(#18)가 담당한다.
 */
final class RawLogWriter
{
    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function append(array $entry): void
    {
        if (! is_dir($this->basePath)) {
            @mkdir($this->basePath, 0770, true);
        }

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }

        $file = $this->basePath . '/' . gmdate('Y-m-d') . '.log';
        file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
