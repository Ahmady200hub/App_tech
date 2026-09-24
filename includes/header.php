<?php
require_once __DIR__ . '/auth.php';
$user = currentUser();
$role = $user['role'];
$threshold = (int)ATTENDANCE_THRESHOLD;

// Unread notifications count
$unread = 0;
if ($user['id']) {
    $stmt = getDB()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user['id']]);
    $unread = (int)$stmt->fetchColumn();
}

// At-risk count for sidebar badge (admin/lecturer only) — safe subquery
$atRiskCount = 0;
if ($user['id'] && in_array($role, ['admin','lecturer'])) {
    $arStmt = getDB()->prepare("
        SELECT COUNT(*) FROM (
            SELECT u.id
            FROM enrollments e
            INNER JOIN users   u ON u.id = e.student_id
            INNER JOIN modules m ON m.id = e.module_id
            INNER JOIN sessions s ON s.module_id = m.id
            LEFT  JOIN attendance a ON a.session_id = s.id AND a.student_id = u.id
            WHERE u.is_active = 1
            GROUP BY u.id, m.id
            HAVING COUNT(s.id) > 0
               AND ROUND(
                     COALESCE(SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END),0)
                     / NULLIF(COUNT(s.id),0) * 100
                   , 1) < :threshold
        ) AS sub
    ");
    $arStmt->execute([':threshold' => $threshold]);
    $atRiskCount = (int)$arStmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($pageTitle ?? 'Dashboard') ?> &mdash; Aptech Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="bg-orbs">
  <div class="orb orb1"></div>
  <div class="orb orb2"></div>
  <div class="orb orb3"></div>
</div>

<!-- TOPBAR -->
<header class="topbar">
  <a href="<?= BASE_URL ?>/pages/dashboard.php" class="tb-logo">
    <div class="tb-gem">AP</div>
    <span class="tb-title">Aptech SAP</span>
  </a>
  <span class="tb-chip"><?= e(date('D, d M Y')) ?></span>
  <span class="tb-chip chip-role"><?= ucfirst(e($role)) ?></span>
  <a href="<?= BASE_URL ?>/pages/notifications.php" class="tb-icon-btn" title="Notifications">
    🔔<?php if ($unread > 0): ?><span class="notif-dot"><?= $unread ?></span><?php endif; ?>
  </a>
  <a href="<?= BASE_URL ?>/pages/profile.php" class="tb-icon-btn" title="Profile">👤</a>
  <a href="<?= BASE_URL ?>/logout.php" class="tb-icon-btn" title="Logout">⬅</a>
</header>

<div class="app-body">

<!-- SIDEBAR -->
<nav class="sidebar">

<?php if ($role === 'admin' || $role === 'lecturer'): ?>
  <div class="sb-section">Overview</div>
  <a class="nav-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>"
     href="<?= BASE_URL ?>/pages/dashboard.php"><span class="ni">📊</span>Dashboard</a>

  <div class="sb-section">Management</div>
  <a class="nav-link <?= ($activePage ?? '') === 'mark'     ? 'active' : '' ?>"
     href="<?= BASE_URL ?>/pages/mark_attendance.php"><span class="ni">✅</span>Mark Attendance</a>
  <a class="nav-link <?= ($activePage ?? '') === 'students'  ? 'active' : '' ?>"
     href="<?= BASE_URL ?>/pages/students.php"><span class="ni">👥</span>Students</a>
  <a class="nav-link <?= ($activePage ?? '') === 'modules'   ? 'active' : '' ?>"
     href="<?= BASE_URL ?>/pages/modules.php"><span class="ni">📚</span>Modules</a>

  <div class="sb-section">Analytics</div>
  <a class="nav-link <?= ($activePage ?? '') === 'reports'   ? 'active' : '' ?>"
     href="<?= BASE_URL ?>/pages/reports.php"><span class="ni">📈</span>Reports</a>
  <a class="nav-link <?= ($activePage ?? '') === 'alerts'    ? 'active' : '' ?>"
     href="<?= BASE_URL ?>/pages/alerts.php">
    <span class="ni">⚠️</span>Alerts
    <?php if ($atRiskCount > 0): ?>
      <span class="nav-badge"><?= $atRiskCount ?></span>
    <?php endif; ?>
  </a>

  <?php if ($role === 'admin'): ?>
  <a class="nav-link <?= ($activePage ?? '') === 'users' ? 'active' : '' ?>"
     href="<?= BASE_URL ?>/pages/users.php"><span class="ni">🔑</span>Manage Users</a>
  <?php endif; ?>

<?php else: ?>
  <div class="sb-section">My Portal</div>
  <a class="nav-link <?= ($activePage ?? '') === 'overview'  ? 'active' : '' ?>"
     href="<?= BASE_URL ?>/pages/student_overview.php"><span class="ni">📊</span>Overview</a>
  <a class="nav-link <?= ($activePage ?? '') === 'history'   ? 'active' : '' ?>"
     href="<?= BASE_URL ?>/pages/student_history.php"><span class="ni">📅</span>History</a>
  <a class="nav-link <?= ($activePage ?? '') === 's-modules' ? 'active' : '' ?>"
     href="<?= BASE_URL ?>/pages/student_modules.php"><span class="ni">📚</span>My Modules</a>
<?php endif; ?>

  <div class="sidebar-user">
    <div class="su-wrap">
      <div class="su-av av-c<?= (int)$user['avatar_color'] % 6 ?>"><?= e($user['initials']) ?></div>
      <div>
        <div class="su-name"><?= e($user['name']) ?></div>
        <div class="su-role">
          <?= $role === 'student' ? e($user['student_id']) : ucfirst(e($role)) ?>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/logout.php" class="su-logout" title="Logout">✕</a>
    </div>
  </div>
</nav>

<!-- MAIN CONTENT -->
<main class="main-content">
<?php if (!empty($flashMsg)): ?>
  <div class="flash flash-<?= e($flashType ?? 'success') ?>">
    <?= e($flashMsg) ?>
    <button onclick="this.parentElement.remove()" class="flash-close">✕</button>
  </div>
<?php endif; ?>
