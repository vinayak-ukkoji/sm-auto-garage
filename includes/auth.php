<?php
/** Login, session user and role-based permissions. */

const ROLE_PERMS = [
    'admin'      => ['*'],
    'advisor'    => ['service', 'prices', 'parts', 'accounts', 'customers', 'vendors', 'reminders',
                     'feedback', 'reports', 'search', 'invoice'],
    'technician' => ['service'],
];

function current_user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $id = $_SESSION['uid'] ?? 0;
    if (!$id) return $u = null;
    $u = row('SELECT id, name, mobile, email, role, language, is_active FROM users WHERE id = ?', [$id]);
    if (!$u || !$u['is_active']) { $_SESSION = []; return $u = null; }
    return $u;
}

function uid(): ?int { $u = current_user(); return $u ? (int) $u['id'] : null; }
function role(): string { return current_user()['role'] ?? ''; }
function is_admin(): bool { return role() === 'admin'; }
function is_tech(): bool { return role() === 'technician'; }

function can(string $perm): bool
{
    $perms = ROLE_PERMS[role()] ?? [];
    return in_array('*', $perms, true) || in_array($perm, $perms, true);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    return $u;
}

function require_perm(string $perm): void
{
    require_login();
    if (!can($perm)) {
        http_response_code(403);
        $title = t('access_denied');
        include __DIR__ . '/header.php';
        echo '<div class="empty-state"><i class="bi bi-shield-lock"></i><p>' . e(t('no_permission')) . '</p></div>';
        include __DIR__ . '/footer.php';
        exit;
    }
}

function api_perm(string $perm): void { if (!can($perm)) json_err(t('no_permission'), 403); }

function too_many_attempts(string $mobile): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $n = (int) val('SELECT COUNT(*) FROM login_attempts WHERE (mobile = ? OR ip_address = ?) AND attempted_at > NOW() - INTERVAL 15 MINUTE', [$mobile, $ip]);
    return $n >= 5;
}

function attempt_login(string $mobile, string $password): bool
{
    $u = row('SELECT * FROM users WHERE mobile = ? AND is_active = 1', [$mobile]);
    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid']  = (int) $u['id'];
        $_SESSION['lang'] = $u['language'];
        q('UPDATE users SET last_login = NOW() WHERE id = ?', [$u['id']]);
        q('DELETE FROM login_attempts WHERE mobile = ?', [$mobile]);
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        }
        return true;
    }
    db_insert('login_attempts', ['mobile' => $mobile, 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '']);
    return false;
}
