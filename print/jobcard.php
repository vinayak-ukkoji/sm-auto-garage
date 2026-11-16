<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/print_layout.php';

$id = input_int('id');
$public = doc_key_ok('jc', $id);
if (!$public) require_perm('service');
$jc = row(ORDER_SELECT . ' WHERE jc.id = ?', [$id]);
if (!$jc || (!$public && is_tech() && (int) $jc['technician_id'] !== uid())) { http_response_code(404); exit('Job card not found'); }
$v = row('SELECT * FROM vehicles WHERE id = ?', [$jc['vehicle_id']]);
$c = row('SELECT * FROM customers WHERE id = ?', [$jc['customer_id']]);
$labour = rows('SELECT * FROM job_card_labour WHERE job_card_id = ? ORDER BY id', [$id]);
$parts = rows('SELECT jp.*, p.hsn FROM job_card_parts jp JOIN parts p ON p.id = jp.part_id WHERE jp.job_card_id = ? ORDER BY jp.id', [$id]);
$complaints = rows('SELECT * FROM job_card_complaints WHERE job_card_id = ? ORDER BY id', [$id]);
$checks = rows('SELECT ci.name, ji.status, ji.remarks FROM job_card_inspections ji JOIN checklist_items ci ON ci.id = ji.checklist_item_id WHERE ji.job_card_id = ? ORDER BY ci.sort_order', [$id]);
$prices = $public || can('prices');
$gatepass = input('gatepass') === '1';
$docName = $gatepass ? 'GATE PASS' : ($jc['status'] === 'estimate' ? 'ESTIMATE' : 'JOB CARD / REPAIR ORDER');

print_start($docName . ' ' . $jc['jc_no']);
print_toolbar($prices ? wa_link($c['mobile'], order_wa_text($jc)) : '');
?>
<div class="sheet">
  <?php print_garage_header('<div class="b" style="font-size:14px">' . $docName . '</div><div>No: <b>' . e($jc['jc_no']) . '</b></div><div>Date: ' . e(fmt_dt($jc['created_at'])) . '</div>'); ?>
  <table class="grid" style="margin-top:8px">
    <tr>
      <td style="width:50%"><div class="muted">Customer</div><div class="b"><?= e($c['name']) ?></div><div>Mobile: <?= e($c['mobile']) ?></div>
        <?php if ($c['address']): ?><div><?= e($c['address'] . ($c['city'] ? ', ' . $c['city'] : '')) ?></div><?php endif; ?></td>
      <td><div class="muted">Vehicle</div><div class="b"><?= e($v['reg_no']) ?></div><div><?= e(vehicle_name($v)) ?><?= $v['year'] ? ' (' . (int) $v['year'] . ')' : '' ?></div>
        <div>KM: <?= $jc['odometer_in'] ? inr($jc['odometer_in'], 0) : '-' ?> · Fuel: <?= (int) $jc['fuel_level'] ?>%</div>
        <?php if ($v['chassis_no']): ?><div>Chassis: <?= e($v['chassis_no']) ?></div><?php endif; ?></td>
    </tr>
    <tr><td>Service Advisor: <?= e($jc['advisor_name'] ?: '-') ?> · Technician: <?= e($jc['tech_name'] ?: '-') ?></td>
        <td>Expected Delivery: <?= e(fmt_dt($jc['expected_delivery'])) ?><?= $jc['delivered_at'] ? '<br>Delivered: ' . e(fmt_dt($jc['delivered_at'])) : '' ?></td></tr>
  </table>

<?php if ($gatepass): ?>
  <div class="box2" style="margin-top:20px;font-size:14px;line-height:1.8">
    Allow vehicle <b><?= e($v['reg_no']) ?></b> (<?= e(vehicle_name($v)) ?>) of <b><?= e($c['name']) ?></b> to leave the workshop.<br>
    Job Card: <?= e($jc['jc_no']) ?> · Invoice: <?= e($jc['invoice_no'] ?: '-') ?> · Payment status: <b><?= $jc['inv_id'] ? ($jc['inv_balance'] > 0 ? 'Due ₹' . inr($jc['inv_balance']) : 'Paid') : 'Not invoiced' ?></b><br>
    Date / Time: <?= date('d M Y, h:i A') ?>
  </div>
  <div class="sign"><div>Security</div><div>Authorised Signatory</div></div>
<?php else: ?>
  <?php if ($complaints): ?>
  <div style="margin-top:10px" class="b">Customer Complaints</div>
  <table class="items"><tr><th style="width:30px">#</th><th style="text-align:left">Complaint</th><th style="text-align:left">Work Done</th></tr>
    <?php foreach ($complaints as $i => $cm): ?><tr><td class="c"><?= $i + 1 ?></td><td><?= e($cm['complaint']) ?></td><td><?= e($cm['resolution']) ?></td></tr><?php endforeach; ?>
  </table>
  <?php endif; ?>

  <div style="margin-top:10px" class="b">Labour / Services</div>
  <table class="items"><tr><th>#</th><th style="text-align:left">Description</th><th>Qty</th><?php if ($prices): ?><th>Rate</th><th>Disc</th><th>GST%</th><th>Amount</th><?php endif; ?><th>Done</th></tr>
    <?php foreach ($labour as $i => $l): ?><tr><td class="c"><?= $i + 1 ?></td><td><?= e($l['description']) ?></td><td class="r"><?= qty($l['qty']) ?></td>
      <?php if ($prices): ?><td class="r"><?= inr($l['rate']) ?></td><td class="r"><?= inr($l['discount']) ?></td><td class="c"><?= qty($l['gst_rate']) ?></td><td class="r"><?= inr($l['amount']) ?></td><?php endif; ?>
      <td class="c"><?= $l['is_done'] ? '✔' : '☐' ?></td></tr><?php endforeach; ?>
    <?php if (!$labour): ?><tr><td colspan="8" class="c muted">—</td></tr><?php endif; ?>
  </table>

  <div style="margin-top:10px" class="b">Parts</div>
  <table class="items"><tr><th>#</th><th style="text-align:left">Part</th><th>HSN</th><th>Qty</th><?php if ($prices): ?><th>Rate</th><th>Disc</th><th>GST%</th><th>Amount</th><?php endif; ?><th>Done</th></tr>
    <?php foreach ($parts as $i => $p): ?><tr><td class="c"><?= $i + 1 ?></td><td><?= e($p['description']) ?></td><td class="c"><?= e($p['hsn']) ?></td><td class="r"><?= qty($p['qty']) ?></td>
      <?php if ($prices): ?><td class="r"><?= inr($p['rate']) ?></td><td class="r"><?= inr($p['discount']) ?></td><td class="c"><?= qty($p['gst_rate']) ?></td><td class="r"><?= inr($p['amount']) ?></td><?php endif; ?>
      <td class="c"><?= $p['is_done'] ? '✔' : '☐' ?></td></tr><?php endforeach; ?>
    <?php if (!$parts): ?><tr><td colspan="9" class="c muted">—</td></tr><?php endif; ?>
  </table>

  <?php if ($prices): ?>
  <table class="tot" style="width:40%;margin-left:auto;margin-top:8px">
    <tr><td>Labour</td><td class="r"><?= inr($jc['labour_total']) ?></td></tr>
    <tr><td>Parts</td><td class="r"><?= inr($jc['parts_total']) ?></td></tr>
    <tr><td>GST</td><td class="r"><?= inr($jc['tax_total']) ?></td></tr>
    <tr class="g"><td><?= $jc['status'] === 'estimate' ? 'Estimated Total' : 'Total' ?></td><td class="r">₹<?= inr($jc['grand_total']) ?></td></tr>
  </table>
  <?php endif; ?>

  <?php if ($checks): ?>
  <div style="margin-top:10px" class="b">Inspection Checklist</div>
  <div class="box2"><?php foreach ($checks as $ck): ?><span class="chk"><?= ['ok' => '✔', 'attention' => '⚠', 'replace' => '✖', 'na' => '–'][$ck['status']] ?> <?= e($ck['name']) ?><?= $ck['remarks'] ? ' <span class="muted">(' . e($ck['remarks']) . ')</span>' : '' ?></span><?php endforeach; ?></div>
  <?php endif; ?>

  <table class="grid" style="margin-top:10px"><tr>
    <td style="width:50%"><b>Belongings:</b> <?= e($jc['belongings'] ?: '-') ?></td>
    <td><b>Damage / Remarks:</b> <?= e($jc['damage_notes'] ?: '-') ?></td></tr></table>
  <p class="muted" style="margin-top:8px;font-size:11px">I authorise the above repairs along with necessary materials. Estimates may change after inspection. The garage is not responsible for loss of articles left in the vehicle.</p>
  <div class="sign"><div>Customer Signature</div><div>Service Advisor</div></div>
<?php endif; ?>
</div>
<?php print_end(); ?>
