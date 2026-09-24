<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin','lecturer');
$pageTitle  = 'Modules';
$activePage = 'modules';
$db   = getDB();
$user = currentUser();
$flashMsg=''; $flashType='success';

// Add module (admin only)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_module']) && $user['role']==='admin') {
    if (!csrf_verify()) { $flashMsg='Security error.'; $flashType='error'; }
    else {
        $code    = strtoupper(sanitize($_POST['code']     ?? ''));
        $name    = sanitize($_POST['name']     ?? '');
        $lecId   = (int)($_POST['lecturer_id'] ?? 0);
        $yr      = (int)($_POST['year_level']  ?? 1);
        $sched   = sanitize($_POST['schedule'] ?? '');
        if (!$code || !$name) { $flashMsg='Code and name required.'; $flashType='error'; }
        else {
            try {
                $db->prepare("INSERT INTO modules (code,name,lecturer_id,year_level,schedule) VALUES (?,?,?,?,?)")
                   ->execute([$code,$name,$lecId?:null,$yr,$sched]);
                $flashMsg = "Module $code added.";
            } catch (\PDOException $ex) {
                $flashMsg = 'Code already exists.'; $flashType='error';
            }
        }
    }
}

$modules = $db->query("
    SELECT m.*, u.full_name AS lecturer_name,
           COUNT(DISTINCT e.student_id) AS student_count,
           COUNT(DISTINCT s.id) AS session_count,
           ROUND(SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END)/NULLIF(COUNT(a.id),0)*100,1) AS att_rate
    FROM modules m
    LEFT JOIN users u ON u.id=m.lecturer_id
    LEFT JOIN enrollments e ON e.module_id=m.id
    LEFT JOIN sessions s ON s.module_id=m.id
    LEFT JOIN attendance a ON a.session_id=s.id
    WHERE m.is_active=1
    GROUP BY m.id ORDER BY m.code
")->fetchAll();

$lecturers = $db->query("SELECT id,full_name FROM users WHERE role='lecturer' AND is_active=1 ORDER BY full_name")->fetchAll();
$mcols = ['var(--cyan)','var(--blue)','var(--purple)','var(--amber)','var(--green)','var(--red)'];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-fade">
<div class="pg-header-row">
  <div>
    <div class="pg-eyebrow">Course Registry</div>
    <div class="pg-title">Modules</div>
    <div class="pg-sub">All active course modules and their attendance rates.</div>
  </div>
  <?php if ($user['role']==='admin'): ?><button class="btn btn-prim" onclick="openModal('mod-modal')">+ Add Module</button><?php endif; ?>
</div>
<div class="mod-grid">
  <?php foreach ($modules as $i => $m): $rate=(float)($m['att_rate']??0); ?>
  <div class="mod-card" style="--mc:<?= $mcols[$i%count($mcols)] ?>">
    <div class="mod-card-head">
      <div><div class="mod-name"><?= e($m['name']) ?></div><div class="mod-code"><?= e($m['code']) ?> · Year <?= $m['year_level'] ?></div></div>
      <span class="badge <?= $rate>0?($rate>=75?'b-green':'b-amber'):'b-blue' ?>"><?= $rate>0?$rate.'%':'No data' ?></span>
    </div>
    <div class="prog-wrap"><div class="prog-track"><div class="prog-fill" data-pct="<?= $rate ?>" style="width:0;background:<?= $mcols[$i%count($mcols)] ?>"></div></div><span class="prog-pct" style="color:<?= $mcols[$i%count($mcols)] ?>"><?= $rate>0?$rate.'%':'—' ?></span></div>
    <div class="spark">
      <?php for($j=0;$j<5;$j++): $h=rand(40,95); ?>
      <div class="spark-bar" style="height:<?= $h ?>%;background:<?= $mcols[$i%count($mcols)] ?>;opacity:<?= 0.5+$j*0.1 ?>"></div>
      <?php endfor; ?>
    </div>
    <div class="mod-footer"><span>Lecturer: <?= e($m['lecturer_name']??'Unassigned') ?></span><span><?= $m['student_count'] ?> students · <?= $m['session_count'] ?> sessions</span></div>
    <?php if ($m['schedule']): ?><div style="font-size:11px;color:var(--t3);margin-top:6px">🕐 <?= e($m['schedule']) ?></div><?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
</div>

<?php if ($user['role']==='admin'): ?>
<div id="mod-modal" class="modal-overlay">
  <div class="modal">
    <div class="modal-head"><div><div class="modal-title">Add Module</div><div class="modal-sub">Create a new course module</div></div><button class="modal-close" onclick="closeModal('mod-modal')">✕</button></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="add_module" value="1"/>
      <div class="form-group"><label>Module Code</label><input class="form-control" name="code" placeholder="e.g. APT-401" required/></div>
      <div class="form-group"><label>Module Name</label><input class="form-control" name="name" placeholder="e.g. Advanced Web Development" required/></div>
      <div class="form-group"><label>Lecturer</label><select class="form-control" name="lecturer_id"><option value="">— Unassigned —</option><?php foreach ($lecturers as $l): ?><option value="<?= $l['id'] ?>"><?= e($l['full_name']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Year Level</label><select class="form-control" name="year_level"><option value="1">Year 1</option><option value="2">Year 2</option><option value="3">Year 3</option></select></div>
      <div class="form-group"><label>Schedule</label><input class="form-control" name="schedule" placeholder="e.g. Mon & Wed 9–11 AM"/></div>
      <div class="flex-c gap-8 mt-2"><button type="submit" class="btn btn-prim">Add Module</button><button type="button" class="btn btn-ghost" onclick="closeModal('mod-modal')">Cancel</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
