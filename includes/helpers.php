<?php
/** Generic helpers: URLs, escaping, formatting, JSON, validation. */

class ValidationError extends RuntimeException {}

function base_url(): string
{
    static $base = null;
    if ($base !== null) return $base;
    if (BASE_URL !== '') return $base = rtrim(BASE_URL, '/');
    $root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $doc  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
    if ($doc !== '' && strpos($root, $doc) === 0) {
        $base = substr($root, strlen($doc));
    } else {
        // Fallback: derive from the running script path
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = preg_replace('#/(api|print)/[^/]*$#', '', $script);
        $base = preg_replace('#/[^/]*\.php$#', '', $base);
    }
    return $base = rtrim($base, '/');
}

function url(string $path = ''): string { return base_url() . '/' . ltrim($path, '/'); }
function asset(string $path): string
{
    $file = __DIR__ . '/../assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . $path) . '?v=' . $v;
}

function e($s): string { return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Indian number grouping: 1234567.5 → 12,34,567.50 */
function inr($n, int $dec = 2): string
{
    $n = (float) $n;
    $neg = $n < 0; $n = abs($n);
    $parts = explode('.', number_format($n, $dec, '.', ''));
    $int = $parts[0];
    $last3 = substr($int, -3);
    $rest = substr($int, 0, -3);
    if ($rest !== '' && $rest !== false) $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',';
    $out = ($rest ?: '') . $last3 . ($dec ? '.' . $parts[1] : '');
    return ($neg ? '-' : '') . $out;
}
function money($n, int $dec = 2): string { return ((float) $n < 0 ? '-₹' : '₹') . inr(abs((float) $n), $dec); }
function qty($n): string { $n = (float) $n; return floor($n) == $n ? (string) (int) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'); }

function fmt_date($d, string $f = 'd M Y'): string { return $d ? date($f, strtotime((string) $d)) : '-'; }
function fmt_dt($d): string { return $d ? date('d M Y, h:i A', strtotime((string) $d)) : '-'; }
function time_ago($d): string
{
    if (!$d) return '';
    $s = time() - strtotime((string) $d);
    if ($s < 60) return t('just_now');
    $units = [31536000 => 'year', 2592000 => 'month', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $sec => $name) {
        if ($s >= $sec) { $n = intdiv($s, $sec); return $n . ' ' . $name . ($n > 1 ? 's' : '') . ' ' . t('ago'); }
    }
    return '';
}

// ---------- Request input ----------
function input(string $key, $default = '')
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}
function input_int(string $key, int $default = 0): int { $v = input($key, $default); return is_numeric($v) ? (int) $v : $default; }
function input_num(string $key, float $default = 0): float { $v = input($key, $default); return is_numeric($v) ? (float) $v : $default; }
function required(string $key, string $label): string
{
    $v = input($key);
    if (!is_string($v) || $v === '') throw new ValidationError($label . ' ' . t('is_required'));
    return $v;
}
function valid_mobile(string $m): string
{
    $m = preg_replace('/\D/', '', $m);
    if (strlen($m) === 12 && strpos($m, '91') === 0) $m = substr($m, 2);
    if (!preg_match('/^[6-9]\d{9}$/', $m)) throw new ValidationError(t('invalid_mobile'));
    return $m;
}
function valid_date(?string $d, bool $allowEmpty = true): ?string
{
    if ($d === null || $d === '') {
        if ($allowEmpty) return null;
        throw new ValidationError(t('invalid_date'));
    }
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    if (!$dt || $dt->format('Y-m-d') !== $d) throw new ValidationError(t('invalid_date'));
    return $d;
}
function valid_gstin(?string $g): ?string
{
    $g = strtoupper(trim((string) $g));
    if ($g === '') return null;
    if (!preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z0-9]Z[A-Z0-9]$/', $g)) throw new ValidationError(t('invalid_gstin'));
    return $g;
}
function reg_clean(string $r): string { return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $r)); }
function nullable($v) { return ($v === '' || $v === null) ? null : $v; }
function one_of($v, array $allowed, $default) { return in_array($v, $allowed, true) ? $v : $default; }

// ---------- JSON ----------
function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function json_ok($data = [], string $msg = ''): void { json_out(['ok' => true, 'msg' => $msg, 'data' => $data]); }
function json_err(string $msg, int $code = 400): void { json_out(['ok' => false, 'msg' => $msg], $code); }

/** Wrap an API endpoint: login + CSRF on POST + error handling. */
function api_run(callable $fn, bool $needLogin = true): void
{
    try {
        if ($needLogin && !current_user()) json_err(t('session_expired'), 401);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check()) json_err(t('csrf_failed'), 419);
        $fn(input('action'));
        json_err('Unknown action', 404);
    } catch (ValidationError $e) {
        json_err($e->getMessage(), 422);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $msg = APP_DEBUG ? 'DB error: ' . $e->getMessage() : t('server_error');
        if ($e->getCode() === '23000') $msg = str_contains($e->getMessage(), 'Duplicate') ? t('duplicate_entry') : t('in_use_cannot_delete');
        json_err($msg, 500);
    } catch (Throwable $e) {
        error_log((string) $e);
        json_err(APP_DEBUG ? $e->getMessage() : t('server_error'), 500);
    }
}
function require_post(): void { if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('POST required', 405); }

// ---------- Settings ----------
function setting(?string $key = null)
{
    static $s = null;
    if ($s === null) $s = row('SELECT * FROM settings WHERE id = 1') ?: ['garage_name' => APP_NAME];
    return $key === null ? $s : ($s[$key] ?? null);
}

/** Favicon, home-screen icons and web-app manifest (same branding on every page, incl. print views). */
function brand_head(): string
{
    return '<link rel="icon" href="' . e(url('favicon.ico')) . '" sizes="any">' . "
"
        . '<link rel="icon" type="image/png" sizes="32x32" href="' . e(asset('img/favicon-32.png')) . '">' . "
"
        . '<link rel="apple-touch-icon" href="' . e(asset('img/apple-touch-icon.png')) . '">' . "
"
        . '<link rel="manifest" href="' . e(url('manifest.webmanifest')) . '">' . "
"
        . '<meta name="apple-mobile-web-app-title" content="SM Garage">' . "
"
        . '<meta name="application-name" content="S M AUTO GARAGE">' . "
";
}

/** Garage logo: the uploaded one from Settings, otherwise the built-in S M AUTO GARAGE mark. */
function logo_url(): string
{
    $l = setting('logo');
    return $l ? url($l) : asset('img/logo-mark.png');
}

/** Is GST currently enabled in Settings? (new documents follow this; saved invoices keep their own flag) */
function gst_on(): bool
{
    return (int) (setting('gst_enabled') ?? 1) === 1;
}

// ---------- Links ----------
function wa_link(?string $mobile, string $text = ''): string
{
    $m = preg_replace('/\D/', '', (string) $mobile);
    if (strlen($m) === 10) $m = COUNTRY_CODE . $m;
    return 'https://wa.me/' . $m . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}
function sms_link(?string $mobile, string $text = ''): string { return 'sms:' . preg_replace('/\D/', '', (string) $mobile) . '?body=' . rawurlencode($text); }
function tel_link(?string $mobile): string { return 'tel:' . preg_replace('/\D/', '', (string) $mobile); }
function fill_template(string $tpl, array $vars): string
{
    $vars += ['garage_name' => setting('garage_name'), 'garage_phone' => setting('phone')];
    return preg_replace_callback('/\{(\w+)\}/', fn($m) => (string) ($vars[$m[1]] ?? $m[0]), $tpl);
}
function abs_url(string $path): string
{
    if (APP_URL !== '') return APP_URL . url($path);
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}

// ---------- Flash / redirect ----------
function flash(string $msg, string $type = 'success'): void { $_SESSION['flash'][] = [$msg, $type]; }
function take_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function redirect(string $path): void { header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path))); exit; }

// ---------- Periods ----------
function period_range(string $p, string $from = '', string $to = ''): array
{
    $today = date('Y-m-d');
    switch ($p) {
        case 'today':      return [$today, $today, t('today')];
        case 'yesterday':  $y = date('Y-m-d', strtotime('-1 day')); return [$y, $y, t('yesterday')];
        case 'this_week':  return [date('Y-m-d', strtotime('monday this week')), $today, t('this_week')];
        case 'last_month': return [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month')), t('last_month')];
        case 'this_year':
            $fyStart = (int) date('n') >= 4 ? date('Y-04-01') : (date('Y') - 1) . '-04-01';
            return [$fyStart, $today, t('this_fy')];
        case 'custom':
            $f = valid_date($from) ?? $today; $tt = valid_date($to) ?? $today;
            if ($f > $tt) [$f, $tt] = [$tt, $f];
            return [$f, $tt, fmt_date($f) . ' - ' . fmt_date($tt)];
        default:           return [date('Y-m-01'), $today, t('this_month')];
    }
}

// ---------- Amount in words (Indian) ----------
function amount_in_words(float $amount): string
{
    $amount = round($amount, 2);
    $rupees = (int) floor($amount);
    $paise  = (int) round(($amount - $rupees) * 100);
    $w = $rupees === 0 ? 'Zero' : _num_words($rupees);
    $out = 'Rupees ' . $w;
    if ($paise > 0) $out .= ' and ' . _num_words($paise) . ' Paise';
    return $out . ' Only';
}
function _num_words(int $n): string
{
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
        'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $two = function (int $x) use ($ones, $tens) { return $x < 20 ? $ones[$x] : trim($tens[intdiv($x, 10)] . ' ' . $ones[$x % 10]); };
    $parts = [];
    foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$div, $name]) {
        if ($n >= $div) {
            $c = intdiv($n, $div); $n %= $div;
            $parts[] = ($c >= 100 ? _num_words($c) : $two($c)) . ' ' . $name;
        }
    }
    if ($n > 0) $parts[] = $two($n);
    return implode(' ', $parts);
}

// ---------- Uploads ----------
function save_upload(array $file, string $subdir, array $exts = ['jpg', 'jpeg', 'png', 'webp']): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new ValidationError(t('upload_failed'));
    if ($file['size'] > UPLOAD_MAX_MB * 1024 * 1024) throw new ValidationError(t('file_too_large'));
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $exts, true)) throw new ValidationError(t('invalid_file_type'));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && @getimagesize($file['tmp_name']) === false) throw new ValidationError(t('invalid_file_type'));
    $dir = __DIR__ . '/../uploads/' . $subdir;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new ValidationError(t('upload_failed'));
    return 'uploads/' . $subdir . '/' . $name;
}

function csv_download(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header);
    foreach ($rows as $r) fputcsv($out, array_values($r));
    fclose($out);
    exit;
}

// ---------- Signed public document links ----------
function doc_key(string $type, int $id): string { return substr(hash_hmac('sha256', $type . ':' . $id, APP_KEY), 0, 20); }
function doc_key_ok(string $type, int $id): bool { $k = (string) ($_GET['k'] ?? ''); return $k !== '' && hash_equals(doc_key($type, $id), $k); }
function public_doc_url(string $type, int $id): string
{
    $file = $type === 'inv' ? 'print/invoice.php' : 'print/jobcard.php';
    return abs_url($file . '?id=' . $id . '&k=' . doc_key($type, $id));
}
