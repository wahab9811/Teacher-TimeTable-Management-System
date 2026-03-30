<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';
$teacherId = $_SESSION['user_id'];

// Swaps and Free Changes history
$reqs = $pdo->prepare("SELECT r.*, u.Name as TargetName FROM requests r LEFT JOIN users u ON r.TargetTeacherID=u.UserID WHERE r.RequestedBy = ? ORDER BY r.CreatedAt DESC");
$reqs->execute([$teacherId]);
$requests = $reqs->fetchAll();

// Leaves history
$leavesQ = $pdo->prepare("SELECT * FROM leave_requests WHERE TeacherID = ? ORDER BY CreatedAt DESC");
$leavesQ->execute([$teacherId]);
$leaves = $leavesQ->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <aside class="w-64 bg-white p-4 shadow-md rounded h-full">
        <ul class="space-y-2">
            <li><a href="dashboard.php" class="block p-2 hover:bg-gray-100 rounded">Dashboard</a></li>
            <li><a href="request_swap.php" class="block p-2 hover:bg-gray-100 rounded">Swap Request</a></li>
            <li><a href="history_requests.php" class="block p-2 bg-gray-100 rounded font-bold text-maroon">Request History</a></li>
            <li><a href="history_substitute.php" class="block p-2 hover:bg-gray-100 rounded">Substitute History</a></li>
        </ul>
    </aside>
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Request History</h2>
        
        <div class="bg-white p-6 shadow-md rounded mb-6">
            <h3 class="font-bold mb-4 border-b pb-2">Timetable Requests (Swaps & Free Changes)</h3>
            <table class="w-full text-left text-sm"><thead class="bg-gray-100"><tr><th class="p-2">Date</th><th class="p-2">Type</th><th class="p-2">Target</th><th class="p-2">Status</th><th class="p-2">Admin Note</th></tr></thead>
            <tbody>
                <?php foreach($requests as $r): ?>
                <tr class="border-b"><td class="p-2"><?php echo $r['CreatedAt']; ?></td><td class="p-2 uppercase font-bold"><?php echo str_replace('_',' ',$r['Type']); ?></td>
                <td class="p-2"><?php echo $r['TargetName'] ? htmlspecialchars($r['TargetName']) . ' ('.$r['TeacherBStatus'].')' : '-'; ?></td>
                <td class="p-2 font-bold <?php echo $r['Status']=='approved'?'text-green-600':($r['Status']=='rejected'?'text-red-600':'text-yellow-600'); ?>"><?php echo str_replace('_',' ',$r['Status']); ?></td>
                <td class="p-2 text-gray-500 italic"><?php echo $r['AdminNote']; ?></td></tr>
                <?php endforeach; ?>
            </tbody></table>
        </div>
        
        <div class="bg-white p-6 shadow-md rounded">
            <h3 class="font-bold mb-4 border-b pb-2">Leave Requests</h3>
            <table class="w-full text-left text-sm"><thead class="bg-gray-100"><tr><th class="p-2">Dates</th><th class="p-2">Shift</th><th class="p-2">Reason</th><th class="p-2">Status</th><th class="p-2">Admin Note</th></tr></thead>
            <tbody>
                <?php foreach($leaves as $l): ?>
                <tr class="border-b"><td class="p-2"><?php echo $l['FromDate'].' to '.$l['ToDate']; ?></td><td class="p-2"><?php echo $l['Shift']; ?></td><td class="p-2 max-w-xs truncate" title="<?php echo htmlspecialchars($l['Reason']); ?>"><?php echo htmlspecialchars($l['Reason']); ?></td>
                <td class="p-2 font-bold <?php echo $l['Status']=='approved'?'text-green-600':($l['Status']=='rejected'?'text-red-600':'text-yellow-600'); ?>"><?php echo $l['Status']; ?></td>
                <td class="p-2 text-gray-500 italic"><?php echo $l['AdminNote']; ?></td></tr>
                <?php endforeach; ?>
            </tbody></table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
