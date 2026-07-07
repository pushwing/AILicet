<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LogModel;
use App\Queue\LogQueue;
use Throwable;

/**
 * 로그 큐 소비자 — 큐에서 꺼내 원시 파일 보존 + 가공 DB INSERT.
 *
 * 흐름(설계 로그 파이프라인): 큐 pop → 원시파일 append(writable/logs/raw) → 가공 후 DB 저장.
 * 처리 실패 시 dead-letter(writable/logs/queue-failed)로 남긴다.
 */
final class LogQueueConsumer
{
    private string $rawPath;
    private string $deadLetterPath;

    public function __construct(
        private readonly LogQueue $queue,
        ?string $rawPath = null,
        ?string $deadLetterPath = null,
    ) {
        $this->rawPath        = $rawPath        ?? (WRITEPATH . 'logs/raw');
        $this->deadLetterPath = $deadLetterPath ?? (WRITEPATH . 'logs/queue-failed');
    }

    /**
     * 큐를 최대 $max 건 소비한다.
     *
     * @return array{processed:int, failed:int}
     */
    public function consume(int $max = 1000): array
    {
        $processed = 0;
        $failed    = 0;

        while ($processed + $failed < $max && ($entry = $this->queue->pop()) !== null) {
            try {
                $this->appendRaw($entry);
                model(LogModel::class)->insert($this->transform($entry));
                $processed++;
            } catch (Throwable $e) {
                $this->deadLetter($entry, $e->getMessage());
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /**
     * 큐 엔트리 → logs 행.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function transform(array $entry): array
    {
        $context = $entry['context'] ?? null;

        return [
            'level'     => (string) ($entry['level'] ?? 'info'),
            'source'    => isset($entry['source']) ? (string) $entry['source'] : null,
            'message'   => (string) ($entry['message'] ?? ''),
            'context'   => $context !== null ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            'client_ip' => isset($entry['client_ip']) ? (string) $entry['client_ip'] : null,
            'user_id'   => isset($entry['user_id']) && is_numeric($entry['user_id']) ? (int) $entry['user_id'] : null,
            'logged_at' => isset($entry['logged_at']) ? (string) $entry['logged_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function appendRaw(array $entry): void
    {
        $this->write($this->rawPath, $entry);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function deadLetter(array $entry, string $reason): void
    {
        $this->write($this->deadLetterPath, ['reason' => $reason, 'entry' => $entry]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function write(string $dir, array $data): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0770, true) && ! is_dir($dir)) {
            return;
        }
        $line = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }
        file_put_contents($dir . '/' . date('Y-m-d') . '.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
