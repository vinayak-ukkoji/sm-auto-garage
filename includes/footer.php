<?php
/** Bottom navigation + scripts. Pages may set $page_script (captured with ob_start) before including. */
$tab = $tab ?? '';
$tabs = [
    'service'  => ['service.php',  'bi-tools',      t('service')],
    'parts'    => ['parts.php',    'bi-wrench',     t('parts')],
    'accounts' => ['accounts.php', 'bi-calculator', t('accounts')],
    'more'     => ['more.php',     'bi-list',       t('more')],
];
if (current_user() && is_tech()) unset($tabs['parts'], $tabs['accounts']);
?>
  </main>
  <?php if (current_user() && empty($no_nav)): ?>
  <nav class="bottom-nav">
    <?php foreach ($tabs as $key => [$href, $icon, $label]): ?>
      <a href="<?= url($href) ?>" class="<?= $tab === $key ? 'active' : '' ?>"><i class="bi <?= $icon ?>"></i><span><?= e($label) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
</div>

<div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" id="toastBox"></div>

<script src="<?= asset('vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script>window.APP = <?= json_encode([
    'base' => base_url(), 'csrf' => csrf_token(), 'lang' => lang(), 'role' => role(), 'gst' => gst_on(),
    'i18n' => ['saved' => t('saved'), 'error' => t('error'), 'confirm' => t('are_you_sure'), 'no_results' => t('no_results'),
               'loading' => t('loading'), 'session_expired' => t('session_expired')],
    'flash' => take_flash(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="<?= asset('js/app.js') ?>"></script>
<?= $page_script ?? '' ?>
</body>
</html>
