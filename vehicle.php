<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('service');
if (is_tech()) require_perm('search');
$tab = 'more';
$id = input_int('id');
$v = row('SELECT v.*, c.name AS cname, c.mobile AS cmobile FROM vehicles v JOIN customers c ON c.id = v.customer_id WHERE v.id = ?', [$id]);
if (!$v) redirect('vehicle_search.php');
[$orders] = query_orders('all', '', 0, 100, ['vehicle_id' => $id]);
$rem = rows("SELECT * FROM reminders WHERE vehicle_id = ? AND status <> 'done' ORDER BY due_date", [$id]);
$modalCustomerId = (int) $v['customer_id'];
$fill = $v; unset($fill['created_at'], $fill['cname'], $fill['cmobile']);

$title = $v['reg_no'];
$back = 'javascript:history.back()';
$header_right = can('customers') ? '<button class="hdr-icon" data-bs-toggle="modal" data-bs-target="#vehModal" data-fill=\'' . e(json_encode($fill)) . '\' data-title="' . te('edit_vehicle') . '"><i class="bi bi-pencil-square"></i></button>' : '';
include __DIR__ . '/includes/header.php';
$due = function ($d) { if (!$d) return '-'; $days = (int) floor((strtotime($d) - strtotime(date('Y-m-d'))) / 86400); return e(fmt_date($d)) . ' <small class="' . ($days < 0 ? 'text-danger' : ($days <= 30 ? 'text-warning' : 'text-muted')) . '">(' . ($days < 0 ? abs($days) . ' ' . t('days_overdue') : $days . ' ' . t('days_left')) . ')</small>'; };
?>
<div class="box">
  <div class="d-flex justify-content-between"><div><div class="fw-bold fs-5"><?= e($v['reg_no']) ?></div><div><?= e(vehicle_name($v)) ?><?= $v['year'] ? ' · ' . (int) $v['year'] : '' ?><?= $v['color'] ? ' · ' . e($v['color']) : '' ?></div></div>
    <div class="text-end"><div class="fs-6"><?= $v['odometer'] ? inr($v['odometer'], 0) : '-' ?></div><small class="text-muted">KM</small></div></div>
  <div class="mt-2"><i class="bi bi-person"></i> <?= can('customers') ? '<a href="' . url('customer.php?id=' . $v['customer_id']) . '">' . e($v['cname']) . '</a>' : e($v['cname']) ?> · <?= e($v['cmobile']) ?></div>
</div>
<div class="box">
  <div class="kv"><span><?= te('chassis_no') ?></span><span><?= e($v['chassis_no'] ?: '-') ?></span></div>
  <div class="kv"><span><?= te('engine_no') ?></span><span><?= e($v['engine_no'] ?: '-') ?></span></div>
  <div class="kv"><span><?= te('insurance') ?></span><span><?= e($v['insurance_company'] ?: '-') ?> <?= $v['policy_no'] ? '(' . e($v['policy_no']) . ')' : '' ?></span></div>
  <div class="kv"><span><?= te('insurance_expiry') ?></span><span><?= $due($v['insurance_expiry']) ?></span></div>
  <div class="kv"><span><?= te('puc_expiry') ?></span><span><?= $due($v['puc_expiry']) ?></span></div>
  <div class="kv"><span><?= te('next_service') ?></span><span><?= $due($v['next_service_date']) ?><?= $v['next_service_km'] ? ' / ' . inr($v['next_service_km'], 0) . ' km' : '' ?></span></div>
</div>
<?php if (can('prices')): ?><a class="btn-main text-center pt-2 mb-3" href="<?= url('jobcard_new.php?vehicle_id=' . $id) ?>"><i class="bi bi-plus-circle"></i> <?= te('create_repair_order') ?></a><?php endif; ?>
<?php if ($rem && can('reminders')): ?>
<div class="box"><h6><?= te('pending_reminders') ?></h6>
  <?php foreach ($rem as $r): ?><div class="kv"><span><?= te('rem_' . $r['type']) ?></span><span><?= $due($r['due_date']) ?></span></div><?php endforeach; ?>
</div>
<?php endif; ?>
<h6 class="text-muted small text-uppercase ms-1 mt-3"><?= te('service_history') ?> (<?= count($orders) ?>)</h6>
<?php if (!$orders) echo empty_state(t('no_orders_found')); foreach ($orders as $o) echo order_card($o); ?>
<?php if (is_admin() && !$orders): ?><button class="btn btn-link text-danger w-100" data-post="customers.php?action=vehicle_delete" data-params='{"id":<?= $id ?>}' data-confirm="<?= te('are_you_sure') ?>" data-redirect="customer.php?id=<?= (int) $v['customer_id'] ?>"><?= te('delete_vehicle') ?></button><?php endif; ?>
<?php if (can('customers')) include __DIR__ . '/includes/customer_modals.php'; include __DIR__ . '/includes/footer.php'; ?>
