<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('feedback');
$tab = 'more';
$view = one_of(input('view', 'received'), ['received', 'pending'], 'received');
$rating = input_int('rating');
$stats = row('SELECT COUNT(rating) n, AVG(rating) avg, SUM(rating IS NULL) pending FROM feedbacks');
$dist = [];
foreach (rows('SELECT rating, COUNT(*) n FROM feedbacks WHERE rating IS NOT NULL GROUP BY rating') as $d) $dist[(int) $d['rating']] = (int) $d['n'];

$w = $view === 'pending' ? 'f.rating IS NULL' : 'f.rating IS NOT NULL';
$p = [];
if ($view === 'received' && $rating) { $w .= ' AND f.rating = ?'; $p[] = $rating; }
$list = rows("SELECT f.*, jc.jc_no, jc.delivered_at, c.name, c.mobile, v.reg_no, tu.name AS tech FROM feedbacks f JOIN job_cards jc ON jc.id = f.job_card_id
    JOIN customers c ON c.id = f.customer_id JOIN vehicles v ON v.id = jc.vehicle_id LEFT JOIN users tu ON tu.id = jc.technician_id
    WHERE $w ORDER BY COALESCE(f.submitted_at, f.created_at) DESC LIMIT 200", $p);

$title = t('service_feedbacks');
$back = url('more.php');
include __DIR__ . '/includes/header.php';
?>
<div class="box d-flex align-items-center gap-3">
  <div class="text-center" style="min-width:90px"><div style="font-size:34px;color:#f5b301"><?= $stats['n'] ? number_format((float) $stats['avg'], 1) : '-' ?></div><?= stars((int) round((float) $stats['avg'])) ?><div class="small text-muted"><?= (int) $stats['n'] ?> <?= te('ratings') ?></div></div>
  <div class="flex-fill">
    <?php for ($s = 5; $s >= 1; $s--): $n = $dist[$s] ?? 0; $pct = $stats['n'] ? round($n * 100 / $stats['n']) : 0; ?>
      <a href="?view=received&rating=<?= $s ?>" class="d-flex align-items-center gap-2 small text-dark"><span style="width:14px"><?= $s ?></span><i class="bi bi-star-fill" style="color:#f5b301"></i>
        <div class="progress flex-fill" style="height:7px"><div class="progress-bar" style="width:<?= $pct ?>%;background:#12926f"></div></div><span style="width:24px"><?= $n ?></span></a>
    <?php endfor; ?>
  </div>
</div>
<div class="tabs-x bg-white mb-2">
  <a class="<?= $view === 'received' ? 'active' : '' ?>" href="?view=received"><?= te('received') ?></a>
  <a class="<?= $view === 'pending' ? 'active' : '' ?>" href="?view=pending"><?= te('pending') ?> (<?= (int) $stats['pending'] ?>)</a>
</div>
<?php if ($rating): ?><div class="filter-pill-wrap"><a href="?view=received" class="filter-pill"><?= $rating ?> ★ &times;</a></div><?php endif; ?>
<?php if (!$list) echo empty_state(t('no_records'), 'bi-star'); ?>
<?php foreach ($list as $f): ?>
  <div class="list-card">
    <div class="r"><?= $f['rating'] !== null ? stars((int) $f['rating']) : '' ?><div class="small text-muted"><?= e(fmt_date($f['submitted_at'] ?: $f['delivered_at'])) ?></div></div>
    <div class="t"><?= e($f['name']) ?></div>
    <div class="s"><a href="<?= url('jobcard.php?id=' . $f['job_card_id']) ?>"><?= e($f['jc_no']) ?></a> · <?= e($f['reg_no']) ?><?= $f['tech'] ? ' · ' . e($f['tech']) : '' ?></div>
    <?php if ($f['comment']): ?><div class="mt-1 fst-italic">“<?= e($f['comment']) ?>”</div><?php endif; ?>
    <?php if ($f['rating'] === null):
        $msg = fill_template((string) setting('wa_template_feedback'), ['customer' => $f['name'], 'link' => abs_url('feedback.php?t=' . $f['token'])]); ?>
      <a class="btn btn-sm btn-success mt-2" target="_blank" href="<?= e(wa_link($f['mobile'], $msg)) ?>"><i class="bi bi-whatsapp"></i> <?= te('send_feedback_link') ?></a>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
