<?php
/** Invoices + customer payments API. */
require __DIR__ . '/../includes/bootstrap.php';

api_run(function ($action) {
    api_perm('invoice');
    switch ($action) {
        case 'create':
            require_post();
            $cid = input_int('customer_id');
            if ($cid && !val('SELECT id FROM customers WHERE id = ?', [$cid])) throw new ValidationError(t('not_found'));
            $mob = (string) input('customer_mobile');
            if (!$cid && $mob !== '') $mob = valid_mobile($mob);
            $h = ['customer_id' => $cid, 'customer_name' => (string) input('customer_name') ?: 'Walk-in Customer', 'customer_mobile' => $mob,
                  'invoice_date' => valid_date((string) input('invoice_date'), false), 'notes' => (string) input('notes'),
                  'pay_amount' => input_num('pay_amount'), 'pay_mode' => (string) input('pay_mode')];
            $id = create_counter_invoice($h, clean_lines(input('labour'), 'labour'), clean_lines(input('parts'), 'parts'));
            json_ok(['id' => $id], t('invoice_created'));

        case 'cancel':
            require_post();
            if (!is_admin()) json_err(t('no_permission'), 403);
            cancel_invoice(input_int('id'), (string) input('reason') ?: 'Cancelled');
            json_ok([], t('invoice_cancelled'));

        case 'add_payment':
            require_post();
            add_payment(input_int('invoice_id'), input_num('amount'), (string) input('mode'), (string) input('reference_no'),
                valid_date((string) input('payment_date'), false), (string) input('note'));
            json_ok([], t('payment_saved'));

        case 'delete_payment':
            require_post();
            if (!is_admin()) json_err(t('no_permission'), 403);
            $p = row('SELECT * FROM payments WHERE id = ?', [input_int('id')]);
            if (!$p) json_err(t('not_found'), 404);
            tx(function () use ($p) {
                q('DELETE FROM payments WHERE id = ?', [$p['id']]);
                recalc_invoice_payments((int) $p['invoice_id']);
            });
            json_ok([], t('deleted'));
    }
});
