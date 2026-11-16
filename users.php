<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('users');
$tab = 'more';
$list = rows("SELECT u.*, (SELECT COUNT(*) FROM job_cards jc WHERE jc.technician_id = u.id AND jc.status IN ('approved','in_progress')) AS active_jobs FROM users u ORDER BY u.is_active DESC, FIELD(u.role,'admin','advisor','technician'), u.name");
$title = t('staff_users');
$back = url('more.php');
include __DIR__ . '/includes/header.php';
$roleCls = ['admin' => 'bg-danger', 'advisor' => 'bg-primary', 'technician' => 'bg-secondary'];
?>
<div class="box small text-muted"><?= te('roles_help') ?></div>
<?php foreach ($list as $u): $fill = ['id' => $u['id'], 'name' => $u['name'], 'mobile' => $u['mobile'], 'email' => $u['email'], 'role' => $u['role'], 'language' => $u['language']]; ?>
  <div class="list-card <?= $u['is_active'] ? '' : 'opacity-50' ?>">
    <div class="r"><span class="badge <?= $roleCls[$u['role']] ?>"><?= te('role_' . $u['role']) ?></span>
      <div class="mt-2">
        <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#userModal" data-fill='<?= e(json_encode($fill)) ?>' data-title="<?= te('edit_user') ?>"><i class="bi bi-pencil"></i></button>
        <?php if ((int) $u['id'] !== uid()): ?><button class="btn btn-sm btn-outline-secondary py-0" data-post="users.php?action=toggle" data-params='{"id":<?= $u['id'] ?>}' data-reload><?= $u['is_active'] ? te('disable') : te('enable') ?></button><?php endif; ?>
      </div></div>
    <div class="t"><?= e($u['name']) ?> <?= (int) $u['id'] === uid() ? '<small class="text-muted">(' . te('you') . ')</small>' : '' ?></div>
    <div class="s"><i class="bi bi-telephone"></i> <?= e($u['mobile']) ?><?= $u['email'] ? ' · ' . e($u['email']) : '' ?></div>
    <div class="s"><?= te('last_login') ?>: <?= e(fmt_dt($u['last_login'])) ?><?= $u['role'] === 'technician' ? ' · ' . (int) $u['active_jobs'] . ' ' . te('active_jobs') : '' ?></div>
  </div>
<?php endforeach; ?>
<button class="fab" data-bs-toggle="modal" data-bs-target="#userModal" data-fill='{"role":"advisor","language":"en"}' data-title="<?= te('add_user') ?>"><i class="bi bi-person-plus-fill"></i></button>
<div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" data-api="users.php?action=save" data-reload autocomplete="off">
  <div class="modal-header"><h5 class="modal-title"><?= te('add_user') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id"><div class="row g-2">
    <div class="col-12"><label class="form-label"><?= te('name') ?> *</label><input name="name" class="form-control" required></div>
    <div class="col-6"><label class="form-label"><?= te('mobile_number') ?> * <small>(<?= te('login_id') ?>)</small></label><input name="mobile" type="tel" maxlength="10" class="form-control" required></div>
    <div class="col-6"><label class="form-label"><?= te('email_address') ?></label><input name="email" type="email" class="form-control"></div>
    <div class="col-6"><label class="form-label"><?= te('role') ?></label><select name="role" class="form-select"><?php foreach (['admin', 'advisor', 'technician'] as $r): ?><option value="<?= $r ?>"><?= te('role_' . $r) ?></option><?php endforeach; ?></select></div>
    <div class="col-6"><label class="form-label"><?= te('language') ?></label><select name="language" class="form-select"><?php foreach (LANGS as $c => $n): ?><option value="<?= $c ?>"><?= e($n) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label"><?= te('password') ?> <small class="text-muted">(<?= te('password_edit_hint') ?>)</small></label><input name="password" type="password" minlength="6" class="form-control" autocomplete="new-password"></div>
  </div></div>
  <div class="modal-footer"><button class="btn btn-primary w-100"><?= te('save') ?></button></div>
</form></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
