<?php
/** Alias kept for forms: payments.php?action=add → add customer payment against an invoice. */
require __DIR__ . '/../includes/bootstrap.php';

api_run(function ($action) {
    api_perm('invoice');
    if ($action === 'add') {
        require_post();
        add_payment(input_int('invoice_id'), input_num('amount'), (string) input('mode'), (string) input('reference_no'),
            valid_date((string) input('payment_date'), false), (string) input('note'));
        json_ok([], t('payment_saved'));
    }
});
