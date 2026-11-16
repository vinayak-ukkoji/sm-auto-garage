<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('customers');
$tab = 'more';
$title = t('my_customers');
$back = url('more.php');
$total = (int) val('SELECT COUNT(*) FROM customers');
$title_html = te('my_customers') . ' <span class="count-pill">' . $total . '</span>';
include __DIR__ . '/includes/header.php';
?>
<div class="search-card"><i class="bi bi-search"></i><input type="search" id="q" placeholder="<?= te('search_customer_ph') ?>" autocomplete="off"><i class="bi bi-x-lg clear"></i></div>
<div id="list"></div>
<div class="text-center my-3"><button class="btn btn-outline-primary btn-sm" id="more" style="display:none"><?= te('load_more') ?></button></div>
<button class="fab" data-bs-toggle="modal" data-bs-target="#custModal" data-fill='{}' data-title="<?= te('add_customer') ?>"><i class="bi bi-person-plus-fill"></i></button>
<?php include __DIR__ . '/includes/customer_modals.php'; ?>
<?php ob_start(); ?>
<script>
$(function () {
  let offset = 0;
  function load(reset) {
    if (reset) offset = 0;
    apiGet('customers.php', { action: 'list', q: $('#q').val().trim(), offset: offset }).then(r => {
      const h = r.data.html || (reset ? <?= json_encode(empty_state(t('no_records'), 'bi-person')) ?> : '');
      if (reset) $('#list').html(h); else $('#list').append(h);
      offset += r.data.count; $('#more').toggle(r.data.count === 50);
    });
  }
  $('#q').on('input', debounce(() => load(true), 300));
  $('#more').on('click', () => load(false));
  load(true);
});
</script>
<?php $page_script = ob_get_clean(); include __DIR__ . '/includes/footer.php'; ?>
