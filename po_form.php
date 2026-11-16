<?php
/** Create / edit a purchase order. ?mode=stockin creates a received purchase (direct stock in). */
require __DIR__ . '/includes/bootstrap.php';
require_perm('parts');
$tab = 'parts';
$id = input_int('id');
$stockIn = input('mode') === 'stockin';
$po = $id ? row('SELECT * FROM purchase_orders WHERE id = ?', [$id]) : null;
if ($po && !in_array($po['status'], ['draft', 'ordered'], true)) { flash(t('po_locked'), 'error'); redirect('po.php?id=' . $id); }
$items = $po ? rows('SELECT poi.part_id, p.name AS description, poi.qty, poi.rate, poi.gst_rate, p.stock FROM purchase_order_items poi JOIN parts p ON p.id = poi.part_id WHERE poi.po_id = ?', [$id]) : [];
if (!$po && input_int('part_id')) {
    $pp = row('SELECT id AS part_id, name AS description, GREATEST(min_stock*2 - stock, 1) AS qty, purchase_price AS rate, gst_rate, stock FROM parts WHERE id = ?', [input_int('part_id')]);
    if ($pp) $items[] = $pp;
}
if (!$po && input('alerts') === '1') {
    $items = rows('SELECT id AS part_id, name AS description, GREATEST(min_stock*2 - stock, 1) AS qty, purchase_price AS rate, gst_rate, stock FROM parts WHERE is_active = 1 AND stock <= min_stock ORDER BY name');
}
$vendors = rows('SELECT id, name FROM vendors ORDER BY name');

$title = $stockIn ? t('stock_in') : ($po ? t('edit') . ' ' . $po['po_no'] : t('new_purchase_order'));
$back = $po ? url('po.php?id=' . $id) : url($stockIn ? 'parts.php' : 'purchase_orders.php');
include __DIR__ . '/includes/header.php';
?>
<form id="hdr" class="fsec" autocomplete="off">
  <div class="fsec-h"><?= te('vendor') ?> <a href="<?= url('vendors.php') ?>"><i class="bi bi-plus-circle"></i> <?= te('add_vendor') ?></a></div>
  <input type="hidden" name="id" value="<?= $id ?: '' ?>">
  <div class="frow"><i class="bi bi-shop"></i><select name="vendor_id" required><option value=""><?= te('select_vendor') ?></option>
    <?php foreach ($vendors as $v): ?><option value="<?= $v['id'] ?>" <?= $po && (int) $po['vendor_id'] === (int) $v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option><?php endforeach; ?></select></div>
  <div class="frow"><i class="bi bi-calendar"></i><label><?= $stockIn ? te('bill_date') : te('po_date') ?></label><input type="date" name="po_date" class="text-end" value="<?= e($po['po_date'] ?? date('Y-m-d')) ?>" required></div>
  <?php if ($stockIn): ?>
    <div class="frow"><i class="bi bi-receipt"></i><label><?= te('vendor_bill_no') ?></label><input name="bill_no" class="text-end" maxlength="50"></div>
  <?php else: ?>
    <div class="frow"><i class="bi bi-calendar-check"></i><label><?= te('expected_date') ?></label><input type="date" name="expected_date" class="text-end" value="<?= e($po['expected_date'] ?? '') ?>"></div>
  <?php endif; ?>
  <div class="frow"><i class="bi bi-journal"></i><input name="notes" placeholder="<?= te('notes') ?>" value="<?= e($po['notes'] ?? '') ?>"></div>
</form>
<div class="fsec"><div class="fsec-h"><?= te('items') ?>
  <?php if (!$po && !$stockIn): ?><a href="<?= url('po_form.php?alerts=1') ?>"><i class="bi bi-lightning"></i> <?= te('add_low_stock_items') ?></a><?php endif; ?></div>
  <div id="leItems"></div></div>
<?php if ($stockIn): ?>
<div class="fsec">
  <div class="fsec-h"><?= te('payment_to_vendor') ?></div>
  <div class="frow"><i class="bi bi-cash"></i><label><?= te('amount_paid') ?></label><input type="number" step="0.01" min="0" id="payAmt" class="text-end" value="0"></div>
  <div class="frow"><i class="bi bi-wallet2"></i><label><?= te('payment_mode') ?></label><select id="payMode" class="text-end"><?= mode_options() ?></select></div>
</div>
<?php endif; ?>
<div class="box"><div class="kv"><span><?= te('total') ?></span><b id="grand">₹0</b></div></div>
<?php if ($stockIn): ?>
  <button class="btn-main mb-3 save" data-status="received"><?= te('save_add_stock') ?></button>
<?php else: ?>
  <button class="btn-main save" data-status="ordered"><?= te('save_mark_ordered') ?></button>
  <button class="btn-main outline mb-3 save" data-status="draft"><?= te('save_draft') ?></button>
<?php endif; ?>
<?php ob_start(); ?>
<script>
$(function () {
  window.I = new LineEditor('#leItems', { kind: 'po', lines: <?= json_encode($items) ?>, onChange: () => window.I && $('#grand').text(inr(Math.round(I.totals().total), 0)) });
  $('#grand').text(inr(Math.round(I.totals().total), 0));
  $('.save').on('click', function () {
    if (!$('[name=vendor_id]').val()) { toast(<?= json_encode(t('select_vendor')) ?>, 'err'); return; }
    const b = $('.save').prop('disabled', true);
    const extra = { items: I.json(), status: $(this).data('status') };
    if ($('#payAmt').length) { extra.pay_amount = $('#payAmt').val(); extra.pay_mode = $('#payMode').val(); }
    api('purchases.php?action=save', $('#hdr').serialize() + '&' + $.param(extra)).then(r => location.href = url('po.php?id=' + r.data.id)).always(() => b.prop('disabled', false));
  });
});
</script>
<?php $page_script = ob_get_clean(); include __DIR__ . '/includes/footer.php'; ?>
