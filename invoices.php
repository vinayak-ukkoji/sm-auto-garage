<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('invoice');
$tab = 'accounts';
$status = one_of(input('status', 'all'), ['all', 'unpaid', 'partial', 'paid', 'cancelled', 'due'], 'all');
$q = (string) input('q');
[$from, $to, $plabel] = period_range((string) input('period', $status === 'due' ? 'this_year' : 'this_month'), (string) input('from'), (string) input('to'));
if ($status === 'due') { $from = '2000-01-01'; $plabel = t('all_time'); }

$where = ['i.invoice_date BETWEEN ? AND ?']; $params = [$from, $to];
if ($status === 'due') $where[] = "i.status IN ('unpaid','partial')";
elseif ($status !== 'all') { $where[] = 'i.status = ?'; $params[] = $status; }
if ($q !== '') { $where[] = '(i.invoice_no LIKE ? OR c.name LIKE ? OR c.mobile LIKE ? OR i.customer_name LIKE ? OR v.reg_no LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", '%' . reg_clean($q) . '%'); }
$list = rows('SELECT i.*, c.name AS cname, c.mobile AS cmobile, v.reg_no FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN job_cards jc ON jc.id = i.job_card_id LEFT JOIN vehicles v ON v.id = jc.vehicle_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY i.invoice_date DESC, i.id DESC LIMIT 300', $params);
$sum = ['total' => 0, 'paid' => 0, 'balance' => 0];
foreach ($list as $r) if ($r['status'] !== 'cancelled') { $sum['total'] += $r['total']; $sum['paid'] += $r['paid']; $sum['balance'] += $r['balance']; }

$title = $status === 'due' ? t('outstanding_dues') : t('invoices');
$back = url('accounts.php');
$header_right = '<a class="hdr-icon" href="' . url('invoice_new.php') . '"><i class="bi bi-plus-lg"></i></a>';
include __DIR__ . '/includes/header.php';
?>
<?php if ($status !== 'due') echo period_tabs((string) input('period', 'this_month'), 'invoices.php?status=' . $status); ?>
<div class="seg">
  <?php foreach (['all', 'due', 'unpaid', 'partial', 'paid', 'cancelled'] as $s): ?>
    <a class="<?= $s === $status ? 'active' : '' ?>" href="<?= url('invoices.php?status=' . $s) ?>"><?= te($s === 'all' ? 'all' : ($s === 'due' ? 'outstanding_dues' : 'inv_' . $s)) ?></a>
  <?php endforeach; ?>
</div>
<form class="search-card" method="get">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <i class="bi bi-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= te('search_invoice_ph') ?>">
</form>
<div class="kpi" style="grid-template-columns:repeat(3,1fr)">
  <div><small><?= te('total') ?></small><b><?= money($sum['total'], 0) ?></b></div>
  <div><small><?= te('received') ?></small><b class="text-success"><?= money($sum['paid'], 0) ?></b></div>
  <div><small><?= te('due') ?></small><b class="text-danger"><?= money($sum['balance'], 0) ?></b></div>
</div>
<?php if (!$list) echo empty_state(t('no_records'), 'bi-receipt'); ?>
<?php foreach ($list as $r): $name = $r['cname'] ?: $r['customer_name']; $mob = $r['cmobile'] ?: $r['customer_mobile']; ?>
  <div class="list-card" data-href="invoice.php?id=<?= $r['id'] ?>" style="cursor:pointer">
    <div class="r"><div><?= money($r['total'], 0) ?></div>
      <?php if ($r['balance'] > 0 && $r['status'] !== 'cancelled'): ?><div class="text-danger small"><?= money($r['balance'], 0) ?> <?= te('due') ?></div><?php endif; ?>
      <span class="sq <?= $r['status'] === 'paid' ? 'paid' : ($r['status'] === 'partial' ? 'partial' : 'credit') ?>"></span><small><?= te('inv_' . $r['status']) ?></small></div>
    <div class="t"><?= e($r['invoice_no']) ?> <small class="text-muted fw-normal"><?= e(fmt_date($r['invoice_date'])) ?></small></div>
    <div class="s"><?= e($name ?: t('walk_in')) ?> <?= $mob ? '· ' . e($mob) : '' ?> <?= $r['reg_no'] ? '· ' . e($r['reg_no']) : '' ?></div>
    <?php if ($status === 'due' && $mob): ?>
      <a class="small" style="color:#128c7e" target="_blank" href="<?= e(wa_link($mob, fill_template((string) setting('wa_template_invoice'), ['customer' => $name, 'reg_no' => $r['reg_no'] ?? '', 'invoice_no' => $r['invoice_no'], 'total' => inr($r['total']), 'balance' => inr($r['balance'])]))) ?>"><i class="bi bi-whatsapp"></i> <?= te('send_payment_reminder') ?></a>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
