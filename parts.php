<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('parts');
$tab = 'parts';

const PART_CSV_COLS = ['part_no', 'name', 'brand', 'category', 'hsn', 'unit', 'purchase_price', 'mrp', 'selling_price', 'gst_rate', 'stock', 'min_stock', 'rack_no'];

if (input('sample') === '1') {
    csv_download('parts_sample.csv', PART_CSV_COLS, [['EO-001', 'Engine Oil 5W-30 (4L)', 'Castrol', 'Lubricants', '27101980', 'can', 1800, 2250, 2250, 18, 10, 4, 'A1']]);
}
if (input('export') === '1') {
    csv_download('parts_' . date('Ymd') . '.csv', PART_CSV_COLS, rows('SELECT ' . implode(',', PART_CSV_COLS) . ' FROM parts WHERE is_active = 1 ORDER BY name'));
}

$filter = one_of(input('filter', 'all'), ['all', 'alerts', 'out', 'inactive'], 'all');
$cats = rows('SELECT DISTINCT category FROM parts WHERE category IS NOT NULL AND category <> "" ORDER BY category');
$sum = row('SELECT COUNT(*) n, COALESCE(SUM(stock),0) st, COALESCE(SUM(GREATEST(stock,0) * purchase_price),0) val FROM parts WHERE is_active = 1');
$alerts = (int) val('SELECT COUNT(*) FROM parts WHERE is_active = 1 AND stock <= min_stock');

$title = 'PARTS';
$title_html = 'PARTS <small>(' . te('inventory') . ')</small>';
$back = url('service.php');
$header_right = '<button class="hdr-icon" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i></button>'
  . '<div class="dropdown"><button class="hdr-icon" data-bs-toggle="dropdown"><i class="bi bi-filter"></i></button><ul class="dropdown-menu dropdown-menu-end">'
  . '<li><a class="dropdown-item' . ($filter === 'all' ? ' active' : '') . '" href="' . url('parts.php') . '">' . te('all_parts') . '</a></li>'
  . '<li><a class="dropdown-item' . ($filter === 'alerts' ? ' active' : '') . '" href="' . url('parts.php?filter=alerts') . '">' . te('low_stock') . '</a></li>'
  . '<li><a class="dropdown-item' . ($filter === 'out' ? ' active' : '') . '" href="' . url('parts.php?filter=out') . '">' . te('out_of_stock') . '</a></li>'
  . '<li><a class="dropdown-item' . ($filter === 'inactive' ? ' active' : '') . '" href="' . url('parts.php?filter=inactive') . '">' . te('inactive_parts') . '</a></li></ul></div>'
  . '<div class="dropdown"><button class="hdr-icon" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end">'
  . '<li><a class="dropdown-item" href="' . url('parts.php?export=1') . '"><i class="bi bi-download me-2"></i>' . te('export_csv') . '</a></li>'
  . '<li><a class="dropdown-item" href="' . url('vendors.php') . '"><i class="bi bi-people me-2"></i>' . te('my_vendors') . '</a></li>'
  . '<li><a class="dropdown-item" href="' . url('accounts.php?tab=purchase') . '"><i class="bi bi-bag me-2"></i>' . te('part_purchase') . '</a></li></ul></div>';
$body_class = 'white-bg';
include __DIR__ . '/includes/header.php';
?>
<div style="margin:-10px -10px 0;background:#fff;padding-bottom:70px">
  <div class="btn-grid">
    <a class="btn-dark-x" href="<?= url('purchase_orders.php') ?>"><i class="bi bi-plus-circle-fill"></i> <?= te('purchase_order') ?></a>
    <a class="btn-red-x" href="<?= url('parts.php?filter=alerts') ?>"><i class="bi bi-search"></i> <?= te('view_alerts') ?><?= $alerts ? ' (' . $alerts . ')' : '' ?></a>
    <a class="btn-dark-x" href="<?= url('invoice_new.php?from=parts') ?>"><i class="bi bi-graph-up-arrow"></i> <?= te('counter_sale') ?></a>
    <a class="btn-dark-x" href="<?= url('stock_in.php') ?>"><i class="bi bi-briefcase-fill"></i> <?= te('stock_in') ?></a>
  </div>
  <div class="px-3 pt-2">
    <div class="search-card"><i class="bi bi-search"></i><input type="search" id="q" placeholder="<?= te('search') ?>" autocomplete="off"><i class="bi bi-x-lg clear"></i></div>
    <div class="d-flex justify-content-between align-items-center mb-2">
      <a href="#" class="upload-link" data-bs-toggle="modal" data-bs-target="#csvModal"><i class="bi bi-cloud-arrow-up-fill"></i> <?= te('upload_csv') ?></a>
      <button class="btn-dark-x" data-bs-toggle="modal" data-bs-target="#partModal" data-fill='{"gst_rate":18,"unit":"pcs"}' data-title="<?= te('add_part') ?>"><i class="bi bi-plus-circle-fill"></i> <?= te('add_stock') ?></button>
    </div>
    <?php if ($filter !== 'all'): ?><div class="filter-pill-wrap"><span class="filter-pill"><?= te(['alerts' => 'low_stock', 'out' => 'out_of_stock', 'inactive' => 'inactive_parts'][$filter]) ?></span></div><?php endif; ?>
    <table class="ptable">
      <thead><tr><th>(P No.) <?= te('name') ?></th><th><?= te('stock') ?></th><th>MRP</th><th><?= te('rack_no') ?></th><th></th></tr></thead>
      <tbody id="rows"></tbody>
    </table>
    <div class="text-center my-3"><button class="btn btn-outline-primary btn-sm" id="more" style="display:none"><?= te('load_more') ?></button></div>
  </div>
</div>
<div class="sum-bar">
  <div><?= te('total_parts') ?><b><?= (int) $sum['n'] ?></b></div>
  <div><?= te('total_stock') ?><b><?= number_format((float) $sum['st'], 2) ?></b></div>
  <div><?= te('total_stock_value') ?><b><?= money($sum['val']) ?></b></div>
</div>

<!-- Add / edit part -->
<div class="modal fade" id="partModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><form class="modal-content" data-api="parts.php?action=save" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('add_part') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><?php include __DIR__ . '/includes/part_form.php'; ?></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>

<!-- CSV upload -->
<div class="modal fade" id="csvModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="parts.php?action=import" data-reload enctype="multipart/form-data">
  <div class="modal-header"><h5 class="modal-title"><?= te('upload_csv') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p class="small text-muted"><?= te('csv_help') ?><br><code><?= e(implode(', ', PART_CSV_COLS)) ?></code></p>
    <a href="<?= url('parts.php?sample=1') ?>" class="small"><i class="bi bi-download"></i> <?= te('download_sample') ?></a>
    <input type="file" name="csv" accept=".csv,text/csv" class="form-control mt-3" required>
  </div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('upload') ?></button></div>
</form></div></div>

<?php ob_start(); ?>
<script>
$(function () {
  let offset = 0;
  const filter = <?= json_encode($filter) ?>;
  function load(reset) {
    if (reset) offset = 0;
    apiGet('parts.php', { action: 'list', q: $('#q').val().trim(), filter: filter, offset: offset }).then(r => {
      const html = r.data.html || (reset ? '<tr><td colspan="5" class="text-center text-muted py-4">' + esc(APP.i18n.no_results) + '</td></tr>' : '');
      if (reset) $('#rows').html(html); else $('#rows').append(html);
      offset += r.data.count;
      $('#more').toggle(r.data.count === 100);
    });
  }
  $('#q').on('input', debounce(() => load(true), 300));
  $('#more').on('click', () => load(false));
  load(true);
});
</script>
<?php $page_script = ob_get_clean(); include __DIR__ . '/includes/footer.php'; ?>
