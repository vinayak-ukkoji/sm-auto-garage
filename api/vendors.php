<?php
/** Vendors API. */
require __DIR__ . '/../includes/bootstrap.php';

api_run(function ($action) {
    api_perm('vendors');
    switch ($action) {
        case 'save':
            require_post();
            $id = input_int('id');
            $name = required('name', t('vendor_name'));
            $mob = (string) input('mobile');
            $d = ['name' => mb_substr($name, 0, 150), 'contact_person' => nullable((string) input('contact_person')),
                  'mobile' => $mob !== '' ? valid_mobile($mob) : null,
                  'email' => nullable(filter_var((string) input('email'), FILTER_VALIDATE_EMAIL) ?: ''),
                  'address' => nullable((string) input('address')), 'gstin' => valid_gstin((string) input('gstin'))];
            if ($id) db_update('vendors', $d, 'id = ?', [$id]); else $id = db_insert('vendors', $d);
            json_ok(['id' => $id], t('saved'));

        case 'delete':
            require_post();
            if (!is_admin()) json_err(t('no_permission'), 403);
            $id = input_int('id');
            if (val('SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = ?', [$id])) throw new ValidationError(t('in_use_cannot_delete'));
            q('DELETE FROM vendors WHERE id = ?', [$id]);
            json_ok([], t('deleted'));
    }
});
