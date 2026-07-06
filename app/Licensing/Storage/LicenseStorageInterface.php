<?php

declare(strict_types=1);

namespace App\Licensing\Storage;

/**
 * 라이센스 파일 저장소 추상화.
 *
 * 구현체: LocalLicenseStorage(개발·기본), S3LicenseStorage(운영, private ACL).
 * 서비스는 이 인터페이스에만 의존해 저장 위치를 교체할 수 있다.
 */
interface LicenseStorageInterface
{
    /**
     * 라이센스 파일을 저장하고 저장 경로(키)를 반환한다.
     *
     * @param string $relativePath 저장 상대 경로(예: prd/12/34/NLicense.lic)
     * @param string $contents     라이센스 파일 내용(서명 JSON)
     *
     * @return string 기록용 경로(licenses.path)
     */
    public function put(string $relativePath, string $contents): string;

    /** 저장된 파일 내용을 읽는다(없으면 null). */
    public function get(string $relativePath): ?string;

    public function exists(string $relativePath): bool;
}
