<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin', 'lecturer');
$db  = getDB();
$sid = (int)($_GET['id'] ?? 0);

if (!$sid) redirect('pages/students.php');

$student = $db->prepare("SELECT * FROM users WHERE id=? AND role='student'");
$student->execute([$sid]);
$student = $student->fetch();
if (!$student) redirect('pages/students.php');

$pageTitle  = e($student['full_name']) . ' — Detail';
$activePage = 'students';

// Per-module breakdown
$mods = $db->prepare("
    SELECT m.code, m.name,
           COUNT(s.id) AS total,
           SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present,
           SUM(CASE WHEN a.status='absent'  THEN 1 ELSE 0 END) AS absent,
           SUM(CASE WHEN a.status='late'    THEN 1 ELSE 0 END) AS late,
           ROUND(SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END)/NULLIF(COUNT(s.id),0)*100,1) AS pct
    FROM enrollments e
    JOIN modules m  ON m.id=e.module_id
    JOIN sessions s ON s.module_id=m.id
    LEFT JOIN attendance a ON a.session_id=s.id AND a.student_id=e.student_id
    WHERE e.student_id=? GROUP BY m.id ORDER BY m.code
");
$mods->execute([$sid]);
$modules = $mods->fetchAll();

// Recent history
$hist = $db->prepare("
    SELECT a.status, s.session_date, s.session_time, m.code AS mod_code
    FROM attendance a
    JOIN sessions s ON s.id=a.session_id
    JOIN modules m  ON m.id=s.module_id
    WHERE a.student_id=?
    ORDER BY s.session_date DESC LIMIT 20
");
$hist->execute([$sid]);
$history = $hist->fetchAll();

// Overall pct
$overall = $db->prepare("
    SELECT ROUND(SUM(a.status IN ('present','late'))/NULLIF(COUNT(a.id),0)*100,1) AS pct,
           COUNT(a.id) AS total, SUM(a.status='absent') AS absent
    FROM enrollments e
    JOIN sessions s ON s.module_id=e.module_id
    LEFT JOIN attendance a ON a.session_id=s.id AND a.student_id=e.student_id
    WHERE e.student_id=?
");
$overall->execute([$sid]);
$ov = $overall->fetch();

$mcols = ['var(--cyan)','var(--blue)','var(--purple)','var(--amber)','var(--green)','var(--red)'];
$statusBadge = ['present'=>'<span class="badge b-green">Present</span>','absent'=>'<span class="badge b-red">Absent</span>','late'=>'<span class="badge b-amber">Late</span>'];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-fade">
<div class="pg-header-row">
  <div>
    <div class="pg-eyebrow">Student Detail</div>
    <div class="pg-title"><?= e($student['full_name']) ?></div>
    <div class="pg-sub"><?= e($student['student_id']) ?> · <?= e($student['email']) ?></div>
  </div>
  <a href="<?= BASE_URL ?>/pages/students.php" class="btn btn-ghost">← Back to Students</a>
</div>

<!-- Summary Cards -->
<div class="stats-row">
  <div class="stat-card" style="--accent:linear-gradient(90deg,var(--cyan),var(--blue))">
    <div class="stat-label">Overall Attendance</div>
    <div class="stat-val" style="color:var(--cyan)"><?= ($ov['pct']??0)>0?$ov['pct'].'%':'—' ?></div>
    <div class="stat-sub"><span class="delta <?= ($ov['pct']??0)>=ATTENDANCE_THRESHOLD?'delta-up':'delta-dn' ?>"><?= ($ov['pct']??0)>=ATTENDANCE_THRESHOLD?'On Track':'At Risk' ?></span></div>
  </div>
  <div class="stat-card"><div class="stat-label">Total Sessions</div><div class="stat-val"><?= (int)($ov['total']??0) ?></div></div>
  <div class="stat-card"><div class="stat-label">Absent</div><div class="stat-val" style="color:var(--red)"><?= (int)($ov['absent']??0) ?></div></div>
  <div class="stat-card"><div class="stat-label">Modules</div><div class="stat-val" style="color:var(--purple)"><?= count($modules) ?></div></div>
</div>

<div class="grid2">
<!-- Module breakdown -->
<div class="card">
  <div class="card-head"><span class="card-title">📚 Module Breakdown</span></div>
  <div class="card-body">
    <?php foreach ($modules as $i => $m): $r=(float)($m['pct']??0); ?>
    <div class="att-bar-row">
      <div class="att-bar-top">
        <span class="att-bar-name"><?= e($m['code']) ?> · <?= e($m['name']) ?></span>
        <span class="att-bar-pct" style="color:<?= pctColor($r) ?>"><?= $r>0?$r.'%':'—' ?></span>
      </div>
      <div class="prog-track"><div class="prog-fill" data-pct="<?= $r ?>" style="width:0;background:<?= $mcols[$i%count($mcols)] ?>"></div></div>
      <div style="font-size:11px;color:var(--t3);margin-top:3px"><?= $m['present'] ?> present · <?= $m['absent'] ?> absent · <?= $m['late'] ?> late (<?= $m['total'] ?> total)</div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Recent sessions -->
<div class="card">
  <div class="card-head"><span class="card-title">📅 Recent Sessions</span></div>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Date</th><th>Module</th><th>Time</th><th>Status</th></tr></thead>
    <tbody>
      <?php if (empty($history)): ?>
      <tr><td colspan="4" style="text-align:center;color:var(--t3);padding:1.5rem">No records yet.</td></tr>
      <?php else: ?>
      <?php foreach ($history as $h): ?>
      <tr>
        <td><?= date('d M Y', strtotime($h['session_date'])) ?></td>
        <td><span class="badge b-blue"><?= e($h['mod_code']) ?></span></td>
        <td style="color:var(--t2);font-size:12px"><?= ucfirst($h['session_time']) ?></td>
        <td><?= $statusBadge[$h['status']] ?? '' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table></div>
</div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
