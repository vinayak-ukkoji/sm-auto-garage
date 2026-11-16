<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('parts');
$tab = 'parts';
$status = one_of(input('status', 'open'), ['open', 'draft', 'ordered', 'received', 'cancelled', 'all'], 'open');
$where = ['open' => "po.status IN ('draft','ordered')", 'all' => '1=1'][$status] ?? 'po.status = ?';
$params = in_array($status, ['open', 'all'], true) ? [] : [$status];
$list = rows("SELECT po.*, v.name AS vname, (SELECT COUNT(*) FROM purchase_order_items i WHERE i.po_id = po.id) AS n
    FROM purchase_orders po JOIN vendors v ON v.id = po.vendor_id WHERE $where ORDER BY po.po_date DESC, po.id DESC LIMIT 200", $params);
$title = t('purchase_orders');
$back = url('parts.php');
include __DIR__ . '/includes/header.php';
$cls = ['draft' => 'st-created', 'ordered' => 'st-wip', 'received' => 'st-paid', 'cancelled' => 'st-cancelled'];
?>
<div class="seg">
  <?php foreach (['open', 'draft', 'ordered', 'received', 'cancelled', 'all'] as $s): ?>
    <a class="<?= $s === $status ? 'active' : '' ?>" href="<?= url('purchase_orders.php?status=' . $s) ?>"><?= te('po_' . $s) ?></a>
  <?php endforeach; ?>
</div>
<?php if (!$list) echo empty_state(t('no_records'), 'bi-bag'); ?>
<?php foreach ($list as $r): $due = $r['total'] - $r['paid']; ?>
  <a class="list-card" href="<?= url('po.php?id=' . $r['id']) ?>">
    <div class="r"><div><?= money($r['total'], 0) ?></div>
      <?php if ($r['status'] === 'received' && $due > 0): ?><small class="text-danger"><?= money($due, 0) ?> <?= te('payable') ?></small><?php endif; ?></div>
    <div class="t"><?= e($r['po_no']) ?> <span class="badge-st <?= $cls[$r['status']] ?>"><?= te('po_' . $r['status']) ?></span></div>
    <div class="s"><?= e($r['vname']) ?> · <?= (int) $r['n'] ?> <?= te('items') ?> · <?= e(fmt_date($r['po_date'])) ?><?= $r['bill_no'] ? ' · ' . te('bill') . ' ' . e($r['bill_no']) : '' ?></div>
  </a>
<?php endforeach; ?>
<a class="fab" href="<?= url('po_form.php') ?>" title="<?= te('new_purchase_order') ?>"><i class="bi bi-plus-lg"></i></a>
<?php include __DIR__ . '/includes/footer.php'; ?>
