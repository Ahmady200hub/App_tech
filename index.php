<?php
require_once __DIR__ . '/includes/auth.php';
startSecureSession();

// Already logged in — redirect
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    if ($role === 'student') redirect('pages/student_overview.php');
    else redirect('pages/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            $result = login($email, $password);
            if ($result['success']) {
                $role = $result['role'];
                if ($role === 'student') redirect('pages/student_overview.php');
                else redirect('pages/dashboard.php');
            } else {
                $error = $result['message'];
            }
        }
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Sign In — Aptech Attendance Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
<style>
body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem}
.login-wrap{width:100%;max-width:460px;position:relative;z-index:1}
.logo-row{display:flex;align-items:center;gap:12px;margin-bottom:2rem}
.logo-gem{width:46px;height:46px;background:linear-gradient(135deg,var(--cyan),var(--blue));border-radius:13px;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:17px;color:#060d1a;box-shadow:0 0 30px rgba(0,229,195,.3)}
.logo-wm{font-family:'Syne',sans-serif;font-size:19px;font-weight:800;letter-spacing:-.5px;color:var(--t1)}
.logo-wm span{color:var(--cyan)}
.headline{font-family:'Syne',sans-serif;font-size:40px;font-weight:800;line-height:1.08;margin-bottom:8px;letter-spacing:-2px}
.headline em{background:linear-gradient(135deg,var(--cyan),var(--blue));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;font-style:normal}
.sub{color:var(--t2);font-size:15px;margin-bottom:2rem;line-height:1.6}
.glass-card{background:rgba(12,22,40,.88);border:1px solid var(--border2);border-radius:24px;padding:2rem;backdrop-filter:blur(20px);box-shadow:0 24px 80px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.06)}
.err-box{background:rgba(244,63,94,.1);border:1px solid rgba(244,63,94,.3);border-radius:10px;padding:10px 14px;font-size:13px;color:var(--red);margin-bottom:1.25rem;display:flex;align-items:center;gap:8px}
.creds-hint{background:rgba(0,229,195,.06);border:1px solid rgba(0,229,195,.15);border-radius:10px;padding:12px 14px;font-size:12px;color:var(--t2);margin-top:1rem;line-height:1.8}
.creds-hint strong{color:var(--cyan)}
.btn-login{width:100%;padding:14px;background:linear-gradient(135deg,var(--cyan),var(--blue));border:none;border-radius:12px;color:#060d1a;font-family:'Syne',sans-serif;font-weight:800;font-size:16px;cursor:pointer;transition:all .2s;letter-spacing:.3px;margin-top:4px}
.btn-login:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(0,229,195,.35)}
.btn-login:active{transform:translateY(0)}
</style>
</head>
<body>
<div class="bg-orbs"><div class="orb orb1"></div><div class="orb orb2"></div><div class="orb orb3"></div></div>

<div class="login-wrap">
  <div class="logo-row">
    <div class="logo-gem">AP</div>
    <div class="logo-wm">APTECH <span>PORTAL</span></div>
  </div>
  <div class="headline">Attendance,<br><em>Reimagined.</em></div>
  <div class="sub">The digital attendance system built for Aptech —<br>fast, accurate, and always on.</div>

  <div class="glass-card">
    <?php if ($error): ?>
    <div class="err-box">⚠ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($token) ?>"/>

      <div class="form-group">
        <label for="email">Email Address</label>
        <input class="form-control <?= $error?'is-error':'' ?>"
               type="email" id="email" name="email"
               placeholder="you@aptech.edu.ng"
               value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email"/>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input class="form-control <?= $error?'is-error':'' ?>"
               type="password" id="password" name="password"
               placeholder="••••••••" required autocomplete="current-password"/>
      </div>

      <button type="submit" class="btn-login">Sign In →</button>
    </form>

    <div class="creds-hint">
      <strong>Demo Credentials</strong><br>
      Admin: <strong>admin@aptech.edu.ng</strong> / <strong>Admin@1234</strong><br>
      Lecturer: <strong>obi@aptech.edu.ng</strong> / <strong>Lecturer@1234</strong><br>
      Student: <strong>chidi@aptech.edu.ng</strong> / <strong>Student@1234</strong>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
