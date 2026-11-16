<?php /* Shared part form fields (used in modals). */
$catList = rows('SELECT DISTINCT category FROM parts WHERE category IS NOT NULL AND category <> "" ORDER BY category'); ?>
<input type="hidden" name="id">
<div class="row g-2">
  <div class="col-12"><label class="form-label"><?= te('part_name') ?> *</label><input name="name" class="form-control" required maxlength="150"></div>
  <div class="col-6"><label class="form-label"><?= te('part_no') ?></label><input name="part_no" class="form-control" maxlength="50"></div>
  <div class="col-6"><label class="form-label"><?= te('brand') ?></label><input name="brand" class="form-control" maxlength="60"></div>
  <div class="col-6"><label class="form-label"><?= te('category') ?></label><input name="category" class="form-control" list="catList" maxlength="60">
    <datalist id="catList"><?php foreach ($catList as $c) echo '<option value="' . e($c['category']) . '">'; ?></datalist></div>
  <div class="col-6"><label class="form-label">HSN</label><input name="hsn" class="form-control" maxlength="10"></div>
  <div class="col-4"><label class="form-label"><?= te('unit') ?></label><input name="unit" class="form-control" list="unitList" maxlength="10">
    <datalist id="unitList"><option value="pcs"><option value="set"><option value="ltr"><option value="can"><option value="btl"><option value="kg"><option value="mtr"></datalist></div>
  <div class="col-4"><label class="form-label"><?= te('purchase_price') ?></label><input name="purchase_price" type="number" step="0.01" min="0" class="form-control"></div>
  <div class="col-4"><label class="form-label">MRP</label><input name="mrp" type="number" step="0.01" min="0" class="form-control"></div>
  <div class="col-4"><label class="form-label"><?= te('selling_price') ?> *</label><input name="selling_price" type="number" step="0.01" min="0" class="form-control" required></div>
  <div class="col-4"><label class="form-label">GST %</label><select name="gst_rate" class="form-select"><?= gst_options(18) ?></select></div>
  <div class="col-4"><label class="form-label"><?= te('min_stock') ?></label><input name="min_stock" type="number" step="0.01" min="0" class="form-control"></div>
  <div class="col-6"><label class="form-label"><?= te('rack_no') ?></label><input name="rack_no" class="form-control" maxlength="30"></div>
  <div class="col-6 opening-wrap"><label class="form-label"><?= te('opening_stock') ?></label><input name="opening_stock" type="number" step="0.01" min="0" class="form-control"></div>
</div>
