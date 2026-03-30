<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';
$teacherId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT s.*, u1.Name as Orig, u2.Name as Sub, t.Day, ts.PeriodNumber, c.Name as CN FROM substitute_assignments s JOIN users u1 ON s.OriginalTeacherID=u1.UserID JOIN users u2 ON s.SubstituteTeacherID=u2.UserID JOIN timetable t ON s.TimetableID=t.TimetableID JOIN time_slots ts ON t.SlotID=ts.SlotID LEFT JOIN courses c ON t.CourseID=c.CourseID WHERE s.OriginalTeacherID=? OR s.SubstituteTeacherID=? ORDER BY s.CreatedAt DESC");
$stmt->execute([$teacherId, $teacherId]);
$subs = $stmt->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <aside class="w-64 bg-white p-4 shadow-md rounded h-full">
        <ul class="space-y-2">
            <li><a href="dashboard.php" class="block p-2 hover:bg-gray-100 rounded">Dashboard</a></li>
            <li><a href="history_requests.php" class="block p-2 hover:bg-gray-100 rounded">Request History</a></li>
            <li><a href="history_substitute.php" class="block p-2 bg-gray-100 rounded font-bold text-maroon">Substitute History</a></li>
        </ul>
    </aside>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <h2 class="text-2xl font-bold text-maroon mb-4">Substitute History</h2>
        <table class="w-full text-left text-sm"><thead class="bg-gray-100"><tr><th class="p-2">Dates</th><th class="p-2">Role</th><th class="p-2">Details</th><th class="p-2">Course / Slot</th><th class="p-2">Status</th></tr></thead>
        <tbody>
            <?php foreach($subs as $s): ?>
            <?php $isMySub = ($s['OriginalTeacherID'] == $teacherId); ?>
            <tr class="border-b"><td class="p-2 font-bold"><?php echo $s['FromDate'].' to '.$s['ToDate']; ?></td>
            <td class="p-2"><span class="<?php echo $isMySub?'text-blue-500':'text-green-500'; ?> font-bold"><?php echo $isMySub ? 'I am Subbed' : 'I am Substitute'; ?></span></td>
            <td class="p-2"><?php echo $isMySub ? "Sub: <b>{$s['Sub']}</b>" : "For: <b>{$s['Orig']}</b>"; ?></td>
            <td class="p-2"><?php echo $s['Day'].' P'.$s['PeriodNumber'].' ('.$s['CN'].')'; ?></td>
            <td class="p-2"><span class="px-2 py-1 rounded text-white <?php echo $s['Status']=='active'?'bg-green-500':'bg-gray-500'; ?>"><?php echo $s['Status']; ?></span></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
