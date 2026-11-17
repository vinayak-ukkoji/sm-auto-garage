<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('invoice');
$tab = 'accounts';
$id = input_int('id');
$inv = row('SELECT i.*, c.name AS cname, c.mobile AS cmobile, jc.jc_no, jc.vehicle_id, v.reg_no FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN job_cards jc ON jc.id = i.job_card_id LEFT JOIN vehicles v ON v.id = jc.vehicle_id WHERE i.id = ?', [$id]);
if (!$inv) redirect('invoices.php');
$items = rows('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY item_type DESC, id', [$id]);
$pays = rows('SELECT p.*, u.name AS uname FROM payments p LEFT JOIN users u ON u.id = p.user_id WHERE invoice_id = ? ORDER BY payment_date, id', [$id]);
$name = $inv['cname'] ?: $inv['customer_name'];
$mobile = $inv['cmobile'] ?: $inv['customer_mobile'];
$wa = fill_template((string) setting('wa_template_invoice'), ['customer' => $name, 'reg_no' => $inv['reg_no'] ?? '', 'invoice_no' => $inv['invoice_no'],
    'total' => inr($inv['total']), 'balance' => inr($inv['balance'])]) . "\n" . public_doc_url('inv', $id);

$title = t('invoice') . ' ' . $inv['invoice_no'];
$back = url('invoices.php');
$header_right = '<a class="hdr-icon" target="_blank" href="' . url('print/invoice.php?id=' . $id) . '"><i class="bi bi-printer"></i></a>';
include __DIR__ . '/includes/header.php';
$cls = ['paid' => 'st-paid', 'partial' => 'st-wip', 'unpaid' => 'st-due', 'cancelled' => 'st-cancelled'][$inv['status']];
?>
<div class="box">
  <div class="d-flex justify-content-between">
    <div><div class="fw-bold fs-6"><?= e($name ?: t('walk_in')) ?></div><div class="small text-muted"><?= e($mobile) ?></div>
      <?php if ($inv['jc_no']): ?><div class="small"><a href="<?= url('jobcard.php?id=' . $inv['job_card_id']) ?>"><?= e($inv['jc_no']) ?></a> · <?= e($inv['reg_no']) ?></div><?php endif; ?></div>
    <div class="text-end"><span class="badge-st <?= $cls ?>"><?= te('inv_' . $inv['status']) ?></span><div class="small text-muted mt-1"><?= e(fmt_date($inv['invoice_date'])) ?></div>
      <div class="small"><?= $inv['type'] === 'counter' ? te('counter_sale') : te('job_invoice') ?></div></div>
  </div>
</div>
<div class="box p-0">
  <table class="table-x">
    <tr><th>#</th><th><?= te('item') ?></th><th class="text-end"><?= te('qty') ?></th><th class="text-end"><?= te('rate') ?></th><?php if ($inv['gst_enabled']): ?><th class="text-end">GST</th><?php endif; ?><th class="text-end"><?= te('amount') ?></th></tr>
    <?php foreach ($items as $i => $it): ?>
    <tr><td><?= $i + 1 ?></td><td><?= e($it['description']) ?><br><small class="text-muted"><?= $it['item_type'] === 'labour' ? 'SAC' : 'HSN' ?> <?= e($it['hsn_sac']) ?><?= $it['discount'] > 0 ? ' · ' . te('disc') . ' ' . money($it['discount']) : '' ?></small></td>
      <td class="text-end"><?= qty($it['qty']) ?></td><td class="text-end"><?= inr($it['rate']) ?></td><?php if ($inv['gst_enabled']): ?><td class="text-end"><?= qty($it['gst_rate']) ?>%</td><?php endif; ?><td class="text-end"><?= inr($it['amount']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <div class="totals-box">
    <div class="kv"><span><?= te('taxable_amount') ?></span><span><?= money($inv['subtotal']) ?></span></div>
    <?php if (!$inv['gst_enabled']): ?>
    <?php elseif ($inv['is_igst']): ?><div class="kv"><span>IGST</span><span><?= money($inv['igst']) ?></span></div>
    <?php else: ?><div class="kv"><span>CGST</span><span><?= money($inv['cgst']) ?></span></div><div class="kv"><span>SGST</span><span><?= money($inv['sgst']) ?></span></div><?php endif; ?>
    <?php if ((float) $inv['round_off'] != 0): ?><div class="kv"><span><?= te('round_off') ?></span><span><?= money($inv['round_off']) ?></span></div><?php endif; ?>
    <div class="kv"><span><?= te('total') ?></span><span><?= money($inv['total']) ?></span></div>
  </div>
</div>
<div class="tot-row bg-white mb-3"><div><div class="text-success"><?= te('received') ?></div><?= money($inv['paid']) ?></div><div><div class="text-danger"><?= te('due') ?></div><?= money($inv['balance']) ?></div></div>

<?php if ($pays): ?>
<div class="box"><h6><?= te('payments') ?></h6>
  <?php foreach ($pays as $p): ?>
  <div class="kv"><span><?= e(fmt_date($p['payment_date'])) ?> · <?= te('mode_' . $p['mode']) ?><?= $p['reference_no'] ? ' · ' . e($p['reference_no']) : '' ?><br><small><?= e($p['uname']) ?> <?= e($p['note']) ?></small></span>
    <span><?= money($p['amount']) ?><?php if (is_admin()): ?> <button class="btn btn-sm text-danger p-0 ms-1" data-post="invoices.php?action=delete_payment" data-params='{"id":<?= (int) $p['id'] ?>}' data-confirm="<?= te('are_you_sure') ?>" data-reload><i class="bi bi-trash"></i></button><?php endif; ?></span></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($inv['notes']): ?><div class="box small" style="white-space:pre-line"><?= e($inv['notes']) ?></div><?php endif; ?>

<?php if ($inv['status'] !== 'cancelled'): ?>
  <?php if ($inv['balance'] > 0): ?><button class="btn-main" data-bs-toggle="modal" data-bs-target="#payModal"><?= te('add_payment') ?></button><?php endif; ?>
  <?php if ($mobile): ?><a class="btn-main outline text-center pt-2" target="_blank" href="<?= url('print/invoice.php?id=' . $id . '&share=1') ?>"><i class="bi bi-whatsapp"></i> <?= te('share_whatsapp') ?></a><?php endif; ?>
  <a class="btn-main outline text-center pt-2" target="_blank" href="<?= url('print/invoice.php?id=' . $id . '&download=1') ?>"><i class="bi bi-download"></i> <?= te('print_pdf') ?></a>
  <?php if (is_admin() && (float) $inv['paid'] == 0): ?>
    <button class="btn btn-link text-danger w-100 mt-2" id="cancelInv"><?= te('cancel_invoice') ?></button>
  <?php endif; ?>
<?php endif; ?>

<div class="modal fade" id="payModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="invoices.php?action=add_payment" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('add_payment') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="invoice_id" value="<?= $id ?>">
    <div class="mb-2"><label class="form-label"><?= te('amount') ?></label><input type="number" step="0.01" min="0.01" max="<?= e($inv['balance']) ?>" name="amount" value="<?= e($inv['balance']) ?>" class="form-control" required></div>
    <div class="row g-2">
      <div class="col-6"><label class="form-label"><?= te('payment_mode') ?></label><select name="mode" class="form-select"><?= mode_options() ?></select></div>
      <div class="col-6"><label class="form-label"><?= te('date') ?></label><input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" class="form-control" required></div>
    </div>
    <div class="mt-2"><label class="form-label"><?= te('reference_no') ?></label><input name="reference_no" class="form-control"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save_payment') ?></button></div>
</form></div></div>
<?php ob_start(); ?>
<script>
$('#cancelInv').on('click', function () {
  const r = prompt(<?= json_encode(t('cancel_reason')) ?>);
  if (r === null) return;
  api('invoices.php?action=cancel', { id: <?= $id ?>, reason: r }).then(() => location.reload());
});
</script>
<?php $page_script = ob_get_clean(); include __DIR__ . '/includes/footer.php'; ?>
