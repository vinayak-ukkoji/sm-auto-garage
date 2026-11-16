<?php
/** Reusable HTML fragments + shared list queries. */

const ORDER_FILTERS = [
    'open'      => "jc.status IN ('estimate','approved')",
    'wip'       => "jc.status = 'in_progress'",
    'ready'     => "(jc.status = 'ready' OR (jc.status = 'invoiced' AND i.balance <= 0))",
    'due'       => "(jc.status IN ('invoiced','delivered') AND i.balance > 0)",
    'completed' => "(jc.status = 'delivered' AND (i.balance IS NULL OR i.balance <= 0))",
    'cancelled' => "jc.status = 'cancelled'",
    'all'       => '1=1',
];

function order_filter_label(string $f): string
{
    return ['open' => t('open_orders'), 'wip' => t('wip_orders'), 'ready' => t('ready_orders'), 'due' => t('payment_due'),
            'completed' => t('completed_orders'), 'cancelled' => t('st_cancelled'), 'all' => t('all_orders')][$f] ?? $f;
}

const ORDER_SELECT = "SELECT jc.*, c.name AS cname, c.mobile, v.reg_no, v.make, v.model, v.variant, v.fuel_type,
       u.name AS advisor_name, tu.name AS tech_name, i.id AS inv_id, i.invoice_no, i.total AS inv_total, i.paid AS inv_paid, i.balance AS inv_balance
  FROM job_cards jc
  JOIN customers c ON c.id = jc.customer_id
  JOIN vehicles v ON v.id = jc.vehicle_id
  LEFT JOIN users u ON u.id = jc.advisor_id
  LEFT JOIN users tu ON tu.id = jc.technician_id
  LEFT JOIN invoices i ON i.job_card_id = jc.id";

/** Returns [rows, total] for the orders list. */
function query_orders(string $filter, string $search, int $offset = 0, int $limit = 30, array $extra = []): array
{
    $where = [ORDER_FILTERS[$filter] ?? '1=1'];
    $params = [];
    if (is_tech()) { $where[] = 'jc.technician_id = ?'; $params[] = uid(); }
    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(c.name LIKE ? OR c.mobile LIKE ? OR v.reg_no LIKE ? OR jc.jc_no LIKE ? OR i.invoice_no LIKE ? OR c.email LIKE ?)';
        $reg = '%' . reg_clean($search) . '%';
        array_push($params, $like, $like, $reg ?: $like, $like, $like, $like);
    }
    if (!empty($extra['from'])) { $where[] = 'DATE(jc.created_at) >= ?'; $params[] = $extra['from']; }
    if (!empty($extra['to']))   { $where[] = 'DATE(jc.created_at) <= ?'; $params[] = $extra['to']; }
    if (!empty($extra['customer_id'])) { $where[] = 'jc.customer_id = ?'; $params[] = $extra['customer_id']; }
    if (!empty($extra['vehicle_id']))  { $where[] = 'jc.vehicle_id = ?';  $params[] = $extra['vehicle_id']; }
    $w = implode(' AND ', $where);
    $total = (int) val("SELECT COUNT(*) FROM job_cards jc JOIN customers c ON c.id = jc.customer_id JOIN vehicles v ON v.id = jc.vehicle_id LEFT JOIN invoices i ON i.job_card_id = jc.id WHERE $w", $params);
    $rows = rows(ORDER_SELECT . " WHERE $w ORDER BY jc.created_at DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset, $params);
    return [$rows, $total];
}

function vehicle_name(array $r): string
{
    return trim(($r['make'] ?? '') . ' ' . ($r['model'] ?? '') . ' ' . ($r['variant'] ?? '') . ' ' . strtoupper((string) ($r['fuel_type'] ?? '')));
}

function order_wa_text(array $r): string
{
    $amt = $r['inv_id'] ? $r['inv_total'] : $r['grand_total'];
    $map = [
        'estimate'    => "Dear {$r['cname']}, estimate {$r['jc_no']} for {$r['reg_no']} is Rs. " . inr($amt) . '. Please confirm to proceed.',
        'approved'    => "Dear {$r['cname']}, work on your vehicle {$r['reg_no']} ({$r['jc_no']}) has been approved and scheduled.",
        'in_progress' => "Dear {$r['cname']}, work on your vehicle {$r['reg_no']} is in progress.",
        'ready'       => fill_template((string) setting('wa_template_ready'), ['customer' => $r['cname'], 'reg_no' => $r['reg_no'], 'total' => inr($amt)]),
    ];
    if (isset($map[$r['status']])) {
        $msg = $map[$r['status']] . ' - ' . setting('garage_name');
        if ($r['status'] === 'estimate') $msg .= "\n" . public_doc_url('jc', (int) $r['id']);
        return $msg;
    }
    $msg = fill_template((string) setting('wa_template_invoice'), ['customer' => $r['cname'], 'reg_no' => $r['reg_no'],
        'invoice_no' => $r['invoice_no'] ?? $r['jc_no'], 'total' => inr($amt), 'balance' => inr($r['inv_balance'] ?? 0)]);
    return $r['inv_id'] ? $msg . "\n" . public_doc_url('inv', (int) $r['inv_id']) : $msg;
}

function order_card(array $r): string
{
    [$badge, $cls] = order_badge($r);
    $amt = $r['inv_id'] ? $r['inv_total'] : $r['grand_total'];
    $due = $r['inv_id'] ? (float) $r['inv_balance'] : null;
    $prices = can('prices');
    ob_start(); ?>
<div class="order-card">
  <div class="oc-body" data-href="jobcard.php?id=<?= (int) $r['id'] ?>">
    <div class="oc-left">
      <?php if ($prices): ?>
        <div class="oc-amt"><?= money($amt, 0) ?></div>
        <?php if ($due !== null && $due > 0): ?><div class="oc-due"><?= money($due, 0) ?> <?= te('due') ?></div><?php endif; ?>
      <?php endif; ?>
      <div class="oc-status <?= $cls ?>"><?= e($badge) ?></div>
    </div>
    <div class="oc-right">
      <div class="oc-no">#<?= e(ltrim(preg_replace('/\D/', '', $r['jc_no']), '0') ?: $r['jc_no']) ?></div>
      <div class="dropdown position-absolute" style="right:4px;top:4px">
        <button class="oc-dots" data-bs-toggle="dropdown" type="button"><i class="bi bi-three-dots-vertical"></i></button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= url('jobcard.php?id=' . $r['id']) ?>"><i class="bi bi-eye me-2"></i><?= te('view_details') ?></a></li>
          <?php if (can('prices') && in_array($r['status'], ['estimate', 'approved', 'in_progress', 'ready'], true)): ?>
          <li><a class="dropdown-item" href="<?= url('jobcard_edit.php?id=' . $r['id']) ?>"><i class="bi bi-pencil me-2"></i><?= te('edit_order') ?></a></li>
          <?php endif; ?>
          <li><a class="dropdown-item" target="_blank" href="<?= url('print/jobcard.php?id=' . $r['id']) ?>"><i class="bi bi-printer me-2"></i><?= te('print_job_card') ?></a></li>
        </ul>
      </div>
      <div class="oc-ago"><?= e(time_ago($r['created_at'])) ?></div>
      <div class="oc-name"><?= e($r['cname']) ?></div>
      <div class="oc-mob"><?= e($r['mobile']) ?></div>
      <div class="oc-veh"><?= e(vehicle_name($r)) ?></div>
      <div class="oc-reg"><?= e($r['reg_no']) ?></div>
      <div class="oc-links">
        <a href="<?= url('print/jobcard.php?id=' . $r['id']) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> <?= te('repair_order') ?></a>
        <?php if ($r['inv_id'] && $prices): ?><a href="<?= url('print/invoice.php?id=' . $r['inv_id']) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> <?= te('invoice') ?></a><?php endif; ?>
        <a class="wa" href="<?= e(wa_link($r['mobile'], order_wa_text($r))) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
      </div>
    </div>
  </div>
  <div class="oc-foot">
    <div><?= te('invoice') ?><b><?= e($r['invoice_no'] ?: '-') ?></b></div>
    <div><?= te('created_by') ?><b><?= e($r['advisor_name'] ?: '-') ?></b></div>
    <div><?= $r['delivered_at'] ? te('completion_date') : te('expected_delivery') ?><b><?= e(fmt_date($r['delivered_at'] ?: $r['expected_delivery'])) ?></b></div>
  </div>
</div>
<?php
    return ob_get_clean();
}

function stars(?int $n): string
{
    $n = (int) $n;
    $h = '<span class="stars">';
    for ($i = 1; $i <= 5; $i++) $h .= '<i class="bi bi-star-fill ' . ($i <= $n ? '' : 'off') . '"></i>';
    return $h . '</span>';
}

function empty_state(string $msg, string $icon = 'bi-cart-fill'): string
{
    return '<div class="empty-state"><i class="bi ' . $icon . '"></i><p>' . e($msg) . '</p></div>';
}

function mode_options(string $sel = 'cash'): string
{
    $h = '';
    foreach (PAY_MODES as $m) $h .= '<option value="' . $m . '"' . ($m === $sel ? ' selected' : '') . '>' . e(t('mode_' . $m)) . '</option>';
    return $h;
}

function gst_options($sel = 18): string
{
    $h = '';
    foreach (GST_RATES as $g) $h .= '<option value="' . $g . '"' . ((int) $sel === $g ? ' selected' : '') . '>' . $g . '%</option>';
    return $h;
}

function period_tabs(string $current, string $baseUrl): string
{
    $h = '<div class="seg">';
    foreach (['today', 'this_week', 'this_month', 'last_month', 'this_year'] as $p) {
        $sep = str_contains($baseUrl, '?') ? '&' : '?';
        $h .= '<a class="' . ($p === $current ? 'active' : '') . '" href="' . e(url($baseUrl . $sep . 'period=' . $p)) . '">' . e(t($p === 'this_year' ? 'this_fy' : $p)) . '</a>';
    }
    return $h . '</div>';
}
