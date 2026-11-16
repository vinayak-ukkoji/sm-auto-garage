<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('vendors');
$tab = 'more';
$q = (string) input('q');
$list = rows("SELECT v.*, (SELECT COUNT(*) FROM purchase_orders po WHERE po.vendor_id = v.id AND po.status <> 'cancelled') AS pos,
    (SELECT COALESCE(SUM(total - paid),0) FROM purchase_orders po WHERE po.vendor_id = v.id AND po.status = 'received') AS payable
    FROM vendors v WHERE v.name LIKE ? OR v.mobile LIKE ? OR v.contact_person LIKE ? ORDER BY v.name LIMIT 300", ["%$q%", "%$q%", "%$q%"]);
$title = t('my_vendors');
$back = url('more.php');
include __DIR__ . '/includes/header.php';
?>
<form class="search-card" method="get"><i class="bi bi-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= te('search_vendor_ph') ?>"></form>
<?php if (!$list) echo empty_state(t('no_records'), 'bi-people'); ?>
<?php foreach ($list as $v): ?>
  <a class="list-card" href="<?= url('vendor.php?id=' . $v['id']) ?>">
    <div class="r"><?php if ($v['payable'] > 0): ?><div class="text-danger"><?= money($v['payable'], 0) ?></div><small class="text-muted"><?= te('payable') ?></small><?php else: ?><small class="text-muted"><?= (int) $v['pos'] ?> <?= te('orders') ?></small><?php endif; ?></div>
    <div class="t"><?= e($v['name']) ?></div>
    <div class="s"><?= e(implode(' · ', array_filter([$v['contact_person'], $v['mobile'], $v['gstin']]))) ?></div>
  </a>
<?php endforeach; ?>
<button class="fab" data-bs-toggle="modal" data-bs-target="#venModal" data-fill='{}' data-title="<?= te('add_vendor') ?>"><i class="bi bi-plus-lg"></i></button>
<?php include __DIR__ . '/includes/vendor_modal.php'; include __DIR__ . '/includes/footer.php'; ?>
