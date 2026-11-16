<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/print_layout.php';
$id = input_int('id');
if (!doc_key_ok('inv', $id)) require_perm('invoice');
$inv = row('SELECT i.*, c.name AS cname, c.mobile AS cmobile, c.address AS caddress, c.city AS ccity, c.gstin AS cgstin,
        jc.jc_no, jc.odometer_in, v.reg_no, v.make, v.model, v.variant, v.fuel_type, v.chassis_no
    FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN job_cards jc ON jc.id = i.job_card_id
    LEFT JOIN vehicles v ON v.id = jc.vehicle_id WHERE i.id = ?', [$id]);
if (!$inv) { http_response_code(404); exit('Invoice not found'); }
$items = rows('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY item_type DESC, id', [$id]);
$gstSummary = rows('SELECT gst_rate, SUM(taxable) taxable, SUM(tax) tax FROM invoice_items WHERE invoice_id = ? GROUP BY gst_rate ORDER BY gst_rate', [$id]);
$s = setting();
$name = $inv['cname'] ?: $inv['customer_name'];
$mobile = $inv['cmobile'] ?: $inv['customer_mobile'];
$wa = $mobile ? wa_link($mobile, fill_template((string) $s['wa_template_invoice'], ['customer' => $name, 'reg_no' => $inv['reg_no'] ?? '',
    'invoice_no' => $inv['invoice_no'], 'total' => inr($inv['total']), 'balance' => inr($inv['balance'])]) . "\n" . public_doc_url('inv', $id)) : '';

print_start('Invoice ' . $inv['invoice_no']);
print_toolbar($wa);
?>
<div class="sheet">
  <?php print_garage_header('<div class="b" style="font-size:14px">TAX INVOICE</div><div class="muted">Original for Recipient</div>'
      . ($inv['status'] === 'cancelled' ? '<div style="color:#c00;font-size:18px;font-weight:700">CANCELLED</div>' : '')); ?>
  <table class="grid" style="margin-top:8px">
    <tr>
      <td style="width:50%">
        <div class="muted">Bill To</div>
        <div class="b" style="font-size:13px"><?= e($name ?: 'Walk-in Customer') ?></div>
        <?php if ($mobile): ?><div>Mobile: <?= e($mobile) ?></div><?php endif; ?>
        <?php if ($inv['caddress'] || $inv['ccity']): ?><div><?= e(trim($inv['caddress'] . ', ' . $inv['ccity'], ', ')) ?></div><?php endif; ?>
        <?php if ($inv['cgstin']): ?><div>GSTIN: <b><?= e($inv['cgstin']) ?></b></div><?php endif; ?>
      </td>
      <td>
        <table>
          <tr><td style="border:0;padding:1px">Invoice No</td><td style="border:0;padding:1px" class="b"><?= e($inv['invoice_no']) ?></td></tr>
          <tr><td style="border:0;padding:1px">Invoice Date</td><td style="border:0;padding:1px"><?= e(fmt_date($inv['invoice_date'], 'd-m-Y')) ?></td></tr>
          <?php if ($inv['jc_no']): ?><tr><td style="border:0;padding:1px">Job Card</td><td style="border:0;padding:1px"><?= e($inv['jc_no']) ?></td></tr><?php endif; ?>
          <tr><td style="border:0;padding:1px">Place of Supply</td><td style="border:0;padding:1px"><?= e($s['state_name']) ?> (<?= e($inv['place_of_supply']) ?>)</td></tr>
        </table>
      </td>
    </tr>
    <?php if ($inv['reg_no']): ?>
    <tr><td colspan="2"><b>Vehicle:</b> <?= e($inv['reg_no']) ?> · <?= e(vehicle_name($inv)) ?><?= $inv['odometer_in'] ? ' · ' . inr($inv['odometer_in'], 0) . ' km' : '' ?><?= $inv['chassis_no'] ? ' · Chassis ' . e($inv['chassis_no']) : '' ?></td></tr>
    <?php endif; ?>
  </table>

  <table class="items" style="margin-top:10px">
    <tr><th>#</th><th style="text-align:left">Description</th><th>HSN/SAC</th><th>Qty</th><th>Rate</th><th>Disc</th><th>Taxable</th><th>GST%</th>
      <?= $inv['is_igst'] ? '<th>IGST</th>' : '<th>CGST</th><th>SGST</th>' ?><th>Amount</th></tr>
    <?php foreach ($items as $i => $it): ?>
    <tr>
      <td class="c"><?= $i + 1 ?></td>
      <td><?= e($it['description']) ?><?= $it['item_type'] === 'labour' ? ' <span class="muted">(Labour)</span>' : '' ?></td>
      <td class="c"><?= e($it['hsn_sac']) ?></td>
      <td class="r"><?= qty($it['qty']) ?></td>
      <td class="r"><?= inr($it['rate']) ?></td>
      <td class="r"><?= (float) $it['discount'] ? inr($it['discount']) : '-' ?></td>
      <td class="r"><?= inr($it['taxable']) ?></td>
      <td class="c"><?= qty($it['gst_rate']) ?></td>
      <?php if ($inv['is_igst']): ?><td class="r"><?= inr($it['tax']) ?></td>
      <?php else: $h = round($it['tax'] / 2, 2); ?><td class="r"><?= inr($h) ?></td><td class="r"><?= inr($it['tax'] - $h) ?></td><?php endif; ?>
      <td class="r"><?= inr($it['amount']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <div style="display:flex;gap:12px;margin-top:10px">
    <div style="flex:1">
      <table class="items">
        <tr><th>GST %</th><th>Taxable</th><?= $inv['is_igst'] ? '<th>IGST</th>' : '<th>CGST</th><th>SGST</th>' ?><th>Total Tax</th></tr>
        <?php foreach ($gstSummary as $g): $h = round($g['tax'] / 2, 2); ?>
        <tr><td class="c"><?= qty($g['gst_rate']) ?>%</td><td class="r"><?= inr($g['taxable']) ?></td>
          <?= $inv['is_igst'] ? '<td class="r">' . inr($g['tax']) . '</td>' : '<td class="r">' . inr($h) . '</td><td class="r">' . inr($g['tax'] - $h) . '</td>' ?>
          <td class="r"><?= inr($g['tax']) ?></td></tr>
        <?php endforeach; ?>
      </table>
      <div class="box2" style="margin-top:8px"><b>Amount in words:</b> <?= e(amount_in_words((float) $inv['total'])) ?></div>
      <?php if ($s['bank_details'] || $s['upi_id']): ?>
        <div class="box2" style="margin-top:6px"><b>Bank / UPI:</b> <?= nl2br(e($s['bank_details'])) ?> <?= $s['upi_id'] ? '<br>UPI: ' . e($s['upi_id']) : '' ?></div>
      <?php endif; ?>
    </div>
    <div style="width:40%">
      <table class="tot">
        <tr><td>Taxable Amount</td><td class="r"><?= inr($inv['subtotal']) ?></td></tr>
        <?php if ((float) $inv['discount']): ?><tr><td class="muted">(Discount given)</td><td class="r muted"><?= inr($inv['discount']) ?></td></tr><?php endif; ?>
        <?php if ($inv['is_igst']): ?><tr><td>IGST</td><td class="r"><?= inr($inv['igst']) ?></td></tr>
        <?php else: ?><tr><td>CGST</td><td class="r"><?= inr($inv['cgst']) ?></td></tr><tr><td>SGST</td><td class="r"><?= inr($inv['sgst']) ?></td></tr><?php endif; ?>
        <tr><td>Round Off</td><td class="r"><?= inr($inv['round_off']) ?></td></tr>
        <tr class="g"><td>Grand Total</td><td class="r">₹<?= inr($inv['total']) ?></td></tr>
        <tr><td>Paid</td><td class="r"><?= inr($inv['paid']) ?></td></tr>
        <tr><td class="b">Balance Due</td><td class="r b"><?= inr($inv['balance']) ?></td></tr>
      </table>
    </div>
  </div>

  <?php if ($s['invoice_terms']): ?><div style="margin-top:12px"><b>Terms & Conditions</b><div class="muted" style="white-space:pre-line"><?= e($s['invoice_terms']) ?></div></div><?php endif; ?>
  <div class="sign"><div>Customer Signature</div><div>For <?= e($s['garage_name']) ?><br>Authorised Signatory</div></div>
  <p class="c muted" style="margin-top:20px">This is a computer generated invoice.</p>
</div>
<?php print_end(); ?>
