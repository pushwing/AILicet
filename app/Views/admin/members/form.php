<?php
/**
 * @var array<string, mixed>|null                                           $customer
 * @var list<\App\Enums\CustomerType>                                        $types
 * @var list<array{id:int, company_name:string}>                            $agencies
 * @var list<array<string, mixed>>                                          $clients
 * @var array{id:int, email:?string, name:?string, is_active:?bool}|null    $linkedAccount
 */
$isEdit        = $customer !== null;
$action        = $isEdit ? '/admin/members/' . $customer['id'] : '/admin/members';
$val           = static fn (string $k, string $d = ''): string => esc((string) ($customer[$k] ?? old($k) ?? $d));
$clients       = $clients ?? [];
$linkedAccount = $linkedAccount ?? null;
$linkedStatus  = $linkedAccount['status'] ?? null; // found | missing | error | null(조회 안 함)
$isAgency      = $isEdit && ($customer['customer_type'] ?? '') === 'agency';
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-head__title"><?= $isEdit ? '회원 수정' : '회원 등록' ?></h1>
    <p class="page-head__desc">대행사 또는 고객 회원 정보를 입력합니다.</p>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">회원 정보</div>
        <div class="card__body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="field">
                    <label class="field__label" for="customer_type">회원 유형 *</label>
                    <select class="input" id="customer_type" name="customer_type" required onchange="toggleParent()">
                        <?php foreach ($types as $t): ?>
                            <option value="<?= esc($t->value) ?>" <?= $val('customer_type') === $t->value ? 'selected' : '' ?>>
                                <?= esc($t->label()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" id="parentField">
                    <label class="field__label" for="parent_id">소속 대행사</label>
                    <select class="input" id="parent_id" name="parent_id">
                        <option value="">— 없음 —</option>
                        <?php foreach ($agencies as $a): ?>
                            <option value="<?= esc((string) $a['id']) ?>" <?= $val('parent_id') === (string) $a['id'] ? 'selected' : '' ?>>
                                <?= esc($a['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="company_name">회사명 *</label>
                    <input class="input" id="company_name" name="company_name" required value="<?= $val('company_name') ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="name">담당자명 *</label>
                    <input class="input" id="name" name="name" required value="<?= $val('name') ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="email">이메일 *</label>
                    <input class="input" type="email" id="email" name="email" required value="<?= $val('email') ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="phone">연락처</label>
                    <input class="input" id="phone" name="phone" value="<?= $val('phone') ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="user_id">연동 사용자 ID
                        <span class="muted" style="font-weight:400;">(AITessera user_id — 대행사/고객 로그인 연결)</span>
                    </label>
                    <input class="input" type="number" id="user_id" name="user_id" value="<?= $val('user_id') ?>">
                    <?php if ($linkedStatus === 'found'): ?>
                        <p class="muted" style="margin:6px 0 0;font-size:13px;">
                            연동 계정:
                            <strong><?= esc((string) ($linkedAccount['name'] ?? '-')) ?></strong>
                            <?php if (! empty($linkedAccount['email'])): ?>
                                &lt;<?= esc((string) $linkedAccount['email']) ?>&gt;
                            <?php endif; ?>
                            <?php if (($linkedAccount['is_active'] ?? null) !== null): ?>
                                <span class="badge <?= $linkedAccount['is_active'] ? 'badge--success' : 'badge--muted' ?>">
                                    <?= $linkedAccount['is_active'] ? '활성' : '비활성' ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    <?php elseif ($linkedStatus === 'missing'): ?>
                        <p style="margin:6px 0 0;font-size:13px;color:var(--color-danger,#c0392b);">
                            AITessera에 해당 연동 계정(#<?= esc((string) $linkedAccount['id']) ?>)이 없습니다. 사용자 ID를 확인해 주세요.
                        </p>
                    <?php elseif ($linkedStatus === 'error'): ?>
                        <p class="muted" style="margin:6px 0 0;font-size:13px;">연동 계정 정보를 일시적으로 불러오지 못했습니다.</p>
                    <?php endif; ?>
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

    <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn--primary"><?= $isEdit ? '수정' : '등록' ?></button>
        <a href="/admin/members" class="btn btn--ghost">취소</a>
    </div>
</form>

<?php if ($isAgency): ?>
    <div class="card" style="margin-top:24px;">
        <div class="card__head" style="display:flex;justify-content:space-between;align-items:center;">
            <span>하위 고객 <span class="muted" style="font-weight:400;">(<?= count($clients) ?>)</span></span>
            <a href="/admin/members/new" class="btn btn--ghost" style="padding:4px 12px;">+ 고객 등록</a>
        </div>
        <div class="card__body">
            <?php if ($clients === []): ?>
                <p class="muted" style="margin:0;">이 대행사에 연결된 하위 고객이 없습니다.</p>
            <?php else: ?>
                <table class="table" style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid var(--color-border);">
                            <th style="padding:8px;">회사명</th>
                            <th style="padding:8px;">담당자</th>
                            <th style="padding:8px;">이메일</th>
                            <th style="padding:8px;">연락처</th>
                            <th style="padding:8px;">연동 ID</th>
                            <th style="padding:8px;">상태</th>
                            <th style="padding:8px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $c): ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:8px;"><?= esc((string) ($c['company_name'] ?? '')) ?></td>
                                <td style="padding:8px;"><?= esc((string) ($c['name'] ?? '')) ?></td>
                                <td style="padding:8px;"><?= esc((string) ($c['email'] ?? '')) ?></td>
                                <td style="padding:8px;"><?= esc((string) ($c['phone'] ?? '-')) ?></td>
                                <td style="padding:8px;"><?= $c['user_id'] !== null ? esc((string) $c['user_id']) : '-' ?></td>
                                <td style="padding:8px;">
                                    <?php if ((int) ($c['is_active'] ?? 0) === 1): ?>
                                        <span class="badge badge--success">활성</span>
                                    <?php else: ?>
                                        <span class="badge badge--muted">비활성</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:8px;">
                                    <a class="btn btn--ghost" style="padding:4px 10px;" href="/admin/members/<?= esc((string) $c['id']) ?>/edit">수정</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    // 대행사 유형이면 소속(parent) 선택 숨김
    function toggleParent() {
        const isClient = document.getElementById('customer_type').value === 'client';
        document.getElementById('parentField').style.display = isClient ? '' : 'none';
    }
    toggleParent();
</script>
<?= $this->endSection() ?>
