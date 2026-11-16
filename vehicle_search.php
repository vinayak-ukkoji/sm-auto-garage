<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('search');
$tab = 'more';
$title = t('vehicle_search');
$back = url('more.php');
include __DIR__ . '/includes/header.php';
?>
<div class="search-card"><i class="bi bi-search"></i><input type="search" id="q" value="<?= e(input('q')) ?>" placeholder="<?= te('search_vehicle_ph') ?>" autocomplete="off" autofocus><i class="bi bi-x-lg clear"></i></div>
<div id="list"></div>
<?php ob_start(); ?>
<script>
$(function () {
  function load() {
    apiGet('customers.php', { action: 'vehicle_list', q: $('#q').val().trim() }).then(r => $('#list').html(r.data.html || <?= json_encode(empty_state(t('no_records'), 'bi-car-front')) ?>));
  }
  $('#q').on('input', debounce(load, 300));
  load();
});
</script>
<?php $page_script = ob_get_clean(); include __DIR__ . '/includes/footer.php'; ?>
