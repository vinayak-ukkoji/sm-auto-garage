<?php
/** Business rules: numbering, job cards, invoices, stock, purchases, reminders. */

const GST_RATES = [0, 5, 12, 18, 28];
const PAY_MODES = ['cash', 'upi', 'card', 'bank', 'cheque'];

const GST_STATES = ['01' => 'Jammu & Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh', '05' => 'Uttarakhand', '06' => 'Haryana',
    '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh', '13' => 'Nagaland',
    '14' => 'Manipur', '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal', '20' => 'Jharkhand',
    '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat', '26' => 'Dadra & Nagar Haveli and Daman & Diu', '27' => 'Maharashtra',
    '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry', '35' => 'Andaman & Nicobar',
    '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh'];

const JC_STATUSES = [
    'estimate'    => ['label' => 'Created',       'class' => 'st-created'],
    'approved'    => ['label' => 'Approved',      'class' => 'st-approved'],
    'in_progress' => ['label' => 'In Progress',   'class' => 'st-wip'],
    'ready'       => ['label' => 'Vehicle Ready', 'class' => 'st-ready'],
    'invoiced'    => ['label' => 'Invoiced',      'class' => 'st-invoiced'],
    'delivered'   => ['label' => 'Delivered',     'class' => 'st-delivered'],
    'cancelled'   => ['label' => 'Cancelled',     'class' => 'st-cancelled'],
];

/** Manual status moves allowed from the job card screen. */
const JC_MOVES = [
    'estimate'    => ['approved', 'cancelled'],
    'approved'    => ['in_progress', 'estimate', 'cancelled'],
    'in_progress' => ['ready', 'approved'],
    'ready'       => ['in_progress'],
];

function jc_label(string $s): string { return t('st_' . $s); }
function jc_class(string $s): string { return JC_STATUSES[$s]['class'] ?? ''; }

/** Badge text shown on order cards (payment aware). */
function order_badge(array $jc): array
{
    if ($jc['status'] === 'invoiced' || $jc['status'] === 'delivered') {
        $bal = (float) ($jc['inv_balance'] ?? 0);
        if ($bal > 0.009) return [t('payment_due'), 'st-due'];
        if ($jc['status'] === 'invoiced') return [t('payment_done'), 'st-paid'];
        return [t('st_delivered'), 'st-delivered'];
    }
    return [jc_label($jc['status']), jc_class($jc['status'])];
}

/** Next document number. MUST be called inside a transaction. */
function next_number(string $type): string
{
    $map = ['invoice' => ['invoice_prefix', 'invoice_next'], 'jobcard' => ['jobcard_prefix', 'jobcard_next'], 'po' => ['po_prefix', 'po_next']];
    [$p, $n] = $map[$type];
    $s = row("SELECT $p AS prefix, $n AS num FROM settings WHERE id = 1 FOR UPDATE");
    q("UPDATE settings SET $n = $n + 1 WHERE id = 1");
    return $s['prefix'] . str_pad((string) $s['num'], 4, '0', STR_PAD_LEFT);
}

function log_status(int $jcId, string $status, string $note = ''): void
{
    db_insert('job_card_status_log', ['job_card_id' => $jcId, 'status' => $status, 'user_id' => uid(), 'note' => nullable($note)]);
}

// ---------------------------------------------------------------- Lines

/** Validate line arrays coming from the browser (JSON). */
function clean_lines($raw, string $kind): array
{
    if (is_string($raw)) $raw = json_decode($raw, true);
    if (!is_array($raw)) return [];
    $out = [];
    foreach ($raw as $l) {
        $desc = trim((string) ($l['description'] ?? ''));
        $qty  = (float) ($l['qty'] ?? 0);
        $rate = (float) ($l['rate'] ?? 0);
        $disc = (float) ($l['discount'] ?? 0);
        $gst  = (float) ($l['gst_rate'] ?? 18);
        if ($desc === '' && empty($l['part_id'])) continue;
        if ($qty <= 0)  throw new ValidationError(t('qty_positive') . ': ' . $desc);
        if ($rate < 0)  throw new ValidationError(t('rate_invalid') . ': ' . $desc);
        if ($disc < 0 || $disc > $qty * $rate + 0.001) throw new ValidationError(t('discount_invalid') . ': ' . $desc);
        if (!in_array((int) $gst, GST_RATES, true)) $gst = 18;
        $line = ['description' => mb_substr($desc, 0, 200), 'qty' => round($qty, 2), 'rate' => round($rate, 2),
                 'discount' => round($disc, 2), 'gst_rate' => $gst, 'amount' => round($qty * $rate - $disc, 2),
                 'is_done' => !empty($l['is_done']) ? 1 : 0];
        if ($kind === 'parts') {
            $pid = (int) ($l['part_id'] ?? 0);
            $p = $pid ? row('SELECT id, name FROM parts WHERE id = ?', [$pid]) : null;
            if (!$p) throw new ValidationError(t('select_valid_part') . ': ' . $desc);
            $line['part_id'] = $pid;
            if ($line['description'] === '') $line['description'] = $p['name'];
        } else {
            $line['labour_rate_id'] = !empty($l['labour_rate_id']) ? (int) $l['labour_rate_id'] : null;
        }
        $out[] = $line;
    }
    return $out;
}

function save_jobcard_lines(int $jcId, array $labour, array $parts): void
{
    if (!gst_on()) {   // GST disabled: new / edited lines carry no tax
        foreach ($labour as &$l) $l['gst_rate'] = 0;
        foreach ($parts as &$p) $p['gst_rate'] = 0;
        unset($l, $p);
    }
    q('DELETE FROM job_card_labour WHERE job_card_id = ?', [$jcId]);
    q('DELETE FROM job_card_parts WHERE job_card_id = ?', [$jcId]);
    foreach ($labour as $l) db_insert('job_card_labour', ['job_card_id' => $jcId] + $l);
    foreach ($parts as $p)  db_insert('job_card_parts',  ['job_card_id' => $jcId] + $p);
    recalc_jobcard($jcId);
}

function recalc_jobcard(int $jcId): void
{
    $l = row('SELECT COALESCE(SUM(amount),0) amt, COALESCE(SUM(ROUND(amount*gst_rate/100,2)),0) tx, COALESCE(SUM(discount),0) d FROM job_card_labour WHERE job_card_id = ?', [$jcId]);
    $p = row('SELECT COALESCE(SUM(amount),0) amt, COALESCE(SUM(ROUND(amount*gst_rate/100,2)),0) tx, COALESCE(SUM(discount),0) d FROM job_card_parts WHERE job_card_id = ?', [$jcId]);
    $tax = $l['tx'] + $p['tx'];
    db_update('job_cards', [
        'labour_total' => $l['amt'], 'parts_total' => $p['amt'], 'tax_total' => $tax,
        'discount' => $l['d'] + $p['d'], 'grand_total' => round($l['amt'] + $p['amt'] + $tax),
    ], 'id = ?', [$jcId]);
}

// ---------------------------------------------------------------- Stock

function stock_move(int $partId, string $type, float $qty, float $rate, string $refType, ?int $refId, string $note = ''): void
{
    db_insert('stock_movements', ['part_id' => $partId, 'type' => $type, 'qty' => $qty, 'rate' => $rate,
        'ref_type' => $refType, 'ref_id' => $refId, 'note' => nullable($note), 'user_id' => uid()]);
    $delta = $type === 'out' ? -$qty : $qty;
    q('UPDATE parts SET stock = stock + ? WHERE id = ?', [$delta, $partId]);
}

// ---------------------------------------------------------------- Invoices

function is_igst_for(?int $customerId): bool
{
    if (!$customerId || !gst_on()) return false;
    $g = (string) val('SELECT gstin FROM customers WHERE id = ?', [$customerId]);
    return $g !== '' && substr($g, 0, 2) !== (string) setting('state_code');
}

function insert_invoice_item(int $invId, array $i): void
{
    if (!gst_on()) $i['gst_rate'] = 0;   // invoices are only created while the current setting applies
    $tax = round($i['taxable'] * $i['gst_rate'] / 100, 2);
    db_insert('invoice_items', $i + ['invoice_id' => $invId, 'tax' => $tax, 'amount' => $i['taxable'] + $tax]);
}

function recalc_invoice(int $invId): void
{
    $inv = row('SELECT is_igst FROM invoices WHERE id = ?', [$invId]);
    $x = row('SELECT COALESCE(SUM(taxable),0) st, COALESCE(SUM(tax),0) tx, COALESCE(SUM(discount),0) d FROM invoice_items WHERE invoice_id = ?', [$invId]);
    $gross = $x['st'] + $x['tx'];
    $total = round($gross);
    $cg = $inv['is_igst'] ? 0 : round($x['tx'] / 2, 2);
    db_update('invoices', [
        'subtotal' => $x['st'], 'discount' => $x['d'],
        'cgst' => $cg, 'sgst' => $inv['is_igst'] ? 0 : $x['tx'] - $cg, 'igst' => $inv['is_igst'] ? $x['tx'] : 0,
        'total' => $total, 'round_off' => round($total - $gross, 2),
    ], 'id = ?', [$invId]);
    recalc_invoice_payments($invId);
}

function recalc_invoice_payments(int $invId): void
{
    $inv = row('SELECT total, status FROM invoices WHERE id = ?', [$invId]);
    $paid = (float) val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ?', [$invId]);
    $status = $inv['status'] === 'cancelled' ? 'cancelled'
        : ($paid >= $inv['total'] - 0.009 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'));
    db_update('invoices', ['paid' => $paid, 'balance' => round($inv['total'] - $paid, 2), 'status' => $status], 'id = ?', [$invId]);
}

function create_invoice_from_jobcard(int $jcId): int
{
    return tx(function () use ($jcId) {
        $jc = row('SELECT * FROM job_cards WHERE id = ? FOR UPDATE', [$jcId]);
        if (!$jc) throw new ValidationError(t('not_found'));
        if (in_array($jc['status'], ['invoiced', 'delivered', 'cancelled'], true)) throw new ValidationError(t('already_invoiced'));
        if ((int) val('SELECT COUNT(*) FROM invoices WHERE job_card_id = ?', [$jcId]) > 0) throw new ValidationError(t('already_invoiced'));
        if (!gst_on()) {   // open order invoiced after GST was disabled: drop its tax first
            q('UPDATE job_card_labour SET gst_rate = 0 WHERE job_card_id = ?', [$jcId]);
            q('UPDATE job_card_parts SET gst_rate = 0 WHERE job_card_id = ?', [$jcId]);
            recalc_jobcard($jcId);
        }
        $labour = rows('SELECT * FROM job_card_labour WHERE job_card_id = ?', [$jcId]);
        $parts  = rows('SELECT jp.*, p.hsn FROM job_card_parts jp JOIN parts p ON p.id = jp.part_id WHERE jp.job_card_id = ?', [$jcId]);
        if (!$labour && !$parts) throw new ValidationError(t('add_lines_first'));

        $no = next_number('invoice');
        $igst = is_igst_for((int) $jc['customer_id']);
        $invId = db_insert('invoices', [
            'invoice_no' => $no, 'type' => 'job', 'job_card_id' => $jcId, 'customer_id' => $jc['customer_id'],
            'invoice_date' => date('Y-m-d'), 'place_of_supply' => setting('state_code'), 'is_igst' => $igst ? 1 : 0,
            'gst_enabled' => gst_on() ? 1 : 0, 'created_by' => uid(),
        ]);
        foreach ($labour as $l) {
            insert_invoice_item($invId, ['item_type' => 'labour', 'part_id' => null, 'description' => $l['description'],
                'hsn_sac' => '998714', 'qty' => $l['qty'], 'rate' => $l['rate'], 'discount' => $l['discount'],
                'gst_rate' => $l['gst_rate'], 'taxable' => $l['amount']]);
        }
        foreach ($parts as $p) {
            insert_invoice_item($invId, ['item_type' => 'part', 'part_id' => $p['part_id'], 'description' => $p['description'],
                'hsn_sac' => $p['hsn'], 'qty' => $p['qty'], 'rate' => $p['rate'], 'discount' => $p['discount'],
                'gst_rate' => $p['gst_rate'], 'taxable' => $p['amount']]);
            stock_move((int) $p['part_id'], 'out', (float) $p['qty'], (float) $p['rate'], 'invoice', $invId, 'Sold on ' . $no);
        }
        recalc_invoice($invId);
        q("UPDATE job_cards SET status = 'invoiced' WHERE id = ?", [$jcId]);
        log_status($jcId, 'invoiced', $no);
        return $invId;
    });
}

/** Direct (counter) invoice for parts and/or labour without a job card. */
function create_counter_invoice(array $h, array $labour, array $parts): int
{
    if (!$labour && !$parts) throw new ValidationError(t('add_lines_first'));
    return tx(function () use ($h, $labour, $parts) {
        $no = next_number('invoice');
        $cid = $h['customer_id'] ?: null;
        $invId = db_insert('invoices', [
            'invoice_no' => $no, 'type' => 'counter', 'job_card_id' => null, 'customer_id' => $cid,
            'customer_name' => $cid ? null : nullable($h['customer_name']), 'customer_mobile' => $cid ? null : nullable($h['customer_mobile']),
            'invoice_date' => $h['invoice_date'], 'place_of_supply' => setting('state_code'),
            'is_igst' => is_igst_for($cid) ? 1 : 0, 'gst_enabled' => gst_on() ? 1 : 0, 'notes' => nullable($h['notes']), 'created_by' => uid(),
        ]);
        foreach ($labour as $l) {
            insert_invoice_item($invId, ['item_type' => 'labour', 'part_id' => null, 'description' => $l['description'], 'hsn_sac' => '998714',
                'qty' => $l['qty'], 'rate' => $l['rate'], 'discount' => $l['discount'], 'gst_rate' => $l['gst_rate'], 'taxable' => $l['amount']]);
        }
        foreach ($parts as $p) {
            $hsn = val('SELECT hsn FROM parts WHERE id = ?', [$p['part_id']]);
            insert_invoice_item($invId, ['item_type' => 'part', 'part_id' => $p['part_id'], 'description' => $p['description'], 'hsn_sac' => $hsn,
                'qty' => $p['qty'], 'rate' => $p['rate'], 'discount' => $p['discount'], 'gst_rate' => $p['gst_rate'], 'taxable' => $p['amount']]);
            stock_move((int) $p['part_id'], 'out', (float) $p['qty'], (float) $p['rate'], 'counter', $invId, 'Counter sale ' . $no);
        }
        recalc_invoice($invId);
        if (!empty($h['pay_amount']) && $h['pay_amount'] > 0) {
            add_payment($invId, (float) $h['pay_amount'], $h['pay_mode'], '', $h['invoice_date'], 'At billing', false);
        }
        return $invId;
    });
}

function add_payment(int $invId, float $amount, string $mode, string $ref, string $date, string $note, bool $ownTx = true): int
{
    $fn = function () use ($invId, $amount, $mode, $ref, $date, $note) {
        $inv = row('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invId]);
        if (!$inv || $inv['status'] === 'cancelled') throw new ValidationError(t('not_found'));
        if ($amount <= 0) throw new ValidationError(t('amount_positive'));
        if ($amount > (float) $inv['balance'] + 0.009) throw new ValidationError(t('amount_exceeds_due') . ' (' . money($inv['balance']) . ')');
        $id = db_insert('payments', ['invoice_id' => $invId, 'customer_id' => $inv['customer_id'], 'amount' => $amount,
            'mode' => one_of($mode, PAY_MODES, 'cash'), 'reference_no' => nullable($ref), 'payment_date' => $date,
            'note' => nullable($note), 'user_id' => uid()]);
        recalc_invoice_payments($invId);
        return $id;
    };
    return $ownTx ? tx($fn) : $fn();
}

function cancel_invoice(int $invId, string $reason): void
{
    tx(function () use ($invId, $reason) {
        $inv = row('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invId]);
        if (!$inv || $inv['status'] === 'cancelled') throw new ValidationError(t('not_found'));
        if ((float) $inv['paid'] > 0) throw new ValidationError(t('delete_payments_first'));
        foreach (rows("SELECT * FROM invoice_items WHERE invoice_id = ? AND item_type = 'part'", [$invId]) as $it) {
            stock_move((int) $it['part_id'], 'in', (float) $it['qty'], (float) $it['rate'], $inv['type'] === 'counter' ? 'counter' : 'invoice', $invId, 'Cancelled ' . $inv['invoice_no']);
        }
        $note = trim(($inv['notes'] ?? '') . "\nCancelled: " . $reason);
        if ($inv['job_card_id']) {
            $jc = row('SELECT jc_no FROM job_cards WHERE id = ?', [$inv['job_card_id']]);
            $note .= ' (was ' . $jc['jc_no'] . ')';
            q("UPDATE job_cards SET status = 'ready', delivered_at = NULL WHERE id = ?", [$inv['job_card_id']]);
            log_status((int) $inv['job_card_id'], 'ready', 'Invoice ' . $inv['invoice_no'] . ' cancelled');
        }
        db_update('invoices', ['status' => 'cancelled', 'job_card_id' => null, 'balance' => 0, 'notes' => $note], 'id = ?', [$invId]);
    });
}

// ---------------------------------------------------------------- Delivery & reminders

function deliver_jobcard(int $jcId): array
{
    return tx(function () use ($jcId) {
        $jc = row('SELECT * FROM job_cards WHERE id = ? FOR UPDATE', [$jcId]);
        if (!$jc || $jc['status'] !== 'invoiced') throw new ValidationError(t('invoice_before_delivery'));
        q("UPDATE job_cards SET status = 'delivered', delivered_at = NOW() WHERE id = ?", [$jcId]);
        log_status($jcId, 'delivered');
        $days = (int) setting('service_interval_days'); $km = (int) setting('service_interval_km');
        $odo = (int) $jc['odometer_in'];
        q('UPDATE vehicles SET odometer = GREATEST(COALESCE(odometer,0), ?), next_service_date = CURDATE() + INTERVAL ? DAY, next_service_km = ? WHERE id = ?',
            [$odo, $days, $odo ? $odo + $km : null, $jc['vehicle_id']]);
        q("UPDATE reminders SET status = 'done', job_card_id = ? WHERE vehicle_id = ? AND type = 'service' AND status <> 'done'", [$jcId, $jc['vehicle_id']]);
        sync_vehicle_reminders((int) $jc['vehicle_id']);
        $token = (string) val('SELECT token FROM feedbacks WHERE job_card_id = ?', [$jcId]);
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
            db_insert('feedbacks', ['job_card_id' => $jcId, 'customer_id' => $jc['customer_id'], 'token' => $token]);
        }
        return ['token' => $token];
    });
}

/** Keep one pending reminder per vehicle+type that matches the vehicle's current dates. */
function sync_vehicle_reminders(int $vid): void
{
    $v = row('SELECT * FROM vehicles WHERE id = ?', [$vid]);
    if (!$v) return;
    $map = ['service' => $v['next_service_date'], 'insurance' => $v['insurance_expiry'], 'puc' => $v['puc_expiry']];
    foreach ($map as $type => $date) {
        if ($date) {
            q("UPDATE reminders SET status = 'done' WHERE vehicle_id = ? AND type = ? AND due_date <> ? AND status IN ('pending','snoozed','sent')", [$vid, $type, $date]);
            q('INSERT IGNORE INTO reminders (vehicle_id, customer_id, type, due_date, due_km, status) VALUES (?,?,?,?,?,\'pending\')',
                [$vid, $v['customer_id'], $type, $date, $type === 'service' ? $v['next_service_km'] : null]);
            q('UPDATE reminders SET customer_id = ? WHERE vehicle_id = ?', [$v['customer_id'], $vid]);
        } else {
            q("UPDATE reminders SET status = 'done' WHERE vehicle_id = ? AND type = ? AND status IN ('pending','snoozed','sent')", [$vid, $type]);
        }
    }
}

// ---------------------------------------------------------------- Purchases

function recalc_po(int $poId): void
{
    $x = row('SELECT COALESCE(SUM(amount),0) st, COALESCE(SUM(ROUND(amount*gst_rate/100,2)),0) tx FROM purchase_order_items WHERE po_id = ?', [$poId]);
    $paid = (float) val('SELECT COALESCE(SUM(amount),0) FROM vendor_payments WHERE po_id = ?', [$poId]);
    db_update('purchase_orders', ['subtotal' => $x['st'], 'tax_total' => $x['tx'], 'total' => round($x['st'] + $x['tx']), 'paid' => $paid], 'id = ?', [$poId]);
}

function save_po_items(int $poId, $raw): void
{
    if (is_string($raw)) $raw = json_decode($raw, true);
    if (!is_array($raw) || !$raw) throw new ValidationError(t('add_lines_first'));
    q('DELETE FROM purchase_order_items WHERE po_id = ?', [$poId]);
    foreach ($raw as $l) {
        $pid = (int) ($l['part_id'] ?? 0); $qn = (float) ($l['qty'] ?? 0); $r = (float) ($l['rate'] ?? 0);
        $g = (float) ($l['gst_rate'] ?? 18);
        if (!$pid || !val('SELECT id FROM parts WHERE id = ?', [$pid])) throw new ValidationError(t('select_valid_part'));
        if ($qn <= 0 || $r < 0) throw new ValidationError(t('qty_positive'));
        if (!in_array((int) $g, GST_RATES, true)) $g = 18;
        db_insert('purchase_order_items', ['po_id' => $poId, 'part_id' => $pid, 'qty' => $qn, 'rate' => $r, 'gst_rate' => $g, 'amount' => round($qn * $r, 2)]);
    }
    recalc_po($poId);
}

function receive_po(int $poId, string $billNo = ''): void
{
    $po = row('SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE', [$poId]);
    if (!$po || in_array($po['status'], ['received', 'cancelled'], true)) throw new ValidationError(t('po_cannot_receive'));
    foreach (rows('SELECT * FROM purchase_order_items WHERE po_id = ?', [$poId]) as $it) {
        stock_move((int) $it['part_id'], 'in', (float) $it['qty'], (float) $it['rate'], 'po', $poId, $po['po_no'] . ' received');
        q('UPDATE parts SET purchase_price = ? WHERE id = ?', [$it['rate'], $it['part_id']]);
    }
    db_update('purchase_orders', ['status' => 'received', 'received_at' => date('Y-m-d H:i:s'), 'bill_no' => nullable($billNo) ?? $po['bill_no']], 'id = ?', [$poId]);
}

function add_vendor_payment(int $poId, float $amount, string $mode, string $ref, string $date, string $note): void
{
    tx(function () use ($poId, $amount, $mode, $ref, $date, $note) {
        $po = row('SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE', [$poId]);
        if (!$po) throw new ValidationError(t('not_found'));
        $due = (float) $po['total'] - (float) $po['paid'];
        if ($amount <= 0) throw new ValidationError(t('amount_positive'));
        if ($amount > $due + 0.009) throw new ValidationError(t('amount_exceeds_due') . ' (' . money($due) . ')');
        db_insert('vendor_payments', ['po_id' => $poId, 'vendor_id' => $po['vendor_id'], 'amount' => $amount,
            'mode' => one_of($mode, PAY_MODES, 'cash'), 'reference_no' => nullable($ref), 'payment_date' => $date,
            'note' => nullable($note), 'user_id' => uid()]);
        recalc_po($poId);
    });
}

// ---------------------------------------------------------------- Customers / vehicles

/** Create or update a customer from posted fields (prefix e.g. "c_"). Returns id. */
function upsert_customer(array $d, ?int $id = null): int
{
    $name = trim($d['name'] ?? '');
    if ($name === '') throw new ValidationError(t('customer_name') . ' ' . t('is_required'));
    $mobile = valid_mobile($d['mobile'] ?? '');
    $data = ['name' => mb_substr($name, 0, 100), 'mobile' => $mobile,
        'alt_mobile' => nullable(preg_replace('/\D/', '', $d['alt_mobile'] ?? '')),
        'email' => nullable(filter_var(trim($d['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: ''),
        'address' => nullable(trim($d['address'] ?? '')), 'city' => nullable(trim($d['city'] ?? '')),
        'gstin' => valid_gstin($d['gstin'] ?? ''), 'notes' => nullable(trim($d['notes'] ?? ''))];
    $dup = val('SELECT id FROM customers WHERE mobile = ? AND id <> ?', [$mobile, $id ?? 0]);
    if ($dup) {
        if ($id === null) return (int) $dup; // reuse existing customer with this mobile
        throw new ValidationError(t('mobile_exists'));
    }
    if ($id) { db_update('customers', $data, 'id = ?', [$id]); return $id; }
    return db_insert('customers', $data);
}

function upsert_vehicle(array $d, int $customerId, ?int $id = null): int
{
    $reg = reg_clean($d['reg_no'] ?? '');
    if (strlen($reg) < 4) throw new ValidationError(t('reg_no') . ' ' . t('is_required'));
    if (trim($d['make'] ?? '') === '' || trim($d['model'] ?? '') === '') throw new ValidationError(t('make_model_required'));
    $dup = val('SELECT id FROM vehicles WHERE reg_no = ? AND id <> ?', [$reg, $id ?? 0]);
    if ($dup) throw new ValidationError(t('reg_exists'));
    $data = ['customer_id' => $customerId, 'reg_no' => $reg, 'make' => mb_substr(trim($d['make']), 0, 50), 'model' => mb_substr(trim($d['model']), 0, 50),
        'variant' => nullable(trim($d['variant'] ?? '')), 'fuel_type' => one_of($d['fuel_type'] ?? 'petrol', ['petrol', 'diesel', 'cng', 'ev', 'other'], 'petrol'),
        'year' => !empty($d['year']) ? (int) $d['year'] : null, 'color' => nullable(trim($d['color'] ?? '')),
        'chassis_no' => nullable(strtoupper(trim($d['chassis_no'] ?? ''))), 'engine_no' => nullable(strtoupper(trim($d['engine_no'] ?? ''))),
        'insurance_company' => nullable(trim($d['insurance_company'] ?? '')), 'policy_no' => nullable(trim($d['policy_no'] ?? '')),
        'insurance_expiry' => valid_date($d['insurance_expiry'] ?? ''), 'puc_expiry' => valid_date($d['puc_expiry'] ?? '')];
    if (isset($d['odometer']) && $d['odometer'] !== '') $data['odometer'] = (int) $d['odometer'];
    if (array_key_exists('next_service_date', $d)) $data['next_service_date'] = valid_date($d['next_service_date']);
    if (array_key_exists('next_service_km', $d)) $data['next_service_km'] = $d['next_service_km'] !== '' ? (int) $d['next_service_km'] : null;
    if ($id) db_update('vehicles', $data, 'id = ?', [$id]); else $id = db_insert('vehicles', $data);
    sync_vehicle_reminders($id);
    return $id;
}
