<?php
// ─── Database Configuration ───────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Change to your MySQL username
define('DB_PASS', '');              // Change to your MySQL password
define('DB_NAME', 'aptech_portal');
define('DB_CHARSET', 'utf8mb4');

// ─── App Configuration ────────────────────────────────────────
define('APP_NAME',    'Aptech Attendance Portal');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost/aptech_portal'); // Adjust as needed

// ─── Session Configuration ────────────────────────────────────
define('SESSION_NAME',    'aptech_session');
define('SESSION_TIMEOUT', 3600); // 1 hour

// ─── Security ────────────────────────────────────────────────
define('BCRYPT_COST', 12);

// ─── Attendance Threshold ────────────────────────────────────
define('ATTENDANCE_THRESHOLD', 75); // percent

// ─── PDO Connection ───────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Show friendly error instead of leaking credentials
            die(renderDBError($e->getMessage()));
        }
    }
    return $pdo;
}

function renderDBError(string $msg): string {
    return '<!DOCTYPE html><html><head><title>Database Error</title>
    <style>body{font-family:sans-serif;background:#060d1a;color:#f0f6ff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
    .box{background:#0c1628;border:1px solid rgba(244,63,94,.3);border-radius:16px;padding:2rem;max-width:500px;text-align:center}
    h2{color:#f43f5e;margin-bottom:12px}p{color:#8fa4c8;font-size:14px;line-height:1.6}
    code{background:rgba(255,255,255,.06);padding:2px 8px;border-radius:6px;font-size:13px}</style></head>
    <body><div class="box"><h2>⚠ Database Connection Failed</h2>
    <p>Could not connect to MySQL. Please check your settings in <code>includes/config.php</code> and make sure XAMPP/MySQL is running.</p>
    <p style="margin-top:1rem;font-size:12px;color:#4a5f7e">'.htmlspecialchars($msg).'</p></div></body></html>';
}
