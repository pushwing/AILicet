<?php

declare(strict_types=1);

namespace App\Licensing\Storage;

use RuntimeException;

/**
 * 로컬 파일시스템 라이센스 저장소 (개발·기본).
 *
 * 기본 경로: WRITEPATH/licenses (public 외부 — 직접 노출 안 됨).
 * 운영에서는 S3LicenseStorage(private ACL)로 교체한다.
 */
final class LocalLicenseStorage implements LicenseStorageInterface
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?? (WRITEPATH . 'licenses'), '/\\');
    }

    public function put(string $relativePath, string $contents): string
    {
        $full = $this->fullPath($relativePath);
        $dir  = dirname($full);

        if (! is_dir($dir) && ! mkdir($dir, 0770, true) && ! is_dir($dir)) {
            throw new RuntimeException("라이센스 저장 디렉토리를 만들 수 없습니다: {$dir}");
        }

        if (file_put_contents($full, $contents) === false) {
            throw new RuntimeException("라이센스 파일 저장에 실패했습니다: {$relativePath}");
        }

        return $relativePath;
    }

    public function get(string $relativePath): ?string
    {
        $full = $this->fullPath($relativePath);
        if (! is_file($full)) {
            return null;
        }

        $contents = file_get_contents($full);

        return $contents === false ? null : $contents;
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->fullPath($relativePath));
    }

    private function fullPath(string $relativePath): string
    {
        // 상위 경로 탈출 방지
        $safe = str_replace('..', '', $relativePath);

        return $this->basePath . '/' . ltrim($safe, '/\\');
    }
}
