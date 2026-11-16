<?php
/** Reminders (service / insurance / PUC) API. */
require __DIR__ . '/../includes/bootstrap.php';

api_run(function ($action) {
    api_perm('reminders');
    switch ($action) {
        case 'mark':
            require_post();
            $id = input_int('id');
            $to = one_of((string) input('to'), ['sent', 'done', 'snoozed', 'pending'], 'sent');
            $data = ['status' => $to];
            if ($to === 'sent') $data['last_sent_at'] = date('Y-m-d H:i:s');
            if ($to === 'snoozed') $data['snooze_until'] = date('Y-m-d', strtotime('+' . max(1, min(60, input_int('days', 7))) . ' days'));
            db_update('reminders', $data, 'id = ?', [$id]);
            json_ok([], t('saved'));

        case 'update_expiry':
            require_post();
            $vid = input_int('vehicle_id');
            $col = ['insurance' => 'insurance_expiry', 'puc' => 'puc_expiry', 'service' => 'next_service_date'][(string) input('type')] ?? null;
            if (!$col || !val('SELECT id FROM vehicles WHERE id = ?', [$vid])) throw new ValidationError(t('not_found'));
            $date = valid_date((string) input('date'), false);
            tx(function () use ($vid, $col, $date) {
                q("UPDATE vehicles SET $col = ? WHERE id = ?", [$date, $vid]);
                if (input('policy_no') !== '') q('UPDATE vehicles SET policy_no = ? WHERE id = ?', [(string) input('policy_no'), $vid]);
                if (input('insurance_company') !== '') q('UPDATE vehicles SET insurance_company = ? WHERE id = ?', [(string) input('insurance_company'), $vid]);
                sync_vehicle_reminders($vid);
            });
            json_ok([], t('saved'));
    }
});
