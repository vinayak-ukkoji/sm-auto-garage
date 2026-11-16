<?php
/** Customers + vehicles API. */
require __DIR__ . '/../includes/bootstrap.php';

api_run(function ($action) {
    switch ($action) {
        case 'vehicle':
            api_perm('service');
            $v = row('SELECT * FROM vehicles WHERE id = ?', [input_int('id')]);
            if (!$v) json_err(t('not_found'), 404);
            $c = row('SELECT * FROM customers WHERE id = ?', [$v['customer_id']]);
            $open = val("SELECT jc_no FROM job_cards WHERE vehicle_id = ? AND status IN ('estimate','approved','in_progress','ready') LIMIT 1", [$v['id']]);
            json_ok(['vehicle' => $v, 'customer' => $c, 'open_order' => $open]);

        case 'by_mobile':
            api_perm('service');
            $m = preg_replace('/\D/', '', (string) input('mobile'));
            json_ok(row('SELECT * FROM customers WHERE mobile = ?', [$m]));

        case 'list':
            api_perm('customers');
            $q = (string) input('q'); $like = '%' . $q . '%';
            $list = rows("SELECT c.*, (SELECT COUNT(*) FROM vehicles v WHERE v.customer_id = c.id) AS vcount,
                    (SELECT COALESCE(SUM(balance),0) FROM invoices i WHERE i.customer_id = c.id AND i.status IN ('unpaid','partial')) AS due,
                    (SELECT GROUP_CONCAT(reg_no SEPARATOR ', ') FROM vehicles v WHERE v.customer_id = c.id) AS regs
                FROM customers c
                WHERE c.name LIKE ? OR c.mobile LIKE ? OR c.id IN (SELECT customer_id FROM vehicles WHERE reg_no LIKE ?)
                ORDER BY c.name LIMIT " . 50 . ' OFFSET ' . max(0, input_int('offset')), [$like, $like, '%' . reg_clean($q) . '%']);
            $html = '';
            foreach ($list as $c) {
                $html .= '<a class="list-card" href="' . e(url('customer.php?id=' . $c['id'])) . '"><div class="r">'
                    . ($c['due'] > 0 ? '<div class="text-danger">' . money($c['due'], 0) . ' ' . te('due') . '</div>' : '')
                    . '<small class="text-muted">' . (int) $c['vcount'] . ' ' . te('vehicles') . '</small></div>'
                    . '<div class="t">' . e($c['name']) . '</div><div class="s"><i class="bi bi-telephone"></i> ' . e($c['mobile'])
                    . ($c['regs'] ? ' · ' . e($c['regs']) : '') . '</div></a>';
            }
            json_ok(['html' => $html, 'count' => count($list)]);

        case 'save':
            require_post(); api_perm('customers');
            $id = input_int('id') ?: null;
            if ($id && !val('SELECT id FROM customers WHERE id = ?', [$id])) json_err(t('not_found'), 404);
            if (!$id && val('SELECT id FROM customers WHERE mobile = ?', [preg_replace('/\D/', '', (string) input('mobile'))])) throw new ValidationError(t('mobile_exists'));
            $id = upsert_customer($_POST, $id);
            json_ok(['id' => $id], t('saved'));

        case 'delete':
            require_post(); api_perm('customers');
            if (!is_admin()) json_err(t('no_permission'), 403);
            $id = input_int('id');
            if (val('SELECT COUNT(*) FROM job_cards WHERE customer_id = ?', [$id]) || val('SELECT COUNT(*) FROM invoices WHERE customer_id = ?', [$id])) throw new ValidationError(t('in_use_cannot_delete'));
            tx(function () use ($id) {
                q('DELETE FROM reminders WHERE customer_id = ?', [$id]);
                q('DELETE FROM vehicles WHERE customer_id = ?', [$id]);
                q('DELETE FROM customers WHERE id = ?', [$id]);
            });
            json_ok([], t('deleted'));

        case 'vehicle_save':
            require_post(); api_perm('customers');
            $id = input_int('id') ?: null;
            $cid = input_int('customer_id');
            if (!val('SELECT id FROM customers WHERE id = ?', [$cid])) throw new ValidationError(t('select_customer'));
            $data = $_POST;
            $id = tx(fn() => upsert_vehicle($data, $cid, $id));
            json_ok(['id' => $id], t('saved'));

        case 'vehicle_delete':
            require_post(); api_perm('customers');
            if (!is_admin()) json_err(t('no_permission'), 403);
            $id = input_int('id');
            if (val('SELECT COUNT(*) FROM job_cards WHERE vehicle_id = ?', [$id])) throw new ValidationError(t('in_use_cannot_delete'));
            q('DELETE FROM vehicles WHERE id = ?', [$id]);
            json_ok([], t('deleted'));

        case 'vehicle_list':
            api_perm('search');
            $q = (string) input('q'); $like = '%' . $q . '%';
            $list = rows('SELECT v.*, c.name, c.mobile FROM vehicles v JOIN customers c ON c.id = v.customer_id
                WHERE v.reg_no LIKE ? OR v.chassis_no LIKE ? OR v.make LIKE ? OR v.model LIKE ? OR c.mobile LIKE ? OR c.name LIKE ?
                ORDER BY v.reg_no LIMIT 50', ['%' . reg_clean($q) . '%', $like, $like, $like, $like, $like]);
            $html = '';
            foreach ($list as $v) {
                $html .= '<a class="list-card" href="' . e(url('vehicle.php?id=' . $v['id'])) . '"><div class="r"><small class="text-muted">' . e($v['odometer'] ? inr($v['odometer'], 0) . ' km' : '') . '</small></div>'
                    . '<div class="t">' . e($v['reg_no']) . '</div><div class="s">' . e(vehicle_name($v)) . '</div><div class="s"><i class="bi bi-person"></i> ' . e($v['name']) . ' · ' . e($v['mobile']) . '</div></a>';
            }
            json_ok(['html' => $html, 'count' => count($list)]);
    }
});
