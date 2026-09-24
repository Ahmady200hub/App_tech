<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin','lecturer');
$pageTitle  = 'Reports';
$activePage = 'reports';
$db   = getDB();

// Module rates from DB
$modRates = $db->query("
    SELECT m.id, m.code, m.name,
           COUNT(DISTINCT s.id) AS total_sessions,
           COUNT(DISTINCT e.student_id) AS student_count,
           ROUND(SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END)/NULLIF(COUNT(a.id),0)*100,1) AS rate
    FROM modules m
    LEFT JOIN sessions s    ON s.module_id=m.id
    LEFT JOIN enrollments e ON e.module_id=m.id
    LEFT JOIN attendance a  ON a.session_id=s.id
    WHERE m.is_active=1
    GROUP BY m.id
    ORDER BY rate DESC
")->fetchAll();

$modColors = ['var(--cyan)','var(--blue)','var(--purple)','var(--amber)','var(--green)','var(--red)'];

// Generate report POST
$reportData = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $rangeType = $_POST['range']  ?? 'week';
    $modFilter = (int)($_POST['mod_filter'] ?? 0);

    switch ($rangeType) {
        case 'week':  $from = date('Y-m-d', strtotime('monday this week')); $to = date('Y-m-d'); break;
        case 'month': $from = date('Y-m-01'); $to = date('Y-m-d'); break;
        case 'term':  $from = '2026-01-01';  $to = date('Y-m-d'); break;
        default:      $from = date('Y-m-d', strtotime('-7 days')); $to = date('Y-m-d');
    }

    $modWhere = $modFilter ? " AND m.id = $modFilter " : '';

    $sql = "
        SELECT
            COUNT(DISTINCT s.id)   AS total_sessions,
            COUNT(DISTINCT e.student_id) AS total_students,
            SUM(a.status='present')  AS total_present,
            SUM(a.status='absent')   AS total_absent,
            SUM(a.status='late')     AS total_late,
            ROUND(SUM(a.status IN ('present','late'))/NULLIF(COUNT(a.id),0)*100,1) AS avg_rate
        FROM sessions s
        JOIN modules m ON m.id=s.module_id $modWhere
        JOIN enrollments e ON e.module_id=m.id
        LEFT JOIN attendance a ON a.session_id=s.id
        WHERE s.session_date BETWEEN ? AND ?
    ";
    $stmt = $db->prepare($sql); $stmt->execute([$from,$to]);
    $reportData = $stmt->fetch();
    $reportData['from'] = $from; $reportData['to'] = $to;
}

$allModules = $db->query("SELECT id,code,name FROM modules WHERE is_active=1 ORDER BY code")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-fade">
<div class="pg-header">
  <div class="pg-eyebrow">Analytics</div>
  <div class="pg-title">Reports</div>
  <div class="pg-sub">Generate and export attendance summaries instantly.</div>
</div>

<div class="grid2">
<div class="card">
  <div class="card-head"><span class="card-title">🗂 Generate Report</span></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <div class="form-group">
        <label>Date Range</label>
        <select name="range" class="form-control">
          <option value="week">This Week</option>
          <option value="month">This Month</option>
          <option value="term">This Term (2026)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Module Filter</label>
        <select name="mod_filter" class="form-control">
          <option value="0">All Modules</option>
          <?php foreach ($allModules as $m): ?>
          <option value="<?= $m['id'] ?>"><?= e($m['code']) ?> · <?= e($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-prim">📊 Generate Report</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><span class="card-title">📉 Module Attendance Rates</span></div>
  <div class="card-body">
    <?php foreach ($modRates as $i => $mr): $rate = (float)$mr['rate']; ?>
    <div class="att-bar-row">
      <div class="att-bar-top">
        <span class="att-bar-name"><?= e($mr['code']) ?> · <?= e($mr['name']) ?></span>
        <span class="att-bar-pct" style="color:<?= pctColor($rate) ?>"><?= $rate>0?$rate.'%':'—' ?></span>
      </div>
      <div class="prog-track">
        <div class="prog-fill" data-pct="<?= $rate ?>" style="width:0;background:<?= $modColors[$i%count($modColors)] ?>"></div>
      </div>
      <div style="font-size:11px;color:var(--t3);margin-top:3px"><?= $mr['student_count'] ?> students · <?= $mr['total_sessions'] ?> sessions</div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</div>

<?php if ($reportData): ?>
<div class="card">
  <div class="card-head">
    <span class="card-title">📊 Report: <?= date('d M Y', strtotime($reportData['from'])) ?> – <?= date('d M Y', strtotime($reportData['to'])) ?></span>
    <span class="badge b-green ml-auto">Generated <?= date('d M Y H:i') ?></span>
  </div>
  <div class="card-body">
    <div class="stats-row" style="margin-bottom:1rem">
      <div class="stat-card"><div class="stat-label">Sessions</div><div class="stat-val"><?= (int)$reportData['total_sessions'] ?></div></div>
      <div class="stat-card"><div class="stat-label">Avg. Rate</div><div class="stat-val" style="color:var(--cyan)"><?= $reportData['avg_rate'] ?>%</div></div>
      <div class="stat-card"><div class="stat-label">Present</div><div class="stat-val" style="color:var(--green)"><?= (int)$reportData['total_present'] ?></div></div>
      <div class="stat-card"><div class="stat-label">Absent</div><div class="stat-val" style="color:var(--red)"><?= (int)$reportData['total_absent'] ?></div></div>
      <div class="stat-card"><div class="stat-label">Late</div><div class="stat-val" style="color:var(--amber)"><?= (int)$reportData['total_late'] ?></div></div>
    </div>
    <p style="font-size:13px;color:var(--t2)">Report generated on <?= date('d M Y H:i') ?> · <?= (int)$reportData['total_students'] ?> students tracked</p>
  </div>
</div>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
