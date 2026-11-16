<?php
/** Live search endpoints used by autocompletes and search bars. GET ?type=...&q=... */
require __DIR__ . '/../includes/bootstrap.php';

api_run(function () {
    $type = input('type');
    $q = (string) input('q');
    $like = '%' . $q . '%';
    $reg = '%' . reg_clean($q) . '%';

    switch ($type) {
        case 'global':
            api_perm('service');
            $out = [];
            $tech = is_tech() ? ' AND jc.technician_id = ' . (int) uid() : '';
            foreach (rows("SELECT jc.id, jc.jc_no, jc.status, c.name, c.mobile, v.reg_no, v.make, v.model FROM job_cards jc
                    JOIN customers c ON c.id = jc.customer_id JOIN vehicles v ON v.id = jc.vehicle_id
                    WHERE (c.name LIKE ? OR c.mobile LIKE ? OR c.email LIKE ? OR v.reg_no LIKE ? OR jc.jc_no LIKE ?) $tech
                    ORDER BY jc.id DESC LIMIT 8", [$like, $like, $like, $reg, $like]) as $r) {
                $out[] = ['href' => 'jobcard.php?id=' . $r['id'], 'title' => $r['jc_no'] . ' · ' . $r['reg_no'],
                    'sub' => $r['name'] . ' · ' . $r['mobile'] . ' · ' . $r['make'] . ' ' . $r['model'], 'tag' => jc_label($r['status'])];
            }
            if (can('customers')) {
                foreach (rows('SELECT id, name, mobile, city FROM customers WHERE name LIKE ? OR mobile LIKE ? OR email LIKE ? ORDER BY name LIMIT 5', [$like, $like, $like]) as $c) {
                    $out[] = ['href' => 'customer.php?id=' . $c['id'], 'title' => $c['name'], 'sub' => $c['mobile'] . ' ' . ($c['city'] ?? ''), 'tag' => t('customer')];
                }
            }
            json_ok($out);

        case 'vehicles':
            api_perm('service');
            json_ok(rows('SELECT v.id, v.reg_no, v.make, v.model, v.variant, v.fuel_type, c.name, c.mobile FROM vehicles v JOIN customers c ON c.id = v.customer_id
                WHERE v.reg_no LIKE ? OR v.chassis_no LIKE ? ORDER BY v.reg_no LIMIT 15', [$reg, $like]));

        case 'customers':
            api_perm('service');
            json_ok(rows('SELECT id, name, mobile, email, city, address, gstin FROM customers WHERE name LIKE ? OR mobile LIKE ? ORDER BY name LIMIT 15', [$like, $like]));

        case 'parts':
            api_perm('prices');
            json_ok(rows('SELECT id, part_no, name, brand, hsn, unit, stock, mrp, selling_price, purchase_price, gst_rate, rack_no FROM parts
                WHERE is_active = 1 AND (name LIKE ? OR part_no LIKE ? OR brand LIKE ?) ORDER BY name LIMIT 20', [$like, $like, $like]));

        case 'labour':
            api_perm('prices');
            json_ok(rows('SELECT id, name, rate, gst_rate FROM labour_rates WHERE is_active = 1 AND name LIKE ? ORDER BY name LIMIT 20', [$like]));

        case 'vendors':
            api_perm('vendors');
            json_ok(rows('SELECT id, name, mobile, gstin FROM vendors WHERE name LIKE ? OR mobile LIKE ? ORDER BY name LIMIT 15', [$like, $like]));
    }
});
