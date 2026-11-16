<?php
/** Parts / inventory API. */
require __DIR__ . '/../includes/bootstrap.php';

function part_data(array $src): array
{
    $name = trim((string) ($src['name'] ?? ''));
    if ($name === '') throw new ValidationError(t('part_name') . ' ' . t('is_required'));
    $num = fn($k) => isset($src[$k]) && $src[$k] !== '' && is_numeric($src[$k]) ? max(0, round((float) $src[$k], 2)) : 0;
    $gst = (int) ($src['gst_rate'] ?? 18);
    $d = ['name' => mb_substr($name, 0, 150), 'part_no' => nullable(mb_substr(trim((string) ($src['part_no'] ?? '')), 0, 50)),
        'brand' => nullable(trim((string) ($src['brand'] ?? ''))), 'category' => nullable(trim((string) ($src['category'] ?? ''))),
        'hsn' => nullable(trim((string) ($src['hsn'] ?? ''))), 'unit' => trim((string) ($src['unit'] ?? '')) ?: 'pcs',
        'purchase_price' => $num('purchase_price'), 'mrp' => $num('mrp'), 'selling_price' => $num('selling_price'),
        'gst_rate' => in_array($gst, GST_RATES, true) ? $gst : 18, 'min_stock' => $num('min_stock'),
        'rack_no' => nullable(trim((string) ($src['rack_no'] ?? '')))];
    if (!$d['selling_price']) $d['selling_price'] = $d['mrp'];
    if (!$d['mrp']) $d['mrp'] = $d['selling_price'];
    return $d;
}

api_run(function ($action) {
    api_perm('parts');
    switch ($action) {
        case 'list':
            $q = (string) input('q'); $like = '%' . $q . '%';
            $where = ['(name LIKE ? OR part_no LIKE ? OR brand LIKE ? OR category LIKE ? OR rack_no LIKE ?)'];
            $params = [$like, $like, $like, $like, $like];
            $f = (string) input('filter');
            $where[] = $f === 'inactive' ? 'is_active = 0' : 'is_active = 1';
            if ($f === 'alerts') $where[] = 'stock <= min_stock';
            if ($f === 'out') $where[] = 'stock <= 0';
            $list = rows('SELECT * FROM parts WHERE ' . implode(' AND ', $where) . ' ORDER BY name LIMIT 100 OFFSET ' . max(0, input_int('offset')), $params);
            $html = '';
            foreach ($list as $p) {
                $cls = $p['stock'] < 0 ? 'neg' : ($p['stock'] <= $p['min_stock'] ? 'low' : '');
                $html .= '<tr class="prow" data-href="part.php?id=' . $p['id'] . '"><td>' . ($p['part_no'] ? '<small class="text-muted">(' . e($p['part_no']) . ')</small> ' : '') . e($p['name']) . '</td>'
                    . '<td class="' . $cls . '">' . qty($p['stock']) . '</td><td>' . qty($p['mrp']) . '</td><td>' . e($p['rack_no']) . '</td>'
                    . '<td class="go"><i class="bi bi-play-circle-fill"></i></td></tr>';
            }
            json_ok(['html' => $html, 'count' => count($list)]);

        case 'save':
            require_post();
            $id = input_int('id');
            $d = part_data($_POST);
            if ($d['part_no'] && val('SELECT id FROM parts WHERE part_no = ? AND id <> ?', [$d['part_no'], $id])) throw new ValidationError(t('part_no_exists'));
            $id = tx(function () use ($id, $d) {
                if ($id) { db_update('parts', $d, 'id = ?', [$id]); return $id; }
                $id = db_insert('parts', $d + ['stock' => 0]);
                $open = input_num('opening_stock');
                stock_move($id, 'opening', $open, $d['purchase_price'], 'opening', null, 'Opening stock');
                return $id;
            });
            json_ok(['id' => $id], t('saved'));

        case 'adjust':
            require_post();
            $id = input_int('id');
            $p = row('SELECT * FROM parts WHERE id = ?', [$id]);
            if (!$p) json_err(t('not_found'), 404);
            $mode = input('mode');
            $qn = input_num('qty');
            if ($mode === 'set') $delta = $qn - (float) $p['stock'];
            else $delta = $mode === 'remove' ? -abs($qn) : abs($qn);
            if (abs($delta) < 0.001) throw new ValidationError(t('no_change'));
            $note = trim((string) input('note'));
            if ($note === '') throw new ValidationError(t('reason') . ' ' . t('is_required'));
            tx(fn() => stock_move($id, 'adjust', $delta, (float) $p['purchase_price'], 'manual', null, $note));
            json_ok([], t('stock_updated'));

        case 'toggle':
            require_post();
            q('UPDATE parts SET is_active = 1 - is_active WHERE id = ?', [input_int('id')]);
            json_ok([], t('saved'));

        case 'delete':
            require_post();
            if (!is_admin()) json_err(t('no_permission'), 403);
            $id = input_int('id');
            $used = val('SELECT COUNT(*) FROM job_card_parts WHERE part_id = ?', [$id]) + val('SELECT COUNT(*) FROM invoice_items WHERE part_id = ?', [$id])
                  + val('SELECT COUNT(*) FROM purchase_order_items WHERE part_id = ?', [$id]);
            if ($used) throw new ValidationError(t('part_in_use'));
            tx(function () use ($id) { q('DELETE FROM stock_movements WHERE part_id = ?', [$id]); q('DELETE FROM parts WHERE id = ?', [$id]); });
            json_ok([], t('deleted'));

        case 'import':
            require_post();
            if (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) throw new ValidationError(t('upload_failed'));
            if ($_FILES['csv']['size'] > 2 * 1024 * 1024) throw new ValidationError(t('file_too_large'));
            $fh = fopen($_FILES['csv']['tmp_name'], 'r');
            $head = fgetcsv($fh);
            if (!$head) throw new ValidationError(t('invalid_csv'));
            $head = array_map(fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $head);
            if (!in_array('name', $head, true)) throw new ValidationError(t('invalid_csv') . ': name column missing');
            $added = $updated = $skipped = 0;
            tx(function () use ($fh, $head, &$added, &$updated, &$skipped) {
                while (($r = fgetcsv($fh)) !== false) {
                    if (count(array_filter($r, fn($x) => trim((string) $x) !== '')) === 0) continue;
                    $row = array_combine($head, array_pad(array_slice($r, 0, count($head)), count($head), ''));
                    try { $d = part_data($row); } catch (ValidationError $e) { $skipped++; continue; }
                    $ex = $d['part_no'] ? row('SELECT * FROM parts WHERE part_no = ?', [$d['part_no']]) : row('SELECT * FROM parts WHERE name = ?', [$d['name']]);
                    $stock = isset($row['stock']) && is_numeric($row['stock']) ? (float) $row['stock'] : null;
                    if ($ex) {
                        db_update('parts', $d, 'id = ?', [$ex['id']]);
                        if ($stock !== null && abs($stock - (float) $ex['stock']) > 0.001) stock_move((int) $ex['id'], 'adjust', $stock - (float) $ex['stock'], $d['purchase_price'], 'manual', null, 'CSV import');
                        $updated++;
                    } else {
                        $id = db_insert('parts', $d + ['stock' => 0]);
                        stock_move($id, 'opening', $stock ?? 0, $d['purchase_price'], 'opening', null, 'CSV import');
                        $added++;
                    }
                }
            });
            json_ok([], sprintf(t('csv_result'), $added, $updated, $skipped));
    }
});
