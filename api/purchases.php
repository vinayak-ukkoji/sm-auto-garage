<?php
/** Purchase orders, stock-in (direct purchase) and vendor payments. */
require __DIR__ . '/../includes/bootstrap.php';

api_run(function ($action) {
    api_perm('parts');
    switch ($action) {
        case 'save':
            require_post();
            $id = input_int('id');
            $vendor = input_int('vendor_id');
            if (!val('SELECT id FROM vendors WHERE id = ?', [$vendor])) throw new ValidationError(t('select_vendor'));
            $target = one_of((string) input('status'), ['draft', 'ordered', 'received'], 'draft');
            $h = ['vendor_id' => $vendor, 'po_date' => valid_date((string) input('po_date'), false),
                  'expected_date' => valid_date((string) input('expected_date')), 'bill_no' => nullable((string) input('bill_no')),
                  'notes' => nullable((string) input('notes'))];
            $id = tx(function () use ($id, $h, $target) {
                if ($id) {
                    $po = row('SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE', [$id]);
                    if (!$po || !in_array($po['status'], ['draft', 'ordered'], true)) throw new ValidationError(t('po_locked'));
                    db_update('purchase_orders', $h + ['status' => $target === 'received' ? $po['status'] : $target], 'id = ?', [$id]);
                } else {
                    $id = db_insert('purchase_orders', $h + ['po_no' => next_number('po'), 'status' => $target === 'received' ? 'ordered' : $target, 'created_by' => uid()]);
                }
                save_po_items($id, input('items'));
                if ($target === 'received') receive_po($id, (string) input('bill_no'));
                $pay = input_num('pay_amount');
                if ($pay > 0) {
                    recalc_po($id);
                    $tot = (float) val('SELECT total FROM purchase_orders WHERE id = ?', [$id]);
                    db_insert('vendor_payments', ['po_id' => $id, 'vendor_id' => $h['vendor_id'], 'amount' => min($pay, $tot),
                        'mode' => one_of((string) input('pay_mode'), PAY_MODES, 'cash'), 'payment_date' => $h['po_date'], 'user_id' => uid(), 'note' => 'At purchase']);
                    recalc_po($id);
                }
                return $id;
            });
            json_ok(['id' => $id], t('saved'));

        case 'status':
            require_post();
            $po = row('SELECT * FROM purchase_orders WHERE id = ?', [input_int('id')]);
            $to = (string) input('to');
            if (!$po) json_err(t('not_found'), 404);
            $ok = ($po['status'] === 'draft' && in_array($to, ['ordered', 'cancelled'], true)) || ($po['status'] === 'ordered' && in_array($to, ['cancelled', 'draft'], true));
            if (!$ok) throw new ValidationError(t('status_change_not_allowed'));
            if ($to === 'cancelled' && (float) $po['paid'] > 0) throw new ValidationError(t('delete_payments_first'));
            q('UPDATE purchase_orders SET status = ? WHERE id = ?', [$to, $po['id']]);
            json_ok([], t('status_updated'));

        case 'receive':
            require_post();
            tx(fn() => receive_po(input_int('id'), (string) input('bill_no')));
            json_ok([], t('stock_received'));

        case 'pay':
            require_post(); api_perm('accounts');
            add_vendor_payment(input_int('po_id'), input_num('amount'), (string) input('mode'), (string) input('reference_no'),
                valid_date((string) input('payment_date'), false), (string) input('note'));
            json_ok([], t('payment_saved'));

        case 'delete_payment':
            require_post();
            if (!is_admin()) json_err(t('no_permission'), 403);
            $p = row('SELECT * FROM vendor_payments WHERE id = ?', [input_int('id')]);
            if (!$p) json_err(t('not_found'), 404);
            tx(function () use ($p) { q('DELETE FROM vendor_payments WHERE id = ?', [$p['id']]); recalc_po((int) $p['po_id']); });
            json_ok([], t('deleted'));
    }
});
