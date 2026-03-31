<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';

$teacherId = $_SESSION['user_id'];
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reqId = $_POST['request_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    // Verify it's assigned to me
    $chk = $pdo->prepare("SELECT * FROM requests WHERE RequestID = ? AND TargetTeacherID = ? AND Status = 'pending_teacher'");
    $chk->execute([$reqId, $teacherId]);
    $req = $chk->fetch();
    
    if ($req) {
        if ($action === 'accept') {
            $pdo->prepare("UPDATE requests SET Status = 'pending_admin', TeacherBStatus = 'accepted' WHERE RequestID = ?")->execute([$reqId]);
            $message = "You accepted the swap request. Sent to admin for final approval.";
            
            // Notify Admin
            $pdo->prepare("INSERT INTO notifications (ScopeType, Message) VALUES ('global', ?)")->execute(["Teacher {$teacherId} accepted a swap request from Teacher {$req['RequestedBy']}. Pending your approval."]);
        } elseif ($action === 'decline') {
            $pdo->prepare("UPDATE requests SET Status = 'rejected', TeacherBStatus = 'declined' WHERE RequestID = ?")->execute([$reqId]);
            $message = "You declined the swap request.";
            
            // Notify Teacher A
            $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$req['RequestedBy'], "Your swap request was declined by the targeted teacher."]);
        }
    } else {
        $error = "Invalid request or already processed.";
    }
}

$stmt = $pdo->prepare("SELECT r.*, u.Name as ReqName, 
                       c1.Name as C1Name, c2.Name as C2Name,
                       t1.Day as T1Day, ts1.PeriodNumber as T1Period,
                       t2.Day as T2Day, ts2.PeriodNumber as T2Period
                       FROM requests r 
                       JOIN users u ON r.RequestedBy = u.UserID
                       JOIN timetable t1 ON r.TimetableID = t1.TimetableID 
                       JOIN time_slots ts1 ON t1.SlotID = ts1.SlotID
                       LEFT JOIN courses c1 ON t1.CourseID = c1.CourseID
                       JOIN timetable t2 ON r.SwapTimetableID = t2.TimetableID
                       JOIN time_slots ts2 ON t2.SlotID = ts2.SlotID
                       LEFT JOIN courses c2 ON t2.CourseID = c2.CourseID
                       WHERE r.TargetTeacherID = ? AND r.Status = 'pending_teacher' AND r.TeacherBStatus = 'pending'");
$stmt->execute([$teacherId]);
$requests = $stmt->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <!-- Sidebar -->
    <aside class="w-64 bg-white p-4 shadow-md rounded h-full">
        <h3 class="text-lg font-bold text-maroon mb-4">Teacher Menu</h3>
        <ul class="space-y-2">
            <li><a href="dashboard.php" class="block p-2 hover:bg-gray-100 rounded">Dashboard</a></li>
            <li><a href="request_swap.php" class="block p-2 hover:bg-gray-100 rounded">Swap Request</a></li>
            <li><a href="inbox_swap.php" class="block p-2 bg-gray-100 rounded text-maroon font-semibold">Swap Inbox</a></li>
        </ul>
    </aside>
    
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Swap Inbox</h2>
        
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?php echo $error; ?></div><?php endif; ?>
        
        <div class="bg-white p-6 shadow-md rounded">
            <?php if(empty($requests)): ?>
                <p class="text-gray-600">You have no pending swap requests.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach($requests as $r): ?>
                    <div class="border rounded p-4 flex justify-between items-center bg-gray-50">
                        <div>
                            <h4 class="font-bold text-lg"><?php echo htmlspecialchars($r['ReqName']); ?> wants to swap slots</h4>
                            <p class="text-sm mt-2"><b>They give you:</b> <?php echo $r['T1Day']; ?> Period <?php echo $r['T1Period']; ?> (<?php echo $r['C1Name']; ?>)</p>
                            <p class="text-sm"><b>You give them:</b> <?php echo $r['T2Day']; ?> Period <?php echo $r['T2Period']; ?> (<?php echo $r['C2Name']; ?>)</p>
                            
                            <?php 
                                $left = strtotime($r['Deadline']) - time();
                                if ($left > 0) {
                                    $hrs = floor($left / 3600);
                                    echo "<p class='text-xs text-yellow-600 mt-2 font-bold'>Expires in {$hrs} hours</p>";
                                } else {
                                    echo "<p class='text-xs text-red-600 mt-2 font-bold'>Expired</p>";
                                }
                            ?>
                        </div>
                        <div class="flex flex-col space-y-2">
                            <form method="POST">
                                <input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>">
                                <input type="hidden" name="action" value="accept">
                                <button type="submit" class="btn-maroon px-4 py-2 rounded w-full font-bold">Accept Swap</button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>">
                                <input type="hidden" name="action" value="decline">
                                <button type="submit" class="bg-gray-300 text-gray-800 px-4 py-2 rounded w-full font-bold">Decline</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
