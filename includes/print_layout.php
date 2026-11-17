<?php
/** A4 print layout helpers (invoice, job card, PO). */

function print_start(string $docTitle): void
{
    $s = setting();
    $GLOBALS['print_doc_title'] = $docTitle; ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($docTitle) ?></title>
<?= brand_head() ?>
<style>
  @page { size: A4; margin: 10mm; }
  * { box-sizing: border-box; }
  body { font-family: Roboto, "Segoe UI", Arial, "Nirmala UI", sans-serif; font-size: 12px; color: #111; margin: 0; background: #e9ecef; }
  .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; background: #fff; padding: 12mm; box-shadow: 0 0 8px rgba(0,0,0,.15); }
  .toolbar { text-align: center; padding: 10px; }
  .toolbar button, .toolbar a { background: #12926f; color: #fff; border: 0; padding: 8px 18px; border-radius: 3px; font-size: 14px; cursor: pointer; text-decoration: none; margin: 0 4px; display: inline-block; }
  .toolbar a.wa, .toolbar button.wa { background: #25d366; }
  .toolbar button:disabled { opacity: .6; cursor: wait; }
  .share-hint { text-align: center; font-size: 13px; color: #0b3c5d; background: #e8f4fb; padding: 6px; margin: 0 auto 6px; max-width: 210mm; }
  body.pdf-mode .sheet { width: 794px !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; }
  .doc-top { text-align: center; padding-bottom: 8px; letter-spacing: 1px; line-height: 1.5; }
  .doc-top .b { font-size: 17px !important; letter-spacing: 3px; }
  .hd { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #9fcbe3; padding-bottom: 10px; gap: 12px; }
  .hd .logo { max-height: 70px; max-width: 170px; }
  .hd .co { text-align: right; line-height: 1.45; }
  .hd h1 { margin: 0; color: #0b3c5d; font-size: 20px; }
  .hd .tag { font-size: 11px; letter-spacing: 1px; color: #555; }
  .doc-title { text-align: center; font-size: 16px; font-weight: 700; margin: 8px 0; letter-spacing: 1px; }
  table { width: 100%; border-collapse: collapse; }
  .grid td { border: 1px solid #bbb; padding: 5px 6px; vertical-align: top; }
  .items th { background: #bfe0f2; color: #0b3c5d; padding: 6px 4px; font-weight: 600; font-size: 11px; border: 1px solid #a9d1e8; }
  .grid td.lbl, .grid .muted { color: #0b3c5d; }
  .items td { border: 1px solid #ccc; padding: 5px 4px; }
  .r { text-align: right; } .c { text-align: center; }
  .muted { color: #666; } .b { font-weight: 700; }
  .tot td { padding: 4px 6px; }
  .tot tr.g td { font-size: 15px; font-weight: 700; border-top: 2px solid #111; }
  .sign { margin-top: 40px; display: flex; justify-content: space-between; }
  .sign div { width: 40%; text-align: center; border-top: 1px solid #333; padding-top: 4px; }
  .box2 { border: 1px solid #bbb; padding: 6px 8px; }
  .chk { display: inline-block; width: 49%; padding: 2px 0; }
  @media print { body { background: #fff; } .sheet { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; } .toolbar { display: none; } }
  @media (max-width: 800px) { .sheet { width: 100%; padding: 12px; min-height: 0; } }
</style></head><body>
<?php }

function print_toolbar(string $waLink = ''): void
{
    $title = (string) ($GLOBALS['print_doc_title'] ?? 'Document');
    $file = trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $title), '-') . '.pdf';
    $text = '';
    if ($waLink) {   // reuse the WhatsApp message text, minus the web link: the PDF file itself is attached
        parse_str((string) parse_url($waLink, PHP_URL_QUERY), $q);
        $text = trim(preg_replace('~\s*https?://\S+~', '', (string) ($q['text'] ?? '')));
    }
    $mobile = $waLink ? preg_replace('~\D~', '', (string) parse_url($waLink, PHP_URL_PATH)) : '';
    ?>
<?php if (input('share') === '1'): ?><div class="share-hint">Tap <b>Share PDF</b> to send the actual PDF file (WhatsApp, email, etc.).</div><?php endif; ?>
<div class="toolbar" id="pdfBar" data-file="<?= e($file) ?>" data-text="<?= e($text) ?>" data-mobile="<?= e($mobile) ?>" data-auto="<?= input('download') === '1' ? '1' : '0' ?>">
  <button type="button" onclick="window.print()">🖨 Print</button>
  <button type="button" id="btnPdf">⬇ Download PDF</button>
  <button type="button" id="btnShare" class="wa">📤 Share PDF</button>
  <a href="javascript:history.length>1?history.back():window.close()" style="background:#607d8b">Close</a>
</div>
<script src="<?= e(asset('vendor/html2pdf/html2pdf.bundle.min.js')) ?>"></script>
<script>
(function () {
  const bar = document.getElementById('pdfBar'), file = bar.dataset.file;
  function build() {   // render the A4 sheet into a real PDF file (Blob)
    document.body.classList.add('pdf-mode');
    return html2pdf().set({ margin: [8, 8, 8, 8], filename: file, image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollY: 0 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }, pagebreak: { mode: ['css', 'legacy'], avoid: ['tr'] } })
      .from(document.querySelector('.sheet')).outputPdf('blob')
      .finally(() => document.body.classList.remove('pdf-mode'));
  }
  function save(blob) {
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = file;
    document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 10000);
  }
  function busy(btn, on) { btn.disabled = on; if (on) { btn.dataset.t = btn.textContent; btn.textContent = 'Preparing PDF…'; } else btn.textContent = btn.dataset.t; }
  document.getElementById('btnPdf').onclick = async function () { busy(this, true); try { save(await build()); } catch (e) { alert('Could not create PDF: ' + e.message); } busy(this, false); };
  document.getElementById('btnShare').onclick = async function () {
    busy(this, true);
    try {
      const blob = await build(), f = new File([blob], file, { type: 'application/pdf' });
      if (navigator.canShare && navigator.canShare({ files: [f] })) {
        await navigator.share({ files: [f], title: document.title, text: bar.dataset.text || document.title });
      } else {   // browsers that cannot share files (desktop): download the PDF, then open WhatsApp to attach it
        save(blob);
        if (bar.dataset.mobile) window.open('https://wa.me/' + bar.dataset.mobile + '?text=' + encodeURIComponent(bar.dataset.text), '_blank');
        alert('PDF downloaded: ' + file + '\nAttach it to your message to send the file.');
      }
    } catch (e) { if (e.name !== 'AbortError') alert('Could not share PDF: ' + e.message); }
    busy(this, false);
  };
  if (bar.dataset.auto === '1') window.addEventListener('load', () => setTimeout(() => document.getElementById('btnPdf').click(), 400));
})();
</script>
<?php }

function print_garage_header(string $right = ''): void
{
    $s = setting(); ?>
<div class="doc-top"><?= $right ?></div>
<div class="hd">
  <img class="logo" src="<?= e(!empty($s['logo']) ? url($s['logo']) : asset('img/logo.png')) ?>" alt="">
  <div class="co">
    <h1><?= e($s['garage_name']) ?></h1>
    <div class="tag"><?= e($s['tagline']) ?></div>
    <div><?= nl2br(e(trim(($s['address'] ?? '') . ', ' . ($s['city'] ?? '') . ' ' . ($s['pincode'] ?? ''), ', '))) ?></div>
    <div><?= $s['phone'] ? 'Ph: ' . e($s['phone']) : '' ?> <?= $s['email'] ? ' · ' . e($s['email']) : '' ?></div>
    <?php if ($s['gstin'] && ($GLOBALS['print_gst'] ?? true) !== false): ?><div class="b">GSTIN: <?= e($s['gstin']) ?></div><?php endif; ?>
  </div>
</div>
<?php }

function print_end(): void
{
    if (input('print') === '1') echo '<script>window.addEventListener("load",()=>setTimeout(()=>window.print(),300));</script>';
    echo '</body></html>';
}
