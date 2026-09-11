<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/timetable_rules.php';
require_once __DIR__ . '/../api/automations.php';

$message = $error = $alertHtml = '';

$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reqId = $_POST['request_id'] ?? 0;
    $status = $_POST['status'] ?? ''; // approved or rejected
    $note = $_POST['admin_note'] ?? '';
    
    if ($action === 'manage_leave') {
        $pdo->prepare("UPDATE leave_requests SET Status=?, AdminNote=? WHERE LeaveID=?")
            ->execute([$status, $note, $reqId]);
        
        $lq = $pdo->prepare("SELECT TeacherID, FromDate, ToDate, Shift, SuggestedSubstituteID FROM leave_requests WHERE LeaveID = ?");
        $lq->execute([$reqId]);
        $leaveData = $lq->fetch();
        $tId = $leaveData['TeacherID'];
        
        // Notify Teacher
        $msg = "Your leave request was $status." . ($note ? " Note: $note" : "");
        $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$tId, $msg]);
        $message = "Leave Request $status.";
        
        if ($status === 'approved') {
            $startDate = new DateTime($leaveData['FromDate']);
            $endDate = new DateTime($leaveData['ToDate']);
            $endDate->modify('+1 day'); // to iterate inclusive
            $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate);
            $leaveDays = [];
            foreach ($period as $dt) {
                if ($dt->format('N') <= 6) $leaveDays[] = $dt->format('l');
            }
            $leaveDays = array_unique($leaveDays);
            
            if (!empty($leaveDays)) {
                $shiftCond = " t.ShiftID IN (1,2) ";
                if ($leaveData['Shift'] == 'Morning') $shiftCond = " t.ShiftID = 1 ";
                if ($leaveData['Shift'] == 'Evening') $shiftCond = " t.ShiftID = 2 ";
                
                $inStr = str_repeat('?,', count($leaveDays) - 1) . '?';
                
                $tq = $pdo->prepare("SELECT t.Day, t.TimetableID,
                                     IF(t.Day='Friday' AND ts.FridayStartTime IS NOT NULL, ts.FridayStartTime, ts.StartTime) as StartTime, 
                                     c.Name as CourseName, p.Name as ProgName, d.Name as DeptName, s.Label as SemName
                                     FROM timetable t 
                                     JOIN time_slots ts ON t.SlotID=ts.SlotID
                                     LEFT JOIN courses c ON t.CourseID=c.CourseID
                                     LEFT JOIN programs p ON t.ProgramID=p.ProgramID
                                     LEFT JOIN departments d ON t.DepartmentID=d.DepartmentID
                                     LEFT JOIN semesters s ON t.SemesterID=s.SemesterID
                                     WHERE t.TeacherID=? AND t.IsFree=0 AND $shiftCond AND t.Day IN ($inStr)
                                     ORDER BY FIELD(t.Day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), ts.StartTime");
                
                $params = array_merge([$tId], $leaveDays);
                $tq->execute($params);
                $missedClasses = $tq->fetchAll();
                
                if (count($missedClasses) > 0) {
                    $hasSubstitute = !empty($leaveData['SuggestedSubstituteID']);
                    
                    if ($hasSubstitute) {
                        $subId = $leaveData['SuggestedSubstituteID'];
                        $subNameQ = $pdo->prepare("SELECT Name FROM users WHERE UserID = ?");
                        $subNameQ->execute([$subId]);
                        $subName = $subNameQ->fetchColumn();
                        
                        $alertHtml = "<h4 class='font-bold flex items-center gap-2 text-green-700'><i class='fas fa-check-circle'></i> Auto-Assigned Substitute to Missed Classes</h4>";
                        $alertHtml .= "<ul class='list-disc list-inside mt-2 text-sm max-h-48 overflow-y-auto space-y-1'>";
                        
                        $insSub = $pdo->prepare("INSERT INTO substitute_assignments (TimetableID, OriginalTeacherID, SubstituteTeacherID, FromDate, ToDate, Status) VALUES (?,?,?,?,?,?)");
                        
                        foreach ($missedClasses as $mc) {
                            $elig = checkSubstituteEligibility($pdo, $mc['TimetableID'], $subId, $leaveData['FromDate'], $leaveData['ToDate']);
                            if ($elig === true) {
                                $insSub->execute([$mc['TimetableID'], $tId, $subId, $leaveData['FromDate'], $leaveData['ToDate'], 'active']);
                                $alertHtml .= "<li><span class='font-bold w-20 inline-block text-maroon'>{$mc['Day']}</span> <span class='text-gray-800 font-bold'>".substr($mc['StartTime'],0,5)."</span> &mdash; <strong>{$mc['CourseName']}</strong> (Covered by $subName)</li>";
                            } else {
                                $alertHtml .= "<li><span class='font-bold w-20 inline-block text-maroon'>{$mc['Day']}</span> <span class='text-gray-800 font-bold'>".substr($mc['StartTime'],0,5)."</span> &mdash; <strong>{$mc['CourseName']}</strong> (Failed to assign $subName: Conflict)</li>";
                            }
                        }
                        $alertHtml .= "</ul><p class='text-sm mt-3 font-semibold text-gray-700 bg-black/5 p-2 rounded'>Please check Substitutes page for details.</p>";
                        // Notify Substitute
                        $msg = "You have been automatically assigned as a substitute for {$leaveData['FromDate']} to {$leaveData['ToDate']}.";
                        $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$subId, $msg]);
                    } else {
                        $alertHtml = "<h4 class='font-bold flex items-center gap-2'><svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/></svg> Classes needing Substitute coverage during this Leave</h4>";
                        $alertHtml .= "<ul class='list-disc list-inside mt-2 text-sm max-h-48 overflow-y-auto space-y-1'>";
                        foreach ($missedClasses as $mc) {
                            $alertHtml .= "<li><span class='font-bold w-20 inline-block text-maroon'>{$mc['Day']}</span> <span class='text-gray-800 font-bold'>".substr($mc['StartTime'],0,5)."</span> &mdash; <strong>{$mc['CourseName']}</strong> <span class='text-xs text-gray-500'>({$mc['ProgName']} {$mc['DeptName']} {$mc['SemName']})</span></li>";
                        }
                        $alertHtml .= "</ul><p class='text-sm mt-3 font-semibold text-gray-700 bg-black/5 p-2 rounded'>Please arrange substitutes manually with the concerned department to avoid unattended classes.</p>";
                    }
                } else {
                    $alertHtml = "<strong>Notice:</strong> This teacher has no scheduled teaching classes during the approved dates/shift.";
                }
            }
        }
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
                $pdo->beginTransaction();
                try {
                    // Fetch target slot
                    $targQ = $pdo->prepare("SELECT * FROM timetable WHERE TimetableID = ? FOR UPDATE");
                    $targQ->execute([$req['SwapTimetableID']]);
                    $targSlot = $targQ->fetch();
                    
                    if ($targSlot['IsFree'] == 1) {
                        // Fetch current slot
                        $curQ = $pdo->prepare("SELECT * FROM timetable WHERE TimetableID = ? FOR UPDATE");
                        $curQ->execute([$req['TimetableID']]);
                        $cSlot = $curQ->fetch();
                        
                        $newData = $targSlot;
                        $newData['TeacherID'] = $cSlot['TeacherID'];
                        $newData['CourseID'] = $cSlot['CourseID'];
                        $newData['RoomID'] = $cSlot['RoomID'];
                        $newData['IsFree'] = 0;
                        
                        $val = validateTimetableSlot($pdo, $newData, $req['SwapTimetableID']);
                        if (is_string($val) && strpos($val, 'Rule Six') !== false) {
                            // Room conflict - Find another room of the same type automatically
                            $cq = $pdo->prepare("SELECT RoomType FROM courses WHERE CourseID=?");
                            $cq->execute([$cSlot['CourseID']]);
                            $rType = $cq->fetchColumn();

                            // Find a free room
                            $sess = $newData['SessionID'] ?? null;
                            $fq = $pdo->prepare("SELECT RoomID FROM rooms WHERE Type=? AND RoomID NOT IN (SELECT RoomID FROM timetable WHERE Day=? AND SlotID=? AND SessionID<=>? AND RoomID IS NOT NULL AND TimetableID!=?) LIMIT 1");
                            $fq->execute([$rType, $newData['Day'], $newData['SlotID'], $sess, $req['SwapTimetableID']]);
                            $altRoom = $fq->fetchColumn();

                            if ($altRoom) {
                                $newData['RoomID'] = $altRoom;
                                $val = validateTimetableSlot($pdo, $newData, $req['SwapTimetableID']); // re-validate with new room
                            }
                        }

                        if ($val !== true) {
                            throw new Exception("Cannot approve: conflict now exists ($val)");
                        }
                        
                        // Update Target slot to teach
                        $pdo->prepare("UPDATE timetable SET CourseID=?, TeacherID=?, RoomID=?, IsFree=0 WHERE TimetableID=?")
                            ->execute([$newData['CourseID'], $newData['TeacherID'], $newData['RoomID'], $req['SwapTimetableID']]);
                            
                        // Mark Old slot as Free
                        $pdo->prepare("UPDATE timetable SET CourseID=NULL, TeacherID=NULL, RoomID=NULL, IsFree=1 WHERE TimetableID=?")
                            ->execute([$req['TimetableID']]);
                            
                        // Update Request status
                        $pdo->prepare("UPDATE requests SET Status='approved' WHERE RequestID=?")->execute([$reqId]);
                        
                        // Notify
                        $msg = "Free Period Change Request Approved!";
                        $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$req['RequestedBy'], $msg]);
                        
                        $pdo->commit();
                        $message = "Free period change approved. Timetable updated.";
                    } else {
                        throw new Exception("Target slot is no longer free. Please reject this request manually.");
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = $e->getMessage();
                }
            } 
            elseif ($req['Type'] === 'swap') {
                $pdo->beginTransaction();
                try {
                    // Fetch A slot
                    $aQ = $pdo->prepare("SELECT * FROM timetable WHERE TimetableID = ? FOR UPDATE");
                    $aQ->execute([$req['TimetableID']]);
                    $aSlot = $aQ->fetch();
                    
                    // Fetch B slot
                    $bQ = $pdo->prepare("SELECT * FROM timetable WHERE TimetableID = ? FOR UPDATE");
                    $bQ->execute([$req['SwapTimetableID']]);
                    $bSlot = $bQ->fetch();
                    
                    $aNew = $bSlot; $aNew['TeacherID'] = $aSlot['TeacherID']; $aNew['RoomID'] = $aSlot['RoomID']; $aNew['CourseID'] = $aSlot['CourseID'];
                    $bNew = $aSlot; $bNew['TeacherID'] = $bSlot['TeacherID']; $bNew['RoomID'] = $bSlot['RoomID']; $bNew['CourseID'] = $bSlot['CourseID'];
                    
                    $errA1 = checkTeacherDoubleBooking($pdo, $aNew, $bSlot['TimetableID']);
                    if($errA1 !== true) throw new Exception("Conflict for Teacher A: $errA1");
                    $errA2 = checkRoomDoubleBooking($pdo, $aNew, $bSlot['TimetableID']);
                    if($errA2 !== true) throw new Exception("Conflict for Teacher A: $errA2");
                    
                    $errB1 = checkTeacherDoubleBooking($pdo, $bNew, $aSlot['TimetableID']);
                    if($errB1 !== true) throw new Exception("Conflict for Teacher B: $errB1");
                    $errB2 = checkRoomDoubleBooking($pdo, $bNew, $aSlot['TimetableID']);
                    if($errB2 !== true) throw new Exception("Conflict for Teacher B: $errB2");
                    
                    // Swap Timetable Slots Context
                    $pdo->prepare("UPDATE timetable SET CourseID=?, TeacherID=?, RoomID=? WHERE TimetableID=?")
                        ->execute([$bSlot['CourseID'], $bSlot['TeacherID'], $bSlot['RoomID'], $req['TimetableID']]);
                        
                    $pdo->prepare("UPDATE timetable SET CourseID=?, TeacherID=?, RoomID=? WHERE TimetableID=?")
                        ->execute([$aSlot['CourseID'], $aSlot['TeacherID'], $aSlot['RoomID'], $req['SwapTimetableID']]);
                    
                    $pdo->prepare("UPDATE requests SET Status='approved' WHERE RequestID=?")->execute([$reqId]);
                    
                    // Notify
                    $msg = "Swap Request Officially Approved!";
                    $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$req['RequestedBy'], $msg]);
                    $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$req['TargetTeacherID'], $msg]);
                    
                    $pdo->commit();
                    $message = "Swap approved correctly. Timetable updated.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = $e->getMessage();
                }
            }
            elseif ($req['Type'] === 'proxy') {
                $pdo->beginTransaction();
                try {
                    // Check eligibility
                    $elig = checkSubstituteEligibility($pdo, $req['TimetableID'], $req['TargetTeacherID'], $req['ProxyDate'], $req['ProxyDate']);
                    if ($elig !== true) throw new Exception("Conflict for target teacher: $elig");

                    // Insert into substitute assignments
                    $ins = $pdo->prepare("INSERT INTO substitute_assignments (TimetableID, OriginalTeacherID, SubstituteTeacherID, FromDate, ToDate, Status) VALUES (?,?,?,?,?,?)");
                    $ins->execute([$req['TimetableID'], $req['RequestedBy'], $req['TargetTeacherID'], $req['ProxyDate'], $req['ProxyDate'], 'active']);

                    $pdo->prepare("UPDATE requests SET Status='approved' WHERE RequestID=?")->execute([$reqId]);
                    
                    // Notify
                    $msg = "Proxy Request Officially Approved!";
                    $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$req['RequestedBy'], $msg]);
                    $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$req['TargetTeacherID'], $msg]);
                    
                    $pdo->commit();
                    $message = "Proxy approved correctly. Substitute assignment created.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// Fetch pending Free Changes
$freeQ = "
SELECT r.*, u.Name as TeacherName,
       curr_t.Day as CurrDay, curr_ts.StartTime as CurrTime, curr_c.Name as CurrCourse,
       targ_t.Day as TargDay, targ_ts.StartTime as TargTime
FROM requests r
JOIN users u ON r.RequestedBy = u.UserID
JOIN timetable curr_t ON r.TimetableID = curr_t.TimetableID
JOIN time_slots curr_ts ON curr_t.SlotID = curr_ts.SlotID
LEFT JOIN courses curr_c ON curr_t.CourseID = curr_c.CourseID
JOIN timetable targ_t ON r.SwapTimetableID = targ_t.TimetableID
JOIN time_slots targ_ts ON targ_t.SlotID = targ_ts.SlotID
WHERE r.Type='free_change' AND r.Status='pending_admin'";
$freeChanges = $pdo->query($freeQ)->fetchAll();

// Fetch Pending Proxies (Where TeacherB is Accepted)
$proxyQ = "
SELECT r.*, u1.Name as T1Name, u2.Name as T2Name,
       curr_t.Day as CurrDay, curr_ts.StartTime as CurrTime, curr_c.Name as CurrCourse
FROM requests r
JOIN users u1 ON r.RequestedBy = u1.UserID
JOIN users u2 ON r.TargetTeacherID = u2.UserID
JOIN timetable curr_t ON r.TimetableID = curr_t.TimetableID
JOIN time_slots curr_ts ON curr_t.SlotID = curr_ts.SlotID
LEFT JOIN courses curr_c ON curr_t.CourseID = curr_c.CourseID
WHERE r.Type='proxy' AND r.Status='pending_admin'";
$proxies = $pdo->query($proxyQ)->fetchAll();

// Fetch Pending Swaps (Where TeacherB is Accepted)
$swapQ = "
SELECT r.*, 
       u1.Name as T1Name, u2.Name as T2Name,
       curr_t.Day as CurrDay, curr_ts.StartTime as CurrTime, curr_c.Name as CurrCourse,
       targ_t.Day as TargDay, targ_ts.StartTime as TargTime, targ_c.Name as TargCourse
FROM requests r
JOIN users u1 ON r.RequestedBy = u1.UserID
JOIN users u2 ON r.TargetTeacherID = u2.UserID
JOIN timetable curr_t ON r.TimetableID = curr_t.TimetableID
JOIN time_slots curr_ts ON curr_t.SlotID = curr_ts.SlotID
LEFT JOIN courses curr_c ON curr_t.CourseID = curr_c.CourseID
JOIN timetable targ_t ON r.SwapTimetableID = targ_t.TimetableID
JOIN time_slots targ_ts ON targ_t.SlotID = targ_ts.SlotID
LEFT JOIN courses targ_c ON targ_t.CourseID = targ_c.CourseID
WHERE r.Type='swap' AND r.Status='pending_admin'";
$swaps = $pdo->query($swapQ)->fetchAll();

// Fetch pending leaves
$leaves = $pdo->query("SELECT l.*, u.Name as TeacherName, usub.Name as SubName FROM leave_requests l JOIN users u ON l.TeacherID=u.UserID LEFT JOIN users usub ON l.SuggestedSubstituteID=usub.UserID WHERE l.Status='pending'")->fetchAll();

?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <!-- Sidebar -->
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Requests Management</h2>
        
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4 shadow-sm font-semibold"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if($alertHtml): ?><div class="bg-yellow-50 text-yellow-900 border-l-4 border-yellow-500 p-4 rounded mb-4 shadow-md"><?php echo $alertHtml; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4 shadow-sm"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        
        <!-- Vanilla JS Tailwind Tabs -->
        <div class="flex space-x-2 border-b border-gray-200 mb-6">
            <button onclick="switchTab('free')" id="btn-free" class="tab-btn px-4 py-2 border-b-2 font-medium text-maroon border-maroon transition-colors">F.P. Change Requests</button>
            <button onclick="switchTab('swap')" id="btn-swap" class="tab-btn px-4 py-2 border-b-2 font-medium text-gray-500 border-transparent hover:text-maroon hover:border-maroon transition-colors">Swap Requests</button>
            <button onclick="switchTab('proxy')" id="btn-proxy" class="tab-btn px-4 py-2 border-b-2 font-medium text-gray-500 border-transparent hover:text-maroon hover:border-maroon transition-colors">Proxy Requests</button>
            <button onclick="switchTab('leave')" id="btn-leave" class="tab-btn px-4 py-2 border-b-2 font-medium text-gray-500 border-transparent hover:text-maroon hover:border-maroon transition-colors">Leave Requests</button>
        </div>
        
        <!-- Tab Contents -->
        <div>
            <!-- Free Changes -->
            <div id="tab-free" class="tab-content block">
                <div class="bg-white p-4 shadow-md rounded">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="p-3 border-b">Date</th>
                                <th class="p-3 border-b">Teacher</th>
                                <th class="p-3 border-b">Modification Details</th>
                                <th class="p-3 border-b">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($freeChanges as $r): ?>
                            <tr class="border-b hover:bg-gray-50 transition-colors">
                                <td class="p-3 text-sm"><?php echo date('d M Y', strtotime($r['CreatedAt'])); ?></td>
                                <td class="p-3 font-medium text-gray-900"><?php echo htmlspecialchars($r['TeacherName']); ?></td>
                                <td class="p-3 text-sm">
                                    <div class="flex items-center space-x-2">
                                        <span class="bg-gray-200 text-gray-800 px-2 py-1 rounded border border-gray-300">
                                            <?php echo $r['CurrDay']; ?> <?php echo date('H:i', strtotime($r['CurrTime'])); ?> (<?php echo htmlspecialchars($r['CurrCourse'] ?? 'Lab'); ?>)
                                        </span>
                                        <span class="text-maroon font-bold text-lg">➔</span>
                                        <span class="bg-blue-50 text-blue-800 px-2 py-1 rounded border border-blue-200">
                                            <?php echo $r['TargDay']; ?> <?php echo date('H:i', strtotime($r['TargTime'])); ?> (Free)
                                        </span>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <div class="flex flex-col space-y-2 min-w-[140px]">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="manage_request"><input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>">
                                            <button type="submit" name="status" value="approved" class="w-full bg-green-50 text-green-600 hover:bg-green-100 px-3 py-1.5 rounded font-bold text-sm transition-colors">Approve</button>
                                        </form>
                                        <form method="POST" class="flex flex-col space-y-1">
                                            <input type="hidden" name="action" value="manage_request"><input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>">
                                            <input type="text" name="admin_note" required placeholder="Reject reason..." class="border border-red-200 rounded px-2 py-1.5 text-xs text-gray-800 focus:outline-none focus:border-red-500 w-full transition-colors bg-red-50/30">
                                            <button type="submit" name="status" value="rejected" class="w-full bg-red-50 text-red-600 hover:bg-red-100 font-bold px-3 py-1.5 rounded text-sm transition-colors">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($freeChanges)) echo "<tr><td colspan='4' class='p-4 text-center text-gray-500'>No free change requests pending.</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Swaps -->
            <div id="tab-swap" class="tab-content hidden">
                <div class="bg-white p-4 shadow-md rounded">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="p-3 border-b">Date</th>
                                <th class="p-3 border-b">Swap Details</th>
                                <th class="p-3 border-b">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($swaps as $r): ?>
                            <tr class="border-b hover:bg-gray-50 transition-colors">
                                <td class="p-3 text-sm"><?php echo date('d M Y', strtotime($r['CreatedAt'])); ?></td>
                                <td class="p-3 text-sm space-y-2 py-4">
                                    <div class="flex items-center space-x-2">
                                        <span class="font-medium text-gray-900 w-32 truncate text-right border-r pr-2" title="<?php echo htmlspecialchars($r['T1Name']); ?>"><?php echo htmlspecialchars($r['T1Name']); ?></span>
                                        <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded inline-block w-64 border border-gray-200">
                                            <?php echo $r['CurrDay']; ?> <?php echo date('H:i', strtotime($r['CurrTime'])); ?> (<?php echo htmlspecialchars($r['CurrCourse'] ?? 'Lab'); ?>)
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="font-medium text-gray-900 w-32 truncate text-right border-r pr-2" title="<?php echo htmlspecialchars($r['T2Name']); ?>"><?php echo htmlspecialchars($r['T2Name']); ?></span>
                                        <span class="bg-blue-50 text-blue-800 px-2 py-1 rounded inline-block w-64 border border-blue-200">
                                            <?php echo $r['TargDay']; ?> <?php echo date('H:i', strtotime($r['TargTime'])); ?> (<?php echo htmlspecialchars($r['TargCourse'] ?? 'Lab'); ?>)
                                        </span>
                                    </div>
                                </td>
                                <td class="p-3 mt-2">
                                    <div class="flex flex-col space-y-2 min-w-[140px]">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="manage_request"><input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>">
                                            <button type="submit" name="status" value="approved" class="w-full bg-green-50 text-green-600 hover:bg-green-100 px-3 py-1.5 rounded font-bold text-sm transition-colors">Approve</button>
                                        </form>
                                        <form method="POST" class="flex flex-col space-y-1">
                                            <input type="hidden" name="action" value="manage_request"><input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>">
                                            <input type="text" name="admin_note" required placeholder="Reject reason..." class="border border-red-200 rounded px-2 py-1.5 text-xs text-gray-800 focus:outline-none focus:border-red-500 w-full transition-colors bg-red-50/30">
                                            <button type="submit" name="status" value="rejected" class="w-full bg-red-50 text-red-600 hover:bg-red-100 font-bold px-3 py-1.5 rounded text-sm transition-colors">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($swaps)) echo "<tr><td colspan='3' class='p-4 text-center text-gray-500'>No swap requests pending.</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Proxy Requests -->
            <div id="tab-proxy" class="tab-content hidden">
                <div class="bg-white p-4 shadow-md rounded">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="p-3 border-b">Requester</th>
                                <th class="p-3 border-b">Class Details</th>
                                <th class="p-3 border-b">Selected Proxy</th>
                                <th class="p-3 border-b">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($proxies as $r): ?>
                            <tr class="border-b hover:bg-gray-50 transition-colors">
                                <td class="p-3 font-medium text-gray-900"><?php echo htmlspecialchars($r['T1Name']); ?> <br><span class="text-xs text-gray-500">Date: <?php echo date('d M Y', strtotime($r['ProxyDate'])); ?></span></td>
                                <td class="p-3 text-sm">
                                    <span class="bg-maroon/10 text-maroon px-2 py-1 rounded inline-block border border-maroon/20">
                                        <?php echo $r['CurrDay']; ?> <?php echo date('H:i', strtotime($r['CurrTime'])); ?> (<?php echo htmlspecialchars($r['CurrCourse'] ?? 'Lab'); ?>)
                                    </span>
                                </td>
                                <td class="p-3 font-bold text-green-700">
                                    <?php echo htmlspecialchars($r['T2Name']); ?> (Accepted)
                                </td>
                                <td class="p-3">
                                    <div class="flex flex-col space-y-2 min-w-[140px]">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="manage_request"><input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>">
                                            <button type="submit" name="status" value="approved" class="w-full bg-green-50 text-green-600 hover:bg-green-100 px-3 py-1.5 rounded font-bold text-sm transition-colors">Assign</button>
                                        </form>
                                        <form method="POST" class="flex flex-col space-y-1">
                                            <input type="hidden" name="action" value="manage_request"><input type="hidden" name="request_id" value="<?php echo $r['RequestID']; ?>">
                                            <input type="text" name="admin_note" required placeholder="Reject reason..." class="border border-red-200 rounded px-2 py-1.5 text-xs text-gray-800 focus:outline-none focus:border-red-500 w-full transition-colors bg-red-50/30">
                                            <button type="submit" name="status" value="rejected" class="w-full bg-red-50 text-red-600 hover:bg-red-100 font-bold px-3 py-1.5 rounded text-sm transition-colors">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($proxies)) echo "<tr><td colspan='4' class='p-4 text-center text-gray-500'>No proxy requests pending.</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Leave -->
            <div id="tab-leave" class="tab-content hidden">
                <div class="bg-white p-4 shadow-md rounded">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="p-3 border-b">Teacher</th>
                                <th class="p-3 border-b">Leave Type</th>
                                <th class="p-3 border-b">Dates</th>
                                <th class="p-3 border-b">Shift</th>
                                <th class="p-3 border-b">Reason</th>
                                <th class="p-3 border-b">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($leaves as $r): ?>
                            <tr class="border-b hover:bg-gray-50 transition-colors">
                                <td class="p-3 font-medium text-gray-900"><?php echo htmlspecialchars($r['TeacherName']); ?></td>
                                <td class="p-3 text-sm text-maroon font-bold">
                                    <?php echo htmlspecialchars($r['LeaveType'] ?? 'Casual Leave'); ?>
                                    <?php if(!empty($r['SubName'])): ?>
                                        <br><span class="text-xs text-blue-600 bg-blue-50 px-1 py-0.5 rounded font-normal border border-blue-100">Proxy: <?php echo htmlspecialchars($r['SubName']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-sm"><?php echo date('d M', strtotime($r['FromDate'])); ?> - <?php echo date('d M Y', strtotime($r['ToDate'])); ?></td>
                                <td class="p-3"><span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded font-medium border border-yellow-200"><?php echo $r['Shift']; ?></span></td>
                                <td class="p-3 text-sm max-w-sm break-all whitespace-normal text-gray-700 italic border-l border-r border-gray-100 bg-gray-50/30">"<?php echo htmlspecialchars($r['Reason']); ?>"</td>
                                <td class="p-3">
                                    <div class="flex flex-col space-y-2 min-w-[140px]">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="manage_leave"><input type="hidden" name="request_id" value="<?php echo $r['LeaveID']; ?>">
                                            <button type="submit" name="status" value="approved" class="w-full bg-green-50 text-green-600 hover:bg-green-100 px-3 py-1.5 rounded font-bold text-sm transition-colors">Approve</button>
                                        </form>
                                        <form method="POST" class="flex flex-col space-y-1">
                                            <input type="hidden" name="action" value="manage_leave"><input type="hidden" name="request_id" value="<?php echo $r['LeaveID']; ?>">
                                            <input type="text" name="admin_note" required placeholder="Reject reason..." class="border border-red-200 rounded px-2 py-1.5 text-xs text-gray-800 focus:outline-none focus:border-red-500 w-full transition-colors bg-red-50/30">
                                            <button type="submit" name="status" value="rejected" class="w-full bg-red-50 text-red-600 hover:bg-red-100 font-bold px-3 py-1.5 rounded text-sm transition-colors">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($leaves)) echo "<tr><td colspan='6' class='p-4 text-center text-gray-500'>No leave requests pending.</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => {
        el.classList.remove('block');
        el.classList.add('hidden');
    });
    const content = document.getElementById('tab-' + tabId);
    if(content) {
        content.classList.remove('hidden');
        content.classList.add('block');
    }
    
    document.querySelectorAll('.tab-btn').forEach(el => {
        el.classList.remove('text-maroon', 'border-maroon');
        el.classList.add('text-gray-500', 'border-transparent');
    });
    const activeBtn = document.getElementById('btn-' + tabId);
    if(activeBtn) {
        activeBtn.classList.remove('text-gray-500', 'border-transparent');
        activeBtn.classList.add('text-maroon', 'border-maroon');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab') || 'free';
    switchTab(initialTab);
});
</script>

