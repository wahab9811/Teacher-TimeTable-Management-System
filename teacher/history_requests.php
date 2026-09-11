<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';
$teacherId = $_SESSION['user_id'];
require_once __DIR__ . '/../api/automations.php';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw'])) {
    $reqId = intval($_POST['request_id']);
    
    // Check ownership and pending status
    $stmt = $pdo->prepare("SELECT * FROM requests WHERE RequestID = ? AND RequestedBy = ? AND Status IN ('pending_teacher', 'pending_admin')");
    $stmt->execute([$reqId, $teacherId]);
    $req = $stmt->fetch();
    
    if($req) {
        $pdo->prepare("UPDATE requests SET Status = 'cancelled' WHERE RequestID = ?")->execute([$reqId]);
        
        if($req['TargetTeacherID']) {
            $msgB = "Teacher has withdrawn the swap request.";
            $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$req['TargetTeacherID'], $msgB]);
        }
        $withdrawMsg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4 font-semibold text-sm'>Request successfully withdrawn.</div>";
    }
}

// Swaps and Free Changes history
$reqs = $pdo->prepare("
    SELECT r.*, u.Name as TargetName, 
           c.Name as CourseName, sec.Name as SectionName,
           t.Day, ts.PeriodNumber
    FROM requests r 
    LEFT JOIN users u ON r.TargetTeacherID=u.UserID 
    LEFT JOIN timetable t ON r.TimetableID = t.TimetableID
    LEFT JOIN time_slots ts ON t.SlotID = ts.SlotID
    LEFT JOIN courses c ON t.CourseID = c.CourseID
    LEFT JOIN sections sec ON t.SectionID = sec.SectionID
    WHERE r.RequestedBy = ? 
    ORDER BY r.CreatedAt DESC
");
$reqs->execute([$teacherId]);
$requests = $reqs->fetchAll();

// Leaves history
$leavesQ = $pdo->prepare("SELECT * FROM leave_requests WHERE TeacherID = ? ORDER BY CreatedAt DESC");
$leavesQ->execute([$teacherId]);
$leaves = $leavesQ->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/teacher_sidebar.php'; ?>
    
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-6 border-b pb-2">Request History</h2>
        
        <?php if(!empty($withdrawMsg)) echo $withdrawMsg; ?>
        
        <!-- Leave Requests Table -->
        <div class="bg-white p-6 shadow-md rounded border border-gray-100 mb-8">
            <h3 class="font-bold text-lg mb-4 text-gray-800"><i class="fas fa-calendar-times mr-2 text-blue-500"></i> Leave Requests</h3>
            
            <?php if(empty($leaves)): ?>
                <div class="bg-gray-50 text-gray-500 text-center py-6 rounded border border-dashed">No leave history found.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead class="bg-gray-100 border-b">
                            <tr>
                                <th class="p-3 font-semibold text-gray-700">Leave Type</th>
                                <th class="p-3 font-semibold text-gray-700">Dates</th>
                                <th class="p-3 font-semibold text-gray-700">Status</th>
                                <th class="p-3 font-semibold text-gray-700">Admin Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($leaves as $l): ?>
                            <tr class="border-b hover:bg-gray-50 transition-colors">
                                <td class="p-3 font-bold text-maroon"><?php echo htmlspecialchars($l['LeaveType'] ?? 'Casual Leave'); ?></td>
                                <td class="p-3 font-medium"><?php echo date('d M', strtotime($l['FromDate'])) .' to '. date('d M Y', strtotime($l['ToDate'])); ?></td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-bold 
                                        <?php echo $l['Status']=='approved' ? 'bg-green-100 text-green-700' : ($l['Status']=='rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                                        <?php echo ucfirst($l['Status']); ?>
                                    </span>
                                </td>
                                <td class="p-3 text-gray-500 italic text-xs max-w-xs truncate" title="<?php echo htmlspecialchars($l['AdminNote']); ?>">
                                    <?php echo htmlspecialchars($l['AdminNote'] ?: '--'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Timetable Adjustments Table -->
        <div class="bg-white p-6 shadow-md rounded border border-gray-100">
            <h3 class="font-bold text-lg mb-4 text-gray-800"><i class="fas fa-exchange-alt mr-2 text-green-500"></i> Timetable Requests (Proxy, Swaps & Free Changes)</h3>
            
            <?php if(empty($requests)): ?>
                <div class="bg-gray-50 text-gray-500 text-center py-6 rounded border border-dashed">No timetable requests found.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead class="bg-gray-100 border-b">
                            <tr>
                                <th class="p-3 font-semibold text-gray-700">Type</th>
                                <th class="p-3 font-semibold text-gray-700">For Period</th>
                                <th class="p-3 font-semibold text-gray-700">Target / Details</th>
                                <th class="p-3 font-semibold text-gray-700">Status</th>
                                <th class="p-3 font-semibold text-gray-700">Admin Note</th>
                                <th class="p-3 font-semibold text-gray-700 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($requests as $r): ?>
                            <tr class="border-b hover:bg-gray-50 transition-colors">
                                <td class="p-3 uppercase font-bold text-xs text-gray-600">
                                    <?php echo str_replace('_',' ',$r['Type']); ?>
                                </td>
                                <td class="p-3 text-xs">
                                    <span class="font-bold"><?php echo $r['Day'] . ' P' . $r['PeriodNumber']; ?></span><br>
                                    <span class="text-gray-500"><?php echo htmlspecialchars($r['CourseName'] ?? ''); ?></span>
                                    <?php if(!empty($r['SectionName'])): ?>
                                        <span class="bg-gray-100 px-1 rounded text-gray-500 ml-1">Sec: <?php echo htmlspecialchars($r['SectionName']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-xs">
                                    <?php if($r['Type'] == 'swap'): ?>
                                        <span class="text-blue-600 font-bold"><?php echo htmlspecialchars($r['TargetName']); ?></span> 
                                        <span class="bg-gray-100 px-1 rounded text-gray-500 ml-1"><?php echo ucfirst($r['TeacherBStatus']); ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic">Self Change</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-bold 
                                        <?php echo $r['Status']=='approved' ? 'bg-green-100 text-green-700' : ($r['Status']=='rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                                        <?php echo str_replace('_',' ',ucfirst($r['Status'])); ?>
                                    </span>
                                </td>
                                <td class="p-3 text-gray-500 italic text-xs max-w-xs truncate" title="<?php echo htmlspecialchars($r['AdminNote']); ?>">
                                    <?php echo htmlspecialchars($r['AdminNote'] ?: '--'); ?>
                                </td>
                                <td class="p-3 text-right">
                                    <?php if(in_array($r['Status'], ['pending_admin', 'pending_teacher'])): ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to withdraw this request?');" class="inline">
                                        <input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>">
                                        <button name="withdraw" class="bg-red-100 text-red-600 hover:bg-red-200 px-3 py-1 rounded text-xs font-bold transition">Withdraw</button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-gray-300 text-xs">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>
<?php include '../includes/footer.php'; ?>
