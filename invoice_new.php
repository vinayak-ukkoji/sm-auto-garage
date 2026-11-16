<?php
require __DIR__ . '/includes/bootstrap.php';
require_perm('invoice');
$tab = input('from') === 'parts' ? 'parts' : 'service';
$title = $tab === 'parts' ? t('counter_sale') : t('create_invoice');
include __DIR__ . '/includes/header.php';
?>
<form id="hdr" autocomplete="off">
  <input type="hidden" name="customer_id">
  <div class="fsec">
    <div class="fsec-h"><?= te('customer') ?> <small class="text-muted fw-normal"><?= te('optional_walkin') ?></small></div>
    <div class="le-add"><input type="text" id="custSearch" placeholder="<?= te('search_customer_ph') ?>"></div>
    <div id="custBox" class="px-3 pb-2" style="display:none"></div>
    <div id="walkin">
      <div class="frow"><i class="bi bi-person"></i><input name="customer_name" placeholder="<?= te('customer_name') ?>"></div>
      <div class="frow"><i class="bi bi-telephone"></i><input name="customer_mobile" type="tel" maxlength="10" placeholder="<?= te('mobile_number') ?>"></div>
    </div>
    <div class="frow"><i class="bi bi-calendar"></i><label><?= te('invoice_date') ?></label><input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" class="text-end" required></div>
  </div>
</form>
<div class="fsec"><div class="fsec-h"><?= te('parts') ?></div><div id="leParts"></div></div>
<div class="fsec"><div class="fsec-h"><?= te('services_labour') ?></div><div id="leLabour"></div></div>
<div class="fsec">
  <div class="fsec-h"><?= te('payment') ?></div>
  <div class="frow"><i class="bi bi-cash"></i><label><?= te('amount_received') ?></label><input type="number" step="0.01" min="0" id="payAmt" class="text-end" value="0"></div>
  <div class="frow"><i class="bi bi-wallet2"></i><label><?= te('payment_mode') ?></label><select id="payMode" class="text-end"><?= mode_options() ?></select></div>
  <div class="frow"><i class="bi bi-journal"></i><input id="notes" placeholder="<?= te('notes') ?>"></div>
</div>
<div class="box"><div class="kv"><span><?= te('invoice_total') ?></span><b id="grand">₹0</b></div></div>
<button class="btn-main mb-3" id="saveBtn"><?= te('generate_invoice') ?></button>

<?php ob_start(); ?>
<script>
$(function () {
  let fullPaid = true;
  const upd = () => {
    if (!window.L || !window.P) return;
    const t = Math.round(L.totals().total + P.totals().total);
    $('#grand').text(inr(t, 0));
    if (fullPaid) $('#payAmt').val(t);
  };
  $('#payAmt').on('input', () => fullPaid = false);
  window.P = new LineEditor('#leParts', { kind: 'parts', onChange: upd });
  window.L = new LineEditor('#leLabour', { kind: 'labour', onChange: upd });
  autocomplete('#custSearch', t => apiGet('search.php', { type: 'customers', q: t }).then(r => r.data),
    c => '<b>' + esc(c.name) + '</b> <small>' + esc(c.mobile) + '</small>',
    c => {
      $('[name=customer_id]').val(c.id); $('#walkin').hide(); $('#custSearch').val('');
      $('#custBox').html('<div class="chip d-inline-block">' + esc(c.name) + ' · ' + esc(c.mobile) + ' <span class="x">&times;</span></div>').show();
    });
  $('#custBox').on('click', '.x', () => { $('[name=customer_id]').val(''); $('#custBox').hide(); $('#walkin').show(); });
  $('#saveBtn').on('click', function () {
    const b = $(this).prop('disabled', true);
    const data = $('#hdr').serialize() + '&' + $.param({ labour: L.json(), parts: P.json(), pay_amount: $('#payAmt').val(), pay_mode: $('#payMode').val(), notes: $('#notes').val() });
    api('invoices.php?action=create', data).then(r => location.href = url('invoice.php?id=' + r.data.id)).always(() => b.prop('disabled', false));
  });
});
</script>
<?php $page_script = ob_get_clean(); include __DIR__ . '/includes/footer.php'; ?>
