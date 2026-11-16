<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/print_layout.php';
require_perm('parts');
$id = input_int('id');
$po = row('SELECT po.*, v.name AS vname, v.mobile AS vmobile, v.address AS vaddress, v.gstin AS vgstin, v.contact_person FROM purchase_orders po JOIN vendors v ON v.id = po.vendor_id WHERE po.id = ?', [$id]);
if (!$po) { http_response_code(404); exit('PO not found'); }
$items = rows('SELECT poi.*, p.name, p.part_no, p.hsn, p.unit FROM purchase_order_items poi JOIN parts p ON p.id = poi.part_id WHERE poi.po_id = ? ORDER BY poi.id', [$id]);
print_start('Purchase Order ' . $po['po_no']);
print_toolbar();
?>
<div class="sheet">
  <?php print_garage_header('<div class="b" style="font-size:14px">' . ($po['status'] === 'received' ? 'PURCHASE / GOODS RECEIPT' : 'PURCHASE ORDER') . '</div><div>No: <b>' . e($po['po_no']) . '</b></div><div>Date: ' . e(fmt_date($po['po_date'])) . '</div>'); ?>
  <table class="grid" style="margin-top:8px"><tr>
    <td style="width:50%"><div class="muted">Vendor</div><div class="b"><?= e($po['vname']) ?></div><?= $po['contact_person'] ? '<div>' . e($po['contact_person']) . '</div>' : '' ?>
      <div><?= e($po['vmobile']) ?></div><div><?= e($po['vaddress']) ?></div><?= $po['vgstin'] ? '<div>GSTIN: ' . e($po['vgstin']) . '</div>' : '' ?></td>
    <td>Expected: <?= e(fmt_date($po['expected_date'])) ?><br>Status: <?= e(strtoupper($po['status'])) ?><?= $po['bill_no'] ? '<br>Vendor Bill: ' . e($po['bill_no']) : '' ?></td>
  </tr></table>
  <table class="items" style="margin-top:10px">
    <tr><th>#</th><th style="text-align:left">Part</th><th>Part No</th><th>HSN</th><th>Qty</th><th>Rate</th><th>GST%</th><th>Amount</th></tr>
    <?php foreach ($items as $i => $it): ?>
      <tr><td class="c"><?= $i + 1 ?></td><td><?= e($it['name']) ?></td><td><?= e($it['part_no']) ?></td><td class="c"><?= e($it['hsn']) ?></td>
        <td class="r"><?= qty($it['qty']) ?> <?= e($it['unit']) ?></td><td class="r"><?= inr($it['rate']) ?></td><td class="c"><?= qty($it['gst_rate']) ?></td><td class="r"><?= inr($it['amount']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <table class="tot" style="width:40%;margin-left:auto;margin-top:8px">
    <tr><td>Taxable</td><td class="r"><?= inr($po['subtotal']) ?></td></tr>
    <tr><td>GST</td><td class="r"><?= inr($po['tax_total']) ?></td></tr>
    <tr class="g"><td>Total</td><td class="r">₹<?= inr($po['total']) ?></td></tr>
  </table>
  <?php if ($po['notes']): ?><p><b>Notes:</b> <?= e($po['notes']) ?></p><?php endif; ?>
  <div class="sign"><div>Received By</div><div>For <?= e(setting('garage_name')) ?></div></div>
</div>
<?php print_end(); ?>
