<?php /* student_history.php */
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');
$pageTitle  = 'Attendance History';
$activePage = 'history';
$db   = getDB();
$uid  = currentUser()['id'];

$modFilter = (int)($_GET['mod'] ?? 0);

$sql = "
    SELECT a.status, a.marked_at, s.session_date, s.session_time,
           m.code AS mod_code, m.name AS mod_name,
           u.full_name AS lecturer
    FROM attendance a
    JOIN sessions s ON s.id=a.session_id
    JOIN modules m  ON m.id=s.module_id
    LEFT JOIN users u ON u.id=s.marked_by
    WHERE a.student_id=?
";
$params = [$uid];
if ($modFilter) { $sql.=" AND m.id=?"; $params[]=$modFilter; }
$sql .= " ORDER BY s.session_date DESC, s.session_time LIMIT 100";

$stmt=$db->prepare($sql); $stmt->execute($params);
$history=$stmt->fetchAll();

$myMods=$db->prepare("SELECT m.id,m.code,m.name FROM enrollments e JOIN modules m ON m.id=e.module_id WHERE e.student_id=? ORDER BY m.code");
$myMods->execute([$uid]); $myModules=$myMods->fetchAll();

$statusBadge=['present'=>'<span class="badge b-green">● Present</span>','absent'=>'<span class="badge b-red">● Absent</span>','late'=>'<span class="badge b-amber">● Late</span>'];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-fade">
<div class="pg-header">
  <div class="pg-eyebrow">Session Log</div>
  <div class="pg-title">Attendance History</div>
  <div class="pg-sub">Your detailed record of all sessions.</div>
</div>
<div class="filter-row" style="margin-bottom:1rem">
  <a href="?mod=0" class="chip <?= !$modFilter?'active':'' ?>">All</a>
  <?php foreach ($myModules as $m): ?>
  <a href="?mod=<?= $m['id'] ?>" class="chip <?= $modFilter===$m['id']?'active':'' ?>"><?= e($m['code']) ?></a>
  <?php endforeach; ?>
</div>
<div class="card"><div class="tbl-wrap"><table>
  <thead><tr><th>Date</th><th>Module</th><th>Session</th><th>Status</th><th>Marked By</th><th>Time</th></tr></thead>
  <tbody>
    <?php if(empty($history)): ?>
    <tr><td colspan="6" style="text-align:center;color:var(--t3);padding:2rem">No records found.</td></tr>
    <?php else: ?>
    <?php foreach ($history as $h): ?>
    <tr>
      <td><?= date('d M Y',strtotime($h['session_date'])) ?></td>
      <td><span class="badge b-blue"><?= e($h['mod_code']) ?></span></td>
      <td style="color:var(--t2)"><?= ucfirst($h['session_time']) ?></td>
      <td><?= $statusBadge[$h['status']] ?? e($h['status']) ?></td>
      <td style="color:var(--t2)"><?= e($h['lecturer']??'—') ?></td>
      <td style="color:var(--t3);font-size:12px"><?= $h['status']==='absent'?'—':date('H:i',strtotime($h['marked_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table></div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
