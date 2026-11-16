<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('parts');
$tab = 'parts';
$id = input_int('id');
$po = row('SELECT po.*, v.name AS vname, v.mobile AS vmobile, v.gstin AS vgstin FROM purchase_orders po JOIN vendors v ON v.id = po.vendor_id WHERE po.id = ?', [$id]);
if (!$po) redirect('purchase_orders.php');
$items = rows('SELECT poi.*, p.name, p.part_no, p.unit FROM purchase_order_items poi JOIN parts p ON p.id = poi.part_id WHERE poi.po_id = ? ORDER BY poi.id', [$id]);
$pays = rows('SELECT * FROM vendor_payments WHERE po_id = ? ORDER BY payment_date, id', [$id]);
$due = (float) $po['total'] - (float) $po['paid'];
$waTxt = 'Purchase Order ' . $po['po_no'] . ' from ' . setting('garage_name') . ":\n";
foreach ($items as $i => $it) $waTxt .= ($i + 1) . '. ' . $it['name'] . ($it['part_no'] ? ' (' . $it['part_no'] . ')' : '') . ' x ' . qty($it['qty']) . "\n";
$waTxt .= 'Please confirm availability.';
$cls = ['draft' => 'st-created', 'ordered' => 'st-wip', 'received' => 'st-paid', 'cancelled' => 'st-cancelled'][$po['status']];

$title = $po['po_no'];
$back = url('purchase_orders.php');
$header_right = '<a class="hdr-icon" target="_blank" href="' . url('print/po.php?id=' . $id) . '"><i class="bi bi-printer"></i></a>';
include __DIR__ . '/includes/header.php';
?>
<div class="box">
  <div class="d-flex justify-content-between">
    <div><div class="fw-bold fs-6"><a href="<?= url('vendor.php?id=' . $po['vendor_id']) ?>"><?= e($po['vname']) ?></a></div><div class="small text-muted"><?= e($po['vmobile']) ?> <?= $po['vgstin'] ? '· ' . e($po['vgstin']) : '' ?></div></div>
    <div class="text-end"><span class="badge-st <?= $cls ?>"><?= te('po_' . $po['status']) ?></span>
      <div class="small text-muted mt-1"><?= e(fmt_date($po['po_date'])) ?></div>
      <?php if ($po['bill_no']): ?><div class="small"><?= te('bill') ?>: <?= e($po['bill_no']) ?></div><?php endif; ?></div>
  </div>
  <?php if ($po['notes']): ?><div class="small mt-2"><?= e($po['notes']) ?></div><?php endif; ?>
</div>
<div class="box p-0">
  <table class="table-x">
    <tr><th><?= te('item') ?></th><th class="text-end"><?= te('qty') ?></th><th class="text-end"><?= te('rate') ?></th><th class="text-end">GST</th><th class="text-end"><?= te('amount') ?></th></tr>
    <?php foreach ($items as $it): ?>
    <tr><td><?= e($it['name']) ?><br><small class="text-muted"><?= e($it['part_no']) ?></small></td><td class="text-end"><?= qty($it['qty']) ?> <?= e($it['unit']) ?></td>
      <td class="text-end"><?= inr($it['rate']) ?></td><td class="text-end"><?= qty($it['gst_rate']) ?>%</td><td class="text-end"><?= inr($it['amount']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <div class="totals-box">
    <div class="kv"><span><?= te('taxable_amount') ?></span><span><?= money($po['subtotal']) ?></span></div>
    <div class="kv"><span>GST</span><span><?= money($po['tax_total']) ?></span></div>
    <div class="kv"><span><?= te('total') ?></span><span><?= money($po['total']) ?></span></div>
  </div>
</div>
<?php if ($po['status'] === 'received'): ?>
<div class="tot-row bg-white mb-3"><div><div class="text-success"><?= te('paid') ?></div><?= money($po['paid']) ?></div><div><div class="text-danger"><?= te('payable') ?></div><?= money($due) ?></div></div>
<?php endif; ?>
<?php if ($pays): ?>
<div class="box"><h6><?= te('payments') ?></h6>
  <?php foreach ($pays as $p): ?><div class="kv"><span><?= e(fmt_date($p['payment_date'])) ?> · <?= te('mode_' . $p['mode']) ?> <?= e($p['reference_no']) ?></span>
    <span><?= money($p['amount']) ?><?php if (is_admin()): ?> <button class="btn btn-sm text-danger p-0" data-post="purchases.php?action=delete_payment" data-params='{"id":<?= (int) $p['id'] ?>}' data-confirm="<?= te('are_you_sure') ?>" data-reload><i class="bi bi-trash"></i></button><?php endif; ?></span></div><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (in_array($po['status'], ['draft', 'ordered'], true)): ?>
  <button class="btn-main" data-bs-toggle="modal" data-bs-target="#rcvModal"><i class="bi bi-box-arrow-in-down"></i> <?= te('receive_stock') ?></button>
  <?php if ($po['status'] === 'draft'): ?><button class="btn-main outline" data-post="purchases.php?action=status" data-params='{"id":<?= $id ?>,"to":"ordered"}' data-reload><?= te('mark_ordered') ?></button><?php endif; ?>
  <a class="btn-main outline text-center pt-2" href="<?= url('po_form.php?id=' . $id) ?>"><i class="bi bi-pencil"></i> <?= te('edit') ?></a>
  <?php if ($po['vmobile']): ?><a class="btn-main outline text-center pt-2" target="_blank" href="<?= e(wa_link($po['vmobile'], $waTxt)) ?>"><i class="bi bi-whatsapp"></i> <?= te('send_to_vendor') ?></a><?php endif; ?>
  <button class="btn btn-link text-danger w-100 mt-2" data-post="purchases.php?action=status" data-params='{"id":<?= $id ?>,"to":"cancelled"}' data-confirm="<?= te('are_you_sure') ?>" data-reload><?= te('cancel_po') ?></button>
<?php elseif ($po['status'] === 'received' && $due > 0 && can('accounts')): ?>
  <button class="btn-main" data-bs-toggle="modal" data-bs-target="#payModal"><?= te('pay_vendor') ?></button>
<?php endif; ?>

<div class="modal fade" id="rcvModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="purchases.php?action=receive" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('receive_stock') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id" value="<?= $id ?>"><p class="small text-muted"><?= te('receive_help') ?></p>
    <label class="form-label"><?= te('vendor_bill_no') ?></label><input name="bill_no" class="form-control" value="<?= e($po['bill_no']) ?>"></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('receive_stock') ?></button></div>
</form></div></div>
<div class="modal fade" id="payModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="purchases.php?action=pay" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('pay_vendor') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="po_id" value="<?= $id ?>">
    <label class="form-label"><?= te('amount') ?></label><input type="number" step="0.01" min="0.01" max="<?= $due ?>" name="amount" value="<?= $due ?>" class="form-control" required>
    <div class="row g-2 mt-1"><div class="col-6"><label class="form-label"><?= te('payment_mode') ?></label><select name="mode" class="form-select"><?= mode_options() ?></select></div>
      <div class="col-6"><label class="form-label"><?= te('date') ?></label><input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" class="form-control" required></div></div>
    <label class="form-label mt-2"><?= te('reference_no') ?></label><input name="reference_no" class="form-control"></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save_payment') ?></button></div>
</form></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
