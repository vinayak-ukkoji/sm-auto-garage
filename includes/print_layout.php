<?php
/** A4 print layout helpers (invoice, job card, PO). */

function print_start(string $docTitle): void
{
    $s = setting(); ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($docTitle) ?></title>
<style>
  @page { size: A4; margin: 10mm; }
  * { box-sizing: border-box; }
  body { font-family: Roboto, "Segoe UI", Arial, "Nirmala UI", sans-serif; font-size: 12px; color: #111; margin: 0; background: #e9ecef; }
  .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; background: #fff; padding: 12mm; box-shadow: 0 0 8px rgba(0,0,0,.15); }
  .toolbar { text-align: center; padding: 10px; }
  .toolbar button, .toolbar a { background: #12926f; color: #fff; border: 0; padding: 8px 18px; border-radius: 3px; font-size: 14px; cursor: pointer; text-decoration: none; margin: 0 4px; display: inline-block; }
  .toolbar a.wa { background: #25d366; }
  .hd { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #12926f; padding-bottom: 8px; }
  .hd .logo { max-height: 60px; max-width: 120px; margin-right: 10px; }
  .hd h1 { margin: 0; color: #12926f; font-size: 22px; }
  .hd .tag { font-size: 11px; letter-spacing: 1px; color: #555; }
  .doc-title { text-align: center; font-size: 16px; font-weight: 700; margin: 8px 0; letter-spacing: 1px; }
  table { width: 100%; border-collapse: collapse; }
  .grid td { border: 1px solid #bbb; padding: 5px 6px; vertical-align: top; }
  .items th { background: #12926f; color: #fff; padding: 6px 4px; font-weight: 500; font-size: 11px; border: 1px solid #12926f; }
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
{ ?>
<div class="toolbar">
  <button onclick="window.print()">🖨 Print / Save as PDF</button>
  <?php if ($waLink): ?><a class="wa" href="<?= e($waLink) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
  <a href="javascript:history.length>1?history.back():window.close()" style="background:#607d8b">Close</a>
</div>
<?php }

function print_garage_header(string $right = ''): void
{
    $s = setting(); ?>
<div class="hd">
  <div style="display:flex">
    <?php if (!empty($s['logo'])): ?><img class="logo" src="<?= e(url($s['logo'])) ?>" alt=""><?php endif; ?>
    <div>
      <h1><?= e($s['garage_name']) ?></h1>
      <div class="tag"><?= e($s['tagline']) ?></div>
      <div><?= nl2br(e(trim(($s['address'] ?? '') . ', ' . ($s['city'] ?? '') . ' ' . ($s['pincode'] ?? ''), ', '))) ?></div>
      <div><?= $s['phone'] ? 'Ph: ' . e($s['phone']) : '' ?> <?= $s['email'] ? ' · ' . e($s['email']) : '' ?></div>
      <?php if ($s['gstin']): ?><div class="b">GSTIN: <?= e($s['gstin']) ?></div><?php endif; ?>
    </div>
  </div>
  <div class="r"><?= $right ?></div>
</div>
<?php }

function print_end(): void
{
    if (input('print') === '1') echo '<script>window.addEventListener("load",()=>setTimeout(()=>window.print(),300));</script>';
    echo '</body></html>';
}
