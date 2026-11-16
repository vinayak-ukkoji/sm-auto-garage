<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('accounts');
$tab = 'accounts';
$date = valid_date((string) input('date', date('Y-m-d'))) ?? date('Y-m-d');

$sumBefore = fn($sql) => (float) val($sql, [$date]);
$opening = $sumBefore('SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date < ?')
         - $sumBefore('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date < ?')
         - $sumBefore('SELECT COALESCE(SUM(amount),0) FROM vendor_payments WHERE payment_date < ?');

$entries = array_merge(
    rows("SELECT p.created_at AS ts, 'in' AS dir, p.amount, p.mode, CONCAT(?, ' ', i.invoice_no) AS particulars, COALESCE(c.name, i.customer_name) AS party, CONCAT('invoice.php?id=', i.id) AS href
          FROM payments p JOIN invoices i ON i.id = p.invoice_id LEFT JOIN customers c ON c.id = i.customer_id WHERE p.payment_date = ?", [t('payment_received'), $date]),
    rows("SELECT e.created_at AS ts, 'out' AS dir, e.amount, e.mode, CONCAT(?, ': ', ec.name) AS particulars, e.paid_to AS party, NULL AS href
          FROM expenses e JOIN expense_categories ec ON ec.id = e.category_id WHERE e.expense_date = ?", [t('expense'), $date]),
    rows("SELECT vp.created_at AS ts, 'out' AS dir, vp.amount, vp.mode, CONCAT(?, ' ', po.po_no) AS particulars, v.name AS party, CONCAT('po.php?id=', po.id) AS href
          FROM vendor_payments vp JOIN purchase_orders po ON po.id = vp.po_id JOIN vendors v ON v.id = vp.vendor_id WHERE vp.payment_date = ?", [t('vendor_payment'), $date])
);
usort($entries, fn($a, $b) => strcmp($a['ts'], $b['ts']));
$in = $out = 0; $modes = [];
foreach ($entries as $x) {
    $sign = $x['dir'] === 'in' ? 1 : -1;
    if ($sign > 0) $in += $x['amount']; else $out += $x['amount'];
    $modes[$x['mode']] = ($modes[$x['mode']] ?? 0) + $sign * $x['amount'];
}
$invoiced = row("SELECT COUNT(*) n, COALESCE(SUM(total),0) s FROM invoices WHERE invoice_date = ? AND status <> 'cancelled'", [$date]);

$title = t('account_daily_book');
$back = url('accounts.php');
include __DIR__ . '/includes/header.php';
?>
<form class="d-flex gap-2 align-items-center mb-2" method="get">
  <a class="btn btn-light btn-sm" href="?date=<?= date('Y-m-d', strtotime($date . ' -1 day')) ?>"><i class="bi bi-chevron-left"></i></a>
  <input type="date" name="date" value="<?= e($date) ?>" class="form-control form-control-sm" onchange="this.form.submit()">
  <a class="btn btn-light btn-sm" href="?date=<?= date('Y-m-d', strtotime($date . ' +1 day')) ?>"><i class="bi bi-chevron-right"></i></a>
</form>
<div class="kpi">
  <div><small><?= te('opening_balance') ?></small><b><?= money($opening, 0) ?></b></div>
  <div><small><?= te('money_in') ?></small><b class="text-success"><?= money($in, 0) ?></b></div>
  <div><small><?= te('money_out') ?></small><b class="text-danger"><?= money($out, 0) ?></b></div>
  <div><small><?= te('closing_balance') ?></small><b><?= money($opening + $in - $out, 0) ?></b></div>
</div>
<div class="box py-2 small"><?= te('invoices_today') ?>: <b><?= (int) $invoiced['n'] ?></b> · <?= money($invoiced['s'], 0) ?>
  <?php foreach ($modes as $m => $v): ?><span class="ms-2 text-nowrap">· <?= te('mode_' . $m) ?>: <b><?= money($v, 0) ?></b></span><?php endforeach; ?></div>
<div class="box p-0">
  <table class="table-x">
    <tr><th><?= te('time') ?></th><th><?= te('particulars') ?></th><th class="text-end"><?= te('in') ?></th><th class="text-end"><?= te('out') ?></th></tr>
    <?php foreach ($entries as $x): ?>
    <tr <?= $x['href'] ? 'data-href="' . e($x['href']) . '" style="cursor:pointer"' : '' ?>>
      <td><?= date('h:i A', strtotime($x['ts'])) ?></td>
      <td><?= e($x['particulars']) ?><br><small class="text-muted"><?= e($x['party']) ?> · <?= te('mode_' . $x['mode']) ?></small></td>
      <td class="text-end text-success"><?= $x['dir'] === 'in' ? inr($x['amount']) : '' ?></td>
      <td class="text-end text-danger"><?= $x['dir'] === 'out' ? inr($x['amount']) : '' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$entries): ?><tr><td colspan="4" class="text-center text-muted py-4"><?= te('no_entries') ?></td></tr><?php endif; ?>
    <tr class="fw-bold"><td colspan="2"><?= te('total') ?></td><td class="text-end"><?= inr($in) ?></td><td class="text-end"><?= inr($out) ?></td></tr>
  </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
