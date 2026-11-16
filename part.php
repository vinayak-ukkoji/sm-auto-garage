<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('parts');
$tab = 'parts';
$id = input_int('id');
$p = row('SELECT * FROM parts WHERE id = ?', [$id]);
if (!$p) redirect('parts.php');
$moves = rows('SELECT m.*, u.name AS uname FROM stock_movements m LEFT JOIN users u ON u.id = m.user_id WHERE m.part_id = ? ORDER BY m.created_at DESC, m.id DESC LIMIT 200', [$id]);
$sold = row("SELECT COALESCE(SUM(ii.qty),0) q, COALESCE(SUM(ii.taxable),0) v FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
    WHERE ii.part_id = ? AND i.status <> 'cancelled' AND i.invoice_date >= CURDATE() - INTERVAL 90 DAY", [$id]);

$title = $p['name'];
$back = url('parts.php');
$fill = $p; unset($fill['stock'], $fill['created_at']);
$header_right = '<button class="hdr-icon" data-bs-toggle="modal" data-bs-target="#partModal" data-fill=\'' . e(json_encode($fill)) . '\' data-title="' . te('edit_part') . '"><i class="bi bi-pencil-square"></i></button>';
include __DIR__ . '/includes/header.php';
$refUrl = function ($m) {
    if (!$m['ref_id']) return '';
    if (in_array($m['ref_type'], ['invoice', 'counter'], true)) return url('invoice.php?id=' . $m['ref_id']);
    if ($m['ref_type'] === 'po') return url('po.php?id=' . $m['ref_id']);
    return '';
};
?>
<div class="box">
  <div class="d-flex justify-content-between">
    <div><div class="fw-bold fs-6"><?= e($p['name']) ?></div><div class="small text-muted"><?= e(implode(' · ', array_filter([$p['part_no'], $p['brand'], $p['category']]))) ?></div></div>
    <div class="text-end"><div class="fs-4 <?= $p['stock'] <= $p['min_stock'] ? 'text-danger' : 'text-success' ?>"><?= qty($p['stock']) ?></div><small class="text-muted"><?= e($p['unit']) ?> <?= te('in_stock') ?></small></div>
  </div>
  <?php if (!$p['is_active']): ?><div class="badge bg-secondary"><?= te('inactive') ?></div><?php endif; ?>
</div>
<div class="kpi">
  <div><small><?= te('purchase_price') ?></small><b><?= money($p['purchase_price']) ?></b></div>
  <div><small><?= te('selling_price') ?></small><b><?= money($p['selling_price']) ?></b></div>
  <div><small>MRP · GST</small><b><?= money($p['mrp']) ?> · <?= qty($p['gst_rate']) ?>%</b></div>
  <div><small><?= te('min_stock') ?> · <?= te('rack_no') ?></small><b><?= qty($p['min_stock']) ?> · <?= e($p['rack_no'] ?: '-') ?></b></div>
  <div><small>HSN</small><b><?= e($p['hsn'] ?: '-') ?></b></div>
  <div><small><?= te('stock_value') ?></small><b><?= money(max(0, $p['stock']) * $p['purchase_price']) ?></b></div>
  <div><small><?= te('sold_90_days') ?></small><b><?= qty($sold['q']) ?> · <?= money($sold['v'], 0) ?></b></div>
</div>
<div class="d-flex gap-2 mb-3">
  <button class="btn btn-primary flex-fill" data-bs-toggle="modal" data-bs-target="#adjModal"><i class="bi bi-plus-slash-minus"></i> <?= te('adjust_stock') ?></button>
  <button class="btn btn-outline-secondary" data-post="parts.php?action=toggle" data-params='{"id":<?= $id ?>}' data-reload><?= $p['is_active'] ? te('deactivate') : te('activate') ?></button>
  <?php if (is_admin()): ?><button class="btn btn-outline-danger" data-post="parts.php?action=delete" data-params='{"id":<?= $id ?>}' data-confirm="<?= te('are_you_sure') ?>" data-redirect="parts.php"><i class="bi bi-trash"></i></button><?php endif; ?>
</div>

<div class="box p-0"><div class="p-3 pb-1"><h6 class="mb-0"><?= te('stock_history') ?></h6></div>
  <table class="table-x">
    <tr><th><?= te('date') ?></th><th><?= te('type') ?></th><th class="text-end"><?= te('qty') ?></th><th class="text-end"><?= te('rate') ?></th><th><?= te('note') ?></th></tr>
    <?php foreach ($moves as $m): $sign = $m['type'] === 'out' ? -1 : 1; $u = $refUrl($m); ?>
    <tr><td><?= e(fmt_date($m['created_at'], 'd M y')) ?></td><td><?= te('mv_' . $m['type']) ?></td>
      <td class="text-end <?= $sign * $m['qty'] < 0 ? 'neg' : 'text-success' ?>"><?= ($sign * $m['qty'] > 0 ? '+' : '') . qty($sign * $m['qty']) ?></td>
      <td class="text-end"><?= inr($m['rate']) ?></td>
      <td><?= $u ? '<a href="' . e($u) . '">' . e($m['note']) . '</a>' : e($m['note']) ?><br><small class="text-muted"><?= e($m['uname']) ?></small></td></tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="modal fade" id="partModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><form class="modal-content" data-api="parts.php?action=save" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('edit_part') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><?php include __DIR__ . '/includes/part_form.php'; ?><style>.opening-wrap{display:none}</style></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>

<div class="modal fade" id="adjModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="parts.php?action=adjust" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('adjust_stock') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="btn-group w-100 mb-2" role="group">
      <input type="radio" class="btn-check" name="mode" id="m1" value="add" checked><label class="btn btn-outline-primary" for="m1"><?= te('add_qty') ?></label>
      <input type="radio" class="btn-check" name="mode" id="m2" value="remove"><label class="btn btn-outline-primary" for="m2"><?= te('remove_qty') ?></label>
      <input type="radio" class="btn-check" name="mode" id="m3" value="set"><label class="btn btn-outline-primary" for="m3"><?= te('set_qty') ?></label>
    </div>
    <label class="form-label"><?= te('qty') ?></label><input type="number" step="0.01" min="0" name="qty" class="form-control" required>
    <label class="form-label mt-2"><?= te('reason') ?> *</label><input name="note" class="form-control" required placeholder="<?= te('adjust_reason_ph') ?>">
  </div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
