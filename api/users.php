<?php
/** Staff users (admin only). */
require __DIR__ . '/../includes/bootstrap.php';

api_run(function ($action) {
    if (!is_admin()) json_err(t('no_permission'), 403);
    require_post();
    switch ($action) {
        case 'save':
            $id = input_int('id');
            $mobile = valid_mobile((string) input('mobile'));
            if (val('SELECT id FROM users WHERE mobile = ? AND id <> ?', [$mobile, $id])) throw new ValidationError(t('mobile_exists'));
            $role = one_of((string) input('role'), ['admin', 'advisor', 'technician'], 'advisor');
            $d = ['name' => mb_substr(required('name', t('name')), 0, 100), 'mobile' => $mobile,
                  'email' => nullable(filter_var((string) input('email'), FILTER_VALIDATE_EMAIL) ?: ''), 'role' => $role,
                  'language' => one_of((string) input('language'), array_keys(LANGS), 'en')];
            $pass = (string) ($_POST['password'] ?? '');
            if ($id === uid() && $role !== 'admin') throw new ValidationError(t('cannot_demote_self'));
            if ($pass !== '') {
                if (strlen($pass) < 6) throw new ValidationError(t('password_min'));
                $d['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            } elseif (!$id) {
                throw new ValidationError(t('password') . ' ' . t('is_required'));
            }
            if ($id) db_update('users', $d, 'id = ?', [$id]); else $id = db_insert('users', $d);
            json_ok(['id' => $id], t('saved'));

        case 'toggle':
            $id = input_int('id');
            if ($id === uid()) throw new ValidationError(t('cannot_disable_self'));
            q('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$id]);
            json_ok([], t('saved'));
    }
});
