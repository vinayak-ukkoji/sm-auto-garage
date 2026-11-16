<?php
/**
 * S M AUTO GARAGE – configuration
 * Edit these values for your server (XAMPP default: root / empty password).
 */

// ---- Load .env (copy .env.example to .env and edit) ----
(function () {
    $file = __DIR__ . '/.env';
    if (!is_readable($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        $_ENV[$k] = $v;
    }
})();
function env_val($key, $default = null) {
    return array_key_exists($key, $_ENV) ? $_ENV[$key] : $default;
}

// ---- Database ----
define('DB_HOST', env_val('DB_HOST', 'localhost'));
define('DB_PORT', (int) env_val('DB_PORT', 3306));
define('DB_NAME', env_val('DB_NAME', 'garage_db'));
define('DB_USER', env_val('DB_USER', 'root'));
define('DB_PASS', env_val('DB_PASS', ''));

// ---- App ----
define('APP_NAME', env_val('APP_NAME', 'S M AUTO GARAGE'));
define('APP_TZ', env_val('APP_TZ', 'Asia/Kolkata'));
define('APP_DEBUG', filter_var(env_val('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN)); // false on live hosting
define('COUNTRY_CODE', env_val('COUNTRY_CODE', '91'));       // used for WhatsApp links

// Leave empty to auto-detect (e.g. "/garage" when the app is in htdocs/garage).
// Set manually if links break, e.g. BASE_URL=/garage in .env
define('BASE_URL', env_val('BASE_URL', ''));

define('UPLOAD_MAX_MB', 5);
define('SESSION_NAME', 'smgarage_sid');

// Secret used to sign public invoice / job card links shared on WhatsApp.
// Set a long random APP_KEY in .env on your live server.
define('APP_KEY', env_val('APP_KEY', 'change-me-to-a-long-random-string-9f3a7c1e'));
