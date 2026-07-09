<?php

/**
 * PHP CS Fixer 설정 — AILicet
 *
 * PSR-12 를 기반으로 한 안전한 코드 스타일 규칙.
 *
 * 실행:
 *   composer cs        # 검사만 (dry-run + diff)
 *   composer cs-fix    # 실제 수정
 *
 * 정렬(=>, =)은 강제하지 않는다. 이 코드베이스는 손으로 포맷돼 있어
 * 일부 블록만 정렬돼 있는데, 정렬을 기계로 강제하면 리포 전반을 헤집는다.
 * 기존 정렬은 그대로 보존하고, 안전한 위생 규칙만 적용한다.
 *
 * 분석·포맷 대상은 PHPStan 과 동일하게 app/(Views 제외)·tests/ 로 한정한다.
 */

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/tests',
    ])
    ->exclude([
        'Views',                // 뷰 템플릿(HTML 혼재) — 포맷 대상 아님
        'Database/Migrations',  // 마이그레이션 — classmap 제외 관례 유지
    ]);

$config = new PhpCsFixer\Config();

// 시스템 CLI(8.4)와 FrankenPHP(8.5) 혼용 — 8.5 실행 시 지원 경고를 억제한다.
if (method_exists($config, 'setUnsupportedPhpVersionAllowed')) {
    $config->setUnsupportedPhpVersionAllowed(true);
}

return $config
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,

        // ! 뒤 공백 유지 (기존 코드 관례)
        'not_operator_with_successor_space' => true,

        'array_syntax'                 => ['syntax' => 'short'],
        'ordered_imports'              => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
        'no_unused_imports'            => true,
        'single_quote'                 => true,
        'trailing_comma_in_multiline'  => ['elements' => ['arrays']],
        'method_argument_space'        => ['on_multiline' => 'ensure_fully_multiline'],
        'blank_line_after_opening_tag' => true,
        'no_whitespace_in_blank_line'  => true,
    ])
    ->setFinder($finder);
