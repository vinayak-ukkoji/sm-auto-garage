<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('vendors');
$tab = 'more';
$id = input_int('id');
$v = row('SELECT * FROM vendors WHERE id = ?', [$id]);
if (!$v) redirect('vendors.php');
$pos = rows('SELECT * FROM purchase_orders WHERE vendor_id = ? ORDER BY po_date DESC, id DESC LIMIT 100', [$id]);
$tot = row("SELECT COALESCE(SUM(total),0) t, COALESCE(SUM(paid),0) p FROM purchase_orders WHERE vendor_id = ? AND status = 'received'", [$id]);
$title = $v['name'];
$back = url('vendors.php');
$fill = $v; unset($fill['created_at']);
$header_right = '<button class="hdr-icon" data-bs-toggle="modal" data-bs-target="#venModal" data-fill=\'' . e(json_encode($fill)) . '\' data-title="' . te('edit_vendor') . '"><i class="bi bi-pencil-square"></i></button>';
include __DIR__ . '/includes/header.php';
$cls = ['draft' => 'st-created', 'ordered' => 'st-wip', 'received' => 'st-paid', 'cancelled' => 'st-cancelled'];
?>
<div class="box">
  <div class="fw-bold fs-6"><?= e($v['name']) ?></div>
  <div class="small text-muted"><?= e(implode(' · ', array_filter([$v['contact_person'], $v['mobile'], $v['email']]))) ?></div>
  <?php if ($v['gstin']): ?><div class="small">GSTIN: <?= e($v['gstin']) ?></div><?php endif; ?>
  <?php if ($v['address']): ?><div class="small"><?= e($v['address']) ?></div><?php endif; ?>
  <?php if ($v['mobile']): ?><div class="mt-2 d-flex gap-2">
    <a class="btn btn-sm btn-outline-primary" href="<?= e(tel_link($v['mobile'])) ?>"><i class="bi bi-telephone"></i> <?= te('call') ?></a>
    <a class="btn btn-sm btn-outline-success" target="_blank" href="<?= e(wa_link($v['mobile'])) ?>"><i class="bi bi-whatsapp"></i> WhatsApp</a></div><?php endif; ?>
</div>
<div class="kpi" style="grid-template-columns:repeat(3,1fr)">
  <div><small><?= te('total_purchase') ?></small><b><?= money($tot['t'], 0) ?></b></div>
  <div><small><?= te('paid') ?></small><b class="text-success"><?= money($tot['p'], 0) ?></b></div>
  <div><small><?= te('payable') ?></small><b class="text-danger"><?= money($tot['t'] - $tot['p'], 0) ?></b></div>
</div>
<div class="d-flex gap-2 mb-3">
  <a class="btn btn-primary flex-fill" href="<?= url('po_form.php') ?>"><i class="bi bi-plus"></i> <?= te('new_purchase_order') ?></a>
  <?php if (is_admin()): ?><button class="btn btn-outline-danger" data-post="vendors.php?action=delete" data-params='{"id":<?= $id ?>}' data-confirm="<?= te('are_you_sure') ?>" data-redirect="vendors.php"><i class="bi bi-trash"></i></button><?php endif; ?>
</div>
<?php foreach ($pos as $r): ?>
  <a class="list-card" href="<?= url('po.php?id=' . $r['id']) ?>">
    <div class="r"><?= money($r['total'], 0) ?><?php if ($r['status'] === 'received' && $r['total'] > $r['paid']): ?><div class="small text-danger"><?= money($r['total'] - $r['paid'], 0) ?> <?= te('payable') ?></div><?php endif; ?></div>
    <div class="t"><?= e($r['po_no']) ?> <span class="badge-st <?= $cls[$r['status']] ?>"><?= te('po_' . $r['status']) ?></span></div>
    <div class="s"><?= e(fmt_date($r['po_date'])) ?></div>
  </a>
<?php endforeach; ?>
<?php include __DIR__ . '/includes/vendor_modal.php'; include __DIR__ . '/includes/footer.php'; ?>
