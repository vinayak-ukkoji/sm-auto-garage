<?php
/** Settings + masters API (admin), and own password change (any user). */
require __DIR__ . '/../includes/bootstrap.php';

api_run(function ($action) {
    if ($action === 'password') {
        require_post();
        $u = row('SELECT * FROM users WHERE id = ?', [uid()]);
        if (!password_verify((string) ($_POST['current'] ?? ''), $u['password_hash'])) throw new ValidationError(t('wrong_current_password'));
        $new = (string) ($_POST['new'] ?? '');
        if (strlen($new) < 6) throw new ValidationError(t('password_min'));
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), uid()]);
        json_ok([], t('password_changed'));
    }
    if (!is_admin()) json_err(t('no_permission'), 403);
    require_post();
    switch ($action) {
        case 'profile':
            $state = (string) input('state_code');
            if (!isset(GST_STATES[$state])) throw new ValidationError(t('select_state'));
            $d = ['garage_name' => mb_substr(required('garage_name', t('garage_name')), 0, 150), 'tagline' => nullable((string) input('tagline')),
                'address' => nullable((string) input('address')), 'city' => nullable((string) input('city')), 'pincode' => nullable((string) input('pincode')),
                'phone' => nullable((string) input('phone')), 'email' => nullable(filter_var((string) input('email'), FILTER_VALIDATE_EMAIL) ?: ''),
                'gstin' => valid_gstin((string) input('gstin')), 'state_code' => $state, 'state_name' => GST_STATES[$state],
                'upi_id' => nullable((string) input('upi_id')), 'bank_details' => nullable((string) input('bank_details')),
                'invoice_terms' => nullable((string) input('invoice_terms'))];
            if ($d['gstin'] && substr($d['gstin'], 0, 2) !== $state) throw new ValidationError(t('gstin_state_mismatch'));
            if (!empty($_FILES['logo']['name'])) {
                $old = setting('logo');
                $d['logo'] = save_upload($_FILES['logo'], 'logos');
                if ($old && is_file(__DIR__ . '/../' . $old)) @unlink(__DIR__ . '/../' . $old);
            }
            if (input('remove_logo') === '1') $d['logo'] = null;
            db_update('settings', $d, 'id = 1');
            json_ok([], t('saved'));

        case 'numbering':
            $d = [];
            foreach (['invoice', 'jobcard', 'po'] as $k) {
                $d[$k . '_prefix'] = mb_substr(preg_replace('/[^A-Za-z0-9\-\/]/', '', (string) input($k . '_prefix')), 0, 10);
                $d[$k . '_next'] = max(1, input_int($k . '_next', 1));
            }
            $d['service_interval_days'] = max(1, input_int('service_interval_days', 180));
            $d['service_interval_km'] = max(0, input_int('service_interval_km', 10000));
            $d['default_language'] = one_of((string) input('default_language'), array_keys(LANGS), 'en');
            db_update('settings', $d, 'id = 1');
            json_ok([], t('saved'));

        case 'templates':
            $d = [];
            foreach (['wa_template_reminder', 'wa_template_invoice', 'wa_template_ready', 'wa_template_feedback'] as $k) $d[$k] = mb_substr((string) input($k), 0, 1000);
            db_update('settings', $d, 'id = 1');
            json_ok([], t('saved'));

        case 'labour_save':
            $id = input_int('id');
            $d = ['name' => mb_substr(required('name', t('name')), 0, 150), 'rate' => max(0, input_num('rate')), 'sac' => (string) input('sac') ?: '998714',
                  'gst_rate' => in_array(input_int('gst_rate'), GST_RATES, true) ? input_int('gst_rate') : 18];
            if ($id) db_update('labour_rates', $d, 'id = ?', [$id]); else db_insert('labour_rates', $d);
            json_ok([], t('saved'));

        case 'check_save':
            $id = input_int('id');
            $d = ['name' => mb_substr(required('name', t('name')), 0, 150), 'sort_order' => input_int('sort_order')];
            if ($id) db_update('checklist_items', $d, 'id = ?', [$id]); else db_insert('checklist_items', $d);
            json_ok([], t('saved'));

        case 'tax_save':
            $rate = input_num('rate');
            db_insert('tax_rates', ['name' => 'GST ' . qty($rate) . '%', 'rate' => $rate]);
            json_ok([], t('saved'));

        case 'toggle':
            $table = ['labour' => 'labour_rates', 'check' => 'checklist_items', 'tax' => 'tax_rates'][(string) input('what')] ?? null;
            if (!$table) json_err('Bad request');
            q("UPDATE $table SET is_active = 1 - is_active WHERE id = ?", [input_int('id')]);
            json_ok([], t('saved'));

        case 'cat_save':
            $id = input_int('id');
            $name = mb_substr(required('name', t('name')), 0, 60);
            if ($id) db_update('expense_categories', ['name' => $name], 'id = ?', [$id]); else db_insert('expense_categories', ['name' => $name]);
            json_ok([], t('saved'));

        case 'cat_delete':
            $id = input_int('id');
            if (val('SELECT COUNT(*) FROM expenses WHERE category_id = ?', [$id])) throw new ValidationError(t('in_use_cannot_delete'));
            q('DELETE FROM expense_categories WHERE id = ?', [$id]);
            json_ok([], t('deleted'));
    }
});
