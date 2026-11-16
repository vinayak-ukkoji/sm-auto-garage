<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('customers');
$tab = 'more';
$id = input_int('id');
$c = row('SELECT * FROM customers WHERE id = ?', [$id]);
if (!$c) redirect('customers.php');
$vehicles = rows('SELECT * FROM vehicles WHERE customer_id = ? ORDER BY reg_no', [$id]);
[$orders] = query_orders('all', '', 0, 50, ['customer_id' => $id]);
$invs = rows("SELECT * FROM invoices WHERE customer_id = ? ORDER BY invoice_date DESC, id DESC LIMIT 50", [$id]);
$stats = row("SELECT COALESCE(SUM(total),0) billed, COALESCE(SUM(paid),0) paid, COALESCE(SUM(balance),0) due FROM invoices WHERE customer_id = ? AND status <> 'cancelled'", [$id]);
$modalCustomerId = $id;
$fill = $c; unset($fill['created_at']);

$title = $c['name'];
$back = url('customers.php');
$header_right = '<button class="hdr-icon" data-bs-toggle="modal" data-bs-target="#custModal" data-fill=\'' . e(json_encode($fill)) . '\' data-title="' . te('edit_customer') . '"><i class="bi bi-pencil-square"></i></button>';
include __DIR__ . '/includes/header.php';
$view = one_of(input('view', 'vehicles'), ['vehicles', 'orders', 'invoices'], 'vehicles');
?>
<div class="box">
  <div class="fw-bold fs-5"><?= e($c['name']) ?></div>
  <div><i class="bi bi-telephone"></i> <?= e($c['mobile']) ?><?= $c['alt_mobile'] ? ' / ' . e($c['alt_mobile']) : '' ?></div>
  <?php if ($c['email']): ?><div class="small"><i class="bi bi-envelope"></i> <?= e($c['email']) ?></div><?php endif; ?>
  <?php if ($c['address'] || $c['city']): ?><div class="small text-muted"><?= e(trim($c['address'] . ', ' . $c['city'], ', ')) ?></div><?php endif; ?>
  <?php if ($c['gstin']): ?><div class="small">GSTIN: <?= e($c['gstin']) ?></div><?php endif; ?>
  <?php if ($c['notes']): ?><div class="small fst-italic mt-1"><?= e($c['notes']) ?></div><?php endif; ?>
  <div class="mt-2 d-flex gap-2 flex-wrap">
    <a class="btn btn-sm btn-outline-primary" href="<?= e(tel_link($c['mobile'])) ?>"><i class="bi bi-telephone"></i> <?= te('call') ?></a>
    <a class="btn btn-sm btn-outline-success" target="_blank" href="<?= e(wa_link($c['mobile'])) ?>"><i class="bi bi-whatsapp"></i> WhatsApp</a>
    <?php if (is_admin()): ?><button class="btn btn-sm btn-outline-danger ms-auto" data-post="customers.php?action=delete" data-params='{"id":<?= $id ?>}' data-confirm="<?= te('are_you_sure') ?>" data-redirect="customers.php"><i class="bi bi-trash"></i></button><?php endif; ?>
  </div>
</div>
<div class="kpi" style="grid-template-columns:repeat(3,1fr)">
  <div><small><?= te('total_billed') ?></small><b><?= money($stats['billed'], 0) ?></b></div>
  <div><small><?= te('received') ?></small><b class="text-success"><?= money($stats['paid'], 0) ?></b></div>
  <div><small><?= te('due') ?></small><b class="text-danger"><?= money($stats['due'], 0) ?></b></div>
</div>
<div class="tabs-x mb-2 bg-white">
  <a class="<?= $view === 'vehicles' ? 'active' : '' ?>" href="?id=<?= $id ?>&view=vehicles"><?= te('vehicles') ?> (<?= count($vehicles) ?>)</a>
  <a class="<?= $view === 'orders' ? 'active' : '' ?>" href="?id=<?= $id ?>&view=orders"><?= te('orders') ?> (<?= count($orders) ?>)</a>
  <a class="<?= $view === 'invoices' ? 'active' : '' ?>" href="?id=<?= $id ?>&view=invoices"><?= te('invoices') ?> (<?= count($invs) ?>)</a>
</div>
<?php if ($view === 'vehicles'): ?>
  <?php foreach ($vehicles as $v): ?>
    <a class="list-card" href="<?= url('vehicle.php?id=' . $v['id']) ?>"><div class="r"><small class="text-muted"><?= $v['odometer'] ? inr($v['odometer'], 0) . ' km' : '' ?></small></div>
      <div class="t"><?= e($v['reg_no']) ?></div><div class="s"><?= e(vehicle_name($v)) ?></div></a>
  <?php endforeach; ?>
  <button class="btn btn-outline-primary w-100 mt-1" data-bs-toggle="modal" data-bs-target="#vehModal" data-fill='{"customer_id":<?= $id ?>}' data-title="<?= te('add_vehicle') ?>"><i class="bi bi-plus"></i> <?= te('add_vehicle') ?></button>
<?php elseif ($view === 'orders'): ?>
  <?php if (!$orders) echo empty_state(t('no_orders_found')); foreach ($orders as $o) echo order_card($o); ?>
<?php else: ?>
  <?php if (!$invs) echo empty_state(t('no_records'), 'bi-receipt'); ?>
  <?php foreach ($invs as $r): ?>
    <a class="list-card" href="<?= url('invoice.php?id=' . $r['id']) ?>"><div class="r"><?= money($r['total'], 0) ?><?php if ($r['balance'] > 0 && $r['status'] !== 'cancelled'): ?><div class="small text-danger"><?= money($r['balance'], 0) ?> <?= te('due') ?></div><?php endif; ?></div>
      <div class="t"><?= e($r['invoice_no']) ?></div><div class="s"><?= e(fmt_date($r['invoice_date'])) ?> · <?= te('inv_' . $r['status']) ?></div></a>
  <?php endforeach; ?>
<?php endif; ?>
<?php include __DIR__ . '/includes/customer_modals.php'; include __DIR__ . '/includes/footer.php'; ?>
