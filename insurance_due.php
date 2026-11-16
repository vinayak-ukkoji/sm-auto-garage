<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('reminders');
$tab = 'more';
$type = one_of(input('type', 'insurance'), ['insurance', 'puc'], 'insurance');
$days = one_of((int) input('days', 30), [7, 15, 30, 60, 90], 30);
$col = $type === 'insurance' ? 'insurance_expiry' : 'puc_expiry';
$list = rows("SELECT v.*, c.name AS cname, c.mobile FROM vehicles v JOIN customers c ON c.id = v.customer_id
    WHERE v.$col IS NOT NULL AND v.$col <= CURDATE() + INTERVAL ? DAY AND v.$col >= CURDATE() - INTERVAL 60 DAY ORDER BY v.$col", [$days]);

$title = t('insurance_due');
$back = url('more.php');
include __DIR__ . '/includes/header.php';
?>
<div class="tabs-x bg-white mb-2">
  <a class="<?= $type === 'insurance' ? 'active' : '' ?>" href="?type=insurance&days=<?= $days ?>"><?= te('rem_insurance') ?></a>
  <a class="<?= $type === 'puc' ? 'active' : '' ?>" href="?type=puc&days=<?= $days ?>"><?= te('rem_puc') ?></a>
</div>
<div class="seg"><?php foreach ([7, 15, 30, 60, 90] as $d): ?><a class="<?= $d === $days ? 'active' : '' ?>" href="?type=<?= $type ?>&days=<?= $d ?>"><?= $d ?> <?= te('days') ?></a><?php endforeach; ?></div>
<div class="small text-muted ms-1 mb-2"><?= count($list) ?> <?= te('vehicles') ?> · <?= te('incl_overdue_60') ?></div>
<?php if (!$list) echo empty_state(t('nothing_due'), 'bi-shield-check'); ?>
<?php foreach ($list as $v):
    $d = (int) floor((strtotime($v[$col]) - strtotime(date('Y-m-d'))) / 86400);
    $msg = fill_template((string) setting('wa_template_reminder'), ['customer' => $v['cname'], 'reg_no' => $v['reg_no'], 'type' => t('rem_' . $type) . ' ' . t('renewal'), 'due_date' => fmt_date($v[$col])]); ?>
  <div class="list-card">
    <div class="r"><div class="<?= $d < 0 ? 'text-danger' : '' ?>"><?= e(fmt_date($v[$col])) ?></div><small class="text-muted"><?= $d < 0 ? abs($d) . ' ' . te('days_overdue') : $d . ' ' . te('days_left') ?></small></div>
    <div class="t"><a class="text-dark" href="<?= url('vehicle.php?id=' . $v['id']) ?>"><?= e($v['reg_no']) ?></a></div>
    <div class="s"><?= e(vehicle_name($v)) ?></div>
    <div class="s"><i class="bi bi-person"></i> <?= e($v['cname']) ?> · <?= e($v['mobile']) ?></div>
    <?php if ($type === 'insurance' && $v['insurance_company']): ?><div class="s"><i class="bi bi-building"></i> <?= e($v['insurance_company']) ?> <?= e($v['policy_no']) ?></div><?php endif; ?>
    <div class="d-flex gap-2 mt-2">
      <a class="btn btn-sm btn-success" target="_blank" href="<?= e(wa_link($v['mobile'], $msg)) ?>"><i class="bi bi-whatsapp"></i> WhatsApp</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(sms_link($v['mobile'], $msg)) ?>"><i class="bi bi-chat-dots"></i> SMS</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(tel_link($v['mobile'])) ?>"><i class="bi bi-telephone"></i></a>
      <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#renewModal" data-fill='<?= e(json_encode(['vehicle_id' => $v['id'], 'type' => $type, 'date' => date('Y-m-d', strtotime(max($v[$col], date('Y-m-d')) . ($type === 'insurance' ? ' +1 year' : ' +6 months'))), 'insurance_company' => $v['insurance_company'], 'policy_no' => ''])) ?>' data-title="<?= te('renewed') ?> · <?= e($v['reg_no']) ?>"><i class="bi bi-arrow-repeat"></i> <?= te('renewed') ?></button>
    </div>
  </div>
<?php endforeach; ?>
<div class="modal fade" id="renewModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="reminders.php?action=update_expiry" data-reload>
  <div class="modal-header"><h5 class="modal-title"><?= te('renewed') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="vehicle_id"><input type="hidden" name="type">
    <label class="form-label"><?= te('new_expiry_date') ?></label><input type="date" name="date" class="form-control" required>
    <?php if ($type === 'insurance'): ?>
      <label class="form-label mt-2"><?= te('insurance_company') ?></label><input name="insurance_company" class="form-control">
      <label class="form-label mt-2"><?= te('policy_no') ?></label><input name="policy_no" class="form-control">
    <?php endif; ?></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
