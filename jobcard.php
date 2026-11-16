<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('service');
$tab = 'service';

$id = input_int('id');
$jc = row(ORDER_SELECT . ' WHERE jc.id = ?', [$id]);
if (!$jc || (is_tech() && (int) $jc['technician_id'] !== uid())) { http_response_code(404); $title = t('not_found'); include __DIR__ . '/includes/header.php'; echo empty_state(t('not_found')); include __DIR__ . '/includes/footer.php'; exit; }

$prices = can('prices');
$labour = rows('SELECT * FROM job_card_labour WHERE job_card_id = ? ORDER BY id', [$id]);
$parts = rows('SELECT jp.*, p.stock FROM job_card_parts jp JOIN parts p ON p.id = jp.part_id WHERE jp.job_card_id = ? ORDER BY jp.id', [$id]);
$complaints = rows('SELECT * FROM job_card_complaints WHERE job_card_id = ? ORDER BY id', [$id]);
$checklist = rows('SELECT ci.id, ci.name, ji.status, ji.remarks FROM checklist_items ci LEFT JOIN job_card_inspections ji ON ji.checklist_item_id = ci.id AND ji.job_card_id = ?
    WHERE ci.is_active = 1 OR ji.id IS NOT NULL ORDER BY ci.sort_order, ci.id', [$id]);
$photos = rows('SELECT * FROM job_card_photos WHERE job_card_id = ? ORDER BY id', [$id]);
$log = rows('SELECT l.*, u.name FROM job_card_status_log l LEFT JOIN users u ON u.id = l.user_id WHERE l.job_card_id = ? ORDER BY l.created_at, l.id', [$id]);
$payments = $jc['inv_id'] ? rows('SELECT p.*, u.name AS uname FROM payments p LEFT JOIN users u ON u.id = p.user_id WHERE p.invoice_id = ? ORDER BY p.payment_date, p.id', [$jc['inv_id']]) : [];
$feedback = row('SELECT * FROM feedbacks WHERE job_card_id = ?', [$id]);
$techs = rows("SELECT id, name FROM users WHERE is_active = 1 AND role = 'technician' ORDER BY name");
$vehicle = row('SELECT * FROM vehicles WHERE id = ?', [$jc['vehicle_id']]);
$expCats = rows('SELECT * FROM expense_categories ORDER BY name');

$editable = in_array($jc['status'], ['estimate', 'approved', 'in_progress', 'ready'], true);
$total = $jc['inv_id'] ? (float) $jc['inv_total'] : (float) $jc['grand_total'];
$received = $jc['inv_id'] ? (float) $jc['inv_paid'] : 0;
$due = $jc['inv_id'] ? (float) $jc['inv_balance'] : $total;
[$obadge, $badgeCls] = order_badge($jc);

$stepIdx = ['estimate' => 0, 'approved' => 0, 'in_progress' => 1, 'ready' => 2, 'invoiced' => 3, 'delivered' => 4, 'cancelled' => -1][$jc['status']];
$steps = [t('st_created'), t('st_in_progress'), t('st_ready'), $due > 0 && $stepIdx >= 3 ? t('payment_due') : t('st_invoiced'), t('st_delivered')];
$doneCnt = count(array_filter($labour, fn($l) => $l['is_done'])) + count(array_filter($parts, fn($l) => $l['is_done']));
$lineCnt = count($labour) + count($parts);
$waText = order_wa_text($jc);
$dueReminder = fill_template((string) setting('wa_template_invoice'), ['customer' => $jc['cname'], 'reg_no' => $jc['reg_no'], 'invoice_no' => $jc['invoice_no'] ?? '', 'total' => inr($total), 'balance' => inr($due)]);

$title = t('details');
$back = url('orders.php?status=all');
$header_right = '<button class="hdr-icon" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i></button>'
    . '<button class="btn-hdr-more" data-bs-toggle="modal" data-bs-target="#moreSheet"><i class="bi bi-text-left"></i> ' . te('more_caps') . '</button>';
include __DIR__ . '/includes/header.php';
?>
<div class="det">
  <div class="det-top">
    <div>
      <span class="det-no"><?= e(ltrim(preg_replace('/\D/', '', $jc['jc_no']), '0') ?: $jc['jc_no']) ?></span>
      <span class="badge-st <?= $badgeCls ?>"><?= e(str_replace(' ', '_', strtoupper($obadge))) ?></span>
      <div class="text-muted small mt-1"><?= e(time_ago($jc['created_at'])) ?></div>
    </div>
    <div class="det-center">
      <div class="lbl"><?= $jc['inv_id'] ? te('invoice') : te('job_card') ?></div>
      <div class="val"><?= e($jc['invoice_no'] ?: $jc['jc_no']) ?></div>
    </div>
    <div class="text-end">
      <div class="lbl" style="font-size:15px"><?= $jc['delivered_at'] ? te('completion_date') : te('expected_delivery') ?></div>
      <div class="text-muted" style="font-size:15px"><?= e(fmt_date($jc['delivered_at'] ?: $jc['expected_delivery'])) ?></div>
    </div>
  </div>

  <?php if ($stepIdx >= 0): ?>
  <div class="stepper">
    <?php foreach ($steps as $i => $s): ?>
      <div class="st <?= $i > $stepIdx ? 'todo' : '' ?>"><div class="dot"></div><?= e($s) ?></div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="text-center text-danger fw-bold py-2"><?= te('st_cancelled') ?></div>
  <?php endif; ?>

  <div class="det-cust">
    <div class="n"><?= e($jc['cname']) ?></div>
    <div class="m"><a href="<?= e(tel_link($jc['mobile'])) ?>" class="text-dark"><?= e($jc['mobile']) ?></a></div>
    <div class="v"><?= e(vehicle_name($jc)) ?> <span>(<?= e($jc['reg_no']) ?>)</span></div>
    <div><?= $jc['odometer_in'] ? e(inr($jc['odometer_in'], 0)) . ' KM' : '' ?><?= $jc['fuel_level'] !== null ? ' · ' . te('fuel') . ' ' . (int) $jc['fuel_level'] . '%' : '' ?></div>
    <div class="small text-muted mt-1"><i class="bi bi-person-gear"></i> <?= te('technician') ?>: <?= e($jc['tech_name'] ?: t('not_assigned')) ?></div>
    <a class="wa" href="<?= e(wa_link($jc['mobile'], $waText)) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
  </div>

  <?php if ($prices): ?>
  <div class="tot-row">
    <div><div class="h" style="color:#12926f"><?= te('total') ?></div><?= money($total, 0) ?></div>
    <div><div class="h text-success"><?= te('received') ?></div><?= money($received, 0) ?></div>
    <div><div class="h text-danger"><?= te('due') ?></div><?= money($due, 0) ?></div>
    <div><div class="h" style="color:#c62828"><?= te('discount') ?></div><?= money($jc['discount'], 0) ?></div>
  </div>
  <?php endif; ?>

  <div class="doc-row">
    <div>
      <div class="icons">
        <a href="<?= url('print/jobcard.php?id=' . $id) ?>" target="_blank" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
        <button type="button" title="Copy link" onclick="navigator.clipboard && navigator.clipboard.writeText('<?= e(public_doc_url('jc', $id)) ?>').then(()=>toast('Link copied'))"><i class="bi bi-copy"></i></button>
        <a href="<?= url('print/jobcard.php?id=' . $id . '&print=1') ?>" target="_blank" title="Print"><i class="bi bi-printer-fill"></i></a>
      </div>
      <div class="l"><?= $jc['status'] === 'estimate' ? te('estimate') : te('repair_order') ?></div>
    </div>
    <?php if ($jc['inv_id'] && $prices): ?>
    <div>
      <div class="icons">
        <a href="<?= url('print/invoice.php?id=' . $jc['inv_id']) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i></a>
        <a href="<?= url('invoice.php?id=' . $jc['inv_id']) ?>" title="Open"><i class="bi bi-box-arrow-up-right"></i></a>
        <a href="<?= url('print/invoice.php?id=' . $jc['inv_id'] . '&print=1') ?>" target="_blank"><i class="bi bi-printer-fill"></i></a>
      </div>
      <div class="l"><?= te('invoice') ?></div>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($complaints): ?>
  <div class="sec-h"><?= te('customer_complaints') ?></div>
  <div class="line-list">
    <?php foreach ($complaints as $c): ?>
      <div class="line-item"><div class="d">- <?= e($c['complaint']) ?><?= $c['resolution'] ? '<div class="small text-success">↳ ' . e($c['resolution']) . '</div>' : '' ?></div></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="sec-h mt-2"><?= te('services') ?>
    <?php if ($editable && $prices): ?><a class="btn-prog" href="<?= url('jobcard_edit.php?id=' . $id) ?>"><i class="bi bi-pencil-fill"></i> <?= te('edit') ?></a>
    <?php elseif ($lineCnt): ?><span class="btn-prog"><i class="bi bi-check2-square"></i> <?= $doneCnt ?>/<?= $lineCnt ?></span><?php endif; ?>
  </div>
  <div class="line-list">
    <?php if (!$labour): ?><div class="line-item text-muted"><?= te('no_services_added') ?></div><?php endif; ?>
    <?php foreach ($labour as $l): ?>
      <label class="line-item">
        <span class="d">- <?= e($l['description']) ?><?= (float) $l['qty'] != 1 ? ' (' . qty($l['qty']) . ')' : '' ?></span>
        <?php if ($prices): ?><span class="a"><?= money($l['amount']) ?></span><?php endif; ?>
        <input type="checkbox" class="done-cb" data-kind="labour" data-line="<?= $l['id'] ?>" <?= $l['is_done'] ? 'checked' : '' ?> <?= $editable && $jc['status'] !== 'estimate' ? '' : 'disabled' ?>>
      </label>
    <?php endforeach; ?>
  </div>

  <div class="sec-h"><?= te('parts') ?>
    <?php if ($editable && $prices): ?><a class="btn-prog" href="<?= url('jobcard_edit.php?id=' . $id) ?>"><i class="bi bi-pencil-fill"></i> <?= te('edit') ?></a><?php endif; ?>
  </div>
  <div class="line-list">
    <?php if (!$parts): ?><div class="line-item text-muted"><?= te('no_parts_added') ?></div><?php endif; ?>
    <?php foreach ($parts as $p): ?>
      <label class="line-item">
        <span class="d">- <?= e($p['description']) ?> (<?= qty($p['qty']) ?>)
          <?php if ($editable && (float) $p['stock'] < (float) $p['qty']): ?><small class="neg d-block"><?= te('low_stock') ?>: <?= qty($p['stock']) ?></small><?php endif; ?></span>
        <?php if ($prices): ?><span class="a"><?= money($p['amount']) ?></span><?php endif; ?>
        <input type="checkbox" class="done-cb" data-kind="parts" data-line="<?= $p['id'] ?>" <?= $p['is_done'] ? 'checked' : '' ?> <?= $editable && $jc['status'] !== 'estimate' ? '' : 'disabled' ?>>
      </label>
    <?php endforeach; ?>
  </div>

  <?php if ($prices && $lineCnt): ?>
  <div class="mx-3 totals-box">
    <div class="kv"><span><?= te('labour') ?></span><span><?= money($jc['labour_total']) ?></span></div>
    <div class="kv"><span><?= te('parts') ?></span><span><?= money($jc['parts_total']) ?></span></div>
    <div class="kv"><span>GST</span><span><?= money($jc['tax_total']) ?></span></div>
    <div class="kv"><span><?= te('grand_total') ?></span><span><?= money($jc['grand_total']) ?></span></div>
  </div>
  <?php endif; ?>

  <?php $flag = array_filter($checklist, fn($c) => in_array($c['status'], ['attention', 'replace'], true)); $filled = array_filter($checklist, fn($c) => $c['status'] !== null); ?>
  <div class="sec-h mt-2"><?= te('inspection_checklist') ?> <button class="btn-prog" data-bs-toggle="modal" data-bs-target="#chkModal"><i class="bi bi-ui-checks"></i> <?= $filled ? te('edit') : te('fill') ?></button></div>
  <div class="line-list">
    <?php if (!$filled): ?><div class="line-item text-muted"><?= te('checklist_not_filled') ?></div>
    <?php elseif (!$flag): ?><div class="line-item text-success"><i class="bi bi-check-circle me-2"></i><?= count($filled) ?> <?= te('items_ok') ?></div>
    <?php else: foreach ($flag as $c): ?>
      <div class="line-item"><span class="d"><?= e($c['name']) ?><?= $c['remarks'] ? ' – <small class="text-muted">' . e($c['remarks']) . '</small>' : '' ?></span>
        <span class="badge <?= $c['status'] === 'replace' ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= te('chk_' . $c['status']) ?></span></div>
    <?php endforeach; endif; ?>
  </div>

  <?php if ($jc['belongings'] || $jc['damage_notes'] || $jc['notes']): ?>
  <div class="sec-h mt-2"><?= te('notes') ?></div>
  <div class="mx-3 p-2 small" style="white-space:pre-line">
    <?php if ($jc['belongings']): ?><div><b><?= te('belongings') ?>:</b> <?= e($jc['belongings']) ?></div><?php endif; ?>
    <?php if ($jc['damage_notes']): ?><div><b><?= te('damage_notes') ?>:</b> <?= e($jc['damage_notes']) ?></div><?php endif; ?>
    <?php if ($jc['notes']): ?><div class="mt-1"><?= e($jc['notes']) ?></div><?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="sec-h mt-2"><?= te('photos') ?> <label class="btn-prog mb-0" style="cursor:pointer"><i class="bi bi-camera-fill"></i> <?= te('add') ?><input type="file" id="photoIn" accept="image/*" capture="environment" hidden></label></div>
  <div class="mx-3 py-2 d-flex flex-wrap gap-2">
    <?php if (!$photos): ?><span class="text-muted small"><?= te('no_photos') ?></span><?php endif; ?>
    <?php foreach ($photos as $ph): ?>
      <div class="position-relative">
        <a href="<?= e(url($ph['file_path'])) ?>" target="_blank"><img src="<?= e(url($ph['file_path'])) ?>" style="width:84px;height:84px;object-fit:cover;border-radius:3px"></a>
        <?php if ($prices): ?><button class="btn btn-sm btn-light position-absolute top-0 end-0 p-0 px-1" data-post="jobcards.php?action=photo_delete" data-params='{"photo_id":<?= (int) $ph['id'] ?>}' data-confirm="<?= te('are_you_sure') ?>" data-reload>&times;</button><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($payments): ?>
  <div class="sec-h mt-2"><?= te('payments') ?></div>
  <div class="line-list">
    <?php foreach ($payments as $p): ?>
      <div class="line-item"><span class="d"><?= e(fmt_date($p['payment_date'])) ?> · <?= te('mode_' . $p['mode']) ?><?= $p['reference_no'] ? ' · ' . e($p['reference_no']) : '' ?></span><span class="a text-success"><?= money($p['amount']) ?></span></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Main action -->
  <div class="pb-2">
  <?php $mainBtn = function ($label, $to, $cls = '') use ($id) {
      return '<button class="btn-main ' . $cls . '" data-post="jobcards.php?action=status" data-params=\'' . e(json_encode(['id' => $id, 'to' => $to])) . '\' data-reload>' . e($label) . '</button>';
  };
  switch ($jc['status']) {
      case 'estimate':
          if ($prices) echo $mainBtn(t('approve_estimate'), 'approved');
          if ($prices) echo '<a class="btn-main outline text-center pt-2" href="' . e(wa_link($jc['mobile'], $waText)) . '" target="_blank"><i class="bi bi-whatsapp"></i> ' . te('send_estimate') . '</a>';
          break;
      case 'approved': echo $mainBtn(t('start_work'), 'in_progress'); break;
      case 'in_progress': echo $mainBtn(t('mark_vehicle_ready'), 'ready'); break;
      case 'ready':
          if (can('invoice')) echo '<button class="btn-main" data-post="jobcards.php?action=invoice" data-params=\'{"id":' . $id . '}\' data-confirm="' . te('confirm_create_invoice') . '" data-reload>' . te('create_invoice') . '</button>';
          break;
  }
  if ($prices && $jc['inv_id'] && $due > 0) echo '<button class="btn-main" data-bs-toggle="modal" data-bs-target="#payModal">' . te('add_payment') . '</button>';
  if ($prices && $jc['status'] === 'invoiced') echo '<button class="btn-main outline" data-post="jobcards.php?action=deliver" data-params=\'{"id":' . $id . '}\' data-confirm="' . e($due > 0 ? t('deliver_with_due') : t('confirm_deliver')) . '" data-reload>' . te('deliver_vehicle') . '</button>';
  if ($prices && $jc['status'] === 'delivered' && $feedback && $feedback['rating'] === null) {
      $fbMsg = fill_template((string) setting('wa_template_feedback'), ['customer' => $jc['cname'], 'link' => abs_url('feedback.php?t=' . $feedback['token'])]);
      echo '<a class="btn-main outline text-center pt-2" target="_blank" href="' . e(wa_link($jc['mobile'], $fbMsg)) . '"><i class="bi bi-star"></i> ' . te('send_feedback_link') . '</a>';
  }
  if ($feedback && $feedback['rating'] !== null) echo '<div class="text-center mt-3">' . stars((int) $feedback['rating']) . '<div class="small text-muted">' . e($feedback['comment']) . '</div></div>';
  ?>
  </div>
</div>

<!-- MORE sheet -->
<div class="modal fade sheet" id="moreSheet" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="sheet-title"><?= te('order') ?> # <?= e($jc['jc_no']) ?></div>
  <?php if ($editable && $prices): ?><a href="<?= url('jobcard_edit.php?id=' . $id) ?>"><i class="bi bi-pencil-fill"></i><?= te('edit_order') ?></a><?php endif; ?>
  <button class="si" data-bs-toggle="modal" data-bs-target="#chkModal"><i class="bi bi-card-checklist"></i><?= te('create_edit_checklist') ?></button>
  <button class="si" data-bs-toggle="modal" data-bs-target="#logModal"><i class="bi bi-check2-square"></i><?= te('activity_log') ?></button>
  <button class="si" data-bs-toggle="modal" data-bs-target="#noteModal"><i class="bi bi-quote"></i><?= te('add_note') ?></button>
  <?php if ($prices): ?>
    <button class="si" data-bs-toggle="modal" data-bs-target="#techModal"><i class="bi bi-person-gear"></i><?= te('assign_technician') ?></button>
    <?php if (can('accounts')): ?><button class="si" data-bs-toggle="modal" data-bs-target="#expModal"><i class="bi bi-cash-stack"></i><?= te('add_expense') ?></button><?php endif; ?>
    <?php if ($jc['inv_id'] && $due > 0): ?>
      <button class="si" data-bs-toggle="modal" data-bs-target="#payModal"><i class="bi bi-cash"></i><?= te('add_part_payment') ?></button>
      <a href="<?= e(wa_link($jc['mobile'], $dueReminder)) ?>" target="_blank"><i class="bi bi-alarm"></i><?= te('send_payment_reminder') ?></a>
    <?php endif; ?>
  <?php endif; ?>
  <a href="<?= url('print/jobcard.php?id=' . $id . '&gatepass=1') ?>" target="_blank"><i class="bi bi-box-arrow-right"></i><?= te('generate_gatepass') ?></a>
  <a href="<?= url('print/jobcard.php?id=' . $id) ?>" target="_blank"><i class="bi bi-printer"></i><?= te('print_job_card') ?></a>
  <?php if ($jc['inv_id'] && $prices): ?><a href="<?= url('invoice.php?id=' . $jc['inv_id']) ?>"><i class="bi bi-receipt"></i><?= te('view_invoice') ?></a><?php endif; ?>
  <a href="<?= url('vehicle.php?id=' . $jc['vehicle_id']) ?>"><i class="bi bi-car-front"></i><?= te('vehicle_history') ?></a>
  <?php if ($prices && in_array('cancelled', JC_MOVES[$jc['status']] ?? [], true)): ?>
    <button class="si text-danger" data-post="jobcards.php?action=status" data-params='{"id":<?= $id ?>,"to":"cancelled"}' data-confirm="<?= te('confirm_cancel_order') ?>" data-reload><i class="bi bi-x-circle"></i><?= te('cancel_order') ?></button>
  <?php endif; ?>
  <?php foreach (JC_MOVES[$jc['status']] ?? [] as $to): if (in_array($to, ['cancelled'], true) || (is_tech() && !in_array($to, ['in_progress', 'ready'], true))) continue; ?>
    <button class="si" data-post="jobcards.php?action=status" data-params='{"id":<?= $id ?>,"to":"<?= $to ?>"}' data-reload><i class="bi bi-arrow-left-right"></i><?= te('move_to') ?>: <?= e(jc_label($to)) ?></button>
  <?php endforeach; ?>
  <button class="si" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i><?= te('close') ?></button>
</div></div></div>

<!-- Checklist -->
<div class="modal fade" id="chkModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><?= te('inspection_checklist') ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body p-0">
    <table class="table-x" id="chkTable">
      <?php foreach ($checklist as $c): ?>
      <tr data-item="<?= $c['id'] ?>">
        <td><?= e($c['name']) ?><input class="form-control form-control-sm mt-1 rm" placeholder="<?= te('remarks') ?>" value="<?= e($c['remarks']) ?>"></td>
        <td style="width:120px"><select class="form-select form-select-sm st">
          <?php foreach (['ok', 'attention', 'replace', 'na'] as $s): ?><option value="<?= $s ?>" <?= ($c['status'] ?? 'ok') === $s ? 'selected' : '' ?>><?= te('chk_' . $s) ?></option><?php endforeach; ?>
        </select></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="modal-footer"><button class="btn btn-primary w-100" id="chkSave"><?= te('save') ?></button></div>
</div></div></div>

<!-- Activity log -->
<div class="modal fade" id="logModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><?= te('activity_log') ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <?php foreach ($log as $l): ?>
      <div class="kv"><span><?= e(fmt_dt($l['created_at'])) ?><br><small><?= e($l['name'] ?? '') ?><?= $l['note'] ? ' · ' . e($l['note']) : '' ?></small></span><span class="badge-st <?= jc_class($l['status']) ?>" style="height:fit-content"><?= e(jc_label($l['status'])) ?></span></div>
    <?php endforeach; ?>
  </div>
</div></div></div>

<!-- Note -->
<div class="modal fade" id="noteModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="jobcards.php?action=note" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('add_note') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id" value="<?= $id ?>"><textarea name="note" class="form-control" rows="3" required maxlength="1000"></textarea></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>

<!-- Technician -->
<div class="modal fade" id="techModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="jobcards.php?action=assign" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('assign_technician') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id" value="<?= $id ?>">
    <select name="technician_id" class="form-select"><option value=""><?= te('not_assigned') ?></option>
      <?php foreach ($techs as $tch): ?><option value="<?= $tch['id'] ?>" <?= (int) $jc['technician_id'] === (int) $tch['id'] ? 'selected' : '' ?>><?= e($tch['name']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>

<?php if ($jc['inv_id']): ?>
<!-- Payment -->
<div class="modal fade" id="payModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="payments.php?action=add" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('add_payment') ?> · <?= e($jc['invoice_no']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="invoice_id" value="<?= (int) $jc['inv_id'] ?>">
    <div class="mb-2"><label class="form-label"><?= te('amount') ?> (<?= te('due') ?> <?= money($due) ?>)</label><input type="number" step="0.01" min="0.01" max="<?= $due ?>" name="amount" value="<?= $due ?>" class="form-control" required></div>
    <div class="row g-2">
      <div class="col-6"><label class="form-label"><?= te('payment_mode') ?></label><select name="mode" class="form-select"><?= mode_options() ?></select></div>
      <div class="col-6"><label class="form-label"><?= te('date') ?></label><input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" class="form-control" required></div>
    </div>
    <div class="mt-2"><label class="form-label"><?= te('reference_no') ?></label><input name="reference_no" class="form-control" maxlength="60"></div>
    <div class="mt-2"><label class="form-label"><?= te('note') ?></label><input name="note" class="form-control" maxlength="255"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save_payment') ?></button></div>
</form></div></div>
<?php endif; ?>

<!-- Expense -->
<div class="modal fade" id="expModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="expenses.php?action=save" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('add_expense') ?> · <?= e($jc['jc_no']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="job_card_id" value="<?= $id ?>">
    <div class="row g-2">
      <div class="col-6"><label class="form-label"><?= te('category') ?></label><select name="category_id" class="form-select"><?php foreach ($expCats as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-6"><label class="form-label"><?= te('amount') ?></label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
      <div class="col-6"><label class="form-label"><?= te('date') ?></label><input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" class="form-control" required></div>
      <div class="col-6"><label class="form-label"><?= te('payment_mode') ?></label><select name="mode" class="form-select"><?= mode_options() ?></select></div>
      <div class="col-12"><label class="form-label"><?= te('paid_to') ?></label><input name="paid_to" class="form-control"></div>
      <div class="col-12"><label class="form-label"><?= te('note') ?></label><input name="note" class="form-control" value="<?= e($jc['jc_no'] . ' ' . $jc['reg_no']) ?>"></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>

<?php ob_start(); ?>
<script>
$(function () {
  const id = <?= $id ?>;
  // Bootstrap can't stack modals: close the sheet before opening another modal from it
  $('#moreSheet').on('click', '[data-bs-toggle=modal]', function (e) {
    e.preventDefault(); e.stopPropagation();
    const target = $(this).data('bs-target');
    bootstrap.Modal.getInstance('#moreSheet').hide();
    $('#moreSheet').one('hidden.bs.modal', () => bootstrap.Modal.getOrCreateInstance(target).show());
  });
  $('.done-cb').on('change', function () {
    api('jobcards.php?action=toggle_done', { id: id, kind: $(this).data('kind'), line_id: $(this).data('line'), done: this.checked ? 1 : 0 });
  });
  $('#chkSave').on('click', function () {
    const items = {};
    $('#chkTable tr').each(function () { items[$(this).data('item')] = { status: $(this).find('.st').val(), remarks: $(this).find('.rm').val() }; });
    api('jobcards.php?action=checklist', { id: id, items: JSON.stringify(items) }).then(() => location.reload());
  });
  $('#photoIn').on('change', function () {
    if (!this.files.length) return;
    const fd = new FormData(); fd.append('id', id); fd.append('photo', this.files[0]);
    apiForm('jobcards.php?action=photo', fd).then(() => location.reload());
  });
});
</script>
<?php $page_script = ob_get_clean(); include __DIR__ . '/includes/footer.php'; ?>
