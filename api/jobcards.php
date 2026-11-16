<?php
/** Job card (repair order) API. */
require __DIR__ . '/../includes/bootstrap.php';

function jc_or_404(int $id): array
{
    $jc = row('SELECT * FROM job_cards WHERE id = ?', [$id]);
    if (!$jc) json_err(t('not_found'), 404);
    if (is_tech() && (int) $jc['technician_id'] !== uid()) json_err(t('no_permission'), 403);
    return $jc;
}

function jc_header_fields(): array
{
    $tech = input_int('technician_id');
    if ($tech && !val("SELECT id FROM users WHERE id = ? AND is_active = 1", [$tech])) $tech = 0;
    $exp = input('expected_delivery');
    return [
        'technician_id' => $tech ?: null,
        'odometer_in' => input('odometer_in') !== '' ? max(0, input_int('odometer_in')) : null,
        'fuel_level' => max(0, min(100, input_int('fuel_level'))),
        'expected_delivery' => $exp !== '' && strtotime($exp) ? date('Y-m-d H:i:s', strtotime($exp)) : null,
        'belongings' => nullable((string) input('belongings')),
        'damage_notes' => nullable((string) input('damage_notes')),
        'notes' => nullable((string) input('notes')),
    ];
}

function save_complaints(int $jcId, $raw): void
{
    $list = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($list)) return;
    q('DELETE FROM job_card_complaints WHERE job_card_id = ?', [$jcId]);
    foreach ($list as $c) {
        $txt = trim(is_array($c) ? (string) ($c['complaint'] ?? '') : (string) $c);
        if ($txt === '') continue;
        db_insert('job_card_complaints', ['job_card_id' => $jcId, 'complaint' => mb_substr($txt, 0, 1000),
            'resolution' => is_array($c) ? nullable(trim((string) ($c['resolution'] ?? ''))) : null]);
    }
}

api_run(function ($action) {
    switch ($action) {
        case 'list':
            api_perm('service');
            $status = one_of(input('status', 'all'), array_keys(ORDER_FILTERS), 'all');
            [$list, $total] = query_orders($status, (string) input('q'), max(0, input_int('offset')), 30,
                ['from' => valid_date(input('from')), 'to' => valid_date(input('to')), 'customer_id' => input_int('customer_id'), 'vehicle_id' => input_int('vehicle_id')]);
            json_ok(['html' => implode('', array_map('order_card', $list)), 'count' => count($list), 'total' => $total]);

        case 'create':
            require_post(); api_perm('prices');
            $id = tx(function () {
                // Customer: existing or new
                $cid = input_int('customer_id');
                $cdata = ['name' => input('c_name'), 'mobile' => input('c_mobile'), 'email' => input('c_email'),
                          'address' => input('c_address'), 'city' => input('c_city'), 'gstin' => input('c_gstin')];
                if ($cid) {
                    if (!val('SELECT id FROM customers WHERE id = ?', [$cid])) throw new ValidationError(t('not_found'));
                    upsert_customer($cdata + ['alt_mobile' => val('SELECT alt_mobile FROM customers WHERE id=?', [$cid]) ?? '', 'notes' => val('SELECT notes FROM customers WHERE id=?', [$cid]) ?? ''], $cid);
                } else {
                    $cid = upsert_customer($cdata);
                }
                // Vehicle: existing or new
                $vid = input_int('vehicle_id');
                $vdata = ['reg_no' => input('v_reg_no'), 'make' => input('v_make'), 'model' => input('v_model'), 'variant' => input('v_variant'),
                    'fuel_type' => input('v_fuel_type'), 'year' => input('v_year'), 'color' => input('v_color'), 'chassis_no' => input('v_chassis_no'),
                    'engine_no' => input('v_engine_no'), 'insurance_company' => input('v_insurance_company'), 'policy_no' => input('v_policy_no'),
                    'insurance_expiry' => input('v_insurance_expiry'), 'puc_expiry' => input('v_puc_expiry'), 'odometer' => input('odometer_in')];
                if ($vid) {
                    if (!val('SELECT id FROM vehicles WHERE id = ?', [$vid])) throw new ValidationError(t('not_found'));
                    $vid = upsert_vehicle($vdata, $cid, $vid);
                } else {
                    $vid = upsert_vehicle($vdata, $cid);
                }
                $open = val("SELECT jc_no FROM job_cards WHERE vehicle_id = ? AND status IN ('estimate','approved','in_progress','ready')", [$vid]);
                if ($open && input('force') !== '1') throw new ValidationError(t('vehicle_has_open_order') . ' ' . $open);

                $labour = clean_lines(input('labour'), 'labour');
                $parts = clean_lines(input('parts'), 'parts');
                $jcId = db_insert('job_cards', ['jc_no' => next_number('jobcard'), 'customer_id' => $cid, 'vehicle_id' => $vid,
                    'advisor_id' => uid(), 'status' => 'estimate'] + jc_header_fields());
                save_complaints($jcId, input('complaints'));
                save_jobcard_lines($jcId, $labour, $parts);
                log_status($jcId, 'estimate', 'Created');
                return $jcId;
            });
            json_ok(['id' => $id], t('order_created'));

        case 'save':
            require_post(); api_perm('prices');
            $jc = jc_or_404(input_int('id'));
            if (!in_array($jc['status'], ['estimate', 'approved', 'in_progress', 'ready'], true)) throw new ValidationError(t('order_locked'));
            tx(function () use ($jc) {
                db_update('job_cards', jc_header_fields(), 'id = ?', [$jc['id']]);
                save_complaints((int) $jc['id'], input('complaints'));
                save_jobcard_lines((int) $jc['id'], clean_lines(input('labour'), 'labour'), clean_lines(input('parts'), 'parts'));
                if (input('odometer_in') !== '') q('UPDATE vehicles SET odometer = GREATEST(COALESCE(odometer,0), ?) WHERE id = ?', [input_int('odometer_in'), $jc['vehicle_id']]);
            });
            json_ok(['id' => $jc['id']], t('saved'));

        case 'status':
            require_post();
            $jc = jc_or_404(input_int('id'));
            $to = (string) input('to');
            $allowed = JC_MOVES[$jc['status']] ?? [];
            if (!in_array($to, $allowed, true)) throw new ValidationError(t('status_change_not_allowed'));
            if (is_tech() && !in_array($to, ['in_progress', 'ready'], true)) json_err(t('no_permission'), 403);
            if ($to === 'cancelled' && !can('prices')) json_err(t('no_permission'), 403);
            q('UPDATE job_cards SET status = ? WHERE id = ?', [$to, $jc['id']]);
            log_status((int) $jc['id'], $to, (string) input('note'));
            json_ok([], t('status_updated') . ': ' . jc_label($to));

        case 'toggle_done':
            require_post();
            $jc = jc_or_404(input_int('id'));
            $table = input('kind') === 'parts' ? 'job_card_parts' : 'job_card_labour';
            q("UPDATE $table SET is_done = ? WHERE id = ? AND job_card_id = ?", [input_int('done') ? 1 : 0, input_int('line_id'), $jc['id']]);
            json_ok([], t('saved'));

        case 'invoice':
            require_post(); api_perm('invoice');
            $jc = jc_or_404(input_int('id'));
            $invId = create_invoice_from_jobcard((int) $jc['id']);
            json_ok(['invoice_id' => $invId], t('invoice_created'));

        case 'deliver':
            require_post(); api_perm('prices');
            $jc = jc_or_404(input_int('id'));
            $r = deliver_jobcard((int) $jc['id']);
            $c = row('SELECT name, mobile FROM customers WHERE id = ?', [$jc['customer_id']]);
            $msg = fill_template((string) setting('wa_template_feedback'), ['customer' => $c['name'], 'link' => abs_url('feedback.php?t=' . $r['token'])]);
            json_ok(['wa' => wa_link($c['mobile'], $msg)], t('vehicle_delivered'));

        case 'note':
            require_post();
            $jc = jc_or_404(input_int('id'));
            $note = trim((string) input('note'));
            if ($note === '') throw new ValidationError(t('note') . ' ' . t('is_required'));
            $line = '[' . date('d M Y h:i A') . ' · ' . current_user()['name'] . '] ' . $note;
            q('UPDATE job_cards SET notes = TRIM(CONCAT(COALESCE(notes, \'\'), ?)) WHERE id = ?', ["\n" . $line, $jc['id']]);
            json_ok([], t('saved'));

        case 'checklist':
            require_post();
            $jc = jc_or_404(input_int('id'));
            $items = json_decode((string) input('items'), true) ?: [];
            tx(function () use ($jc, $items) {
                q('DELETE FROM job_card_inspections WHERE job_card_id = ?', [$jc['id']]);
                foreach ($items as $itemId => $d) {
                    if (!val('SELECT id FROM checklist_items WHERE id = ?', [(int) $itemId])) continue;
                    db_insert('job_card_inspections', ['job_card_id' => $jc['id'], 'checklist_item_id' => (int) $itemId,
                        'status' => one_of($d['status'] ?? 'ok', ['ok', 'attention', 'replace', 'na'], 'ok'),
                        'remarks' => nullable(mb_substr(trim((string) ($d['remarks'] ?? '')), 0, 255))]);
                }
            });
            json_ok([], t('saved'));

        case 'complaints':
            require_post();
            $jc = jc_or_404(input_int('id'));
            save_complaints((int) $jc['id'], input('complaints'));
            json_ok([], t('saved'));

        case 'assign':
            require_post(); api_perm('prices');
            $jc = jc_or_404(input_int('id'));
            $tid = input_int('technician_id');
            q('UPDATE job_cards SET technician_id = ? WHERE id = ?', [$tid ?: null, $jc['id']]);
            json_ok([], t('saved'));

        case 'photo':
            require_post();
            $jc = jc_or_404(input_int('id'));
            if (empty($_FILES['photo'])) throw new ValidationError(t('upload_failed'));
            $path = save_upload($_FILES['photo'], 'jobcards');
            db_insert('job_card_photos', ['job_card_id' => $jc['id'], 'file_path' => $path, 'caption' => nullable((string) input('caption'))]);
            json_ok([], t('saved'));

        case 'photo_delete':
            require_post(); api_perm('prices');
            $p = row('SELECT * FROM job_card_photos WHERE id = ?', [input_int('photo_id')]);
            if ($p) {
                jc_or_404((int) $p['job_card_id']);
                q('DELETE FROM job_card_photos WHERE id = ?', [$p['id']]);
                $f = __DIR__ . '/../' . $p['file_path'];
                if (is_file($f)) @unlink($f);
            }
            json_ok([], t('deleted'));
    }
});
