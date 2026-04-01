<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/timetable_rules.php'; // Required for simulating the conflict checker, though maybe just checking isFree is enough

$teacherId = $_SESSION['user_id'];
$message = $error = '';

// Fetch teacher's timetable
$stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, ts.StartTime, c.Name as CourseName, r.Name as RoomName 
                       FROM timetable t 
                       JOIN time_slots ts ON t.SlotID = ts.SlotID
                       LEFT JOIN courses c ON t.CourseID = c.CourseID
                       LEFT JOIN rooms r ON t.RoomID = r.RoomID
                       WHERE t.TeacherID = ? ORDER BY t.ShiftID, FIELD(t.Day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), ts.PeriodNumber");
$stmt->execute([$teacherId]);
$myTimetable = $stmt->fetchAll();

$teachingSlots = [];
$freeSlots = [];

foreach($myTimetable as $slot) {
    if ($slot['IsFree'] == 0) {
        $teachingSlots[] = $slot;
    } else {
        $freeSlots[] = $slot;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentTT = $_POST['current_tt_id'] ?? 0;
    $targetTT = $_POST['target_tt_id'] ?? 0;
    $reason = $_POST['reason'] ?? '';

    if ($currentTT && $targetTT) {
        // Validate both belong to teacher
        $curr = $pdo->prepare("SELECT * FROM timetable WHERE TimetableID = ? AND TeacherID = ? AND IsFree = 0");
        $curr->execute([$currentTT, $teacherId]);
        $cData = $curr->fetch();
        
        $targ = $pdo->prepare("SELECT * FROM timetable WHERE TimetableID = ? AND TeacherID = ? AND IsFree = 1");
        $targ->execute([$targetTT, $teacherId]);
        $tData = $targ->fetch();

        if (!$cData || !$tData) {
            $error = "Invalid timetable slot selection.";
        } elseif ($cData['ShiftID'] !== $tData['ShiftID']) {
            $error = "Free period change requests must be within the same shift.";
        } else {
            // Need to check if there are no conflicts logically if we just moved the course to the target slot
            // Since it's a structural swap for the teacher, we simulate moving the course to $tData['SlotID'] on $tData['Day']
            $simulateData = [
                'ProgramID' => $cData['ProgramID'],
                'DepartmentID' => $cData['DepartmentID'],
                'SemesterID' => $cData['SemesterID'],
                'ShiftID' => $cData['ShiftID'],
                'Day' => $tData['Day'],
                'SlotID' => $tData['SlotID'],
                'CourseID' => $cData['CourseID'],
                'TeacherID' => $teacherId,
                'RoomID' => $cData['RoomID'],
                'IsFree' => 0
            ];
            
            // Check Class Double Booking, Room Double Booking and Course Rule Eight on new day
            // We ignore $currentTT for counting
            $valid = validateTimetableSlot($pdo, $simulateData, $currentTT);
            
            if ($valid === true) {
                // Save Request
                $insert = $pdo->prepare("INSERT INTO requests (Type, RequestedBy, TimetableID, NewSlotID, Status, CreatedAt) VALUES ('free_change', ?, ?, ?, 'pending_admin', NOW())");
                // Note: The prompt says NewSlotID. But strictly, we might need to know the Day since time_slots just defines time, not day. 
                // But the target is an existing timetable slot, so we can save the target timetable ID in NewSlotID (misnomer maybe) or SwapTimetableID.
                // Let's use SwapTimetableID for the target free slot reference so we know exactly the Day and Slot context.
                $insert = $pdo->prepare("INSERT INTO requests (Type, RequestedBy, TimetableID, SwapTimetableID, Status, CreatedAt) VALUES ('free_change', ?, ?, ?, 'pending_admin', NOW())");
                $insert->execute([$teacherId, $currentTT, $targetTT]);
                $message = "Request submitted successfully. Waiting for admin approval.";
                
                // Notify admin
                $msg = "New Free Period Change Request from Teacher ID $teacherId.";
                $pdo->prepare("INSERT INTO notifications (ScopeType, Message) VALUES ('global', ?)")->execute([$msg]);
            } else {
                $error = "Request impossible due to conflict: " . trim($valid);
            }
        }
    } else {
        $error = "Please select both a current teaching period and a free period.";
    }
}
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4">
    <!-- Sidebar -->
    <aside class="w-64 bg-white p-4 shadow-md rounded">
        <h3 class="text-lg font-bold text-maroon mb-4">Teacher Menu</h3>
        <ul class="space-y-2">
            <li><a href="dashboard.php" class="block p-2 hover:bg-gray-100 rounded">Dashboard</a></li>
            <li><a href="timetable.php" class="block p-2 hover:bg-gray-100 rounded">My Timetable</a></li>
            <li><a href="request_free_change.php" class="block p-2 bg-gray-100 rounded text-maroon font-semibold">Free Change Request</a></li>
            <li><a href="request_swap.php" class="block p-2 hover:bg-gray-100 rounded">Swap Request</a></li>
            <li><a href="request_leave.php" class="block p-2 hover:bg-gray-100 rounded">Leave Request</a></li>
        </ul>
    </aside>
    
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Free Period Change Request</h2>
        
        <div class="bg-white p-6 shadow-md rounded">
            <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?php echo $error; ?></div><?php endif; ?>
            
            <form method="POST">
                <div class="mb-4">
                    <label class="block font-bold mb-1">Select Teaching Period to Move</label>
                    <select name="current_tt_id" required class="w-full border rounded p-2">
                        <option value="">-- Select --</option>
                        <?php foreach($teachingSlots as $ts): ?>
                            <option value="<?php echo $ts['TimetableID']; ?>">
                                <?php echo $ts['Day'] . ' - Period ' . $ts['PeriodNumber'] . ' (' . ($ts['ShiftID']==1?'Morning':'Evening') . ') - ' . $ts['CourseName']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block font-bold mb-1">Select Target Free Period</label>
                    <select name="target_tt_id" required class="w-full border rounded p-2">
                        <option value="">-- Select --</option>
                        <?php foreach($freeSlots as $fs): ?>
                            <option value="<?php echo $fs['TimetableID']; ?>">
                                <?php echo $fs['Day'] . ' - Period ' . $fs['PeriodNumber'] . ' (' . ($fs['ShiftID']==1?'Morning':'Evening') . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block font-bold mb-1">Reason (Optional)</label>
                    <textarea name="reason" rows="3" class="w-full border rounded p-2"></textarea>
                </div>
                
                <button type="submit" class="btn-maroon px-4 py-2 rounded font-bold">Submit Request</button>
            </form>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
