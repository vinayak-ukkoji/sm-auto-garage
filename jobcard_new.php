<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('prices');
$tab = 'service';
$title = t('customer_repair_order');
$back = 'javascript:goBack()';
$techs = rows("SELECT id, name FROM users WHERE is_active = 1 AND role = 'technician' ORDER BY name");
$preVehicle = input_int('vehicle_id');
$makes = ['Maruti Suzuki', 'Hyundai', 'Tata', 'Mahindra', 'Honda', 'Toyota', 'Kia', 'Renault', 'Nissan', 'Volkswagen', 'Skoda', 'MG', 'Ford', 'Jeep', 'Citroen', 'BMW', 'Mercedes-Benz', 'Audi', 'Chevrolet', 'Fiat', 'Datsun', 'Isuzu', 'Force'];
include __DIR__ . '/includes/header.php';
?>
<!-- STEP 1: customer + vehicle -->
<div id="step1">
  <div class="hint"><?= te('search_vehicle_hint') ?></div>
  <div class="search-card position-relative le-add p-0 px-3" style="display:flex">
    <i class="bi bi-search"></i>
    <input type="text" id="regSearch" placeholder="<?= te('vehicle_reg_no') ?>" autocomplete="off" style="text-transform:uppercase">
  </div>

  <form id="f1" style="display:none" autocomplete="off">
    <input type="hidden" name="customer_id">
    <input type="hidden" name="vehicle_id">
    <div class="fsec">
      <div class="fsec-h"><?= te('personal_details') ?></div>
      <div class="frow"><i class="bi bi-person"></i><input name="c_name" placeholder="<?= te('customer_name') ?> *" required maxlength="100"></div>
      <div class="frow"><i class="bi bi-telephone"></i><input name="c_mobile" type="tel" inputmode="numeric" maxlength="10" placeholder="<?= te('mobile_number') ?> *" required></div>
      <div class="frow"><i class="bi bi-envelope"></i><input name="c_email" type="email" placeholder="<?= te('email_address') ?>"></div>
      <div class="more-c" style="display:none">
        <div class="frow"><i class="bi bi-geo-alt"></i><input name="c_address" placeholder="<?= te('address') ?>"></div>
        <div class="frow"><i class="bi bi-buildings"></i><input name="c_city" placeholder="<?= te('city') ?>"></div>
        <div class="frow"><i class="bi bi-upc"></i><input name="c_gstin" placeholder="GSTIN" maxlength="15" style="text-transform:uppercase"></div>
      </div>
      <div class="frow"><a href="#" class="more-link" data-toggle-more=".more-c"><?= te('more') ?></a></div>
    </div>

    <div class="fsec">
      <div class="fsec-h"><?= te('vehicle_details') ?> <a href="#" id="viewHist" style="display:none"><i class="bi bi-hourglass-split"></i> <?= te('view_history') ?></a></div>
      <div class="frow"><i class="bi bi-123"></i><label><?= te('reg_no') ?> *</label><input name="v_reg_no" class="text-end" required style="text-transform:uppercase" maxlength="20"></div>
      <div class="frow"><i class="bi bi-shield"></i><label><?= te('make') ?> *</label><input name="v_make" class="text-end" list="makes" required maxlength="50"></div>
      <div class="frow"><i class="bi bi-car-front"></i><label><?= te('model') ?> *</label><input name="v_model" class="text-end" required maxlength="50"></div>
      <div class="frow"><i class="bi bi-calendar"></i><label><?= te('year') ?></label><input name="v_year" type="number" min="1980" max="<?= date('Y') + 1 ?>" class="text-end"></div>
      <div class="more-v" style="display:none">
        <div class="frow"><i class="bi bi-tag"></i><label><?= te('variant') ?></label><input name="v_variant" class="text-end"></div>
        <div class="frow"><i class="bi bi-fuel-pump"></i><label><?= te('fuel_type') ?></label>
          <select name="v_fuel_type" class="text-end"><?php foreach (['petrol', 'diesel', 'cng', 'ev', 'other'] as $f): ?><option value="<?= $f ?>"><?= te('fuel_' . $f) ?></option><?php endforeach; ?></select></div>
        <div class="frow"><i class="bi bi-palette"></i><label><?= te('color') ?></label><input name="v_color" class="text-end"></div>
        <div class="frow"><i class="bi bi-upc-scan"></i><label><?= te('chassis_no') ?></label><input name="v_chassis_no" class="text-end" style="text-transform:uppercase"></div>
        <div class="frow"><i class="bi bi-gear"></i><label><?= te('engine_no') ?></label><input name="v_engine_no" class="text-end" style="text-transform:uppercase"></div>
        <div class="frow"><i class="bi bi-building"></i><label><?= te('insurance_company') ?></label><input name="v_insurance_company" class="text-end"></div>
        <div class="frow"><i class="bi bi-file-earmark"></i><label><?= te('policy_no') ?></label><input name="v_policy_no" class="text-end"></div>
        <div class="frow"><i class="bi bi-shield-check"></i><label><?= te('insurance_expiry') ?></label><input name="v_insurance_expiry" type="date" class="text-end"></div>
        <div class="frow"><i class="bi bi-cloud-haze2"></i><label><?= te('puc_expiry') ?></label><input name="v_puc_expiry" type="date" class="text-end"></div>
      </div>
      <div class="frow"><a href="#" class="more-link" data-toggle-more=".more-v"><?= te('more') ?></a></div>
    </div>

    <div class="fsec">
      <div class="fsec-h"><?= te('additional_information') ?></div>
      <div class="frow"><i class="bi bi-speedometer2"></i><input name="odometer_in" type="number" min="0" placeholder="<?= te('odometer_km') ?>"></div>
      <div class="fuel-wrap">
        <div class="d-flex align-items-center gap-2"><i class="bi bi-thermometer-low"></i><input type="range" name="fuel_level" min="0" max="100" step="10" value="0" list="fticks"><i class="bi bi-thermometer-high"></i></div>
        <datalist id="fticks"><?php for ($i = 0; $i <= 100; $i += 10) echo "<option value=\"$i\">"; ?></datalist>
        <div class="fl"><?= te('fuel_level') ?> : <span id="fuelVal">0</span>%</div>
      </div>
    </div>
    <button type="submit" class="btn-main mb-3"><?= te('next') ?></button>
  </form>
  <datalist id="makes"><?php foreach ($makes as $m) echo '<option value="' . e($m) . '">'; ?></datalist>
</div>

<!-- STEP 2: complaints, services, parts -->
<div id="step2" style="display:none">
  <div class="fsec">
    <div class="fsec-h"><?= te('customer_complaints') ?></div>
    <div class="p-2 px-3">
      <div class="input-group input-group-sm mb-2">
        <input class="form-control" id="cmpIn" placeholder="<?= te('add_complaint_ph') ?>">
        <button class="btn btn-primary" type="button" id="cmpAdd"><i class="bi bi-plus"></i></button>
      </div>
      <div class="chips" id="cmpList"></div>
    </div>
  </div>
  <div class="fsec"><div class="fsec-h"><?= te('services_labour') ?></div><div id="leLabour"></div></div>
  <div class="fsec"><div class="fsec-h"><?= te('parts') ?></div><div id="leParts"></div></div>
  <div class="fsec">
    <div class="fsec-h"><?= te('other_details') ?></div>
    <div class="frow"><i class="bi bi-person-gear"></i><label><?= te('technician') ?></label>
      <select id="techSel" class="text-end"><option value=""><?= te('not_assigned') ?></option><?php foreach ($techs as $tch): ?><option value="<?= $tch['id'] ?>"><?= e($tch['name']) ?></option><?php endforeach; ?></select></div>
    <div class="frow"><i class="bi bi-calendar-check"></i><label><?= te('expected_delivery') ?></label><input type="datetime-local" id="expDel" class="text-end" value="<?= date('Y-m-d\T18:00', strtotime('+1 day')) ?>"></div>
    <div class="frow"><i class="bi bi-bag"></i><input id="belongings" placeholder="<?= te('belongings_ph') ?>"></div>
    <div class="frow"><i class="bi bi-exclamation-triangle"></i><input id="damage" placeholder="<?= te('damage_ph') ?>"></div>
  </div>
  <div class="box"><div class="kv"><span><?= te('estimated_total') ?></span><b id="grand">₹0.00</b></div></div>
  <button type="button" class="btn-main mb-3" id="createBtn"><?= te('create_repair_order') ?></button>
</div>

<?php ob_start(); ?>
<script>
let step = 1, complaints = [];
function goBack() { if (step === 2) { step = 1; $('#step2').hide(); $('#step1').show(); $('.hdr-title').text(<?= json_encode(t('customer_repair_order')) ?>); window.scrollTo(0, 0); } else history.back(); }
$(function () {
  const f = $('#f1');
  $(document).on('click', '[data-toggle-more]', function (e) { e.preventDefault(); $($(this).data('toggle-more')).slideToggle(150); });
  f.find('[name=fuel_level]').on('input', function () { $('#fuelVal').text(this.value); });

  function fillVehicle(d) {
    const v = d.vehicle, c = d.customer;
    f[0].reset();
    f.find('[name=customer_id]').val(c.id); f.find('[name=vehicle_id]').val(v.id);
    ['name', 'mobile', 'email', 'address', 'city', 'gstin'].forEach(k => f.find('[name=c_' + k + ']').val(c[k] || ''));
    ['reg_no', 'make', 'model', 'year', 'variant', 'fuel_type', 'color', 'chassis_no', 'engine_no', 'insurance_company', 'policy_no', 'insurance_expiry', 'puc_expiry'].forEach(k => f.find('[name=v_' + k + ']').val(v[k] || ''));
    f.find('[name=odometer_in]').attr('placeholder', <?= json_encode(t('odometer_km')) ?> + (v.odometer ? ' (last: ' + v.odometer + ')' : ''));
    $('#viewHist').attr('href', url('vehicle.php?id=' + v.id)).show();
    $('#regSearch').val(v.reg_no);
    f.show();
    if (d.open_order) toast(<?= json_encode(t('vehicle_has_open_order')) ?> + ' ' + d.open_order, 'err');
  }
  function newVehicle(reg) {
    f[0].reset(); f.find('[name=customer_id],[name=vehicle_id]').val('');
    f.find('[name=v_reg_no]').val(reg.toUpperCase().replace(/[^A-Z0-9]/g, ''));
    $('#viewHist').hide(); f.show(); f.find('[name=c_name]').focus();
  }
  autocomplete('#regSearch', t => apiGet('search.php', { type: 'vehicles', q: t }).then(r => r.data),
    it => '<b>' + esc(it.reg_no) + '</b> <small>' + esc(it.make + ' ' + it.model) + ' · ' + esc(it.name) + '</small>',
    it => apiGet('customers.php', { action: 'vehicle', id: it.id }).then(r => fillVehicle(r.data)),
    { extra: t => '<div data-extra="1" class="text-primary"><i class="bi bi-plus-circle"></i> ' + <?= json_encode(t('create_new_vehicle')) ?> + ' "' + esc(t.toUpperCase()) + '"</div>', onExtra: newVehicle });

  // Existing customer by mobile (for new vehicles)
  f.find('[name=c_mobile]').on('change', function () {
    if (f.find('[name=customer_id]').val() || this.value.length < 10) return;
    apiGet('customers.php', { action: 'by_mobile', mobile: this.value }).then(r => {
      if (!r.data) return;
      const c = r.data;
      f.find('[name=customer_id]').val(c.id);
      ['name', 'email', 'address', 'city', 'gstin'].forEach(k => f.find('[name=c_' + k + ']').val(c[k] || ''));
      toast(<?= json_encode(t('existing_customer_found')) ?> + ': ' + c.name);
    });
  });

  <?php if ($preVehicle): ?>apiGet('customers.php', { action: 'vehicle', id: <?= $preVehicle ?> }).then(r => fillVehicle(r.data));<?php endif; ?>

  f.on('submit', function (e) {
    e.preventDefault();
    if (!/^[6-9]\d{9}$/.test(f.find('[name=c_mobile]').val())) { toast(<?= json_encode(t('invalid_mobile')) ?>, 'err'); return; }
    step = 2; $('#step1').hide(); $('#step2').show(); $('.hdr-title').text(<?= json_encode(t('services_parts')) ?>); window.scrollTo(0, 0);
  });

  // Step 2
  function drawCmp() { $('#cmpList').html(complaints.map((c, i) => '<span class="chip">' + esc(c) + '<span class="x" data-i="' + i + '">&times;</span></span>').join('')); }
  $('#cmpAdd').on('click', () => { const v = $('#cmpIn').val().trim(); if (v) { complaints.push(v); $('#cmpIn').val('').focus(); drawCmp(); } });
  $('#cmpIn').on('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); $('#cmpAdd').click(); } });
  $('#cmpList').on('click', '.x', function () { complaints.splice($(this).data('i'), 1); drawCmp(); });

  const upd = () => { if (window.L && window.P) { const a = L.totals(), b = P.totals(); $('#grand').text(inr(Math.round(a.total + b.total))); } };
  window.L = new LineEditor('#leLabour', { kind: 'labour', onChange: upd });
  window.P = new LineEditor('#leParts', { kind: 'parts', onChange: upd });

  $('#createBtn').on('click', function () {
    const pending = $('#cmpIn').val().trim(); if (pending) { complaints.push(pending); $('#cmpIn').val(''); drawCmp(); }
    const data = f.serializeArray();
    data.push({ name: 'complaints', value: JSON.stringify(complaints) }, { name: 'labour', value: L.json() }, { name: 'parts', value: P.json() },
      { name: 'technician_id', value: $('#techSel').val() }, { name: 'expected_delivery', value: $('#expDel').val() },
      { name: 'belongings', value: $('#belongings').val() }, { name: 'damage_notes', value: $('#damage').val() });
    const btn = $(this).prop('disabled', true);
    const send = force => api('jobcards.php?action=create', $.param(data.concat(force ? [{ name: 'force', value: 1 }] : [])));
    send(false).then(r => { location.href = url('jobcard.php?id=' + r.data.id); }, err => {
      const msg = err && err.responseJSON && err.responseJSON.msg;
      if (msg && msg.indexOf(<?= json_encode(t('vehicle_has_open_order')) ?>) === 0 && confirm(msg + '\n\n' + <?= json_encode(t('create_anyway')) ?>)) {
        send(true).then(r => { location.href = url('jobcard.php?id=' + r.data.id); });
      }
    }).always(() => btn.prop('disabled', false));
  });
});
</script>
<?php $page_script = ob_get_clean(); include __DIR__ . '/includes/footer.php'; ?>
