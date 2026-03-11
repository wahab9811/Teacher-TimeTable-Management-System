<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/timetable_rules.php';

$message = $error = '';

$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reqId = $_POST['request_id'] ?? 0;
    $status = $_POST['status'] ?? ''; // approved or rejected
    $note = $_POST['admin_note'] ?? '';
    
    if ($action === 'manage_leave') {
        $pdo->prepare("UPDATE leave_requests SET Status=?, AdminNote=? WHERE LeaveID=?")
            ->execute([$status, $note, $reqId]);
        
        $lq = $pdo->prepare("SELECT TeacherID FROM leave_requests WHERE LeaveID = ?");
        $lq->execute([$reqId]);
        $tId = $lq->fetchColumn();
        
        // Notify Teacher
        $msg = "Your leave request was $status." . ($note ? " Note: $note" : "");
        $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$tId, $msg]);
        $message = "Leave Request $status.";
    } 
    elseif ($action === 'manage_request') {
        // Fetch Request
        $qReq = $pdo->prepare("SELECT * FROM requests WHERE RequestID = ?");
        $qReq->execute([$reqId]);
        $req = $qReq->fetch();
        
        if ($req && $status === 'rejected') {
            $pdo->prepare("UPDATE requests SET Status=?, AdminNote=? WHERE RequestID=?")
                ->execute([$status, $note, $reqId]);
            $msg = "Your request was rejected. Note: $note";
            $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$req['RequestedBy'], $msg]);
            if ($req['TargetTeacherID']) {
                $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$req['TargetTeacherID'], $msg]);
            }
            $message = "Request rejected.";
        } 
        elseif ($req && $status === 'approved') {
            if ($req['Type'] === 'free_change') {
                // We need to move course to target slot
                // SwapTimetableID holds the target Free slot, TimetableID holds current teaching slot
                
                // Fetch target slot
                $targQ = $pdo->prepare("SELECT ProgramID, DepartmentID, SemesterID, ShiftID, Day, SlotID FROM timetable WHERE TimetableID = ?");
                $targQ->execute([$req['SwapTimetableID']]);
                $tSlot = $targQ->fetch();
                
                // Fetch current slot
                $curQ = $pdo->prepare("SELECT CourseID, TeacherID, RoomID FROM timetable WHERE TimetableID = ?");
                $curQ->execute([$req['TimetableID']]);
                $cSlot = $curQ->fetch();
                
                // Update Target slot to teach
                $pdo->prepare("UPDATE timetable SET CourseID=?, TeacherID=?, RoomID=?, IsFree=0 WHERE TimetableID=?")
                    ->execute([$cSlot['CourseID'], $cSlot['TeacherID'], $cSlot['RoomID'], $req['SwapTimetableID']]);
                    
                // Mark Old slot as Free
                $pdo->prepare("UPDATE timetable SET CourseID=NULL, TeacherID=NULL, RoomID=NULL, IsFree=1 WHERE TimetableID=?")
                    ->execute([$req['TimetableID']]);
                    
                // Update Request status
                $pdo->prepare("UPDATE requests SET Status='approved' WHERE RequestID=?")->execute([$reqId]);
                
                // Notify
                $msg = "Free Period Change Request Approved!";
                $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$req['RequestedBy'], $msg]);
                
                $message = "Free period change approved. Timetable updated.";
            } 
            elseif ($req['Type'] === 'swap') {
                // Fetch A slot
                $aQ = $pdo->prepare("SELECT CourseID, RoomID FROM timetable WHERE TimetableID = ?");
                $aQ->execute([$req['TimetableID']]);
                $aSlot = $aQ->fetch();
                
                // Fetch B slot
                $bQ = $pdo->prepare("SELECT CourseID, RoomID FROM timetable WHERE TimetableID = ?");
                $bQ->execute([$req['SwapTimetableID']]);
                $bSlot = $bQ->fetch();
                
                // Swap Teacher IDs on those slots
                // Note: The logic decided was "Teacher A teaches Course B in Room B". 
                // Wait, if Teacher ID is updated on B's slot from Target to RequestedBy, Teacher A is now teaching B's course.
                
                $pdo->prepare("UPDATE timetable SET TeacherID=? WHERE TimetableID=?")->execute([$req['TargetTeacherID'], $req['TimetableID']]);
                $pdo->prepare("UPDATE timetable SET TeacherID=? WHERE TimetableID=?")->execute([$req['RequestedBy'], $req['SwapTimetableID']]);
                
                $pdo->prepare("UPDATE requests SET Status='approved' WHERE RequestID=?")->execute([$reqId]);
                
                // Notify
                $msg = "Swap Request Officially Approved!";
                $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$req['RequestedBy'], $msg]);
                $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$req['TargetTeacherID'], $msg]);
                
                $message = "Swap approved correctly. Timetable updated.";
            }
        }
    }
}

// Fetch pending Free Changes
$freeChanges = $pdo->query("SELECT r.*, u.Name as TeacherName FROM requests r JOIN users u ON r.RequestedBy=u.UserID WHERE r.Type='free_change' AND r.Status='pending_admin'")->fetchAll();

// Fetch Pending Swaps (Where TeacherB is Accepted)
$swaps = $pdo->query("SELECT r.*, u1.Name as T1Name, u2.Name as T2Name FROM requests r JOIN users u1 ON r.RequestedBy=u1.UserID JOIN users u2 ON r.TargetTeacherID=u2.UserID WHERE r.Type='swap' AND r.Status='pending_admin'")->fetchAll();

// Fetch pending leaves
$leaves = $pdo->query("SELECT l.*, u.Name as TeacherName FROM leave_requests l JOIN users u ON l.TeacherID=u.UserID WHERE l.Status='pending'")->fetchAll();

?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <!-- Sidebar -->
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Requests Management</h2>
        
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?php echo $error; ?></div><?php endif; ?>
        
        <ul class="nav nav-tabs mb-4" id="requestTabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" id="free-tab" data-bs-toggle="tab" href="#free" role="tab">Free Changes</a></li>
            <li class="nav-item"><a class="nav-link" id="swap-tab" data-bs-toggle="tab" href="#swap" role="tab">Swaps</a></li>
            <li class="nav-item"><a class="nav-link" id="leave-tab" data-bs-toggle="tab" href="#leave" role="tab">Leave Requests</a></li>
        </ul>
        
        <div class="tab-content" id="myTabContent">
            <!-- Free Changes -->
            <div class="tab-pane fade show active" id="free" role="tabpanel">
                <div class="bg-white p-4 shadow-md rounded">
                    <table class="w-full text-left">
                        <thead class="bg-gray-100"><tr><th class="p-2">Date</th><th class="p-2">Teacher</th><th class="p-2">Action</th></tr></thead>
                        <tbody>
                            <?php foreach($freeChanges as $r): ?>
                            <tr class="border-b table-row-alt">
                                <td class="p-2"><?php echo $r['CreatedAt']; ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($r['TeacherName']); ?></td>
                                <td class="p-2 flex space-x-2">
                                    <form method="POST"><input type="hidden" name="action" value="manage_request"><input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>"><button type="submit" name="status" value="approved" class="bg-green-500 text-white px-2 py-1 rounded text-sm">Approve</button></form>
                                    <form method="POST" class="flex space-x-1"><input type="hidden" name="action" value="manage_request"><input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>"><input type="text" name="admin_note" placeholder="Reject Reason..." class="border p-1 text-sm"><button type="submit" name="status" value="rejected" class="bg-red-500 text-white px-2 py-1 rounded text-sm">Reject</button></form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($freeChanges)) echo "<tr><td colspan='3' class='p-4 text-center'>No requests pending.</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Swaps -->
            <div class="tab-pane fade" id="swap" role="tabpanel">
                <div class="bg-white p-4 shadow-md rounded">
                    <table class="w-full text-left">
                        <thead class="bg-gray-100"><tr><th class="p-2">Date</th><th class="p-2">Teacher 1</th><th class="p-2">Teacher 2</th><th class="p-2">Action</th></tr></thead>
                        <tbody>
                            <?php foreach($swaps as $r): ?>
                            <tr class="border-b table-row-alt">
                                <td class="p-2"><?php echo $r['CreatedAt']; ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($r['T1Name']); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($r['T2Name']); ?></td>
                                <td class="p-2 flex space-x-2">
                                    <form method="POST"><input type="hidden" name="action" value="manage_request"><input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>"><button type="submit" name="status" value="approved" class="bg-green-500 text-white px-2 py-1 rounded text-sm">Approve</button></form>
                                    <form method="POST" class="flex space-x-1"><input type="hidden" name="action" value="manage_request"><input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>"><input type="text" name="admin_note" placeholder="Reject Reason..." class="border p-1 text-sm"><button type="submit" name="status" value="rejected" class="bg-red-500 text-white px-2 py-1 rounded text-sm">Reject</button></form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($swaps)) echo "<tr><td colspan='4' class='p-4 text-center'>No requests pending.</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Leave -->
            <div class="tab-pane fade" id="leave" role="tabpanel">
                <div class="bg-white p-4 shadow-md rounded">
                    <table class="w-full text-left">
                        <thead class="bg-gray-100"><tr><th class="p-2">Teacher</th><th class="p-2">Dates</th><th class="p-2">Shift</th><th class="p-2">Reason</th><th class="p-2">Action</th></tr></thead>
                        <tbody>
                            <?php foreach($leaves as $r): ?>
                            <tr class="border-b table-row-alt">
                                <td class="p-2"><?php echo htmlspecialchars($r['TeacherName']); ?></td>
                                <td class="p-2"><?php echo $r['FromDate']; ?> to <?php echo $r['ToDate']; ?></td>
                                <td class="p-2"><?php echo $r['Shift']; ?></td>
                                <td class="p-2 text-sm max-w-xs truncate" title="<?php echo htmlspecialchars($r['Reason']); ?>"><?php echo htmlspecialchars($r['Reason']); ?></td>
                                <td class="p-2 flex flex-col space-y-1">
                                    <form method="POST"><input type="hidden" name="action" value="manage_leave"><input type="hidden" name="request_id" value="<?php echo $r['LeaveID']; ?>"><button type="submit" name="status" value="approved" class="bg-green-500 text-white px-2 py-1 rounded text-sm w-full">Approve</button></form>
                                    <form method="POST" class="flex flex-col"><input type="hidden" name="action" value="manage_leave"><input type="hidden" name="request_id" value="<?php echo $r['LeaveID']; ?>"><input type="text" name="admin_note" placeholder="Reason..." class="border p-1 text-sm mb-1"><button type="submit" name="status" value="rejected" class="bg-red-500 text-white px-2 py-1 rounded text-sm">Reject</button></form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($leaves)) echo "<tr><td colspan='5' class='p-4 text-center'>No requests pending.</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
