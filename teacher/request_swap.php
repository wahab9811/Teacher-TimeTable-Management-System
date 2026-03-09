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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $myTT = $_POST['my_tt_id'] ?? 0;
    $targetTT = $_POST['target_tt_id'] ?? 0;

    if ($myTT && $targetTT) {
        $curr = $pdo->prepare("SELECT t.*, c.RoomType FROM timetable t LEFT JOIN courses c ON t.CourseID = c.CourseID WHERE t.TimetableID = ? AND t.TeacherID = ? AND t.Status = 'published'");
        $curr->execute([$myTT, $teacherId]);
        $cData = $curr->fetch();
        
        $targ = $pdo->prepare("SELECT t.*, c.RoomType FROM timetable t LEFT JOIN courses c ON t.CourseID = c.CourseID WHERE t.TimetableID = ? AND t.Status = 'published'");
        $targ->execute([$targetTT]);
        $tData = $targ->fetch();

        if (!$cData || !$tData) {
            $error = "Invalid selection.";
        } else {
            // Check if both teachers belong to the same home department
            $uDeptQ = $pdo->prepare("SELECT DepartmentID FROM users WHERE UserID=?");
            $uDeptQ->execute([$teacherId]);
            $uDept = $uDeptQ->fetchColumn();
            
            $targetUDeptQ = $pdo->prepare("SELECT DepartmentID FROM users WHERE UserID=?");
            $targetUDeptQ->execute([$tData['TeacherID']]);
            $targetUDept = $targetUDeptQ->fetchColumn();

            // They must belong to the exact same Home Department
            if ($uDept === null || $uDept !== $targetUDept) {
                $error = "Swap requests are strictly limited to teachers within the exact same home department.";
            } else {
            $targetTeacher = $tData['TeacherID'];
            
            // Check conflicts for teacher A taking teacher B's slot
            $simA = $tData;
            $simA['TeacherID'] = $teacherId; 
            $simA['SkipRuleFour'] = true;
            
            // Check conflicts for teacher B taking teacher A's slot
            $simB = $cData;
            $simB['TeacherID'] = $targetTeacher;
            $simB['SkipRuleFour'] = true;
            
            // Skip BOTH of the current timetable slots since they are BOTH being cleared and swapped
            $ignoreIDs = [$cData['TimetableID'], $tData['TimetableID']];
            
            // Check ALL conflicts for Teacher A on new slot
            $resA = validateTimetableSlot($pdo, $simA, $ignoreIDs);
            // Check ALL conflicts for Teacher B on new slot
            $resB = validateTimetableSlot($pdo, $simB, $ignoreIDs);
            
            if ($resA !== true) $error = "Conflict for you: $resA";
            elseif ($resB !== true) $error = "Conflict for Target Teacher: $resB";
            else {
                // Deadline 48hrs
                $deadline = date('Y-m-d H:i:s', strtotime('+48 hours'));
                
                $insert = $pdo->prepare("INSERT INTO requests (Type, RequestedBy, TargetTeacherID, TimetableID, SwapTimetableID, Status, TeacherBStatus, Deadline) VALUES ('swap', ?, ?, ?, ?, 'pending_teacher', 'pending', ?)");
                $insert->execute([$teacherId, $targetTeacher, $myTT, $targetTT, $deadline]);
                
                $message = "Swap request sent to target teacher successfully.";
                
                // Notify Teacher B
                $msg = "New Swap Request from " . $_SESSION['user_name'];
                $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$targetTeacher, $msg]);
            }
            }
        }
    } else {
        $error = "Please select both periods.";
    }
}

$currentDay = date('l');
$stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, c.Name as CourseName, sec.Name as SectionName 
                       FROM timetable t 
                       JOIN time_slots ts ON t.SlotID=ts.SlotID 
                       JOIN courses c ON t.CourseID=c.CourseID 
                       LEFT JOIN sections sec ON t.SectionID=sec.SectionID
                       WHERE t.TeacherID=? AND t.IsFree=0 AND t.Day=? AND t.Status='published' ORDER BY ts.PeriodNumber");
$stmt->execute([$teacherId, $currentDay]);
$mySlots = $stmt->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <!-- Sidebar -->
    <?php include '../includes/teacher_sidebar.php'; ?>
    
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-6 border-b pb-2">Swap Request</h2>
        
        <div class="bg-white p-6 shadow-md rounded border border-gray-100">
            <?php if($message): ?><div class="bg-green-100 text-green-700 p-4 rounded mb-6 font-medium shadow-sm border border-green-200"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if($error): ?><div class="bg-red-100 text-red-700 p-4 rounded mb-6 font-medium shadow-sm border border-red-200"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            
            <p class="text-gray-600 mb-6 bg-blue-50 p-3 rounded text-sm border border-blue-100">
                <i class="fas fa-info-circle text-blue-500 mr-2"></i> Select your teaching period. The system will automatically show you other teachers who are teaching the <b>exact same subject/course</b> at different times, protecting the academic structure.
            </p>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block font-bold mb-2 text-gray-800">My Teaching Period</label>
                    <select name="my_tt_id" id="my_tt_id" onchange="fetchTargetSlots()" required class="w-full border border-gray-300 rounded p-3 focus:ring-maroon focus:border-maroon transition-colors bg-gray-50 hover:bg-white text-gray-800">
                        <option value="">-- Select --</option>
                        <?php foreach($mySlots as $s): ?>
                            <option value="<?php echo $s['TimetableID']; ?>" data-sec="<?php echo $s['SectionID']; ?>">
                                <?php echo "{$s['Day']} P{$s['PeriodNumber']} - " . htmlspecialchars($s['CourseName']) . " (Sec: " . htmlspecialchars($s['SectionName'] ?? 'N/A') . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block font-bold mb-2 text-gray-800">Target Teaching Period to Swap With <span class="text-xs text-gray-500 font-normal ml-2">(Auto-loads based on class)</span></label>
                    <select name="target_tt_id" id="target_tt_id" required class="w-full border border-gray-200 bg-gray-100 text-gray-500 rounded p-3 focus:ring-maroon focus:border-maroon transition-colors" disabled>
                        <option value="">-- Select Your Period First --</option>
                    </select>
                </div>
                
                <div class="pt-4 border-t">
                    <button type="submit" class="bg-maroon hover:bg-maroon-dark text-white px-6 py-2.5 rounded font-bold transition-colors shadow-md">Send Swap Offer</button>
                    <a href="dashboard.php" class="ml-4 text-gray-600 hover:text-gray-800 font-medium transition-colors">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function fetchTargetSlots() {
    const mySelect = document.getElementById('my_tt_id');
    const targSelect = document.getElementById('target_tt_id');
    
    if (mySelect.selectedIndex === 0 || !mySelect.value) {
        targSelect.disabled = true;
        targSelect.classList.add('bg-gray-100', 'text-gray-500', 'border-gray-200');
        targSelect.classList.remove('bg-white', 'text-gray-800', 'border-gray-300');
        targSelect.innerHTML = '<option value="">-- Select Your Period First --</option>';
        return;
    }
    
    const selectedOpt = mySelect.options[mySelect.selectedIndex];
    const ttId = mySelect.value;
    
    targSelect.innerHTML = '<option value="">Searching for matching teachers in this section...</option>';
    
    fetch(`../api/public.php?action=get_swap_targets_for_section&tt_id=${ttId}&v=` + Date.now())
        .then(async res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("JSON error. Server said:", text);
                throw e;
            }
        })
        .then(data => {
            targSelect.disabled = false;
            targSelect.classList.remove('bg-gray-100', 'text-gray-500', 'border-gray-200');
            targSelect.classList.add('bg-white', 'text-gray-800', 'border-gray-300');
            targSelect.innerHTML = '<option value="">-- Select Target Period to Swap --</option>';
            
            if (data && data.length > 0) {
                data.forEach(s => {
                    targSelect.innerHTML += `<option value="${s.TimetableID}">${s.Day} P${s.PeriodNumber} - ${s.TeacherName} (${s.CourseName})</option>`;
                });
            } else {
                targSelect.innerHTML = '<option value="">No swap options available for this class</option>';
                targSelect.disabled = true;
                targSelect.classList.add('bg-gray-100', 'text-gray-500', 'border-gray-200');
                targSelect.classList.remove('bg-white', 'text-gray-800', 'border-gray-300');
            }
        })
        .catch(err => {
            console.error(err);
            targSelect.innerHTML = `<option value="">Error: ${err.message}</option>`;
        });
}
</script>
<?php include '../includes/footer.php'; ?>
