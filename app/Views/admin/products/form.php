<?php
/**
 * @var array<string, mixed>|null $product
 * @var list<array{id:int, product_id:int, code:string, name:string}> $modules
 * @var list<array{id:int, product_id:int, version:string}> $versions
 * @var list<array{id:int, code:string, name:string}> $masterModules
 * @var list<\App\Enums\LicenseType> $licenseTypes
 * @var list<\App\Enums\PeriodCode> $periodCodes
 * @var array<string, string> $authenticationMethods
 */
$isEdit = $product !== null;
$action = $isEdit ? '/admin/products/' . $product['id'] : '/admin/products';
$val    = static fn (string $k, string $default = ''): string => esc((string) ($product[$k] ?? old($k) ?? $default));

// 버전 textarea 초기값: 활성 버전 목록(줄 단위). old() 우선(검증 실패 재입력 보존).
$versionsText = old('versions');
if ($versionsText === null) {
    $versionsText = implode("\n", array_map(static fn (array $v): string => $v['version'], $versions));
}

// 상품 설명 초기값(리치 HTML). old() 우선(검증 실패 재입력 보존).
$descriptionHtml = old('description');
if ($descriptionHtml === null) {
    $descriptionHtml = (string) ($product['description'] ?? '');
}
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<style>
    /* 상품 설명 리치 에디터 */
    .rte-toolbar { display:flex; flex-wrap:wrap; gap:4px; padding:6px; border:1px solid var(--color-border); border-bottom:none; border-radius:var(--radius-sm) var(--radius-sm) 0 0; background:var(--color-surface-2, #f8f9fa); }
    .rte-toolbar button { min-width:32px; height:30px; padding:0 8px; border:1px solid transparent; border-radius:var(--radius-sm); background:transparent; cursor:pointer; font-size:13px; line-height:1; color:var(--color-text, #222); }
    .rte-toolbar button:hover { background:rgba(0,0,0,.06); }
    .rte-toolbar button.is-active { background:var(--color-primary, #0F6E56); color:#fff; }
    .rte-editor { min-height:180px; max-height:480px; overflow-y:auto; padding:12px 14px; border:1px solid var(--color-border); border-radius:0 0 var(--radius-sm) var(--radius-sm); outline:none; background:#fff; }
    .rte-editor:focus-within { border-color:var(--color-primary, #0F6E56); }
    .rte-editor p { margin:0 0 8px; }
    .rte-editor h1, .rte-editor h2, .rte-editor h3 { margin:12px 0 8px; }
    .rte-editor ul, .rte-editor ol { margin:0 0 8px; padding-left:22px; }
    .rte-editor:empty::before, .rte-editor .is-editor-empty:first-child::before { content:attr(data-placeholder); color:var(--color-text-muted, #9aa0a6); pointer-events:none; float:left; height:0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-head__title"><?= $isEdit ? '상품 수정' : '상품 등록' ?></h1>
    <p class="page-head__desc">상품 정보와 라이선스 종류·인증 방식·기간정책, 모듈을 정의합니다.</p>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">기본 정보</div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field">
                    <label class="field__label" for="product_code">상품코드 *</label>
                    <input class="input" id="product_code" name="product_code" required
                           value="<?= $val('product_code') ?>" placeholder="예: PT001">
                </div>
                <div class="field">
                    <label class="field__label" for="name">상품명 *</label>
                    <input class="input" id="name" name="name" required
                           value="<?= $val('name') ?>" placeholder="예: tES LAB">
                </div>
                <div class="field">
                    <label class="field__label" for="product_family">제품군</label>
                    <input class="input" id="product_family" name="product_family"
                           value="<?= $val('product_family') ?>" placeholder="예: teslab">
                </div>
                <div class="field">
                    <label class="field__label" for="license_type">라이선스 종류 *</label>
                    <select class="input" id="license_type" name="license_type" required>
                        <?php foreach ($licenseTypes as $lt): ?>
                            <option value="<?= esc($lt->value) ?>" <?= $val('license_type') === $lt->value ? 'selected' : '' ?>>
                                <?= esc($lt->label()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="authentication_method">인증 방식</label>
                    <input class="input" id="authentication_method" readonly aria-describedby="authentication_method_help">
                    <p id="authentication_method_help" class="muted" style="margin:6px 0 0;font-size:12px;">
                        라이선스 종류에 따라 실제 발급·검증 경로가 자동으로 적용됩니다.
                    </p>
                </div>
                <div class="field">
                    <label class="field__label" for="period_code">기간정책</label>
                    <select class="input" id="period_code" name="period_code">
                        <option value="">— 선택 —</option>
                        <?php foreach ($periodCodes as $pc): ?>
                            <option value="<?= esc($pc->value) ?>" <?= $val('period_code') === $pc->value ? 'selected' : '' ?>>
                                <?= esc($pc->label()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="is_active">상태</label>
                    <select class="input" id="is_active" name="is_active">
                        <option value="1" <?= $val('is_active', '1') === '1' ? 'selected' : '' ?>>활성</option>
                        <option value="0" <?= $val('is_active', '1') === '0' ? 'selected' : '' ?>>비활성</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">상품 설명</div>
        <div class="card__body">
            <p class="muted" style="margin-top:0;margin-bottom:12px;font-size:12px;">
                상품 소개·특징 등을 서식과 함께 입력합니다. 저장 시 허용된 태그만 남기고 정화됩니다.
            </p>
            <div class="field" style="margin-bottom:0;">
                <div class="rte-toolbar" id="descToolbar">
                    <button type="button" data-cmd="bold" title="굵게"><strong>B</strong></button>
                    <button type="button" data-cmd="italic" title="기울임"><em>I</em></button>
                    <button type="button" data-cmd="strike" title="취소선"><s>S</s></button>
                    <button type="button" data-cmd="h2" title="제목">H2</button>
                    <button type="button" data-cmd="h3" title="소제목">H3</button>
                    <button type="button" data-cmd="bulletList" title="글머리표">•&nbsp;목록</button>
                    <button type="button" data-cmd="orderedList" title="번호목록">1.&nbsp;목록</button>
                    <button type="button" data-cmd="blockquote" title="인용">&ldquo;&rdquo;</button>
                    <button type="button" data-cmd="undo" title="실행취소">↶</button>
                    <button type="button" data-cmd="redo" title="다시실행">↷</button>
                </div>
                <div class="rte-editor" id="descEditor" data-placeholder="상품 설명을 입력하세요…"></div>
                <input type="hidden" name="description" id="descriptionInput" value="<?= esc($descriptionHtml) ?>">
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">버전</div>
        <div class="card__body">
            <p class="muted" style="margin-top:0;margin-bottom:12px;font-size:12px;">
                이 상품의 버전을 한 줄에 하나씩 입력합니다. 라이센스 발급 시 여기서 선택합니다.
                <?php if ($isEdit): ?>목록에서 제거한 버전은 비활성 처리되어 발급 목록에서 숨겨집니다(이력 보존).<?php endif; ?>
            </p>
            <div class="field" style="margin-bottom:0;">
                <label class="field__label" for="versions">버전 목록</label>
                <textarea class="input" id="versions" name="versions" rows="4"
                          placeholder="예:&#10;2.0.1&#10;1.0.1"><?= esc($versionsText) ?></textarea>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">모듈</div>
        <div class="card__body">
            <?php if ($isEdit): ?>
                <?php /* 모듈 구성은 생성 시 확정 — 수정 불가(이미 판매된 상품 보호). 모듈 변경은 신규 상품으로. */ ?>
                <div class="alert alert--info" style="margin-bottom:12px;">
                    모듈 구성은 상품 생성 시 확정되며 수정할 수 없습니다. 모듈을 바꾸려면 상품을 새로 등록하세요.
                </div>
                <?php if ($modules === []): ?>
                    <p class="muted mb-0">연결된 모듈이 없습니다.</p>
                <?php else: ?>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <?php foreach ($modules as $m): ?>
                            <span class="badge badge--muted"><?= esc($m['code']) ?> · <?= esc($m['name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php elseif ($masterModules === []): ?>
                <p class="muted mb-0">
                    등록된 모듈이 없습니다.
                    <a href="/admin/products?tab=modules">모듈 관리</a> 탭에서 모듈을 먼저 등록하세요.
                </p>
            <?php else: ?>
                <p class="muted" style="margin-top:0;margin-bottom:12px;font-size:12px;">상품에 포함할 모듈을 선택합니다. 저장 후에는 변경할 수 없습니다.</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px 20px;">
                    <?php foreach ($masterModules as $m): ?>
                        <label style="display:inline-flex;gap:6px;align-items:center;">
                            <input type="checkbox" name="module_ids[]" value="<?= esc((string) $m['id']) ?>"
                                <?= in_array($m['id'], old('module_ids', []) ?: [], false) ? 'checked' : '' ?>>
                            <?= esc($m['code']) ?> · <?= esc($m['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn--primary"><?= $isEdit ? '수정' : '등록' ?></button>
        <a href="/admin/products" class="btn btn--ghost">취소</a>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script type="module">
    import { Editor } from 'https://esm.sh/@tiptap/core@2'
    import StarterKit from 'https://esm.sh/@tiptap/starter-kit@2'

    // 초기 HTML 은 서버에서 안전하게 직렬화(json_encode)해 전달한다.
    const INITIAL_HTML = <?= json_encode($descriptionHtml, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const hidden = document.getElementById('descriptionInput');

    const AUTHENTICATION_METHODS = <?= json_encode($authenticationMethods, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const licenseType = document.getElementById('license_type');
    const authenticationMethod = document.getElementById('authentication_method');

    function syncAuthenticationMethod() {
        authenticationMethod.value = AUTHENTICATION_METHODS[licenseType.value] || '-';
    }

    licenseType.addEventListener('change', syncAuthenticationMethod);
    syncAuthenticationMethod();

    const editor = new Editor({
        element: document.getElementById('descEditor'),
        extensions: [StarterKit],
        content: INITIAL_HTML,
        onUpdate: ({ editor }) => {
            // 빈 에디터는 빈 문자열로 저장(<p></p> 노이즈 방지).
            hidden.value = editor.isEmpty ? '' : editor.getHTML()
        },
    })

    // 툴바 커맨드 매핑
    const COMMANDS = {
        bold:        e => e.toggleBold(),
        italic:      e => e.toggleItalic(),
        strike:      e => e.toggleStrike(),
        h2:          e => e.toggleHeading({ level: 2 }),
        h3:          e => e.toggleHeading({ level: 3 }),
        bulletList:  e => e.toggleBulletList(),
        orderedList: e => e.toggleOrderedList(),
        blockquote:  e => e.toggleBlockquote(),
        undo:        e => e.undo(),
        redo:        e => e.redo(),
    }

    document.getElementById('descToolbar').addEventListener('click', (ev) => {
        const btn = ev.target.closest('button[data-cmd]')
        if (!btn) return
        const run = COMMANDS[btn.dataset.cmd]
        if (run) run(editor.chain().focus()).run()
    })

    // 활성 서식 버튼 하이라이트
    const ACTIVE_CHECK = {
        bold: 'bold', italic: 'italic', strike: 'strike',
        bulletList: 'bulletList', orderedList: 'orderedList', blockquote: 'blockquote',
    }
    editor.on('transaction', () => {
        document.querySelectorAll('#descToolbar button[data-cmd]').forEach(btn => {
            const name = ACTIVE_CHECK[btn.dataset.cmd]
            if (name === undefined) return
            btn.classList.toggle('is-active', editor.isActive(name))
        })
        document.querySelector('[data-cmd="h2"]')?.classList.toggle('is-active', editor.isActive('heading', { level: 2 }))
        document.querySelector('[data-cmd="h3"]')?.classList.toggle('is-active', editor.isActive('heading', { level: 3 }))
    })
</script>
<?= $this->endSection() ?>
