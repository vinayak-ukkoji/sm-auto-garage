<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('prices');
$tab = 'service';
$id = input_int('id');
$jc = row(ORDER_SELECT . ' WHERE jc.id = ?', [$id]);
if (!$jc) redirect('orders.php');
if (!in_array($jc['status'], ['estimate', 'approved', 'in_progress', 'ready'], true)) { flash(t('order_locked'), 'error'); redirect('jobcard.php?id=' . $id); }

$labour = rows('SELECT labour_rate_id, description, qty, rate, discount, gst_rate, is_done FROM job_card_labour WHERE job_card_id = ? ORDER BY id', [$id]);
$parts = rows('SELECT jp.part_id, jp.description, jp.qty, jp.rate, jp.discount, jp.gst_rate, jp.is_done, p.stock FROM job_card_parts jp JOIN parts p ON p.id = jp.part_id WHERE jp.job_card_id = ? ORDER BY jp.id', [$id]);
$complaints = rows('SELECT complaint, resolution FROM job_card_complaints WHERE job_card_id = ? ORDER BY id', [$id]);
$techs = rows("SELECT id, name FROM users WHERE is_active = 1 AND role = 'technician' ORDER BY name");

$title = t('edit_order') . ' ' . $jc['jc_no'];
$back = url('jobcard.php?id=' . $id);
include __DIR__ . '/includes/header.php';
?>
<div class="box py-2"><b><?= e($jc['reg_no']) ?></b> · <?= e(vehicle_name($jc)) ?><br><small class="text-muted"><?= e($jc['cname']) ?> · <?= e($jc['mobile']) ?></small></div>

<div class="fsec">
  <div class="fsec-h"><?= te('customer_complaints') ?> <button type="button" id="cmpAdd"><i class="bi bi-plus-circle"></i> <?= te('add') ?></button></div>
  <div id="cmpRows" class="p-2 px-3"></div>
</div>
<div class="fsec"><div class="fsec-h"><?= te('services_labour') ?></div><div id="leLabour"></div></div>
<div class="fsec"><div class="fsec-h"><?= te('parts') ?></div><div id="leParts"></div></div>
<form id="hdr" class="fsec">
  <div class="fsec-h"><?= te('other_details') ?></div>
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="frow"><i class="bi bi-speedometer2"></i><label><?= te('odometer_km') ?></label><input name="odometer_in" type="number" min="0" class="text-end" value="<?= e($jc['odometer_in']) ?>"></div>
  <div class="frow"><i class="bi bi-fuel-pump"></i><label><?= te('fuel_level') ?> %</label><input name="fuel_level" type="number" min="0" max="100" step="10" class="text-end" value="<?= e($jc['fuel_level']) ?>"></div>
  <div class="frow"><i class="bi bi-person-gear"></i><label><?= te('technician') ?></label>
    <select name="technician_id" class="text-end"><option value=""><?= te('not_assigned') ?></option><?php foreach ($techs as $tch): ?><option value="<?= $tch['id'] ?>" <?= (int) $jc['technician_id'] === (int) $tch['id'] ? 'selected' : '' ?>><?= e($tch['name']) ?></option><?php endforeach; ?></select></div>
  <div class="frow"><i class="bi bi-calendar-check"></i><label><?= te('expected_delivery') ?></label><input type="datetime-local" name="expected_delivery" class="text-end" value="<?= $jc['expected_delivery'] ? date('Y-m-d\TH:i', strtotime($jc['expected_delivery'])) : '' ?>"></div>
  <div class="frow"><i class="bi bi-bag"></i><input name="belongings" placeholder="<?= te('belongings_ph') ?>" value="<?= e($jc['belongings']) ?>"></div>
  <div class="frow"><i class="bi bi-exclamation-triangle"></i><input name="damage_notes" placeholder="<?= te('damage_ph') ?>" value="<?= e($jc['damage_notes']) ?>"></div>
  <div class="frow"><i class="bi bi-journal"></i><textarea name="notes" rows="2" placeholder="<?= te('notes') ?>"><?= e($jc['notes']) ?></textarea></div>
</form>
<div class="box"><div class="kv"><span><?= te('estimated_total') ?></span><b id="grand"></b></div></div>
<button type="button" class="btn-main mb-3" id="saveBtn"><?= te('save_changes') ?></button>

<?php ob_start(); ?>
<script>
$(function () {
  let cmp = <?= json_encode($complaints, JSON_HEX_TAG) ?>;
  function draw() {
    $('#cmpRows').html(cmp.map((c, i) => '<div class="d-flex gap-2 mb-2" data-i="' + i + '"><div class="flex-fill"><input class="form-control form-control-sm c" value="' + esc(c.complaint) + '" placeholder="Complaint">' +
      '<input class="form-control form-control-sm mt-1 r" value="' + esc(c.resolution || '') + '" placeholder="Work done / resolution"></div><button class="btn btn-sm text-danger del"><i class="bi bi-trash"></i></button></div>').join('') || '<small class="text-muted">—</small>');
  }
  $('#cmpRows').on('input', 'input', function () { const i = $(this).closest('[data-i]').data('i'); cmp[i][$(this).hasClass('c') ? 'complaint' : 'resolution'] = this.value; });
  $('#cmpRows').on('click', '.del', function () { cmp.splice($(this).closest('[data-i]').data('i'), 1); draw(); });
  $('#cmpAdd').on('click', () => { cmp.push({ complaint: '', resolution: '' }); draw(); $('#cmpRows .c').last().focus(); });
  draw();
  const upd = () => { if (window.L && window.P) $('#grand').text(inr(Math.round(L.totals().total + P.totals().total))); };
  window.L = new LineEditor('#leLabour', { kind: 'labour', lines: <?= json_encode($labour) ?>, onChange: upd });
  window.P = new LineEditor('#leParts', { kind: 'parts', lines: <?= json_encode($parts) ?>, onChange: upd });
  upd();
  $('#saveBtn').on('click', function () {
    const b = $(this).prop('disabled', true);
    const data = $('#hdr').serialize() + '&' + $.param({ complaints: JSON.stringify(cmp), labour: L.json(), parts: P.json() });
    api('jobcards.php?action=save', data).then(() => location.href = url('jobcard.php?id=<?= $id ?>')).always(() => b.prop('disabled', false));
  });
});
</script>
<?php $page_script = ob_get_clean(); include __DIR__ . '/includes/footer.php'; ?>
