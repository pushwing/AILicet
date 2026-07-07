<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-head"><h1 class="page-head__title">회원 계정</h1></div>
<div class="card"><div class="card__body" style="text-align:center;padding:48px;">
    <h2 class="mt-0">AITessera 연동이 필요합니다</h2>
    <p class="muted">회원 계정 관리는 AITessera 로그인 세션(운영자 토큰)이 필요합니다.<br>
        AITessera 인증으로 로그인하면 이용할 수 있습니다.</p>
</div></div>
<?= $this->endSection() ?>
