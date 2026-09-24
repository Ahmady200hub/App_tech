<!DOCTYPE html>
<?php
// ============================================================
//  Aptech Portal — One-Time Installer / Setup
//  Visit: http://localhost/aptech_portal/setup.php
//  DELETE THIS FILE after setup is complete.
// ============================================================

// Prevent running in production if a lock file exists
if (file_exists(__DIR__ . '/setup.lock')) {
    die('<!DOCTYPE html><html><head><title>Setup Complete</title>
    <style>body{font-family:sans-serif;background:#060d1a;color:#f0f6ff;display:flex;align-items:center;justify-content:center;min-height:100vh}
    .box{background:#0c1628;border:1px solid rgba(0,229,195,.3);border-radius:16px;padding:2rem;max-width:460px;text-align:center}
    h2{color:#00e5c3}p{color:#8fa4c8}</style></head>
    <body><div class="box"><h2>✅ Already Installed</h2>
    <p>Setup has already been completed. Delete <code>setup.lock</code> to run again.</p>
    <p><a href="index.php" style="color:#00e5c3">Go to Portal →</a></p>
    </div></body></html>');
}

require_once __DIR__ . '/includes/config.php';

$steps   = [];
$success = true;
$ran     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {
    $ran = true;

    // ── Step 1: Connect as root (no DB selected yet) ───────────
    try {
        $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $steps[] = ['ok' => true,  'msg' => 'Connected to MySQL successfully.'];
    } catch (PDOException $e) {
        $steps[] = ['ok' => false, 'msg' => 'MySQL connection failed: ' . $e->getMessage()];
        $success = false;
    }

    if ($success) {
        // ── Step 2: Create database ────────────────────────────
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");
            $steps[] = ['ok' => true, 'msg' => 'Database `' . DB_NAME . '` created / confirmed.'];
        } catch (PDOException $e) {
            $steps[] = ['ok' => false, 'msg' => 'Could not create database: ' . $e->getMessage()];
            $success = false;
        }
    }

    if ($success) {
        // ── Step 3: Create tables ──────────────────────────────
        $tables = [
            "CREATE TABLE IF NOT EXISTS users (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                full_name    VARCHAR(120)  NOT NULL,
                email        VARCHAR(150)  NOT NULL UNIQUE,
                password     VARCHAR(255)  NOT NULL,
                role         ENUM('admin','lecturer','student') NOT NULL DEFAULT 'student',
                student_id   VARCHAR(30)   DEFAULT NULL,
                avatar_color TINYINT       DEFAULT 0,
                is_active    TINYINT(1)    NOT NULL DEFAULT 1,
                created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_login   DATETIME      DEFAULT NULL
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS modules (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                code         VARCHAR(20)   NOT NULL UNIQUE,
                name         VARCHAR(120)  NOT NULL,
                lecturer_id  INT           DEFAULT NULL,
                year_level   TINYINT       DEFAULT 1,
                schedule     VARCHAR(120)  DEFAULT NULL,
                is_active    TINYINT(1)    NOT NULL DEFAULT 1,
                created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS enrollments (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                student_id  INT NOT NULL,
                module_id   INT NOT NULL,
                enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_enroll (student_id, module_id),
                FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (module_id)  REFERENCES modules(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS sessions (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                module_id    INT  NOT NULL,
                session_date DATE NOT NULL,
                session_time ENUM('morning','afternoon','evening') NOT NULL DEFAULT 'morning',
                marked_by    INT  NOT NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_session (module_id, session_date, session_time),
                FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
                FOREIGN KEY (marked_by) REFERENCES users(id)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS attendance (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                session_id  INT  NOT NULL,
                student_id  INT  NOT NULL,
                status      ENUM('present','absent','late') NOT NULL DEFAULT 'present',
                marked_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_att (session_id, student_id),
                FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES users(id)   ON DELETE CASCADE
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS notifications (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                user_id     INT NOT NULL,
                message     TEXT NOT NULL,
                is_read     TINYINT(1) NOT NULL DEFAULT 0,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        ];

        try {
            foreach ($tables as $sql) $pdo->exec($sql);
            $steps[] = ['ok' => true, 'msg' => 'All 6 tables created successfully.'];
        } catch (PDOException $e) {
            $steps[] = ['ok' => false, 'msg' => 'Table creation failed: ' . $e->getMessage()];
            $success = false;
        }
    }

    if ($success) {
        // ── Step 4: Seed users with REAL bcrypt hashes ─────────
        $adminPw    = password_hash('Admin@1234',    PASSWORD_BCRYPT, ['cost' => 12]);
        $lecturerPw = password_hash('Lecturer@1234', PASSWORD_BCRYPT, ['cost' => 12]);
        $studentPw  = password_hash('Student@1234',  PASSWORD_BCRYPT, ['cost' => 12]);

        $users = [
            // [full_name, email, hash, role, student_id, avatar_color]
            ['Hauwa Bello',       'admin@aptech.edu.ng',   $adminPw,    'admin',    null,            0],
            ['Obi Chukwu',        'obi@aptech.edu.ng',     $lecturerPw, 'lecturer', null,            1],
            ['Grace Eze',         'grace@aptech.edu.ng',   $lecturerPw, 'lecturer', null,            2],
            ['Emeka Dike',        'emeka@aptech.edu.ng',   $lecturerPw, 'lecturer', null,            3],
            ['Aisha Bello',       'aisha@aptech.edu.ng',   $lecturerPw, 'lecturer', null,            4],
            ['Sola Adeyemi',      'sola@aptech.edu.ng',    $lecturerPw, 'lecturer', null,            0],
            ['Uche Nwachukwu',    'uche@aptech.edu.ng',    $lecturerPw, 'lecturer', null,            5],
            ['Chidi Okafor',      'chidi@aptech.edu.ng',   $studentPw,  'student',  'APT-2026-001',  0],
            ['Ngozi Adeyemi',     'ngozi@aptech.edu.ng',   $studentPw,  'student',  'APT-2026-002',  1],
            ['Emeka Musa',        'emekam@aptech.edu.ng',  $studentPw,  'student',  'APT-2026-003',  2],
            ['Adaeze Okonkwo',    'adaeze@aptech.edu.ng',  $studentPw,  'student',  'APT-2026-004',  5],
            ['Kemi Ibrahim',      'kemi@aptech.edu.ng',    $studentPw,  'student',  'APT-2026-005',  3],
            ['Tunde Nwosu',       'tunde@aptech.edu.ng',   $studentPw,  'student',  'APT-2026-006',  5],
            ['Fatima Abdullahi',  'fatima@aptech.edu.ng',  $studentPw,  'student',  'APT-2026-007',  3],
            ['Bayo Eniola',       'bayo@aptech.edu.ng',    $studentPw,  'student',  'APT-2026-008',  4],
            ['Aisha Lawal',       'aishal@aptech.edu.ng',  $studentPw,  'student',  'APT-2026-009',  3],
            ['Uche Nnam',         'uchen@aptech.edu.ng',   $studentPw,  'student',  'APT-2026-010',  4],
            ['Sade Okonkwo',      'sade@aptech.edu.ng',    $studentPw,  'student',  'APT-2026-011',  0],
            ['Dele Obi',          'dele@aptech.edu.ng',    $studentPw,  'student',  'APT-2026-012',  1],
            ['Funke Abiodun',     'funke@aptech.edu.ng',   $studentPw,  'student',  'APT-2026-013',  2],
            ['Musa Garba',        'musag@aptech.edu.ng',   $studentPw,  'student',  'APT-2026-014',  3],
            ['Chisom Nze',        'chisom@aptech.edu.ng',  $studentPw,  'student',  'APT-2026-015',  4],
        ];

        try {
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO users (full_name,email,password,role,student_id,avatar_color)
                 VALUES (?,?,?,?,?,?)"
            );
            $inserted = 0;
            foreach ($users as $u) {
                $stmt->execute($u);
                $inserted += $stmt->rowCount();
            }
            $steps[] = ['ok' => true, 'msg' => "Seeded $inserted user(s) with correct bcrypt hashes (existing users skipped)."];
        } catch (PDOException $e) {
            $steps[] = ['ok' => false, 'msg' => 'User seeding failed: ' . $e->getMessage()];
            $success = false;
        }
    }

    if ($success) {
        // ── Step 5: Seed modules ───────────────────────────────
        try {
            // Get lecturer IDs by email
            $lec = [];
            foreach (['obi','grace','emeka','aisha','sola','uche'] as $prefix) {
                $s = $pdo->prepare("SELECT id FROM users WHERE email=?");
                $s->execute(["$prefix@aptech.edu.ng"]);
                $lec[] = (int)$s->fetchColumn();
            }

            $modules = [
                [$lec[0], 'APT-101', 'HTML/CSS Fundamentals',  1, 'Mon & Wed 9–11 AM'],
                [$lec[1], 'APT-102', 'Python Programming',     1, 'Tue & Thu 1–3 PM'],
                [$lec[2], 'APT-103', 'Java Programming',       1, 'Fri 9 AM–12 PM'],
                [$lec[3], 'APT-201', 'Web Design',             2, 'Wed 1–4 PM'],
                [$lec[4], 'APT-204', 'Database Management',    2, 'Mon & Fri 11 AM–1 PM'],
                [$lec[5], 'APT-305', 'Computer Networking',    3, 'Tue & Thu 9–11 AM'],
            ];

            $mStmt = $pdo->prepare(
                "INSERT IGNORE INTO modules (lecturer_id,code,name,year_level,schedule)
                 VALUES (?,?,?,?,?)"
            );
            foreach ($modules as $m) $mStmt->execute($m);
            $steps[] = ['ok' => true, 'msg' => '6 modules seeded.'];
        } catch (PDOException $e) {
            $steps[] = ['ok' => false, 'msg' => 'Module seeding failed: ' . $e->getMessage()];
            $success = false;
        }
    }

    if ($success) {
        // ── Step 6: Seed enrollments ───────────────────────────
        try {
            // Get module IDs
            $mids = [];
            foreach (['APT-101','APT-102','APT-103','APT-201','APT-204','APT-305'] as $code) {
                $s = $pdo->prepare("SELECT id FROM modules WHERE code=?");
                $s->execute([$code]);
                $mids[$code] = (int)$s->fetchColumn();
            }

            // Get student IDs by student_id field
            $sids = [];
            for ($n=1; $n<=15; $n++) {
                $sid = 'APT-2026-' . str_pad($n, 3, '0', STR_PAD_LEFT);
                $s = $pdo->prepare("SELECT id FROM users WHERE student_id=?");
                $s->execute([$sid]);
                $sids[$sid] = (int)$s->fetchColumn();
            }

            $enrollments = [
                [$sids['APT-2026-001'], $mids['APT-101']],
                [$sids['APT-2026-001'], $mids['APT-102']],
                [$sids['APT-2026-002'], $mids['APT-101']],
                [$sids['APT-2026-002'], $mids['APT-103']],
                [$sids['APT-2026-003'], $mids['APT-102']],
                [$sids['APT-2026-003'], $mids['APT-201']],
                [$sids['APT-2026-004'], $mids['APT-201']],
                [$sids['APT-2026-004'], $mids['APT-101']],
                [$sids['APT-2026-005'], $mids['APT-103']],
                [$sids['APT-2026-005'], $mids['APT-204']],
                [$sids['APT-2026-006'], $mids['APT-204']],
                [$sids['APT-2026-006'], $mids['APT-305']],
                [$sids['APT-2026-007'], $mids['APT-204']],
                [$sids['APT-2026-007'], $mids['APT-102']],
                [$sids['APT-2026-008'], $mids['APT-305']],
                [$sids['APT-2026-008'], $mids['APT-103']],
                [$sids['APT-2026-009'], $mids['APT-101']],
                [$sids['APT-2026-009'], $mids['APT-103']],
                [$sids['APT-2026-010'], $mids['APT-102']],
                [$sids['APT-2026-010'], $mids['APT-204']],
                [$sids['APT-2026-011'], $mids['APT-305']],
                [$sids['APT-2026-011'], $mids['APT-201']],
                [$sids['APT-2026-012'], $mids['APT-101']],
                [$sids['APT-2026-012'], $mids['APT-204']],
                [$sids['APT-2026-013'], $mids['APT-101']],
                [$sids['APT-2026-013'], $mids['APT-102']],
                [$sids['APT-2026-014'], $mids['APT-102']],
                [$sids['APT-2026-014'], $mids['APT-201']],
                [$sids['APT-2026-015'], $mids['APT-204']],
                [$sids['APT-2026-015'], $mids['APT-305']],
            ];

            $eStmt = $pdo->prepare("INSERT IGNORE INTO enrollments (student_id,module_id) VALUES (?,?)");
            foreach ($enrollments as $e) {
                if ($e[0] && $e[1]) $eStmt->execute($e);
            }
            $steps[] = ['ok' => true, 'msg' => count($enrollments) . ' enrollments seeded.'];
        } catch (PDOException $e) {
            $steps[] = ['ok' => false, 'msg' => 'Enrollment seeding failed: ' . $e->getMessage()];
            $success = false;
        }
    }

    if ($success) {
        // ── Step 7: Seed sample attendance data ───────────────
        try {
            $adminId = (int)$pdo->query("SELECT id FROM users WHERE email='admin@aptech.edu.ng'")->fetchColumn();

            // Create sessions for the last 10 weekdays
            $sessionDates = [];
            $d = new DateTime();
            while (count($sessionDates) < 10) {
                if ((int)$d->format('N') <= 5) $sessionDates[] = $d->format('Y-m-d');
                $d->modify('-1 day');
            }

            $allModuleIds = $pdo->query("SELECT id FROM modules")->fetchAll(PDO::FETCH_COLUMN);
            $sessInsert   = $pdo->prepare("INSERT IGNORE INTO sessions (module_id,session_date,session_time,marked_by) VALUES (?,?,'morning',?)");
            $attInsert    = $pdo->prepare("INSERT IGNORE INTO attendance (session_id,student_id,status) VALUES (?,?,?)");

            foreach ($allModuleIds as $mid) {
                // Get enrolled students for this module
                $enrolled = $pdo->prepare("SELECT student_id FROM enrollments WHERE module_id=?");
                $enrolled->execute([$mid]);
                $studentIds = $enrolled->fetchAll(PDO::FETCH_COLUMN);

                foreach (array_slice($sessionDates, 0, 8) as $date) {
                    $sessInsert->execute([$mid, $date, $adminId]);
                    $sessionId = (int)$pdo->lastInsertId();
                    if (!$sessionId) {
                        // Session already existed, get its ID
                        $s2 = $pdo->prepare("SELECT id FROM sessions WHERE module_id=? AND session_date=? AND session_time='morning'");
                        $s2->execute([$mid, $date]);
                        $sessionId = (int)$s2->fetchColumn();
                    }
                    if (!$sessionId) continue;

                    foreach ($studentIds as $stuId) {
                        // Vary attendance: 80% present, 12% absent, 8% late
                        $r = rand(1, 100);
                        $status = $r <= 80 ? 'present' : ($r <= 92 ? 'absent' : 'late');
                        $attInsert->execute([$sessionId, $stuId, $status]);
                    }
                }
            }
            $steps[] = ['ok' => true, 'msg' => 'Sample attendance data seeded for last 8 sessions per module.'];
        } catch (PDOException $e) {
            $steps[] = ['ok' => false, 'msg' => 'Attendance seeding failed: ' . $e->getMessage()];
            // Non-fatal — don't stop
        }

        // Write lock file
        file_put_contents(__DIR__ . '/setup.lock', date('Y-m-d H:i:s'));
        $steps[] = ['ok' => true, 'msg' => 'Setup lock file created. Delete setup.lock to re-run.'];
    }
}
?>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Aptech Portal — Setup</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#060d1a;color:#f0f6ff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
.bg-orbs{position:fixed;inset:0;pointer-events:none;overflow:hidden}
.orb{position:absolute;border-radius:50%;filter:blur(90px)}
.orb1{width:500px;height:500px;background:radial-gradient(circle,rgba(0,229,195,.07),transparent);top:-100px;left:-100px}
.orb2{width:600px;height:600px;background:radial-gradient(circle,rgba(59,158,255,.05),transparent);bottom:-150px;right:-100px}
.wrap{position:relative;z-index:1;width:100%;max-width:580px}
.logo-row{display:flex;align-items:center;gap:12px;margin-bottom:2rem}
.gem{width:44px;height:44px;background:linear-gradient(135deg,#00e5c3,#3b9eff);border-radius:12px;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:17px;color:#060d1a;box-shadow:0 0 25px rgba(0,229,195,.25)}
.wm{font-family:'Syne',sans-serif;font-size:18px;font-weight:800}
.wm span{color:#00e5c3}
h1{font-family:'Syne',sans-serif;font-size:28px;font-weight:800;margin-bottom:6px;letter-spacing:-.5px}
.sub{color:#8fa4c8;font-size:14px;margin-bottom:2rem;line-height:1.6}
.card{background:rgba(12,22,40,.9);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:2rem;backdrop-filter:blur(20px);box-shadow:0 20px 60px rgba(0,0,0,.4)}
.info-box{background:rgba(0,229,195,.06);border:1px solid rgba(0,229,195,.2);border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:13px;color:#8fa4c8;line-height:1.8}
.info-box strong{color:#00e5c3}
.cred-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:.75rem}
.cred{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:.6rem .8rem;font-size:12px}
.cred .role{font-weight:800;color:#00e5c3;font-size:11px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
.cred code{color:#f0f6ff;font-size:11px;display:block;line-height:1.5}
.config-warn{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:10px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:13px;color:#f59e0b}
.btn-run{width:100%;padding:14px;background:linear-gradient(135deg,#00e5c3,#3b9eff);border:none;border-radius:12px;color:#060d1a;font-family:'Syne',sans-serif;font-weight:800;font-size:16px;cursor:pointer;transition:all .2s;letter-spacing:.3px}
.btn-run:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(0,229,195,.3)}
.btn-run:disabled{opacity:.5;cursor:not-allowed;transform:none}
.steps{margin-top:1.5rem}
.step{display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:13px}
.step:last-child{border-bottom:none}
.step-icon{font-size:16px;margin-top:1px;flex-shrink:0}
.step.ok .step-icon::before{content:'✅'}
.step.fail .step-icon::before{content:'❌'}
.step.ok .step-msg{color:#8fa4c8}
.step.fail .step-msg{color:#f43f5e}
.success-banner{background:rgba(16,217,122,.1);border:1px solid rgba(16,217,122,.3);border-radius:12px;padding:1rem 1.25rem;margin-top:1.5rem;text-align:center}
.success-banner h3{color:#10d97a;font-size:16px;margin-bottom:4px}
.success-banner p{color:#8fa4c8;font-size:13px;margin-bottom:.75rem}
.success-banner a{display:inline-block;padding:10px 24px;background:linear-gradient(135deg,#00e5c3,#3b9eff);color:#060d1a;border-radius:10px;font-weight:800;text-decoration:none;font-size:14px}
.fail-banner{background:rgba(244,63,94,.1);border:1px solid rgba(244,63,94,.3);border-radius:12px;padding:1rem 1.25rem;margin-top:1.5rem;text-align:center;color:#f43f5e;font-size:13px}
</style>
</head>
<body>
<div class="bg-orbs"><div class="orb orb1"></div><div class="orb orb2"></div></div>

<div class="wrap">
  <div class="logo-row">
    <div class="gem">AP</div>
    <div class="wm">APTECH <span>PORTAL</span></div>
  </div>
  <h1>Database Setup</h1>
  <p class="sub">This installer creates your database, tables, and seeds all demo data with correct passwords. Run once, then delete this file.</p>

  <div class="card">

    <?php if (!$ran): ?>

    <div class="info-box">
      <strong>What this does:</strong><br>
      1. Creates the <code>aptech_portal</code> MySQL database<br>
      2. Creates all 6 tables (users, modules, enrollments, sessions, attendance, notifications)<br>
      3. Seeds 22 demo accounts with <strong>real bcrypt-hashed passwords</strong><br>
      4. Seeds 6 modules, 30 enrollments, and sample attendance history
      <div class="cred-grid">
        <div class="cred"><div class="role">Admin</div><code>admin@aptech.edu.ng</code><code>Admin@1234</code></div>
        <div class="cred"><div class="role">Lecturer</div><code>obi@aptech.edu.ng</code><code>Lecturer@1234</code></div>
        <div class="cred"><div class="role">Student</div><code>chidi@aptech.edu.ng</code><code>Student@1234</code></div>
      </div>
    </div>

    <div class="config-warn">
      ⚙ Check <code>includes/config.php</code> before running — make sure DB_HOST, DB_USER, and DB_PASS match your MySQL setup. Default XAMPP: user=<strong>root</strong>, password=<strong>(blank)</strong>.
    </div>

    <form method="POST">
      <input type="hidden" name="run_setup" value="1"/>
      <button type="submit" class="btn-run">🚀 Run Setup Now</button>
    </form>

    <?php else: ?>

    <div class="steps">
      <?php foreach ($steps as $step): ?>
      <div class="step <?= $step['ok'] ? 'ok' : 'fail' ?>">
        <span class="step-icon"></span>
        <span class="step-msg"><?= htmlspecialchars($step['msg']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($success): ?>
    <div class="success-banner">
      <h3>✅ Setup Complete!</h3>
      <p>Your database is ready. You can now log in to the portal.<br>
      <strong>Important:</strong> Delete <code>setup.php</code> and <code>setup.lock</code> from your server for security.</p>
      <a href="index.php">Go to Login →</a>
    </div>
    <?php else: ?>
    <div class="fail-banner">
      ❌ Setup failed. Check the errors above, fix your configuration, delete <code>setup.lock</code> (if it exists), and try again.
    </div>
    <?php endif; ?>

    <?php endif; ?>

  </div>
</div>
</body>
</html>
