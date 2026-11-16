<?php
/** Language switch (English / Hindi). Use t('key'). Missing keys fall back to English, then to the key itself. */

const LANGS = ['en' => 'English', 'hi' => 'हिन्दी'];

if (isset($_GET['lang']) && isset(LANGS[$_GET['lang']])) {
    $_SESSION['lang'] = $_GET['lang'];
    if (!empty($_SESSION['uid'])) q('UPDATE users SET language = ? WHERE id = ?', [$_GET['lang'], $_SESSION['uid']]);
}

function lang(): string
{
    if (!empty($_SESSION['lang']) && isset(LANGS[$_SESSION['lang']])) return $_SESSION['lang'];
    $d = function_exists('setting') ? (string) setting('default_language') : 'en';
    return isset(LANGS[$d]) ? $d : 'en';
}

function t(string $key): string
{
    static $dict = [];
    $l = lang();
    if (!isset($dict['en'])) $dict['en'] = require __DIR__ . '/lang/en.php';
    if ($l !== 'en' && !isset($dict[$l])) $dict[$l] = require __DIR__ . '/lang/' . $l . '.php';
    return $dict[$l][$key] ?? $dict['en'][$key] ?? ucfirst(str_replace('_', ' ', $key));
}

/** Echo-escaped translation. */
function te(string $key): string { return e(t($key)); }
