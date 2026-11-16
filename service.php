<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('service');
$tab = 'service';

$techWhere = is_tech() ? ' AND jc.technician_id = ' . (int) uid() : '';
$count = function (string $f) use ($techWhere) {
    return (int) val('SELECT COUNT(*) FROM job_cards jc LEFT JOIN invoices i ON i.job_card_id = jc.id WHERE ' . ORDER_FILTERS[$f] . $techWhere);
};
$open = $count('open'); $wip = $count('wip'); $ready = $count('ready'); $dueCnt = $count('due');
$dueAmt = (float) val("SELECT COALESCE(SUM(balance),0) FROM invoices WHERE status IN ('unpaid','partial')");

include __DIR__ . '/includes/header.php';
?>
<div class="search-card position-relative" style="margin-top:6px">
  <i class="bi bi-search"></i>
  <input type="search" id="homeSearch" placeholder="<?= te('search_home_ph') ?>" autocomplete="off">
  <i class="bi bi-x-lg clear"></i>
</div>
<div class="search-results" id="homeResults" style="display:none"></div>

<?php if (!is_tech()): ?>
<a class="action-card" href="<?= url('jobcard_new.php') ?>">
  <div class="ic"><span class="ill c-blue"><i class="bi bi-file-earmark-text-fill"></i><span class="plus"><i class="bi bi-plus"></i></span></span></div>
  <div class="txt"><div class="t1"><?= te('create_repair_order') ?></div><div class="t2"><?= te('create_repair_order_sub') ?></div></div>
  <div class="chev"><i class="bi bi-caret-right-fill"></i></div>
</a>
<a class="action-card" href="<?= url('invoice_new.php') ?>">
  <div class="ic"><span class="ill c-purple"><i class="bi bi-cart3"></i><span class="plus"><i class="bi bi-plus"></i></span></span></div>
  <div class="txt"><div class="t1"><?= te('create_invoice') ?></div><div class="t2"><?= te('create_invoice_sub') ?></div></div>
  <div class="chev"><i class="bi bi-caret-right-fill"></i></div>
</a>
<?php endif; ?>

<div class="stat-grid">
  <a class="stat-card" href="<?= url('orders.php?status=open') ?>">
    <div class="ic c-sand"><i class="bi bi-box-seam-fill"></i></div>
    <div class="lbl"><span class="count-badge"><?= $open ?></span> <?= te('open_orders') ?></div>
    <div class="sub"><?= te('open_orders_sub') ?></div>
  </a>
  <a class="stat-card" href="<?= url('orders.php?status=wip') ?>">
    <div class="ic c-orange"><i class="bi bi-hourglass-split"></i></div>
    <div class="lbl"><span class="count-badge"><?= $wip ?></span> <?= te('wip_orders') ?></div>
    <div class="sub"><?= te('wip_orders_sub') ?></div>
  </a>
  <a class="stat-card" href="<?= url('orders.php?status=ready') ?>">
    <div class="ic c-purple"><i class="bi bi-file-earmark-check-fill"></i></div>
    <div class="lbl"><span class="count-badge"><?= $ready ?></span> <?= te('ready_orders') ?></div>
    <div class="sub"><?= te('ready_orders_sub') ?></div>
  </a>
  <a class="stat-card" href="<?= url('orders.php?status=due') ?>">
    <div class="ic c-green"><i class="bi bi-cash-coin"></i></div>
    <div class="lbl"><span class="count-badge"><?= $dueCnt ?></span> <?= te('payment_due') ?></div>
    <?php if (can('prices')): ?><div class="due mt-1"><?= te('due') ?> : <?= money($dueAmt, 0) ?></div><?php endif; ?>
    <div class="sub mt-1"><?= te('payment_due_sub') ?></div>
  </a>
</div>

<a class="action-card" href="<?= url('orders.php?status=completed') ?>">
  <div class="ic"><span class="ill c-green"><i class="bi bi-ui-checks-grid"></i></span></div>
  <div class="txt"><div class="t1"><?= te('completed_orders') ?></div><div class="t2"><?= te('completed_orders_sub') ?></div></div>
  <div class="chev"><i class="bi bi-caret-right-fill"></i></div>
</a>

<?php ob_start(); ?>
<script>
$(function () {
  const box = $('#homeResults');
  const run = debounce(function () {
    const q = $('#homeSearch').val().trim();
    if (q.length < 2) { box.hide(); return; }
    apiGet('search.php', { type: 'global', q: q }).then(r => {
      if (!r.data.length) { box.html('<div class="sr text-muted">' + esc(APP.i18n.no_results) + '</div>').show(); return; }
      box.html(r.data.map(x => '<a href="' + url(x.href) + '"><b>' + esc(x.title) + '</b> <small class="float-end">' + esc(x.tag) + '</small><br><small>' + esc(x.sub) + '</small></a>').join('')).show();
    });
  }, 300);
  $('#homeSearch').on('input', run).on('keydown', function (e) {
    if (e.key === 'Enter') location.href = url('orders.php?status=all&q=' + encodeURIComponent(this.value.trim()));
  });
});
</script>
<?php $page_script = ob_get_clean(); include __DIR__ . '/includes/footer.php'; ?>
