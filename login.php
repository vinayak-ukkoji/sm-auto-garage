<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) redirect('service.php');

$error = '';
$mobile = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_die();
    $mobile = preg_replace('/\D/', '', (string) input('mobile'));
    $pass = (string) ($_POST['password'] ?? '');
    if ($mobile === '' || $pass === '') {
        $error = t('enter_mobile_password');
    } elseif (too_many_attempts($mobile)) {
        $error = t('too_many_attempts');
    } elseif (attempt_login($mobile, $pass)) {
        $to = $_SESSION['after_login'] ?? '';
        unset($_SESSION['after_login']);
        // Only allow redirects back into this app
        if (!is_string($to) || $to === '' || strpos($to, base_url() . '/') !== 0 || str_contains($to, '/api/')) $to = url('service.php');
        header('Location: ' . $to);
        exit;
    } else {
        $error = t('invalid_login');
    }
}
$garage = setting('garage_name') ?: APP_NAME;
?><!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#12926f">
<title><?= te('login') ?> · <?= e($garage) ?></title>
<link href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
<link href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body>
<div class="login-wrap">
  <form method="post" class="login-card" autocomplete="on">
    <?= csrf_field() ?>
    <?php if (setting('logo')): ?>
      <img src="<?= e(url(setting('logo'))) ?>" alt="" style="max-height:70px;display:block;margin:0 auto 10px">
    <?php else: ?>
      <div class="login-logo"><i class="bi bi-tools"></i></div>
    <?php endif; ?>
    <h1><?= e($garage) ?></h1>
    <div class="tag"><?= e(setting('tagline') ?: '') ?></div>
    <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
    <div class="mb-3">
      <label class="form-label"><?= te('mobile_number') ?></label>
      <input type="tel" name="mobile" class="form-control form-control-lg" inputmode="numeric" maxlength="13" value="<?= e($mobile) ?>" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label"><?= te('password') ?></label>
      <div class="input-group">
        <input type="password" name="password" id="pw" class="form-control form-control-lg" required>
        <button class="btn btn-outline-secondary" type="button" onclick="var p=document.getElementById('pw');p.type=p.type==='password'?'text':'password'"><i class="bi bi-eye"></i></button>
      </div>
    </div>
    <button class="btn btn-primary btn-lg w-100"><?= te('login') ?></button>
    <div class="text-center mt-3 small">
      <?php foreach (LANGS as $c => $n): ?>
        <a href="?lang=<?= $c ?>" class="mx-2 <?= lang() === $c ? 'fw-bold' : '' ?>"><?= e($n) ?></a>
      <?php endforeach; ?>
    </div>
  </form>
</div>
</body>
</html>
