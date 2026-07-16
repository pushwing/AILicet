<?php
/**
 * 로그인·인증 화면용 최소 레이아웃.
 *
 * @var string $title
 */
$title = $title ?? '로그인';
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> · AILicet</title>
    <link rel="icon" type="image/svg+xml" href="<?= base_url('favicon.svg') ?>">
    <link rel="alternate icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('apple-touch-icon.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/aicura.css') ?>">
</head>
<body>
<div class="auth-wrap">
    <?= $this->renderSection('content') ?>
</div>
</body>
</html>
