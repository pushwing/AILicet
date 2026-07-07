<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card"><div class="card__body" style="text-align:center;padding:48px;">
    <h2 class="mt-0">고객 정보가 연결되지 않았습니다</h2>
    <p class="muted">이 계정에 매핑된 고객 회원 레코드가 없습니다. 관리자에게 계정 연결(user_id)을 요청하세요.</p>
</div></div>
<?= $this->endSection() ?>
