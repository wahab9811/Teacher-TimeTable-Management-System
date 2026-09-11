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
    
    $chk = $pdo->prepare("SELECT * FROM requests WHERE RequestID = ? AND TargetTeacherID = ? AND Status = 'pending_teacher'");
    $chk->execute([$reqId, $teacherId]);
    $req = $chk->fetch();
    
    if ($req) {
        $left = strtotime($req['Deadline']) - time();
        $isSwap = ($req['Type'] === 'swap');
        $msgType = $isSwap ? "swap" : "proxy";
        
        if ($left <= 0 && $action === 'accept') {
            $error = "This request has expired and can no longer be accepted.";
        } elseif ($action === 'accept') {
            $pdo->prepare("UPDATE requests SET Status = 'pending_admin', TeacherBStatus = 'accepted' WHERE RequestID = ?")->execute([$reqId]);
            $message = "You accepted the $msgType request. It has been forwarded to the Admin for final approval.";
            
            $pdo->prepare("INSERT INTO notifications (ScopeType, Message) VALUES ('admin', ?)")->execute(["Teacher {$teacherId} accepted a $msgType request from Teacher {$req['RequestedBy']}. Pending your approval."]);
        } elseif ($action === 'decline') {
            $pdo->prepare("UPDATE requests SET Status = 'rejected', TeacherBStatus = 'declined' WHERE RequestID = ?")->execute([$reqId]);
            $message = "You have declined the request.";
            
            $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$req['RequestedBy'], "Your $msgType request was declined by the targeted colleague."]);
        }
    } else {
        $error = "Invalid request or already processed.";
    }
}

$stmt = $pdo->prepare("SELECT r.*, u.Name as ReqName, 
                       c1.Name as C1Name, t1.Day as T1Day, ts1.PeriodNumber as T1Period, sec1.Name as Sec1Name,
                       c2.Name as C2Name, t2.Day as T2Day, ts2.PeriodNumber as T2Period, sec2.Name as Sec2Name
                       FROM requests r 
                       JOIN users u ON r.RequestedBy = u.UserID
                       JOIN timetable t1 ON r.TimetableID = t1.TimetableID 
                       JOIN time_slots ts1 ON t1.SlotID = ts1.SlotID
                       LEFT JOIN courses c1 ON t1.CourseID = c1.CourseID
                       LEFT JOIN sections sec1 ON t1.SectionID = sec1.SectionID
                       LEFT JOIN timetable t2 ON r.SwapTimetableID = t2.TimetableID
                       LEFT JOIN time_slots ts2 ON t2.SlotID = ts2.SlotID
                       LEFT JOIN courses c2 ON t2.CourseID = c2.CourseID
                       LEFT JOIN sections sec2 ON t2.SectionID = sec2.SectionID
                       WHERE r.TargetTeacherID = ? AND r.Status = 'pending_teacher' AND r.TeacherBStatus = 'pending'
                       ORDER BY r.CreatedAt DESC");
$stmt->execute([$teacherId]);
$requests = $stmt->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <!-- Sidebar -->
    <?php include '../includes/teacher_sidebar.php'; ?>
    
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-6 border-b pb-2">My Inbox <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full align-middle ml-2"><?php echo count($requests); ?></span></h2>
        
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-4 rounded mb-6 font-medium shadow-sm border border-green-200"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 text-red-700 p-4 rounded mb-6 font-medium shadow-sm border border-red-200"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        
        <div class="bg-white p-6 shadow-md rounded border border-gray-100">
            <?php if(empty($requests)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-check-circle text-5xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-500">All Caught Up!</h3>
                    <p class="text-gray-400 mt-2 font-medium">You have no pending requests to review.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach($requests as $r): 
                        $left = strtotime($r['Deadline']) - time();
                        $isExpired = $left <= 0;
                        $isSwap = ($r['Type'] === 'swap');
                        $borderColor = $isSwap ? 'border-l-blue-500' : 'border-l-purple-500';
                        $iconColor = $isSwap ? 'text-blue-500' : 'text-purple-500';
                        $iconClass = $isSwap ? 'fa-exchange-alt' : 'fa-handshake';
                        $headerText = $isSwap ? 'wants to swap slots with you' : 'needs you to substitute a class';
                    ?>
                    <div class="border rounded-lg p-5 flex flex-col md:flex-row justify-between items-center bg-gray-50 shadow-sm hover:shadow-md transition-shadow <?php echo $isExpired ? 'opacity-75 relative overflow-hidden' : "border-l-4 $borderColor"; ?>">
                        
                        <?php if($isExpired): ?>
                            <div class="absolute inset-0 bg-gray-100/50 z-0"></div>
                        <?php endif; ?>

                        <div class="flex-1 z-10 w-full mb-4 md:mb-0">
                            <h4 class="font-bold text-lg text-gray-800 mb-3">
                                <i class="fas <?php echo $iconClass; ?> mr-2 <?php echo $iconColor; ?>"></i>
                                <?php echo htmlspecialchars($r['ReqName']) . " " . $headerText; ?>
                            </h4>
                            
                            <div class="grid grid-cols-1 select-none text-sm bg-white border rounded p-3 mb-2">
                                <?php if($isSwap): ?>
                                    <div class="flex items-center text-green-700 font-medium mb-2 border-b pb-2">
                                        <span class="w-24 text-gray-500 font-normal">They give you:</span>
                                        <span><?php echo $r['T1Day']; ?> (P<?php echo $r['T1Period']; ?>) - <?php echo htmlspecialchars($r['C1Name']); ?> <span class="bg-gray-100 px-1.5 rounded text-gray-600 text-xs ml-1">Sec: <?php echo htmlspecialchars($r['Sec1Name'] ?? 'N/A'); ?></span></span>
                                    </div>
                                    <div class="flex items-center text-maroon font-medium">
                                        <span class="w-24 text-gray-500 font-normal">You give them:</span>
                                        <span><?php echo $r['T2Day']; ?> (P<?php echo $r['T2Period']; ?>) - <?php echo htmlspecialchars($r['C2Name']); ?> <span class="bg-gray-100 px-1.5 rounded text-gray-600 text-xs ml-1">Sec: <?php echo htmlspecialchars($r['Sec2Name'] ?? 'N/A'); ?></span></span>
                                    </div>
                                <?php else: ?>
                                    <div class="flex items-center text-maroon font-medium mb-1">
                                        <span class="w-24 text-gray-500 font-normal">Class details:</span>
                                        <span><?php echo date('d M Y', strtotime($r['ProxyDate'])); ?> | <?php echo $r['T1Day']; ?> (Period <?php echo $r['T1Period']; ?>) - <?php echo htmlspecialchars($r['C1Name']); ?> <span class="bg-gray-100 px-1.5 rounded text-gray-600 text-xs ml-1">Sec: <?php echo htmlspecialchars($r['Sec1Name'] ?? 'N/A'); ?></span></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php 
                                if (!$isExpired) {
                                    $hrs = floor($left / 3600);
                                    echo "<p class='text-xs text-yellow-600 font-bold'><i class='fas fa-clock mr-1'></i> Expires in {$hrs} hours</p>";
                                } else {
                                    echo "<p class='text-xs text-red-600 font-bold'><i class='fas fa-times-circle mr-1'></i> Request Expired</p>";
                                }
                            ?>
                        </div>
                        
                        <div class="flex flex-row md:flex-col gap-2 w-full md:w-32 md:ml-4 z-10">
                            <?php if(!$isExpired): ?>
                            <form method="POST" class="w-full">
                                <input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>">
                                <input type="hidden" name="action" value="accept">
                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded w-full font-bold transition-colors shadow-sm text-sm">Accept</button>
                            </form>
                            <?php endif; ?>

                            <form method="POST" class="w-full">
                                <input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>">
                                <input type="hidden" name="action" value="decline">
                                <button type="submit" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded w-full font-bold transition-colors shadow-sm text-sm" <?php if($isExpired) echo 'onclick="return confirm(\'Delete expired request?\')"'; ?>>
                                    <?php echo $isExpired ? 'Dismiss' : 'Decline'; ?>
                                </button>
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
