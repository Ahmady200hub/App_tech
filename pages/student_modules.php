<?php /* student_modules.php */
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');
$pageTitle  = 'My Modules';
$activePage = 's-modules';
$db   = getDB();
$uid  = currentUser()['id'];

$mods = $db->prepare("
    SELECT m.*, u.full_name AS lecturer,
           COUNT(s.id) AS total,
           SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END) AS attended,
           ROUND(SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END)/NULLIF(COUNT(s.id),0)*100,1) AS pct
    FROM enrollments e
    JOIN modules m ON m.id=e.module_id
    LEFT JOIN users u ON u.id=m.lecturer_id
    LEFT JOIN sessions s ON s.module_id=m.id
    LEFT JOIN attendance a ON a.session_id=s.id AND a.student_id=e.student_id
    WHERE e.student_id=? GROUP BY m.id ORDER BY m.code
");
$mods->execute([$uid]); $modules=$mods->fetchAll();
$mcols=['var(--cyan)','var(--blue)','var(--purple)','var(--amber)','var(--green)','var(--red)'];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-fade">
<div class="pg-header"><div class="pg-eyebrow">Enrollment</div><div class="pg-title">My Modules</div><div class="pg-sub">All courses you're enrolled in this term.</div></div>
<div class="mod-grid">
  <?php foreach ($modules as $i => $m): $r=(float)($m['pct']??0); ?>
  <div class="mod-card" style="--mc:<?= $mcols[$i%count($mcols)] ?>">
    <div class="mod-card-head"><div><div class="mod-name"><?= e($m['name']) ?></div><div class="mod-code"><?= e($m['code']) ?><?= $m['schedule']?' · '.e($m['schedule']):'' ?></div></div><span class="badge <?= $r>= ATTENDANCE_THRESHOLD?'b-green':'b-amber' ?>"><?= $r>0?$r.'%':'No data' ?></span></div>
    <div class="prog-wrap"><div class="prog-track"><div class="prog-fill" data-pct="<?= $r ?>" style="width:0;background:<?= $mcols[$i%count($mcols)] ?>"></div></div><span class="prog-pct" style="color:<?= $mcols[$i%count($mcols)] ?>"><?= $r>0?$r.'%':'—' ?></span></div>
    <?php if ($r>0 && $r< ATTENDANCE_THRESHOLD): ?><div style="font-size:11px;color:var(--red);margin-top:8px;font-weight:700">⚠ Below <?= ATTENDANCE_THRESHOLD ?>% — attend upcoming sessions</div><?php endif; ?>
    <div class="mod-footer"><span>Lecturer: <?= e($m['lecturer']??'—') ?></span><span><?= (int)$m['attended'] ?>/<?= (int)$m['total'] ?> attended</span></div>
  </div>
  <?php endforeach; ?>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
