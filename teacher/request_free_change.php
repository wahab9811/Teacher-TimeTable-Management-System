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

// Fetch teacher's timetable
$stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, ts.StartTime, c.Name as CourseName, r.Name as RoomName 
                       FROM timetable t 
                       JOIN time_slots ts ON t.SlotID = ts.SlotID
                       LEFT JOIN courses c ON t.CourseID = c.CourseID
                       LEFT JOIN rooms r ON t.RoomID = r.RoomID
                       WHERE t.TeacherID = ? AND t.IsFree = 0 AND t.Status = 'published' ORDER BY t.ShiftID, FIELD(t.Day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), ts.PeriodNumber");
$stmt->execute([$teacherId]);
$teachingSlots = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentTT = $_POST['current_tt_id'] ?? 0;
    $targetTT = $_POST['target_tt_id'] ?? 0;

    if ($currentTT && $targetTT) {
        // Validate current teaching slot belongs to teacher
        $curr = $pdo->prepare("SELECT * FROM timetable WHERE TimetableID = ? AND TeacherID = ? AND IsFree = 0 AND Status = 'published'");
        $curr->execute([$currentTT, $teacherId]);
        $cData = $curr->fetch();
        
        // Validate target is actually a free slot for the SAME class
        $targ = $pdo->prepare("SELECT * FROM timetable WHERE TimetableID = ? AND IsFree = 1 AND Status = 'published'");
        $targ->execute([$targetTT]);
        $tData = $targ->fetch();

        if (!$cData || !$tData) {
            $error = "Invalid timetable slot selection.";
        } elseif ($cData['ShiftID'] !== $tData['ShiftID'] || $cData['SectionID'] !== $tData['SectionID']) {
            $error = "Free period change requests must be within the same shift and same class/section.";
        } else {
            // Check conflicts logically if we moved the course to the target slot
            $simulateData = [
                'ProgramID' => $cData['ProgramID'],
                'DepartmentID' => $cData['DepartmentID'],
                'SemesterID' => $cData['SemesterID'],
                'SessionID' => $cData['SessionID'],
                'ShiftID' => $cData['ShiftID'],
                'Day' => $tData['Day'],
                'SlotID' => $tData['SlotID'],
                'CourseID' => $cData['CourseID'],
                'TeacherID' => $teacherId,
                'RoomID' => $cData['RoomID'],
                'IsFree' => 0
            ];
            
            $valid = validateTimetableSlot($pdo, $simulateData, $currentTT);
            
            if ($valid === true) {
                $insert = $pdo->prepare("INSERT INTO requests (Type, RequestedBy, TimetableID, SwapTimetableID, Status, CreatedAt) VALUES ('free_change', ?, ?, ?, 'pending_admin', NOW())");
                $insert->execute([$teacherId, $currentTT, $targetTT]);
                $message = "Request submitted successfully. Waiting for admin approval.";
                
                // Notify admin
                $msg = "New Free Period Change Request from Teacher ID $teacherId.";
                $pdo->prepare("INSERT INTO notifications (ScopeType, Message) VALUES ('admin', ?)")->execute([$msg]);
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
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <!-- Sidebar -->
    <?php include '../includes/teacher_sidebar.php'; ?>
    
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-6 border-b pb-2">Free Period Change Request</h2>
        
        <div class="bg-white p-6 shadow-md rounded border border-gray-100">
            <?php if($message): ?><div class="bg-green-100 text-green-700 p-4 rounded mb-6 font-medium shadow-sm border border-green-200"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if($error): ?><div class="bg-red-100 text-red-700 p-4 rounded mb-6 font-medium shadow-sm border border-red-200"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            
            <p class="text-gray-600 mb-6 bg-blue-50 p-3 rounded text-sm border border-blue-100">
                <i class="fas fa-info-circle text-blue-500 mr-2"></i> Select a class you are teaching, and the system will show available free periods for that specific class (section). Moving your class is subject to administrative approval.
            </p>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block font-bold mb-2 text-gray-800">Select Teaching Period to Move</label>
                    <select name="current_tt_id" id="current_tt_id" onchange="loadFreeSlots()" required class="w-full border border-gray-300 rounded p-3 focus:ring-maroon focus:border-maroon transition-colors bg-gray-50 hover:bg-white text-gray-800">
                        <option value="">-- Select --</option>
                        <?php foreach($teachingSlots as $ts): ?>
                            <option value="<?php echo $ts['TimetableID']; ?>" data-sec="<?php echo $ts['SectionID']; ?>">
                                <?php echo $ts['Day'] . ' - Period ' . $ts['PeriodNumber'] . ' (' . ($ts['ShiftID']==1?'Morning':'Evening') . ') - ' . htmlspecialchars($ts['CourseName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block font-bold mb-2 text-gray-800">Select Target Free Period <span class="text-xs text-gray-500 font-normal ml-2">(Auto-loads based on class)</span></label>
                    <select name="target_tt_id" id="target_tt_id" required class="w-full border border-gray-200 bg-gray-100 text-gray-500 rounded p-3 focus:ring-maroon focus:border-maroon transition-colors" disabled>
                        <option value="">-- Select Teaching Period First --</option>
                    </select>
                </div>
                
                <div class="pt-4 border-t">
                    <button type="submit" class="bg-maroon hover:bg-maroon-dark text-white px-6 py-2.5 rounded font-bold transition-colors shadow-md">Submit Request</button>
                    <a href="dashboard.php" class="ml-4 text-gray-600 hover:text-gray-800 font-medium transition-colors">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function loadFreeSlots() {
    const currSelect = document.getElementById('current_tt_id');
    const targSelect = document.getElementById('target_tt_id');
    const selectedOpt = currSelect.options[currSelect.selectedIndex];
    const secId = selectedOpt.getAttribute('data-sec');
    
    if (!secId) {
        targSelect.disabled = true;
        targSelect.classList.add('bg-gray-100', 'text-gray-500', 'border-gray-200');
        targSelect.classList.remove('bg-white', 'text-gray-800', 'border-gray-300');
        targSelect.innerHTML = '<option value="">-- Select Teaching Period First --</option>';
        return;
    }
    
    targSelect.innerHTML = '<option value="">Loading free slots for this class...</option>';
    
    fetch(`/api/public.php?action=get_free_slots_for_section&sec_id=${secId}`)
    .then(r => r.json())
    .then(data => {
        targSelect.disabled = false;
        targSelect.classList.remove('bg-gray-100', 'text-gray-500', 'border-gray-200');
        targSelect.classList.add('bg-white', 'text-gray-800', 'border-gray-300');
        targSelect.innerHTML = '<option value="">-- Select Target Free Period --</option>';
        
        if (data && data.length > 0) {
            data.forEach(s => {
                let t_start = s.StartTime ? s.StartTime.slice(0,5) : '';
                let shift = s.ShiftID == 1 ? 'Morning' : 'Evening';
                targSelect.innerHTML += `<option value="${s.TimetableID}">${s.Day} - Period ${s.PeriodNumber} (${t_start}) (${shift})</option>`;
            });
        } else {
            targSelect.innerHTML = '<option value="">No free periods available for this class</option>';
            targSelect.disabled = true;
            targSelect.classList.add('bg-gray-100', 'text-gray-500', 'border-gray-200');
            targSelect.classList.remove('bg-white', 'text-gray-800', 'border-gray-300');
        }
    })
    .catch(err => {
        targSelect.innerHTML = '<option value="">Error loading slots. Try again.</option>';
    });
}
</script>

<?php include '../includes/footer.php'; ?>
