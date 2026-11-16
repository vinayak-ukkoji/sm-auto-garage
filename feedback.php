<?php
/** Public feedback page (no login). Link: feedback.php?t=<token> */
require __DIR__ . '/includes/bootstrap.php';

$token = preg_replace('/[^a-f0-9]/', '', (string) input('t'));
$f = strlen($token) === 32 ? row('SELECT f.*, c.name, v.reg_no, v.make, v.model FROM feedbacks f JOIN customers c ON c.id = f.customer_id
    JOIN job_cards jc ON jc.id = f.job_card_id JOIN vehicles v ON v.id = jc.vehicle_id WHERE f.token = ?', [$token]) : null;
$done = false; $err = '';
if ($f && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_die();
    $r = input_int('rating');
    if ($f['rating'] !== null) $done = true;
    elseif ($r < 1 || $r > 5) $err = t('select_rating');
    else {
        q('UPDATE feedbacks SET rating = ?, comment = ?, submitted_at = NOW() WHERE id = ? AND rating IS NULL', [$r, nullable(mb_substr(trim((string) input('comment')), 0, 1000)), $f['id']]);
        $done = true;
    }
}
$g = setting();
?><!doctype html>
<html lang="<?= e(lang()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= te('rate_our_service') ?> · <?= e($g['garage_name']) ?></title>
<link href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
<link href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
<style>.rate{display:flex;flex-direction:row-reverse;justify-content:center;gap:6px}.rate input{display:none}.rate label{font-size:40px;color:#ddd;cursor:pointer}
.rate input:checked~label,.rate label:hover,.rate label:hover~label{color:#f5b301}</style>
</head><body>
<div class="login-wrap"><div class="login-card text-center">
  <div class="login-logo"><i class="bi bi-star-fill"></i></div>
  <h1><?= e($g['garage_name']) ?></h1><div class="tag"><?= e($g['tagline']) ?></div>
  <?php if (!$f): ?>
    <p class="text-muted"><?= te('invalid_link') ?></p>
  <?php elseif ($done || $f['rating'] !== null): ?>
    <p class="fs-5 text-success"><i class="bi bi-check-circle"></i> <?= te('thanks_feedback') ?></p>
  <?php else: ?>
    <p><?= te('hello') ?> <b><?= e($f['name']) ?></b>,<br><?= te('how_was_service') ?> <b><?= e($f['reg_no']) ?></b> (<?= e($f['make'] . ' ' . $f['model']) ?>)?</p>
    <?php if ($err): ?><div class="alert alert-danger py-1 small"><?= e($err) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <div class="rate mb-3"><?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" name="rating" id="r<?= $i ?>" value="<?= $i ?>"><label for="r<?= $i ?>">★</label><?php endfor; ?></div>
      <textarea name="comment" class="form-control mb-3" rows="3" maxlength="1000" placeholder="<?= te('comment_ph') ?>"></textarea>
      <button class="btn btn-primary w-100"><?= te('submit') ?></button>
    </form>
  <?php endif; ?>
</div></div>
</body></html>
