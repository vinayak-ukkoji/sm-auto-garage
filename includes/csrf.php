<?php
/** CSRF token: one per session, checked on every POST (form field or X-CSRF-Token header). */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): bool
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

function csrf_verify_or_die(): void
{
    if (!csrf_check()) {
        http_response_code(419);
        exit('Security token expired. Please go back and reload the page.');
    }
}
