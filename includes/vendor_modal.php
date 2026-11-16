<div class="modal fade" id="venModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="vendors.php?action=save" data-redirect="vendor.php?id={id}">
  <div class="modal-header"><h5 class="modal-title"><?= te('add_vendor') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="id">
    <div class="row g-2">
      <div class="col-12"><label class="form-label"><?= te('vendor_name') ?> *</label><input name="name" class="form-control" required maxlength="150"></div>
      <div class="col-6"><label class="form-label"><?= te('contact_person') ?></label><input name="contact_person" class="form-control" maxlength="100"></div>
      <div class="col-6"><label class="form-label"><?= te('mobile_number') ?></label><input name="mobile" type="tel" maxlength="10" class="form-control"></div>
      <div class="col-6"><label class="form-label"><?= te('email_address') ?></label><input name="email" type="email" class="form-control"></div>
      <div class="col-6"><label class="form-label">GSTIN</label><input name="gstin" class="form-control" maxlength="15" style="text-transform:uppercase"></div>
      <div class="col-12"><label class="form-label"><?= te('address') ?></label><textarea name="address" class="form-control" rows="2"></textarea></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>
