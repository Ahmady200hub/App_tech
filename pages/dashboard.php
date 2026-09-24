<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin', 'lecturer');
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
$db   = getDB();
$user = currentUser();
$role = $user['role'];
$threshold = (int)ATTENDANCE_THRESHOLD;

// ── Total students ────────────────────────────────────────────
$totalStudents = (int)$db->query(
    "SELECT COUNT(*) FROM users WHERE role='student' AND is_active=1"
)->fetchColumn();

// ── Today's attendance totals ─────────────────────────────────
$today = date('Y-m-d');
$todayStmt = $db->prepare("
    SELECT
        COALESCE(SUM(a.status = 'present'), 0) AS present,
        COALESCE(SUM(a.status = 'absent'),  0) AS absent,
        COALESCE(SUM(a.status = 'late'),    0) AS late
    FROM sessions s
    INNER JOIN attendance a ON a.session_id = s.id
    WHERE s.session_date = ?
");
$todayStmt->execute([$today]);
$ts = $todayStmt->fetch(PDO::FETCH_ASSOC);
$presentToday = (int)($ts['present'] ?? 0);
$absentToday  = (int)($ts['absent']  ?? 0);
$lateToday    = (int)($ts['late']    ?? 0);

// ── At-risk students ──────────────────────────────────────────
// Wrap in subquery so HAVING filters on the computed column safely
$atRiskStmt = $db->prepare("
    SELECT *
    FROM (
        SELECT
            u.id,
            u.full_name,
            u.student_id,
            u.avatar_color,
            m.code  AS mod_code,
            m.name  AS mod_name,
            COUNT(s.id) AS total_sessions,
            COALESCE(SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END), 0) AS attended,
            ROUND(
                COALESCE(SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END), 0)
                / NULLIF(COUNT(s.id), 0) * 100
            , 1) AS pct
        FROM enrollments e
        INNER JOIN users    u ON u.id = e.student_id
        INNER JOIN modules  m ON m.id = e.module_id
        INNER JOIN sessions s ON s.module_id = m.id
        LEFT  JOIN attendance a ON a.session_id = s.id AND a.student_id = u.id
        WHERE u.is_active = 1
        GROUP BY u.id, u.full_name, u.student_id, u.avatar_color, m.id, m.code, m.name
    ) AS sub
    WHERE sub.total_sessions > 0
      AND sub.pct < :threshold
    ORDER BY sub.pct ASC
    LIMIT 8
");
$atRiskStmt->execute([':threshold' => $threshold]);
$atRisk = $atRiskStmt->fetchAll(PDO::FETCH_ASSOC);
if (!is_array($atRisk)) $atRisk = [];
$atRiskCount = count($atRisk);

// ── Weekly chart (last 5 days) ────────────────────────────────
$weekDays = [];
for ($i = 4; $i >= 0; $i--) {
    $d     = date('Y-m-d', strtotime("-{$i} days"));
    $label = date('D', strtotime($d));
    $wStmt = $db->prepare("
        SELECT ROUND(
            COALESCE(SUM(a.status IN ('present','late')), 0)
            / NULLIF(COUNT(a.id), 0) * 100
        ) AS rate
        FROM sessions s
        INNER JOIN attendance a ON a.session_id = s.id
        WHERE s.session_date = ?
    ");
    $wStmt->execute([$d]);
    $rate = (int)($wStmt->fetchColumn() ?: 0);
    $weekDays[] = ['label' => $label, 'rate' => $rate, 'today' => ($d === $today)];
}

// ── Active modules ────────────────────────────────────────────
$modulesStmt = $db->query("
    SELECT m.id, m.code, m.name,
           u.full_name AS lecturer_name,
           COUNT(DISTINCT e.student_id) AS student_count
    FROM modules m
    LEFT JOIN users       u ON u.id = m.lecturer_id
    LEFT JOIN enrollments e ON e.module_id = m.id
    WHERE m.is_active = 1
    GROUP BY m.id, m.code, m.name, u.full_name
    LIMIT 6
");
$modules = $modulesStmt->fetchAll(PDO::FETCH_ASSOC);
if (!is_array($modules)) $modules = [];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-fade">

<div class="pg-header">
  <div class="pg-eyebrow">Live Overview</div>
  <div class="pg-title">Good <?= date('G') < 12 ? 'morning' : (date('G') < 17 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $user['name'])[0]) ?> 👋</div>
  <div class="pg-sub">Here's what's happening across all modules today &mdash; <?= e(date('l, d F Y')) ?></div>
</div>

<div class="stats-row">
  <div class="stat-card" style="--accent:linear-gradient(90deg,var(--cyan),var(--blue));--glow:rgba(0,229,195,.07)">
    <div class="stat-glow"></div>
    <div class="stat-label">Total Students</div>
    <div class="stat-val" style="color:var(--cyan)"><?= $totalStudents ?></div>
    <div class="stat-sub" style="color:var(--t3)">Enrolled this term</div>
  </div>
  <div class="stat-card" style="--accent:linear-gradient(90deg,var(--green),var(--cyan));--glow:rgba(16,217,122,.07)">
    <div class="stat-glow" style="background:rgba(16,217,122,.08)"></div>
    <div class="stat-label">Present Today</div>
    <div class="stat-val" style="color:var(--green)"><?= $presentToday ?></div>
    <div class="stat-sub"><span class="delta delta-up">+<?= $lateToday ?> late</span></div>
  </div>
  <div class="stat-card" style="--accent:linear-gradient(90deg,var(--red),var(--pink));--glow:rgba(244,63,94,.07)">
    <div class="stat-glow" style="background:rgba(244,63,94,.07)"></div>
    <div class="stat-label">Absent Today</div>
    <div class="stat-val" style="color:var(--red)"><?= $absentToday ?></div>
    <div class="stat-sub"><span class="delta delta-dn">Today</span></div>
  </div>
  <div class="stat-card" style="--accent:linear-gradient(90deg,var(--amber),var(--red));--glow:rgba(245,158,11,.07)">
    <div class="stat-glow" style="background:rgba(245,158,11,.07)"></div>
    <div class="stat-label">At Risk (&lt;<?= $threshold ?>%)</div>
    <div class="stat-val" style="color:var(--amber)"><?= $atRiskCount ?></div>
    <div class="stat-sub"><span class="delta delta-dn">Need action</span></div>
  </div>
  <div class="stat-card" style="--accent:linear-gradient(90deg,var(--purple),var(--blue))">
    <div class="stat-glow" style="background:rgba(168,85,247,.07)"></div>
    <div class="stat-label">Active Modules</div>
    <div class="stat-val" style="color:var(--purple)"><?= count($modules) ?></div>
    <div class="stat-sub" style="color:var(--t3)">This term</div>
  </div>
</div>

<div class="grid2">

<div class="card">
  <div class="card-head">
    <span class="card-title">📅 Attendance Trend</span>
    <span class="badge b-blue ml-auto">Last 5 Days</span>
  </div>
  <div class="card-body">
    <div class="week-chart">
      <div class="wc-grid">
        <div class="wc-gridline"><span>100%</span></div>
        <div class="wc-gridline"><span>75%</span></div>
        <div class="wc-gridline"><span>50%</span></div>
      </div>
      <?php foreach ($weekDays as $day): ?>
      <div class="wc-col">
        <div class="wc-bar" style="height:<?= max(4, (int)$day['rate']) ?>%;background:<?= $day['today'] ? 'linear-gradient(to top,var(--blue),rgba(59,158,255,.4))' : 'linear-gradient(to top,var(--cyan),rgba(0,229,195,.4))' ?>"></div>
        <span class="wc-label" style="color:<?= $day['today'] ? 'var(--blue)' : 'var(--t3)' ?>"><?= e($day['label']) ?><?= $day['rate'] > 0 ? ' ' . $day['rate'] . '%' : '' ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <span class="card-title">⚠️ At-Risk Students</span>
    <a href="<?= BASE_URL ?>/pages/alerts.php" class="badge b-red ml-auto"><?= $atRiskCount ?> flagged</a>
  </div>
  <div class="card-body" style="padding-top:.6rem">
    <?php if (empty($atRisk)): ?>
      <p style="color:var(--t3);font-size:13px;text-align:center;padding:1rem">✅ No students currently at risk.</p>
    <?php else: ?>
      <?php foreach ($atRisk as $s): ?>
      <div class="risk-card <?= (float)$s['pct'] < 65 ? 'risk-critical' : '' ?>">
        <div class="ac-av av-c<?= (int)$s['avatar_color'] % 6 ?>"><?= e(initials($s['full_name'])) ?></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($s['full_name']) ?></div>
          <div style="font-size:11px;color:var(--t3)"><?= e($s['mod_code']) ?> &middot; <?= e($s['mod_name']) ?></div>
        </div>
        <span class="badge <?= (float)$s['pct'] < 65 ? 'b-red' : 'b-amber' ?>"><?= $s['pct'] ?>%</span>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

</div>

<div class="card">
  <div class="card-head"><span class="card-title">⚡ Quick Actions</span></div>
  <div class="card-body">
    <div class="qa-grid">
      <a class="qa-btn" href="<?= BASE_URL ?>/pages/mark_attendance.php"><span class="qa-icon">✅</span><span class="qa-label">Mark Attendance</span><span class="qa-sub">Record today's session</span></a>
      <a class="qa-btn" href="<?= BASE_URL ?>/pages/reports.php"><span class="qa-icon">📊</span><span class="qa-label">Generate Report</span><span class="qa-sub">Weekly / monthly export</span></a>
      <a class="qa-btn" href="<?= BASE_URL ?>/pages/students.php"><span class="qa-icon">👥</span><span class="qa-label">View Students</span><span class="qa-sub">All enrollments</span></a>
      <a class="qa-btn" href="<?= BASE_URL ?>/pages/alerts.php"><span class="qa-icon">⚠️</span><span class="qa-label">Send Alerts</span><span class="qa-sub">Notify at-risk students</span></a>
      <a class="qa-btn" href="<?= BASE_URL ?>/pages/modules.php"><span class="qa-icon">📚</span><span class="qa-label">Modules</span><span class="qa-sub">Course overview</span></a>
      <?php if ($role === 'admin'): ?>
      <a class="qa-btn" href="<?= BASE_URL ?>/pages/users.php"><span class="qa-icon">🔑</span><span class="qa-label">Manage Users</span><span class="qa-sub">Add / edit accounts</span></a>
      <?php endif; ?>
    </div>
  </div>
</div>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
