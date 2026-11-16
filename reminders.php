<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('reminders');
$tab = 'more';
$type = one_of(input('type', 'all'), ['all', 'service', 'insurance', 'puc'], 'all');
$period = one_of(input('period', 'next30'), ['overdue', 'today', 'next7', 'next30', 'all', 'sent', 'done'], 'next30');

$w = []; $p = [];
if ($type !== 'all') { $w[] = 'r.type = ?'; $p[] = $type; }
$active = "r.status IN ('pending','snoozed') AND (r.snooze_until IS NULL OR r.snooze_until <= CURDATE())";
switch ($period) {
    case 'overdue': $w[] = "$active AND r.due_date < CURDATE()"; break;
    case 'today':   $w[] = "$active AND r.due_date <= CURDATE()"; break;
    case 'next7':   $w[] = "$active AND r.due_date <= CURDATE() + INTERVAL 7 DAY"; break;
    case 'next30':  $w[] = "$active AND r.due_date <= CURDATE() + INTERVAL 30 DAY"; break;
    case 'all':     $w[] = "r.status IN ('pending','snoozed')"; break;
    case 'sent':    $w[] = "r.status = 'sent'"; break;
    case 'done':    $w[] = "r.status = 'done'"; break;
}
$list = rows('SELECT r.*, v.reg_no, v.make, v.model, v.variant, v.fuel_type, v.odometer, c.name AS cname, c.mobile FROM reminders r
    JOIN vehicles v ON v.id = r.vehicle_id JOIN customers c ON c.id = r.customer_id WHERE ' . implode(' AND ', $w) . ' ORDER BY r.due_date ' . ($period === 'done' ? 'DESC' : 'ASC') . ' LIMIT 300', $p);

$title = t('service_reminders');
$back = url('more.php');
include __DIR__ . '/includes/header.php';
$link = fn($k, $v) => url('reminders.php?' . http_build_query(array_merge(['type' => $type, 'period' => $period], [$k => $v])));
?>
<div class="tabs-x bg-white mb-2">
  <?php foreach (['all', 'service', 'insurance', 'puc'] as $tp): ?><a class="<?= $tp === $type ? 'active' : '' ?>" href="<?= e($link('type', $tp)) ?>"><?= te($tp === 'all' ? 'all' : 'rem_' . $tp) ?></a><?php endforeach; ?>
</div>
<div class="seg">
  <?php foreach (['overdue', 'today', 'next7', 'next30', 'all', 'sent', 'done'] as $pp): ?><a class="<?= $pp === $period ? 'active' : '' ?>" href="<?= e($link('period', $pp)) ?>"><?= te('rp_' . $pp) ?></a><?php endforeach; ?>
</div>
<div class="small text-muted ms-1 mb-2"><?= count($list) ?> <?= te('results') ?></div>
<?php if (!$list) echo empty_state(t('no_reminders'), 'bi-alarm'); ?>
<?php foreach ($list as $r):
    $days = (int) floor((strtotime($r['due_date']) - strtotime(date('Y-m-d'))) / 86400);
    $msg = fill_template((string) setting('wa_template_reminder'), ['customer' => $r['cname'], 'reg_no' => $r['reg_no'], 'type' => t('rem_' . $r['type']) . ($r['due_km'] ? ' (' . inr($r['due_km'], 0) . ' km)' : ''), 'due_date' => fmt_date($r['due_date'])]); ?>
  <div class="list-card">
    <div class="r"><span class="badge <?= $days < 0 ? 'bg-danger' : ($days <= 7 ? 'bg-warning text-dark' : 'bg-secondary') ?>"><?= te('rem_' . $r['type']) ?></span>
      <div class="small mt-1 <?= $days < 0 ? 'text-danger' : '' ?>"><?= e(fmt_date($r['due_date'])) ?></div>
      <small class="text-muted"><?= $days < 0 ? abs($days) . ' ' . te('days_overdue') : ($days === 0 ? te('today') : $days . ' ' . te('days_left')) ?></small></div>
    <div class="t"><a href="<?= url('vehicle.php?id=' . $r['vehicle_id']) ?>" class="text-dark"><?= e($r['reg_no']) ?></a></div>
    <div class="s"><?= e(vehicle_name($r)) ?></div>
    <div class="s"><i class="bi bi-person"></i> <?= e($r['cname']) ?> · <?= e($r['mobile']) ?><?= $r['due_km'] ? ' · ' . te('due_at') . ' ' . inr($r['due_km'], 0) . ' km' : '' ?></div>
    <?php if ($r['last_sent_at']): ?><div class="s text-success"><i class="bi bi-check2-all"></i> <?= te('sent_on') ?> <?= e(fmt_dt($r['last_sent_at'])) ?></div><?php endif; ?>
    <div class="d-flex gap-2 mt-2 flex-wrap">
      <a class="btn btn-sm btn-success mark-sent" data-id="<?= $r['id'] ?>" target="_blank" href="<?= e(wa_link($r['mobile'], $msg)) ?>"><i class="bi bi-whatsapp"></i> WhatsApp</a>
      <a class="btn btn-sm btn-outline-secondary mark-sent" data-id="<?= $r['id'] ?>" href="<?= e(sms_link($r['mobile'], $msg)) ?>"><i class="bi bi-chat-dots"></i> SMS</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(tel_link($r['mobile'])) ?>"><i class="bi bi-telephone"></i></a>
      <?php if ($r['status'] !== 'done'): ?>
        <button class="btn btn-sm btn-outline-primary" data-post="reminders.php?action=mark" data-params='{"id":<?= $r['id'] ?>,"to":"snoozed","days":7}' data-reload title="Snooze 7 days"><i class="bi bi-clock"></i> 7d</button>
        <button class="btn btn-sm btn-outline-primary" data-post="reminders.php?action=mark" data-params='{"id":<?= $r['id'] ?>,"to":"done"}' data-reload><i class="bi bi-check2"></i> <?= te('done') ?></button>
      <?php endif; ?>
      <?php if ($r['type'] === 'service' && can('prices')): ?><a class="btn btn-sm btn-outline-dark" href="<?= url('jobcard_new.php?vehicle_id=' . $r['vehicle_id']) ?>"><i class="bi bi-plus"></i> <?= te('job_card') ?></a><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php ob_start(); ?>
<script>
$(document).on('click', '.mark-sent', function () { $.post(url('api/reminders.php?action=mark'), { id: $(this).data('id'), to: 'sent', csrf: APP.csrf }); });
</script>
<?php $page_script = ob_get_clean(); include __DIR__ . '/includes/footer.php'; ?>
