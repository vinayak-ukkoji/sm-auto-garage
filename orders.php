<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('service');
$tab = 'service';

$status = one_of(input('status', 'all'), array_keys(ORDER_FILTERS), 'all');
$search = (string) input('q');
[$list, $total] = query_orders($status, $search, 0, 30);

$title = t('orders');
$title_center = true;
$back = url('service.php');
$title_html = te('orders') . ' <span class="count-pill" id="cnt">' . $total . '</span>';
$filters = '';
foreach (ORDER_FILTERS as $k => $_) {
    $filters .= '<li><a class="dropdown-item ' . ($k === $status ? 'active' : '') . '" href="' . e(url('orders.php?status=' . $k)) . '">' . e(order_filter_label($k)) . '</a></li>';
}
$header_right = '<button class="hdr-icon" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i></button>'
    . '<div class="dropdown"><button class="hdr-icon" data-bs-toggle="dropdown"><i class="bi bi-filter"></i></button><ul class="dropdown-menu dropdown-menu-end">' . $filters . '</ul></div>';
$badge = str_replace(' ', '_', strtoupper(order_filter_label($status)));
include __DIR__ . '/includes/header.php';
?>
<div class="search-card">
  <i class="bi bi-search"></i>
  <input type="search" id="q" value="<?= e($search) ?>" placeholder="<?= te('search_orders_ph') ?>" autocomplete="off">
  <i class="bi bi-x-lg clear" <?= $search ? 'style="display:inline"' : '' ?>></i>
</div>
<div id="list">
  <?php if (!$list) echo empty_state(t('no_orders_found')); else foreach ($list as $r) echo order_card($r); ?>
</div>
<div class="text-center my-3" id="moreWrap" style="<?= $total > count($list) ? '' : 'display:none' ?>">
  <button class="btn btn-outline-primary btn-sm" id="loadMore"><?= te('load_more') ?></button>
</div>
<?php ob_start(); ?>
<script>
$(function () {
  let offset = <?= count($list) ?>;
  const status = <?= json_encode($status) ?>;
  function load(reset) {
    if (reset) offset = 0;
    apiGet('jobcards.php', { action: 'list', status: status, q: $('#q').val().trim(), offset: offset }).then(r => {
      if (reset) $('#list').html(r.data.html || <?= json_encode(empty_state(t('no_orders_found'))) ?>);
      else $('#list').append(r.data.html);
      offset += r.data.count;
      $('#cnt').text(r.data.total);
      $('#moreWrap').toggle(offset < r.data.total);
    });
  }
  $('#q').on('input', debounce(() => load(true), 350));
  $('#loadMore').on('click', () => load(false));
});
</script>
<?php $page_script = ob_get_clean(); include __DIR__ . '/includes/footer.php'; ?>
