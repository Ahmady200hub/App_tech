<?php /* student_overview.php */
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');
$pageTitle  = 'My Attendance';
$activePage = 'overview';
$db   = getDB();
$user = currentUser();
$uid  = $user['id'];

// Overall stats
$overall = $db->prepare("
    SELECT COUNT(DISTINCT s.id) AS total,
           SUM(a.status IN ('present','late')) AS attended,
           SUM(a.status='absent') AS absent,
           SUM(a.status='late') AS late,
           ROUND(SUM(a.status IN ('present','late'))/NULLIF(COUNT(s.id),0)*100,1) AS pct
    FROM enrollments e
    JOIN sessions s ON s.module_id=e.module_id
    LEFT JOIN attendance a ON a.session_id=s.id AND a.student_id=e.student_id
    WHERE e.student_id=?
");
$overall->execute([$uid]); $ov=$overall->fetch();

// Per-module breakdown
$modBreak = $db->prepare("
    SELECT m.id, m.code, m.name, m.schedule,
           COUNT(s.id) AS total,
           SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END) AS attended,
           ROUND(SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END)/NULLIF(COUNT(s.id),0)*100,1) AS pct,
           u.full_name AS lecturer
    FROM enrollments e
    JOIN modules m ON m.id=e.module_id
    LEFT JOIN users u ON u.id=m.lecturer_id
    JOIN sessions s ON s.module_id=m.id
    LEFT JOIN attendance a ON a.session_id=s.id AND a.student_id=e.student_id
    WHERE e.student_id=? GROUP BY m.id ORDER BY pct ASC
");
$modBreak->execute([$uid]); $mods=$modBreak->fetchAll();

$pctVal = (float)($ov['pct'] ?? 0);
$circumf = 402.1;
$dash = $circumf * (1 - $pctVal/100);
$mcols=['var(--cyan)','var(--blue)','var(--purple)','var(--amber)','var(--green)','var(--red)'];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-fade">
<div class="pg-header">
  <div class="pg-eyebrow">Student Portal</div>
  <div class="pg-title">Hi, <?= e(explode(' ',$user['name'])[0]) ?> 👋</div>
  <div class="pg-sub">Your attendance overview for this term.</div>
</div>
<div class="stats-row">
  <div class="stat-card" style="--accent:linear-gradient(90deg,var(--cyan),var(--blue))"><div class="stat-label">Overall</div><div class="stat-val" style="color:var(--cyan)"><?= $pctVal>0?$pctVal.'%':'—' ?></div><div class="stat-sub"><span class="delta <?= $pctVal>= ATTENDANCE_THRESHOLD?'delta-up':'delta-dn' ?>"><?= $pctVal>= ATTENDANCE_THRESHOLD?'On Track':'At Risk' ?></span></div></div>
  <div class="stat-card" style="--accent:linear-gradient(90deg,var(--green),var(--cyan))"><div class="stat-label">Attended</div><div class="stat-val"><?= (int)($ov['attended']??0) ?></div><div class="stat-sub" style="color:var(--t3)">of <?= (int)($ov['total']??0) ?></div></div>
  <div class="stat-card" style="--accent:linear-gradient(90deg,var(--amber),var(--red))"><div class="stat-label">Missed</div><div class="stat-val" style="color:var(--amber)"><?= (int)($ov['absent']??0) ?></div><div class="stat-sub" style="color:var(--t3)">sessions</div></div>
  <div class="stat-card" style="--accent:linear-gradient(90deg,var(--purple),var(--blue))"><div class="stat-label">Modules</div><div class="stat-val" style="color:var(--purple)"><?= count($mods) ?></div><div class="stat-sub" style="color:var(--t3)">enrolled</div></div>
</div>
<div class="grid2">
  <div class="card">
    <div class="card-head"><span class="card-title">📊 Attendance Gauge</span></div>
    <div class="card-body" style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
      <div class="gauge-ring">
        <svg width="160" height="160" viewBox="0 0 160 160">
          <circle cx="80" cy="80" r="64" fill="none" stroke="rgba(255,255,255,.06)" stroke-width="14"/>
          <circle cx="80" cy="80" r="64" fill="none" stroke="url(#gg)" stroke-width="14" stroke-linecap="round"
            stroke-dasharray="<?= $circumf ?>" stroke-dashoffset="<?= $dash ?>" transform="rotate(-90 80 80)"/>
          <defs><linearGradient id="gg" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="#00e5c3"/><stop offset="100%" stop-color="#3b9eff"/></linearGradient></defs>
        </svg>
        <div class="gauge-inner">
          <div class="gauge-pct" style="color:var(--cyan)"><?= $pctVal>0?$pctVal.'%':'—' ?></div>
          <div class="gauge-lbl">attendance</div>
        </div>
      </div>
      <div style="flex:1;min-width:130px">
        <div style="font-size:13px;color:var(--t2);margin-bottom:8px">Threshold: <strong style="color:var(--amber)"><?= ATTENDANCE_THRESHOLD ?>%</strong></div>
        <div style="font-size:13px;color:var(--t2);margin-bottom:8px">Status: <span class="badge <?= $pctVal>= ATTENDANCE_THRESHOLD?'b-green':'b-red' ?>"><?= $pctVal>= ATTENDANCE_THRESHOLD?'On Track':'At Risk' ?></span></div>
        <?php if ($pctVal>0 && $ov['total']>0):
            $canMiss = floor($ov['total'] * (1 - ATTENDANCE_THRESHOLD/100)) - (int)$ov['absent'];
        ?>
        <div style="font-size:13px;color:var(--t2)">You can miss <strong style="color:var(--t1)"><?= max(0,$canMiss) ?> more session<?= $canMiss!=1?'s':'' ?></strong> before falling below threshold.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title">📚 Module Breakdown</span></div>
    <div class="card-body">
      <?php foreach ($mods as $i => $m): $r=(float)($m['pct']??0); ?>
      <div class="att-bar-row">
        <div class="att-bar-top">
          <span class="att-bar-name" style="font-size:13px"><?= e($m['code']) ?> · <?= e($m['name']) ?></span>
          <span class="att-bar-pct" style="color:<?= pctColor($r);?>;font-size:13px"><?= $r>0?$r.'%':'—' ?></span>
        </div>
        <div class="prog-track"><div class="prog-fill" data-pct="<?= $r ?>" style="width:0;background:<?= $mcols[$i%count($mcols)] ?>"></div></div>
        <?php if ($r>0 && $r< ATTENDANCE_THRESHOLD): ?><div style="font-size:11px;color:var(--red);margin-top:3px">⚠ Below <?= ATTENDANCE_THRESHOLD ?>% threshold</div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
