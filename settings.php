<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('settings');
$tab = 'more';
$s = setting();
$labour = rows('SELECT * FROM labour_rates ORDER BY is_active DESC, name');
$checks = rows('SELECT * FROM checklist_items ORDER BY sort_order, id');
$taxes = rows('SELECT * FROM tax_rates ORDER BY rate');
$cats = rows('SELECT c.*, (SELECT COUNT(*) FROM expenses e WHERE e.category_id = c.id) AS n FROM expense_categories c ORDER BY name');
$title = t('settings');
$back = url('more.php');
include __DIR__ . '/includes/header.php';
?>
<div class="accordion" id="acc">
  <!-- Garage profile -->
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#s1"><i class="bi bi-shop me-2"></i><?= te('garage_profile') ?></button></h2>
    <div id="s1" class="accordion-collapse collapse show" data-bs-parent="#acc"><div class="accordion-body">
      <form data-api="settings.php?action=profile" data-reload enctype="multipart/form-data">
        <div class="row g-2">
          <div class="col-12 text-center">
            <?php if ($s['logo']): ?><img src="<?= e(url($s['logo'])) ?>" style="max-height:70px" class="mb-2"><br><label class="small"><input type="checkbox" name="remove_logo" value="1"> <?= te('remove_logo') ?></label><?php endif; ?>
          </div>
          <div class="col-12"><label class="form-label"><?= te('logo') ?> (PNG/JPG, max <?= UPLOAD_MAX_MB ?>MB)</label><input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="form-control"></div>
          <div class="col-12"><label class="form-label"><?= te('garage_name') ?> *</label><input name="garage_name" value="<?= e($s['garage_name']) ?>" class="form-control" required></div>
          <div class="col-12"><label class="form-label"><?= te('tagline') ?></label><input name="tagline" value="<?= e($s['tagline']) ?>" class="form-control"></div>
          <div class="col-12"><label class="form-label"><?= te('address') ?></label><textarea name="address" class="form-control" rows="2"><?= e($s['address']) ?></textarea></div>
          <div class="col-6"><label class="form-label"><?= te('city') ?></label><input name="city" value="<?= e($s['city']) ?>" class="form-control"></div>
          <div class="col-6"><label class="form-label"><?= te('pincode') ?></label><input name="pincode" value="<?= e($s['pincode']) ?>" class="form-control"></div>
          <div class="col-6"><label class="form-label"><?= te('phone') ?></label><input name="phone" value="<?= e($s['phone']) ?>" class="form-control"></div>
          <div class="col-6"><label class="form-label"><?= te('email_address') ?></label><input name="email" type="email" value="<?= e($s['email']) ?>" class="form-control"></div>
          <div class="col-12"><div class="form-check form-switch">
            <input type="hidden" name="gst_enabled" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="gstEnabled" name="gst_enabled" value="1" <?= (int) ($s['gst_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="gstEnabled"><?= te('gst_enabled') ?></label></div>
            <small class="text-muted"><?= te('gst_enabled_help') ?></small></div>
          <div class="col-6"><label class="form-label">GSTIN</label><input name="gstin" value="<?= e($s['gstin']) ?>" maxlength="15" class="form-control" style="text-transform:uppercase"></div>
          <div class="col-6"><label class="form-label"><?= te('state') ?></label><select name="state_code" class="form-select">
            <?php foreach (GST_STATES as $code => $name): $code = str_pad((string) $code, 2, '0', STR_PAD_LEFT); ?><option value="<?= $code ?>" <?= $code === $s['state_code'] ? 'selected' : '' ?>><?= $code ?> - <?= e($name) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label">UPI ID</label><input name="upi_id" value="<?= e($s['upi_id']) ?>" class="form-control"></div>
          <div class="col-12"><label class="form-label"><?= te('bank_details') ?></label><textarea name="bank_details" class="form-control" rows="2"><?= e($s['bank_details']) ?></textarea></div>
          <div class="col-12"><label class="form-label"><?= te('invoice_terms') ?></label><textarea name="invoice_terms" class="form-control" rows="3"><?= e($s['invoice_terms']) ?></textarea></div>
        </div>
        <button class="btn btn-primary w-100 mt-3"><?= te('save') ?></button>
      </form>
    </div></div>
  </div>

  <!-- Numbering -->
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s2"><i class="bi bi-hash me-2"></i><?= te('numbering_defaults') ?></button></h2>
    <div id="s2" class="accordion-collapse collapse" data-bs-parent="#acc"><div class="accordion-body">
      <form data-api="settings.php?action=numbering" data-reload>
        <div class="row g-2">
          <?php foreach (['invoice' => 'invoice', 'jobcard' => 'job_card', 'po' => 'purchase_order'] as $k => $lbl): ?>
            <div class="col-6"><label class="form-label"><?= te($lbl) ?> <?= te('prefix') ?></label><input name="<?= $k ?>_prefix" value="<?= e($s[$k . '_prefix']) ?>" class="form-control" maxlength="10"></div>
            <div class="col-6"><label class="form-label"><?= te('next_number') ?></label><input name="<?= $k ?>_next" type="number" min="1" value="<?= (int) $s[$k . '_next'] ?>" class="form-control"></div>
          <?php endforeach; ?>
          <div class="col-6"><label class="form-label"><?= te('service_interval_days') ?></label><input name="service_interval_days" type="number" min="1" value="<?= (int) $s['service_interval_days'] ?>" class="form-control"></div>
          <div class="col-6"><label class="form-label"><?= te('service_interval_km') ?></label><input name="service_interval_km" type="number" min="0" value="<?= (int) $s['service_interval_km'] ?>" class="form-control"></div>
          <div class="col-12"><label class="form-label"><?= te('default_language') ?></label><select name="default_language" class="form-select"><?php foreach (LANGS as $c => $n): ?><option value="<?= $c ?>" <?= $c === $s['default_language'] ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
        </div>
        <button class="btn btn-primary w-100 mt-3"><?= te('save') ?></button>
      </form>
    </div></div>
  </div>

  <!-- Labour rates -->
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s3"><i class="bi bi-tools me-2"></i><?= te('labour_rate_list') ?> (<?= count($labour) ?>)</button></h2>
    <div id="s3" class="accordion-collapse collapse" data-bs-parent="#acc"><div class="accordion-body p-0">
      <table class="table-x">
        <?php foreach ($labour as $l): ?>
        <tr class="<?= $l['is_active'] ? '' : 'text-muted' ?>"><td><?= e($l['name']) ?><br><small>SAC <?= e($l['sac']) ?> · GST <?= qty($l['gst_rate']) ?>%</small></td><td class="text-end"><?= money($l['rate']) ?></td>
          <td class="text-end text-nowrap"><button class="btn btn-sm p-0" data-bs-toggle="modal" data-bs-target="#labModal" data-fill='<?= e(json_encode($l)) ?>'><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm p-0 ms-2" data-post="settings.php?action=toggle" data-params='{"what":"labour","id":<?= $l['id'] ?>}' data-reload title="On/Off"><i class="bi <?= $l['is_active'] ? 'bi-toggle-on text-success' : 'bi-toggle-off' ?> fs-5"></i></button></td></tr>
        <?php endforeach; ?>
      </table>
      <div class="p-2"><button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#labModal" data-fill='{"gst_rate":18,"sac":"998714"}'><i class="bi bi-plus"></i> <?= te('add_labour') ?></button></div>
    </div></div>
  </div>

  <!-- Checklist -->
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s4"><i class="bi bi-ui-checks me-2"></i><?= te('inspection_checklist') ?></button></h2>
    <div id="s4" class="accordion-collapse collapse" data-bs-parent="#acc"><div class="accordion-body p-0">
      <table class="table-x">
        <?php foreach ($checks as $c): ?>
        <tr class="<?= $c['is_active'] ? '' : 'text-muted' ?>"><td style="width:30px"><?= (int) $c['sort_order'] ?></td><td><?= e($c['name']) ?></td>
          <td class="text-end text-nowrap"><button class="btn btn-sm p-0" data-bs-toggle="modal" data-bs-target="#chkModal" data-fill='<?= e(json_encode($c)) ?>'><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm p-0 ms-2" data-post="settings.php?action=toggle" data-params='{"what":"check","id":<?= $c['id'] ?>}' data-reload><i class="bi <?= $c['is_active'] ? 'bi-toggle-on text-success' : 'bi-toggle-off' ?> fs-5"></i></button></td></tr>
        <?php endforeach; ?>
      </table>
      <div class="p-2"><button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#chkModal" data-fill='{"sort_order":<?= count($checks) + 1 ?>}'><i class="bi bi-plus"></i> <?= te('add_item') ?></button></div>
    </div></div>
  </div>

  <!-- Taxes -->
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s5"><i class="bi bi-percent me-2"></i><?= te('tax_rates') ?></button></h2>
    <div id="s5" class="accordion-collapse collapse" data-bs-parent="#acc"><div class="accordion-body">
      <div class="chips mb-2"><?php foreach ($taxes as $tx): ?><span class="chip <?= $tx['is_active'] ? '' : 'text-muted' ?>"><?= e($tx['name']) ?></span><?php endforeach; ?></div>
      <small class="text-muted"><?= te('tax_rates_help') ?></small>
    </div></div>
  </div>

  <!-- Expense categories -->
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s6"><i class="bi bi-tags me-2"></i><?= te('expense_categories') ?></button></h2>
    <div id="s6" class="accordion-collapse collapse" data-bs-parent="#acc"><div class="accordion-body p-0">
      <table class="table-x"><?php foreach ($cats as $c): ?>
        <tr><td><?= e($c['name']) ?> <small class="text-muted">(<?= (int) $c['n'] ?>)</small></td><td class="text-end text-nowrap">
          <button class="btn btn-sm p-0" data-bs-toggle="modal" data-bs-target="#catModal" data-fill='<?= e(json_encode(['id' => $c['id'], 'name' => $c['name']])) ?>'><i class="bi bi-pencil"></i></button>
          <?php if (!$c['n']): ?><button class="btn btn-sm p-0 ms-2 text-danger" data-post="settings.php?action=cat_delete" data-params='{"id":<?= $c['id'] ?>}' data-confirm="<?= te('are_you_sure') ?>" data-reload><i class="bi bi-trash"></i></button><?php endif; ?></td></tr>
      <?php endforeach; ?></table>
      <div class="p-2"><button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#catModal" data-fill='{}'><i class="bi bi-plus"></i> <?= te('add_category') ?></button></div>
    </div></div>
  </div>

  <!-- Templates -->
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#s7"><i class="bi bi-whatsapp me-2"></i><?= te('message_templates') ?></button></h2>
    <div id="s7" class="accordion-collapse collapse" data-bs-parent="#acc"><div class="accordion-body">
      <form data-api="settings.php?action=templates" data-reload>
        <p class="small text-muted"><?= te('template_help') ?> <code>{customer} {reg_no} {type} {due_date} {invoice_no} {total} {balance} {link} {garage_name} {garage_phone}</code></p>
        <?php foreach (['wa_template_reminder' => 'tpl_reminder', 'wa_template_invoice' => 'tpl_invoice', 'wa_template_ready' => 'tpl_ready', 'wa_template_feedback' => 'tpl_feedback'] as $k => $lbl): ?>
          <label class="form-label mt-2"><?= te($lbl) ?></label><textarea name="<?= $k ?>" class="form-control" rows="3"><?= e($s[$k]) ?></textarea>
        <?php endforeach; ?>
        <button class="btn btn-primary w-100 mt-3"><?= te('save') ?></button>
      </form>
    </div></div>
  </div>
</div>
<a class="btn btn-outline-primary w-100 mt-3" href="<?= url('users.php') ?>"><i class="bi bi-person-badge"></i> <?= te('staff_users') ?></a>

<div class="modal fade" id="labModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="settings.php?action=labour_save" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('labour_rate_list') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id"><div class="row g-2">
    <div class="col-12"><label class="form-label"><?= te('name') ?> *</label><input name="name" class="form-control" required></div>
    <div class="col-4"><label class="form-label"><?= te('rate') ?></label><input name="rate" type="number" step="0.01" min="0" class="form-control" required></div>
    <div class="col-4"><label class="form-label">GST %</label><select name="gst_rate" class="form-select"><?= gst_options(18) ?></select></div>
    <div class="col-4"><label class="form-label">SAC</label><input name="sac" class="form-control"></div></div></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>
<div class="modal fade" id="chkModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="settings.php?action=check_save" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('inspection_checklist') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id"><div class="row g-2">
    <div class="col-9"><label class="form-label"><?= te('name') ?> *</label><input name="name" class="form-control" required></div>
    <div class="col-3"><label class="form-label"><?= te('order') ?></label><input name="sort_order" type="number" class="form-control"></div></div></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>
<div class="modal fade" id="catModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="settings.php?action=cat_save" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('expense_categories') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id"><label class="form-label"><?= te('name') ?> *</label><input name="name" class="form-control" required maxlength="60"></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
