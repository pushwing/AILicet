<?php

declare(strict_types=1);

namespace App\DTO;

use CodeIgniter\HTTP\IncomingRequest;

/**
 * 모듈 마스터 생성·수정 요청 DTO.
 */
final readonly class ModuleRequest
{
    public function __construct(
        public string $code,
        public string $name,
        public bool $isActive,
    ) {
    }

    public static function fromRequest(IncomingRequest $request): self
    {
        return new self(
            code: trim((string) $request->getPost('code')),
            name: trim((string) $request->getPost('name')),
            isActive: (string) $request->getPost('is_active') !== '0',
        );
    }

    /**
     * modules 테이블 저장용 배열.
     *
     * @return array{code:string, name:string, is_active:int}
     */
    public function toRow(): array
    {
        return [
            'code'      => $this->code,
            'name'      => $this->name,
            'is_active' => $this->isActive ? 1 : 0,
        ];
    }
}
