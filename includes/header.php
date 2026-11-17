<?php
/**
 * Page header.
 * Variables a page can set before including:
 *   $title        string|null  null = brand header (garage name), otherwise back-arrow + title
 *   $back         string       URL for back arrow (default: history back)
 *   $tab          string       active bottom tab: service | parts | accounts | more
 *   $header_right string       extra HTML on the right of the header
 *   $badge        string       small grey pill under the header (e.g. filter name)
 */
$u = current_user();
$title = $title ?? null;
$tab = $tab ?? '';
$garage = setting('garage_name') ?: APP_NAME;
$logo = setting('logo');
$langUrl = function ($l) {
    $qs = $_GET; $qs['lang'] = $l;
    return strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($qs);
};
?><!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#12926f">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(base_url()) ?>">
<title><?= e(($title ? $title . ' · ' : '') . $garage) ?></title>
<?= brand_head() ?>
<link href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
<link href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="<?= e($body_class ?? '') ?>">
<div class="app">
  <header class="app-header">
  <?php if ($title === null): ?>
    <div class="hdr-left">
      <?php if ($u && !is_tech()): $dueToday = (int) val("SELECT COUNT(*) FROM reminders WHERE status IN ('pending','snoozed') AND due_date <= CURDATE() AND (snooze_until IS NULL OR snooze_until <= CURDATE())"); ?>
      <a href="<?= url('reminders.php?period=today') ?>" class="hdr-cal" title="<?= te('service_reminders') ?>">
        <i class="bi bi-calendar3"></i><span class="cnt"><?= $dueToday ?></span>
      </a>
      <?php endif; ?>
    </div>
    <div class="hdr-center">
      <img src="<?= e(logo_url()) ?>" alt="" class="hdr-logo">
      <div class="brand"><?= e($garage) ?></div>
      <?php if ($u): ?><div class="sub"><?= e($u['name']) ?></div><?php endif; ?>
    </div>
    <div class="hdr-right">
      <div class="dropdown">
        <button class="btn-lang dropdown-toggle" data-bs-toggle="dropdown" type="button"><?= e(LANGS[lang()]) ?></button>
        <ul class="dropdown-menu dropdown-menu-end">
          <?php foreach (LANGS as $code => $name): ?>
            <li><a class="dropdown-item <?= $code === lang() ? 'active' : '' ?>" href="<?= e($langUrl($code)) ?>"><?= e($name) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php else: ?>
    <?php if (isset($header_left)): ?><div class="hdr-actions"><?= $header_left ?></div>
    <?php else: ?><a class="hdr-back" href="<?= e($back ?? 'javascript:history.back()') ?>" aria-label="Back"><i class="bi bi-arrow-left"></i></a><?php endif; ?>
    <div class="hdr-title <?= !empty($title_center) ? 'center' : '' ?>"><?= $title_html ?? e($title) ?></div>
    <div class="hdr-actions"><?= $header_right ?? '' ?></div>
  <?php endif; ?>
  </header>
  <main class="app-main">
  <?php if (!empty($badge)): ?><div class="filter-pill-wrap"><span class="filter-pill"><?= e($badge) ?></span></div><?php endif; ?>
