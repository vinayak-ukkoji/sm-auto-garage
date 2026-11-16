<?php /* Customer + vehicle add/edit modals. Set $modalCustomerId for the vehicle form. */ ?>
<div class="modal fade" id="custModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><form class="modal-content" data-api="customers.php?action=save" data-redirect="customer.php?id={id}">
  <div class="modal-header"><h5 class="modal-title"><?= te('add_customer') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id">
    <div class="row g-2">
      <div class="col-12"><label class="form-label"><?= te('customer_name') ?> *</label><input name="name" class="form-control" required maxlength="100"></div>
      <div class="col-6"><label class="form-label"><?= te('mobile_number') ?> *</label><input name="mobile" type="tel" maxlength="10" class="form-control" required pattern="[6-9][0-9]{9}"></div>
      <div class="col-6"><label class="form-label"><?= te('alt_mobile') ?></label><input name="alt_mobile" type="tel" maxlength="10" class="form-control"></div>
      <div class="col-12"><label class="form-label"><?= te('email_address') ?></label><input name="email" type="email" class="form-control"></div>
      <div class="col-12"><label class="form-label"><?= te('address') ?></label><textarea name="address" class="form-control" rows="2"></textarea></div>
      <div class="col-6"><label class="form-label"><?= te('city') ?></label><input name="city" class="form-control"></div>
      <div class="col-6"><label class="form-label">GSTIN</label><input name="gstin" class="form-control" maxlength="15" style="text-transform:uppercase"></div>
      <div class="col-12"><label class="form-label"><?= te('notes') ?></label><input name="notes" class="form-control"></div>
    </div></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>

<div class="modal fade" id="vehModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><form class="modal-content" data-api="customers.php?action=vehicle_save" data-redirect="vehicle.php?id={id}">
  <div class="modal-header"><h5 class="modal-title"><?= te('add_vehicle') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="customer_id" value="<?= (int) ($modalCustomerId ?? 0) ?>">
    <div class="row g-2">
      <div class="col-12"><label class="form-label"><?= te('reg_no') ?> *</label><input name="reg_no" class="form-control" required style="text-transform:uppercase" maxlength="20"></div>
      <div class="col-6"><label class="form-label"><?= te('make') ?> *</label><input name="make" class="form-control" required maxlength="50"></div>
      <div class="col-6"><label class="form-label"><?= te('model') ?> *</label><input name="model" class="form-control" required maxlength="50"></div>
      <div class="col-6"><label class="form-label"><?= te('variant') ?></label><input name="variant" class="form-control"></div>
      <div class="col-6"><label class="form-label"><?= te('fuel_type') ?></label><select name="fuel_type" class="form-select"><?php foreach (['petrol', 'diesel', 'cng', 'ev', 'other'] as $f): ?><option value="<?= $f ?>"><?= te('fuel_' . $f) ?></option><?php endforeach; ?></select></div>
      <div class="col-4"><label class="form-label"><?= te('year') ?></label><input name="year" type="number" min="1980" max="<?= date('Y') + 1 ?>" class="form-control"></div>
      <div class="col-4"><label class="form-label"><?= te('color') ?></label><input name="color" class="form-control"></div>
      <div class="col-4"><label class="form-label"><?= te('odometer_km') ?></label><input name="odometer" type="number" min="0" class="form-control"></div>
      <div class="col-6"><label class="form-label"><?= te('chassis_no') ?></label><input name="chassis_no" class="form-control" style="text-transform:uppercase"></div>
      <div class="col-6"><label class="form-label"><?= te('engine_no') ?></label><input name="engine_no" class="form-control" style="text-transform:uppercase"></div>
      <div class="col-6"><label class="form-label"><?= te('insurance_company') ?></label><input name="insurance_company" class="form-control"></div>
      <div class="col-6"><label class="form-label"><?= te('policy_no') ?></label><input name="policy_no" class="form-control"></div>
      <div class="col-6"><label class="form-label"><?= te('insurance_expiry') ?></label><input name="insurance_expiry" type="date" class="form-control"></div>
      <div class="col-6"><label class="form-label"><?= te('puc_expiry') ?></label><input name="puc_expiry" type="date" class="form-control"></div>
      <div class="col-6"><label class="form-label"><?= te('next_service_date') ?></label><input name="next_service_date" type="date" class="form-control"></div>
      <div class="col-6"><label class="form-label"><?= te('next_service_km') ?></label><input name="next_service_km" type="number" min="0" class="form-control"></div>
    </div></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>
