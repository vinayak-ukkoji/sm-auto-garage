<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('reports');
$tab = 'more';
$r = one_of(input('r', 'summary'), ['summary', 'daily', 'monthly', 'parts', 'expenses', 'gst', 'technicians'], 'summary');
$period = one_of(input('period', 'this_month'), ['today', 'yesterday', 'this_week', 'this_month', 'last_month', 'this_year', 'custom'], 'this_month');
[$from, $to, $plabel] = period_range($period, (string) input('from'), (string) input('to'));
$P = [$from, $to];
$INV = "i.status <> 'cancelled' AND i.invoice_date BETWEEN ? AND ?";

$table = null; // [headers, rows] for table reports (also used for CSV)
switch ($r) {
    case 'daily':
    case 'monthly':
        $fmt = $r === 'daily' ? '%Y-%m-%d' : '%Y-%m';
        $data = [];
        foreach (rows("SELECT DATE_FORMAT(invoice_date,'$fmt') k, COUNT(*) n, SUM(total) rev, SUM(subtotal) taxable FROM invoices i WHERE $INV GROUP BY k", $P) as $x) $data[$x['k']] = ['inv' => $x['n'], 'rev' => $x['rev'], 'taxable' => $x['taxable']];
        foreach (rows("SELECT DATE_FORMAT(payment_date,'$fmt') k, SUM(amount) s FROM payments WHERE payment_date BETWEEN ? AND ? GROUP BY k", $P) as $x) $data[$x['k']]['col'] = $x['s'];
        foreach (rows("SELECT DATE_FORMAT(expense_date,'$fmt') k, SUM(amount) s FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY k", $P) as $x) $data[$x['k']]['exp'] = $x['s'];
        foreach (rows("SELECT DATE_FORMAT(created_at,'$fmt') k, COUNT(*) n FROM job_cards WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY k", $P) as $x) $data[$x['k']]['jobs'] = $x['n'];
        krsort($data);
        $rowsOut = [];
        foreach ($data as $k => $d) $rowsOut[] = [$r === 'daily' ? fmt_date($k) : date('M Y', strtotime($k . '-01')), (int) ($d['jobs'] ?? 0), (int) ($d['inv'] ?? 0), round((float) ($d['rev'] ?? 0), 2), round((float) ($d['col'] ?? 0), 2), round((float) ($d['exp'] ?? 0), 2)];
        $table = [[t('date'), t('jobs'), t('invoices'), t('revenue'), t('collected'), t('expenses')], $rowsOut, [3, 4, 5]];
        break;
    case 'parts':
        $rowsOut = array_map(fn($x) => [$x['description'], $x['part_no'], qty($x['q']), round((float) $x['v'], 2), round((float) $x['cost'], 2), round($x['v'] - $x['cost'], 2)],
            rows("SELECT ii.description, p.part_no, SUM(ii.qty) q, SUM(ii.taxable) v, SUM(ii.qty * p.purchase_price) cost FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
                  JOIN parts p ON p.id = ii.part_id WHERE ii.item_type = 'part' AND $INV GROUP BY ii.part_id, ii.description, p.part_no ORDER BY v DESC", $P));
        $table = [[t('part'), t('part_no'), t('qty'), t('sales'), t('cost'), t('margin')], $rowsOut, [3, 4, 5]];
        break;
    case 'expenses':
        $rowsOut = array_map(fn($x) => [$x['name'], (int) $x['n'], round((float) $x['s'], 2)],
            rows('SELECT c.name, COUNT(*) n, SUM(e.amount) s FROM expenses e JOIN expense_categories c ON c.id = e.category_id WHERE e.expense_date BETWEEN ? AND ? GROUP BY c.id, c.name ORDER BY s DESC', $P));
        $table = [[t('category'), t('entries'), t('amount')], $rowsOut, [2]];
        break;
    case 'gst':
        $rowsOut = array_map(fn($x) => [qty($x['gst_rate']) . '%', round((float) $x['taxable'], 2), round((float) ($x['igst'] ? 0 : $x['tax'] / 2), 2), round((float) ($x['igst'] ? 0 : $x['tax'] / 2), 2), round((float) ($x['igst'] ? $x['tax'] : 0), 2), round((float) $x['tax'], 2)],
            rows("SELECT ii.gst_rate, i.is_igst AS igst, SUM(ii.taxable) taxable, SUM(ii.tax) tax FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE $INV GROUP BY ii.gst_rate, i.is_igst ORDER BY ii.gst_rate", $P));
        $table = [['GST', t('taxable'), 'CGST', 'SGST', 'IGST', t('total_tax')], $rowsOut, [1, 2, 3, 4, 5]];
        break;
    case 'technicians':
        $rowsOut = array_map(fn($x) => [$x['name'] ?: t('not_assigned'), (int) $x['jobs'], (int) $x['done'], round((float) $x['labour'], 2)],
            rows("SELECT u.name, COUNT(jc.id) jobs, SUM(jc.status IN ('ready','invoiced','delivered')) done, SUM(jc.labour_total) labour FROM job_cards jc LEFT JOIN users u ON u.id = jc.technician_id
                  WHERE jc.status <> 'cancelled' AND DATE(jc.created_at) BETWEEN ? AND ? GROUP BY jc.technician_id, u.name ORDER BY jobs DESC", $P));
        $table = [[t('technician'), t('jobs'), t('completed'), t('labour_value')], $rowsOut, [3]];
        break;
}
if ($table && input('export') === '1') csv_download($r . '_' . $from . '_' . $to . '.csv', $table[0], $table[1]);

$title = t('reports');
$back = url('more.php');
$header_right = '<button class="hdr-icon" onclick="window.print()"><i class="bi bi-printer"></i></button>';
include __DIR__ . '/includes/header.php';
$qs = fn($extra = []) => url('reports.php?' . http_build_query(array_merge(['r' => $r, 'period' => $period, 'from' => $from, 'to' => $to], $extra)));
?>
<div class="seg no-print">
  <?php foreach (['summary', 'daily', 'monthly', 'parts', 'expenses', 'gst', 'technicians'] as $k): ?><a class="<?= $k === $r ? 'active' : '' ?>" href="<?= e($qs(['r' => $k])) ?>"><?= te('rep_' . $k) ?></a><?php endforeach; ?>
</div>
<form class="d-flex gap-2 mb-2 no-print align-items-end" method="get">
  <input type="hidden" name="r" value="<?= e($r) ?>">
  <select name="period" class="form-select form-select-sm" style="max-width:150px" onchange="this.form.querySelectorAll('.cust').forEach(x=>x.style.display=this.value==='custom'?'':'none'); if(this.value!=='custom') this.form.submit()">
    <?php foreach (['today', 'yesterday', 'this_week', 'this_month', 'last_month', 'this_year', 'custom'] as $p): ?><option value="<?= $p ?>" <?= $p === $period ? 'selected' : '' ?>><?= te($p === 'this_year' ? 'this_fy' : ($p === 'custom' ? 'custom_range' : $p)) ?></option><?php endforeach; ?>
  </select>
  <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm cust" style="<?= $period === 'custom' ? '' : 'display:none' ?>">
  <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm cust" style="<?= $period === 'custom' ? '' : 'display:none' ?>">
  <button class="btn btn-sm btn-primary cust" style="<?= $period === 'custom' ? '' : 'display:none' ?>"><?= te('apply') ?></button>
</form>
<div class="filter-pill-wrap"><span class="filter-pill" style="font-size:11px"><?= e(fmt_date($from)) ?> – <?= e(fmt_date($to)) ?></span></div>

<?php if ($r === 'summary'):
    $inv = row("SELECT COUNT(*) n, COALESCE(SUM(total),0) rev, COALESCE(SUM(subtotal),0) taxable, COALESCE(SUM(cgst+sgst+igst),0) gst, COALESCE(SUM(balance),0) due FROM invoices i WHERE $INV", $P);
    $lab = (float) val("SELECT COALESCE(SUM(ii.taxable),0) FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE ii.item_type = 'labour' AND $INV", $P);
    $prt = row("SELECT COALESCE(SUM(ii.taxable),0) v, COALESCE(SUM(ii.qty * p.purchase_price),0) cost FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id JOIN parts p ON p.id = ii.part_id WHERE ii.item_type = 'part' AND $INV", $P);
    $col = (float) val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date BETWEEN ? AND ?', $P);
    $exp = (float) val('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?', $P);
    $pur = (float) val("SELECT COALESCE(SUM(total),0) FROM purchase_orders WHERE status = 'received' AND po_date BETWEEN ? AND ?", $P);
    $jobs = rows("SELECT status, COUNT(*) n FROM job_cards WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status", $P);
    $gross = $lab + $prt['v'] - $prt['cost'];
    $net = $gross - $exp; ?>
  <div class="kpi">
    <div><small><?= te('revenue') ?> (<?= (int) $inv['n'] ?> <?= te('invoices') ?>)</small><b><?= money($inv['rev'], 0) ?></b></div>
    <div><small><?= te('collected') ?></small><b class="text-success"><?= money($col, 0) ?></b></div>
    <div><small><?= te('labour_revenue') ?></small><b><?= money($lab, 0) ?></b></div>
    <div><small><?= te('parts_sales') ?></small><b><?= money($prt['v'], 0) ?></b></div>
    <div><small><?= te('gst_collected') ?></small><b><?= money($inv['gst'], 0) ?></b></div>
    <div><small><?= te('expenses') ?></small><b class="text-danger"><?= money($exp, 0) ?></b></div>
    <div><small><?= te('part_purchase') ?></small><b><?= money($pur, 0) ?></b></div>
    <div><small><?= te('outstanding_in_period') ?></small><b class="text-danger"><?= money($inv['due'], 0) ?></b></div>
  </div>
  <?php if (is_admin()): ?>
  <div class="box">
    <h6><?= te('profit') ?></h6>
    <div class="kv"><span><?= te('labour_revenue') ?></span><span><?= money($lab) ?></span></div>
    <div class="kv"><span><?= te('parts_sales') ?></span><span><?= money($prt['v']) ?></span></div>
    <div class="kv"><span>(−) <?= te('parts_cost') ?> <small>*</small></span><span><?= money($prt['cost']) ?></span></div>
    <div class="kv"><span><?= te('gross_profit') ?></span><b><?= money($gross) ?></b></div>
    <div class="kv"><span>(−) <?= te('expenses') ?></span><span><?= money($exp) ?></span></div>
    <div class="kv"><span><?= te('net_profit') ?></span><b class="<?= $net < 0 ? 'text-danger' : 'text-success' ?>"><?= money($net) ?></b></div>
    <small class="text-muted">* <?= te('parts_cost_note') ?></small>
  </div>
  <?php endif; ?>
  <div class="box"><h6><?= te('job_cards') ?></h6>
    <?php $tot = 0; foreach ($jobs as $j): $tot += $j['n']; ?><div class="kv"><span><span class="badge-st <?= jc_class($j['status']) ?>"><?= e(jc_label($j['status'])) ?></span></span><span><?= (int) $j['n'] ?></span></div><?php endforeach; ?>
    <div class="kv"><span><?= te('total') ?></span><b><?= $tot ?></b></div>
  </div>
<?php elseif ($table): [$heads, $data, $numCols] = $table; $max = 0; foreach ($data as $d) $max = max($max, (float) ($d[$numCols[0]] ?? 0)); ?>
  <div class="text-end mb-2 no-print"><a class="btn btn-sm btn-outline-primary" href="<?= e($qs(['export' => 1])) ?>"><i class="bi bi-download"></i> <?= te('export_csv') ?></a></div>
  <div class="box p-0" style="overflow-x:auto">
    <table class="table-x">
      <tr><?php foreach ($heads as $i => $h): ?><th class="<?= in_array($i, $numCols, true) ? 'text-end' : '' ?>"><?= e($h) ?></th><?php endforeach; ?></tr>
      <?php $sums = []; foreach ($data as $d): ?>
      <tr><?php foreach ($d as $i => $cell): if (in_array($i, $numCols, true)) $sums[$i] = ($sums[$i] ?? 0) + $cell; ?>
        <td class="<?= in_array($i, $numCols, true) ? 'text-end' : '' ?>"><?= in_array($i, $numCols, true) ? inr($cell) : e($cell) ?>
          <?php if ($i === $numCols[0] && $max > 0 && in_array($r, ['daily', 'monthly', 'parts'], true)): ?><div style="height:3px;background:#12926f;width:<?= round($cell / $max * 100) ?>%;margin-left:auto"></div><?php endif; ?></td>
      <?php endforeach; ?></tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="<?= count($heads) ?>" class="text-center text-muted py-4"><?= te('no_records') ?></td></tr>
      <?php else: ?><tr class="fw-bold"><?php foreach ($heads as $i => $h): ?><td class="text-end"><?= $i === 0 ? te('total') : (isset($sums[$i]) ? inr($sums[$i]) : '') ?></td><?php endforeach; ?></tr><?php endif; ?>
    </table>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
