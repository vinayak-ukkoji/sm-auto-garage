<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$tab = 'more';
$body_class = 'white-bg';

$items = [
    ['customers.php',      'bi-person-lines-fill',  'my_customers',      'customers'],
    ['vendors.php',        'bi-people-fill',        'my_vendors',        'vendors'],
    ['order_search.php',   'bi-journal-text',       'order_search',      'search'],
    ['settings.php',       'bi-gear-fill',          'settings',          'settings'],
    ['reminders.php',      'bi-alarm',              'service_reminders', 'reminders'],
    ['feedbacks.php',      'bi-star-fill',          'service_feedbacks', 'feedback'],
    ['vehicle_search.php', 'bi-car-front-fill',     'vehicle_search',    'search'],
    ['insurance_due.php',  'bi-clock-history',      'insurance_due',     'reminders'],
    ['reports.php',        'bi-bar-chart-fill',     'reports',           'reports'],
    ['invoices.php',       'bi-receipt',            'invoices',          'invoice'],
    ['users.php',          'bi-person-badge-fill',  'staff_users',       'users'],
    ['logout.php',         'bi-box-arrow-right',    'logout',            null],
];
include __DIR__ . '/includes/header.php';
?>
<div class="menu-list">
  <?php foreach ($items as [$href, $icon, $label, $perm]): if ($perm && !can($perm)) continue; ?>
    <a href="<?= url($href) ?>">
      <span class="mi"><i class="bi <?= $icon ?>"></i></span>
      <span class="ml"><?= te($label) ?></span>
      <span class="mc"><i class="bi bi-caret-right-fill"></i></span>
    </a>
  <?php endforeach; ?>
</div>
<div class="menu-list" style="margin-top:0">
  <a href="#" data-bs-toggle="modal" data-bs-target="#pwModal"><span class="mi"><i class="bi bi-key-fill"></i></span><span class="ml"><?= te('change_password') ?></span><span class="mc"><i class="bi bi-caret-right-fill"></i></span></a>
</div>
<p class="text-center text-muted small mt-4"><?= e(setting('garage_name')) ?> · <?= e(setting('tagline')) ?></p>
<div class="modal fade" id="pwModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="settings.php?action=password">
  <div class="modal-header"><h5 class="modal-title"><?= te('change_password') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label"><?= te('current_password') ?></label><input type="password" name="current" class="form-control" required autocomplete="current-password">
    <label class="form-label mt-2"><?= te('new_password') ?></label><input type="password" name="new" class="form-control" required minlength="6" autocomplete="new-password">
  </div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
