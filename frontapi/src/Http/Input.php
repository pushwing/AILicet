<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * 요청 본문(JSON) 파싱 헬퍼.
 */
final class Input
{
    /**
     * @return array<string, mixed>
     */
    public static function json(ServerRequestInterface $request): array
    {
        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    public static function clientIp(ServerRequestInterface $request): string
    {
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }

        return (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
    }
}
