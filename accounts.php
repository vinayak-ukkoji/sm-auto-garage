<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('accounts');
$tab = 'accounts';
$t = one_of(input('tab', 'expense'), ['expense', 'purchase', 'income'], 'expense');
$period = one_of(input('period', 'this_month'), ['today', 'yesterday', 'this_week', 'this_month', 'last_month', 'this_year', 'custom'], 'this_month');
[$from, $to, $plabel] = period_range($period, (string) input('from'), (string) input('to'));
$qs = 'period=' . $period . ($period === 'custom' ? '&from=' . $from . '&to=' . $to : '');

$income = (float) val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date BETWEEN ? AND ?', [$from, $to]);
$expTotal = (float) val('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?', [$from, $to]);
$vendorPaid = (float) val('SELECT COALESCE(SUM(amount),0) FROM vendor_payments WHERE payment_date BETWEEN ? AND ?', [$from, $to]);
$payable = (float) val("SELECT COALESCE(SUM(total - paid),0) FROM purchase_orders WHERE status = 'received' AND po_date BETWEEN ? AND ?", [$from, $to]);
$net = $income - $expTotal - $vendorPaid;

$title = t('accounts');
$title_center = true;
$title_html = '<span style="color:#333;font-size:15px">' . te('accounts') . '</span>';
$header_left = '<a class="acc-hdr-btn" href="' . url('daybook.php') . '">' . te('account_daily_book') . '</a>';
$periodLinks = '';
foreach (['today', 'yesterday', 'this_week', 'this_month', 'last_month', 'this_year'] as $p) {
    $periodLinks .= '<li><a class="dropdown-item' . ($p === $period ? ' active' : '') . '" href="' . url('accounts.php?tab=' . $t . '&period=' . $p) . '">' . te($p === 'this_year' ? 'this_fy' : $p) . '</a></li>';
}
$periodLinks .= '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#customModal">' . te('custom_range') . '</a></li>'
    . '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="' . url('invoices.php') . '"><i class="bi bi-receipt me-2"></i>' . te('invoices') . '</a></li>'
    . '<li><a class="dropdown-item" href="' . url('invoices.php?status=due') . '"><i class="bi bi-exclamation-circle me-2"></i>' . te('outstanding_dues') . '</a></li>'
    . (can('reports') ? '<li><a class="dropdown-item" href="' . url('reports.php') . '"><i class="bi bi-bar-chart me-2"></i>' . te('reports') . '</a></li>' : '');
$header_right = '<div class="dropdown"><button class="hdr-icon" data-bs-toggle="dropdown"><i class="bi bi-filter"></i></button><ul class="dropdown-menu dropdown-menu-end">' . $periodLinks . '</ul></div>';
$body_class = 'white-bg';
include __DIR__ . '/includes/header.php';
?>
<div style="margin:-10px -10px 0;background:#fff;padding:6px 10px 150px">
  <div class="filter-pill-wrap mt-1"><span class="filter-pill" style="font-size:12px"><?= e(str_replace(' ', '_', strtoupper($plabel))) ?></span></div>
  <div class="tabs-x">
    <a class="<?= $t === 'expense' ? 'active' : '' ?>" href="<?= url('accounts.php?tab=expense&' . $qs) ?>"><?= te('expense') ?></a>
    <a class="<?= $t === 'purchase' ? 'active' : '' ?>" href="<?= url('accounts.php?tab=purchase&' . $qs) ?>"><?= te('part_purchase') ?></a>
    <a class="<?= $t === 'income' ? 'active' : '' ?>" href="<?= url('accounts.php?tab=income&' . $qs) ?>"><?= te('income') ?></a>
  </div>

<?php if ($t === 'expense'):
    $list = rows('SELECT e.*, c.name AS cat, jc.jc_no FROM expenses e JOIN expense_categories c ON c.id = e.category_id LEFT JOIN job_cards jc ON jc.id = e.job_card_id
        WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC, e.id DESC', [$from, $to]);
    $cats = rows('SELECT * FROM expense_categories ORDER BY name'); ?>
  <button class="btn-dark-x mt-3 ms-1" data-bs-toggle="modal" data-bs-target="#expModal" data-fill='{"expense_date":"<?= date('Y-m-d') ?>","mode":"cash"}' data-title="<?= te('add_expense') ?>"><i class="bi bi-plus-lg"></i> <?= te('add_expense') ?></button>
  <div class="border mt-4 mx-1">
    <div class="legend"><span><i class="bg-paid"></i><?= te('paid') ?></span><span><i class="bg-partial"></i><?= te('partial_paid') ?></span><span><i class="bg-credit"></i><?= te('credit') ?></span></div>
    <div class="sum3"><div><?= te('total_caps') ?><b><?= money($expTotal) ?></b></div><div style="color:#188038"><?= te('paid_caps') ?><b><?= money($expTotal) ?></b></div><div style="color:#c62828"><?= te('credit_caps') ?><b><?= money(0) ?></b></div></div>
  </div>
  <div class="mt-2">
    <?php foreach ($list as $x): $fill = ['id' => $x['id'], 'category_id' => $x['category_id'], 'expense_date' => $x['expense_date'], 'amount' => $x['amount'], 'mode' => $x['mode'], 'paid_to' => $x['paid_to'], 'note' => $x['note']]; ?>
      <div class="ledger-row">
        <div class="l1"><span class="sq paid"></span><?= e($x['cat']) ?> <?= $x['paid_to'] ? '· ' . e($x['paid_to']) : '' ?>
          <small><?= e(fmt_date($x['expense_date'])) ?> · <?= te('mode_' . $x['mode']) ?><?= $x['note'] ? ' · ' . e($x['note']) : '' ?><?= $x['jc_no'] ? ' · ' . e($x['jc_no']) : '' ?></small></div>
        <div class="amt"><?= money($x['amount']) ?><br>
          <button class="btn btn-sm p-0 text-secondary" data-bs-toggle="modal" data-bs-target="#expModal" data-fill='<?= e(json_encode($fill)) ?>' data-title="<?= te('edit_expense') ?>"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm p-0 ms-2 text-danger" data-post="expenses.php?action=delete" data-params='{"id":<?= (int) $x['id'] ?>}' data-confirm="<?= te('are_you_sure') ?>" data-reload><i class="bi bi-trash"></i></button></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$list): ?><p class="text-muted small mt-3 ms-1"><?= te('no_expense_found') ?></p><?php endif; ?>
  </div>
  <div class="modal fade" id="expModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="expenses.php?action=save" data-reload>
    <div class="modal-header"><h5 class="modal-title"><?= te('add_expense') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" name="id">
      <div class="row g-2">
        <div class="col-6"><label class="form-label"><?= te('category') ?></label><select name="category_id" class="form-select"><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6"><label class="form-label"><?= te('amount') ?></label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
        <div class="col-6"><label class="form-label"><?= te('date') ?></label><input type="date" name="expense_date" class="form-control" required></div>
        <div class="col-6"><label class="form-label"><?= te('payment_mode') ?></label><select name="mode" class="form-select"><?= mode_options() ?></select></div>
        <div class="col-12"><label class="form-label"><?= te('paid_to') ?></label><input name="paid_to" class="form-control" maxlength="100"></div>
        <div class="col-12"><label class="form-label"><?= te('note') ?></label><input name="note" class="form-control" maxlength="255"></div>
        <div class="col-12"><label class="form-label small"><?= te('new_category') ?></label><input name="new_category" class="form-control form-control-sm" placeholder="<?= te('new_category_ph') ?>" maxlength="60"></div>
      </div></div>
    <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
  </form></div></div>

<?php elseif ($t === 'purchase'):
    $list = rows("SELECT po.*, v.name AS vname FROM purchase_orders po JOIN vendors v ON v.id = po.vendor_id WHERE po.status = 'received' AND po.po_date BETWEEN ? AND ? ORDER BY po.po_date DESC, po.id DESC", [$from, $to]);
    $pt = array_sum(array_column($list, 'total')); $pp = array_sum(array_column($list, 'paid')); ?>
  <a class="btn-dark-x mt-3 ms-1 d-inline-block" href="<?= url('stock_in.php') ?>"><i class="bi bi-plus-lg"></i> <?= te('add_purchase') ?></a>
  <div class="border mt-4 mx-1">
    <div class="legend"><span><i class="bg-paid"></i><?= te('paid') ?></span><span><i class="bg-partial"></i><?= te('partial_paid') ?></span><span><i class="bg-credit"></i><?= te('credit') ?></span></div>
    <div class="sum3"><div><?= te('total_caps') ?><b><?= money($pt) ?></b></div><div style="color:#188038"><?= te('paid_caps') ?><b><?= money($pp) ?></b></div><div style="color:#c62828"><?= te('credit_caps') ?><b><?= money($pt - $pp) ?></b></div></div>
  </div>
  <div class="mt-2">
    <?php foreach ($list as $x): $st = $x['paid'] >= $x['total'] ? 'paid' : ($x['paid'] > 0 ? 'partial' : 'credit'); ?>
      <div class="ledger-row" data-href="po.php?id=<?= $x['id'] ?>" style="cursor:pointer">
        <div class="l1"><span class="sq <?= $st ?>"></span><?= e($x['vname']) ?> <small><?= e($x['po_no']) ?> · <?= e(fmt_date($x['po_date'])) ?><?= $x['bill_no'] ? ' · ' . te('bill') . ' ' . e($x['bill_no']) : '' ?></small></div>
        <div class="amt"><?= money($x['total']) ?><?php if ($st !== 'paid'): ?><br><small class="text-danger"><?= money($x['total'] - $x['paid']) ?></small><?php endif; ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$list): ?><p class="text-muted small mt-3 ms-1"><?= te('no_purchase_found') ?></p><?php endif; ?>
  </div>

<?php else:
    $list = rows('SELECT p.*, i.invoice_no, i.job_card_id, COALESCE(c.name, i.customer_name) AS cname FROM payments p JOIN invoices i ON i.id = p.invoice_id
        LEFT JOIN customers c ON c.id = i.customer_id WHERE p.payment_date BETWEEN ? AND ? ORDER BY p.payment_date DESC, p.id DESC', [$from, $to]);
    $byMode = rows('SELECT mode, SUM(amount) s FROM payments WHERE payment_date BETWEEN ? AND ? GROUP BY mode', [$from, $to]); ?>
  <?php if (!$list): ?><p class="mt-2 ms-1"><?= te('no_income_found') ?></p><?php else: ?>
  <div class="seg mt-3 px-1"><?php foreach ($byMode as $m): ?><a><?= te('mode_' . $m['mode']) ?>: <?= money($m['s'], 0) ?></a><?php endforeach; ?></div>
  <?php foreach ($list as $x): ?>
    <div class="ledger-row" data-href="invoice.php?id=<?= $x['invoice_id'] ?>" style="cursor:pointer">
      <div class="l1"><span class="sq paid"></span><?= e($x['cname'] ?: t('walk_in')) ?> <small><?= e($x['invoice_no']) ?> · <?= e(fmt_date($x['payment_date'])) ?> · <?= te('mode_' . $x['mode']) ?><?= $x['reference_no'] ? ' · ' . e($x['reference_no']) : '' ?></small></div>
      <div class="amt text-success"><?= money($x['amount']) ?></div>
    </div>
  <?php endforeach; endif; ?>
<?php endif; ?>
</div>

<div class="net-box">
  <div class="nl">NET</div>
  <table>
    <tr><td><?= te('income_by_payment_date') ?></td><td><?= money($income) ?></td></tr>
    <tr><td><?= te('expense') ?></td><td><?= money($expTotal + $vendorPaid) ?></td></tr>
    <tr><td><?= te('payable') ?></td><td><?= money($payable) ?> (<?= te('credit') ?>)</td></tr>
  </table>
  <div class="nv <?= $net < 0 ? 'text-danger' : '' ?>"><?= money($net) ?></div>
</div>

<div class="modal fade" id="customModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="get">
  <div class="modal-header"><h5 class="modal-title"><?= te('custom_range') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="tab" value="<?= e($t) ?>"><input type="hidden" name="period" value="custom">
    <div class="row g-2"><div class="col-6"><label class="form-label"><?= te('from') ?></label><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
      <div class="col-6"><label class="form-label"><?= te('to') ?></label><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></div></div></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('apply') ?></button></div>
</form></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
