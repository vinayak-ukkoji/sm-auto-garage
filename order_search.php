<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('search');
$tab = 'more';
$q = trim((string) input('q'));
$type = one_of(input('type', 'orders'), ['orders', 'invoices', 'po'], 'orders');
$status = one_of(input('status', 'all'), array_keys(ORDER_FILTERS), 'all');
$from = valid_date((string) input('from')); $to = valid_date((string) input('to'));
$searched = $q !== '' || $from || $to || isset($_GET['type']);

$title = t('search_orders_by');
$back = url('more.php');
include __DIR__ . '/includes/header.php';
?>
<form method="get" class="box">
  <div class="seg mb-2">
    <?php foreach (['orders' => 'repair_orders', 'invoices' => 'invoices', 'po' => 'purchase_orders'] as $k => $lbl): ?>
      <label class="m-0"><input type="radio" name="type" value="<?= $k ?>" class="d-none" <?= $type === $k ? 'checked' : '' ?> onchange="this.form.submit()">
        <a class="<?= $type === $k ? 'active' : '' ?>" style="cursor:pointer" onclick="this.previousElementSibling.click()"><?= te($lbl) ?></a></label>
    <?php endforeach; ?>
  </div>
  <input type="search" name="q" value="<?= e($q) ?>" class="form-control mb-2" placeholder="<?= te('order_search_ph') ?>">
  <div class="row g-2">
    <div class="col-6"><label class="form-label"><?= te('from') ?></label><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
    <div class="col-6"><label class="form-label"><?= te('to') ?></label><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></div>
    <?php if ($type === 'orders'): ?>
    <div class="col-12"><label class="form-label"><?= te('status') ?></label><select name="status" class="form-select">
      <?php foreach (ORDER_FILTERS as $k => $_): ?><option value="<?= $k ?>" <?= $k === $status ? 'selected' : '' ?>><?= e(order_filter_label($k)) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
  </div>
  <button class="btn btn-primary w-100 mt-3"><i class="bi bi-search"></i> <?= te('search') ?></button>
</form>
<?php
if ($searched) {
    if ($type === 'orders') {
        [$list, $total] = query_orders($status, $q, 0, 100, ['from' => $from, 'to' => $to]);
        echo '<div class="small text-muted mb-2 ms-1">' . $total . ' ' . te('results') . '</div>';
        if (!$list) echo empty_state(t('no_orders_found'));
        foreach ($list as $r) echo order_card($r);
    } elseif ($type === 'invoices' && can('invoice')) {
        $w = ['1=1']; $p = [];
        if ($q !== '') { $w[] = '(i.invoice_no LIKE ? OR c.name LIKE ? OR c.mobile LIKE ? OR i.customer_name LIKE ? OR i.customer_mobile LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
        if ($from) { $w[] = 'i.invoice_date >= ?'; $p[] = $from; }
        if ($to) { $w[] = 'i.invoice_date <= ?'; $p[] = $to; }
        $list = rows('SELECT i.*, COALESCE(c.name, i.customer_name) AS cname FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id WHERE ' . implode(' AND ', $w) . ' ORDER BY i.id DESC LIMIT 100', $p);
        if (!$list) echo empty_state(t('no_records'), 'bi-receipt');
        foreach ($list as $r) echo '<a class="list-card" href="' . url('invoice.php?id=' . $r['id']) . '"><div class="r">' . money($r['total'], 0) . '<div class="small">' . te('inv_' . $r['status']) . '</div></div><div class="t">' . e($r['invoice_no']) . '</div><div class="s">' . e($r['cname']) . ' · ' . e(fmt_date($r['invoice_date'])) . '</div></a>';
    } elseif ($type === 'po' && can('parts')) {
        $w = ['1=1']; $p = [];
        if ($q !== '') { $w[] = '(po.po_no LIKE ? OR v.name LIKE ? OR po.bill_no LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
        if ($from) { $w[] = 'po.po_date >= ?'; $p[] = $from; }
        if ($to) { $w[] = 'po.po_date <= ?'; $p[] = $to; }
        $list = rows('SELECT po.*, v.name AS vname FROM purchase_orders po JOIN vendors v ON v.id = po.vendor_id WHERE ' . implode(' AND ', $w) . ' ORDER BY po.id DESC LIMIT 100', $p);
        if (!$list) echo empty_state(t('no_records'), 'bi-bag');
        foreach ($list as $r) echo '<a class="list-card" href="' . url('po.php?id=' . $r['id']) . '"><div class="r">' . money($r['total'], 0) . '<div class="small">' . te('po_' . $r['status']) . '</div></div><div class="t">' . e($r['po_no']) . '</div><div class="s">' . e($r['vname']) . ' · ' . e(fmt_date($r['po_date'])) . '</div></a>';
    }
}
include __DIR__ . '/includes/footer.php';
