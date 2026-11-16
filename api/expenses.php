<?php
/** Expenses API. */
require __DIR__ . '/../includes/bootstrap.php';

api_run(function ($action) {
    api_perm('accounts');
    switch ($action) {
        case 'save':
            require_post();
            $id = input_int('id');
            $cat = input_int('category_id');
            $newCat = trim((string) input('new_category'));
            if ($newCat !== '') {
                $cat = (int) (val('SELECT id FROM expense_categories WHERE name = ?', [$newCat]) ?: db_insert('expense_categories', ['name' => mb_substr($newCat, 0, 60)]));
            }
            if (!val('SELECT id FROM expense_categories WHERE id = ?', [$cat])) throw new ValidationError(t('category') . ' ' . t('is_required'));
            $amt = input_num('amount');
            if ($amt <= 0) throw new ValidationError(t('amount_positive'));
            $jc = input_int('job_card_id');
            $d = ['category_id' => $cat, 'expense_date' => valid_date((string) input('expense_date'), false), 'amount' => round($amt, 2),
                  'mode' => one_of((string) input('mode'), PAY_MODES, 'cash'), 'paid_to' => nullable((string) input('paid_to')),
                  'note' => nullable((string) input('note'))];
            if ($id) db_update('expenses', $d, 'id = ?', [$id]);
            else $id = db_insert('expenses', $d + ['job_card_id' => $jc && val('SELECT id FROM job_cards WHERE id=?', [$jc]) ? $jc : null, 'user_id' => uid()]);
            json_ok(['id' => $id], t('saved'));

        case 'delete':
            require_post();
            if (!is_admin()) json_err(t('no_permission'), 403);
            q('DELETE FROM expenses WHERE id = ?', [input_int('id')]);
            json_ok([], t('deleted'));
    }
});
