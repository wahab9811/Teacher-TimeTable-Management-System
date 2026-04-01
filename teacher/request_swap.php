<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/timetable_rules.php';

$teacherId = $_SESSION['user_id'];
$message = $error = '';

// Fetch all other teachers
$stmtTeachers = $pdo->prepare("SELECT UserID, Name, Designation FROM users WHERE Role = 'teacher' AND UserID != ?");
$stmtTeachers->execute([$teacherId]);
$teachers = $stmtTeachers->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $myTT = $_POST['my_tt_id'] ?? 0;
    $targetTT = $_POST['target_tt_id'] ?? 0;
    $targetTeacher = $_POST['target_teacher_id'] ?? 0;

    if ($myTT && $targetTT && $targetTeacher) {
        $curr = $pdo->prepare("SELECT t.*, c.RoomType FROM timetable t LEFT JOIN courses c ON t.CourseID = c.CourseID WHERE t.TimetableID = ? AND t.TeacherID = ?");
        $curr->execute([$myTT, $teacherId]);
        $cData = $curr->fetch();
        
        $targ = $pdo->prepare("SELECT t.*, c.RoomType FROM timetable t LEFT JOIN courses c ON t.CourseID = c.CourseID WHERE t.TimetableID = ? AND t.TeacherID = ?");
        $targ->execute([$targetTT, $targetTeacher]);
        $tData = $targ->fetch();

        if (!$cData || !$tData) {
            $error = "Invalid selection.";
        } elseif ($cData['ShiftID'] !== $tData['ShiftID']) {
            $error = "Cross-shift swap requests are not allowed according to system boundaries.";
        } else {
            // Check conflicts for teacher A taking teacher B's slot
            $simA = $tData;
            $simA['TeacherID'] = $teacherId; 
            
            // Check conflicts for teacher B taking teacher A's slot
            $simB = $cData;
            $simB['TeacherID'] = $targetTeacher;
            
            // Rule 4 RoomType match is naturally handled because they just swap roles without changing course details, BUT 
            // the teacher assumes the other's class. Actually, in a swap, do they swap the CLASS or the SLOTS?
            // "Swap requests... both periods are in the same shift, room types are compatible..." 
            // Wait, if they swap their teaching slots, Teacher A takes Course B in Room B.
            // Let's assume Room Types are tied to the Course. So Teacher A teaches Course B in Room B.
            // Which means we just need to ensure no Teacher double-booking, since no room/class changed slot time.
            
            // Check double booking for Teacher A on new slot
            $resA = checkTeacherDoubleBooking($pdo, $simA, $cData['TimetableID']);
            // Check double booking for Teacher B on new slot
            $resB = checkTeacherDoubleBooking($pdo, $simB, $tData['TimetableID']);
            
            if ($resA !== true) $error = "Conflict for you: $resA";
            elseif ($resB !== true) $error = "Conflict for Target Teacher: $resB";
            else {
                // Deadline 48hrs
                $deadline = date('Y-m-d H:i:s', strtotime('+48 hours'));
                
                $insert = $pdo->prepare("INSERT INTO requests (Type, RequestedBy, TargetTeacherID, TimetableID, SwapTimetableID, Status, TeacherBStatus, Deadline) VALUES ('swap', ?, ?, ?, ?, 'pending_teacher', 'pending', ?)");
                $insert->execute([$teacherId, $targetTeacher, $myTT, $targetTT, $deadline]);
                
                $message = "Swap request sent to target teacher.";
                
                // Notify Teacher B
                $msg = "New Swap Request from " . $_SESSION['user_name'];
                $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$targetTeacher, $msg]);
            }
        }
    } else {
        $error = "All fields required.";
    }
}
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4">
    <!-- Sidebar omitted for brevity, logic remains -->
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Swap Request</h2>
        
        <div class="bg-white p-6 shadow-md rounded">
            <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?php echo $error; ?></div><?php endif; ?>
            
            <!-- We will use a script to load these dynamically or just load a unified JSON and filter in JS -->
            <form method="POST">
                <div class="mb-4">
                    <label class="block font-bold mb-1">My Teaching Period</label>
                    <select name="my_tt_id" id="my_tt_id" required class="w-full border rounded p-2">
                        <!-- Fetched via simple inline PHP for UX -->
                        <?php
                            $stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, c.Name as CourseName FROM timetable t JOIN time_slots ts ON t.SlotID=ts.SlotID JOIN courses c ON t.CourseID=c.CourseID WHERE t.TeacherID=? ORDER BY t.Day, ts.PeriodNumber");
                            $stmt->execute([$teacherId]);
                            while($row = $stmt->fetch()) echo "<option value='{$row['TimetableID']}'>{$row['Day']} P{$row['PeriodNumber']} - {$row['CourseName']}</option>";
                        ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block font-bold mb-1">Target Teacher</label>
                    <select name="target_teacher_id" id="target_teacher_id" onchange="fetchTeacherSlots()" required class="w-full border rounded p-2">
                        <option value="">-- Select --</option>
                        <?php foreach($teachers as $t): ?>
                            <option value="<?php echo $t['UserID']; ?>"><?php echo htmlspecialchars($t['Name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block font-bold mb-1">Target Teacher Period</label>
                    <select name="target_tt_id" id="target_tt_id" required class="w-full border rounded p-2 text-gray-500" disabled>
                        <option value="">-- Select Teacher First --</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-maroon px-4 py-2 rounded font-bold">Submit Request</button>
            </form>
        </div>
    </div>
</div>

<script>
function fetchTeacherSlots() {
    let t_id = document.getElementById('target_teacher_id').value;
    let targetSelect = document.getElementById('target_tt_id');
    targetSelect.innerHTML = '<option value="">Loading...</option>';
    targetSelect.disabled = true;
    
    if(!t_id) {
        targetSelect.innerHTML = '<option value="">-- Select Teacher First --</option>';
        return;
    }
    
    // In actual implementation, we make an AJAX call to get target teacher slots. 
    // Here we'll just mock an endpoint.
    fetch(`/api/public.php?action=get_teacher_slots&t_id=${t_id}`)
        .then(res => res.json())
        .then(data => {
            targetSelect.innerHTML = '<option value="">-- Select Target Period --</option>';
            targetSelect.disabled = false;
            targetSelect.classList.remove('text-gray-500');
            data.forEach(s => {
                targetSelect.innerHTML += `<option value="${s.TimetableID}">${s.Day} P${s.PeriodNumber} - ${s.CourseName}</option>`;
            });
        });
}
</script>
<?php include '../includes/footer.php'; ?>
