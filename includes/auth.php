<?php
require_once __DIR__ . '/config.php';

// ─── Session Bootstrap ───────────────────────────────────────
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path'     => '/',
            'secure'   => false,   // set true in production with HTTPS
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    // Regenerate session ID every 30 min to prevent fixation
    if (!isset($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
}

// ─── Login ───────────────────────────────────────────────────
function login(string $email, string $password): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    // Update last login
    $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

    // Set session
    startSecureSession();
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email']= $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['student_id']= $user['student_id'];
    $_SESSION['avatar_color'] = $user['avatar_color'];
    $_SESSION['login_time']= time();

    return ['success' => true, 'role' => $user['role']];
}

// ─── Logout ──────────────────────────────────────────────────
function logout(): void {
    startSecureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'],
                  $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect('index.php');
}

// ─── Auth Guards ─────────────────────────────────────────────
function requireLogin(): void {
    startSecureSession();
    if (empty($_SESSION['user_id'])) {
        redirect('index.php');
    }
    // Session timeout check
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
        logout();
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        redirect('index.php');
    }
}

// ─── Helpers ─────────────────────────────────────────────────
function redirect(string $path): void {
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

function currentUser(): array {
    startSecureSession();
    return [
        'id'           => $_SESSION['user_id']    ?? 0,
        'name'         => $_SESSION['user_name']  ?? '',
        'email'        => $_SESSION['user_email'] ?? '',
        'role'         => $_SESSION['user_role']  ?? '',
        'student_id'   => $_SESSION['student_id'] ?? '',
        'avatar_color' => $_SESSION['avatar_color'] ?? 0,
        'initials'     => initials($_SESSION['user_name'] ?? ''),
    ];
}

function initials(string $name): string {
    $parts = explode(' ', trim($name));
    $ini = '';
    foreach ($parts as $p) { $ini .= strtoupper($p[0] ?? ''); }
    return substr($ini, 0, 2);
}

function isLoggedIn(): bool {
    startSecureSession();
    return !empty($_SESSION['user_id']);
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function sanitize(string $str): string {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function pctColor(float $pct): string {
    if ($pct >= 80) return '#10d97a';
    if ($pct >= 75) return '#00e5c3';
    if ($pct >= 65) return '#f59e0b';
    return '#f43f5e';
}

function statusBadge(float $pct): string {
    if ($pct >= 80) return '<span class="badge b-green">Good</span>';
    if ($pct >= 75) return '<span class="badge b-cyan">OK</span>';
    if ($pct >= 65) return '<span class="badge b-amber">At Risk</span>';
    return '<span class="badge b-red">Critical</span>';
}
