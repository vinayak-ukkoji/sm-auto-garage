<?php
/**
 * S M AUTO GARAGE – configuration
 * Edit these values for your server (XAMPP default: root / empty password).
 */

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'garage_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- App ----
define('APP_NAME', 'S M AUTO GARAGE');
define('APP_TZ', 'Asia/Kolkata');
define('APP_DEBUG', true);          // set false on live hosting
define('COUNTRY_CODE', '91');       // used for WhatsApp links

// Leave empty to auto-detect (e.g. "/garage" when the app is in htdocs/garage).
// Set manually if links break, e.g. define('BASE_URL', '/garage');
define('BASE_URL', '');

define('UPLOAD_MAX_MB', 5);
define('SESSION_NAME', 'smgarage_sid');

// Secret used to sign public invoice / job card links shared on WhatsApp.
// CHANGE THIS to any long random text on your live server.
define('APP_KEY', 'change-me-to-a-long-random-string-9f3a7c1e');
