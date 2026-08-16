<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/timetable_rules.php';

$message = $error = '';
$previewData = null;

// Helper to get time slots for a shift
$stmt = $pdo->prepare("SELECT * FROM time_slots WHERE ShiftID = ? ORDER BY PeriodNumber");
$stmt->execute([1]); // Just for testing
$periods = [1, 2, 3, 4, 5, 6]; 
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Fetch lists for dropdowns
$programs = $pdo->query("SELECT * FROM programs")->fetchAll();
$depts = $pdo->query("SELECT * FROM departments")->fetchAll();
$sems = $pdo->query("SELECT * FROM semesters")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pID = $_POST['program_id'];
    $dID = $_POST['department_id'];
    $sID = $_POST['semester_id'];
    $shID = $_POST['shift_id'];
    
    // 1. Fetch courses for this class
    $stmtC = $pdo->prepare("SELECT * FROM courses WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=?");
    $stmtC->execute([$pID, $dID, $sID, $shID]);
    $courses = $stmtC->fetchAll();
    
    // Create a pool of course instances based on CreditHours
    $coursePool = [];
    foreach($courses as $c) {
        for($i=0; $i<$c['CreditHours']; $i++) {
            $coursePool[] = $c;
        }
    }
    
    // Total slots needed vs available
    $totalSlots = count($days) * count($periods); // 36 slots
    if(count($coursePool) > $totalSlots) {
        $error = "Too many credit hours (" . count($coursePool) . ") for available slots (36).";
    } elseif(empty($courses)) {
        $error = "No courses found for this criteria.";
    } else {
        // Auto generation logic (greedy + backtracking simplified)
        // Note: Real algorithm requires deep backtracking. Here we use a greedy approach for Demonstration.
        $generated = [];
        $unassigned = [];
        
        // Fetch all slots to map PeriodNumber to SlotID
        $slotQuery = $pdo->prepare("SELECT SlotID, PeriodNumber FROM time_slots WHERE ShiftID = ?");
        $slotQuery->execute([$shID]);
        $slotMap = [];
        while($r = $slotQuery->fetch()) $slotMap[$r['PeriodNumber']] = $r['SlotID'];
        
        // Prepare grid
        foreach($days as $day) {
            foreach($periods as $per) {
                $generated[$day][$per] = null; // empty
            }
        }
        
        // Shuffle courses for randomness
        shuffle($coursePool);
        
        // Attempt assignment
        foreach($coursePool as $course) {
            $assigned = false;
            foreach($days as $day) {
                // Rule: Course cannot appear twice on same day (Rule 8)
                $courseAlreadyOnDay = false;
                foreach($periods as $per) {
                    if($generated[$day][$per] && $generated[$day][$per]['CourseID'] == $course['CourseID']) {
                        $courseAlreadyOnDay = true; break;
                    }
                }
                
                if($courseAlreadyOnDay) continue; 
                
                foreach($periods as $per) {
                    if($generated[$day][$per] === null) {
                        // Test all rules
                        $testData = [
                            'ProgramID' => $pID,
                            'DepartmentID' => $dID,
                            'SemesterID' => $sID,
                            'ShiftID' => $shID,
                            'Day' => $day,
                            'SlotID' => $slotMap[$per],
                            'CourseID' => $course['CourseID'],
                            'TeacherID' => $course['TeacherID'],
                            'RoomID' => null, // Room mapping is complex, assume unassigned/default for auto generator 
                            'IsFree' => 0
                        ];
                        
                        $valid = validateTimetableSlot($pdo, $testData);
                        if($valid === true) {
                            $generated[$day][$per] = cloneData($testData, $course);
                            $assigned = true;
                            // Temporarily insert to DB to allow subsequent simulate queries to detect conflicts
                            // In a real robust system, we would simulate entirely in memory.
                            $stmtTemp = $pdo->prepare("INSERT INTO timetable (ProgramID, DepartmentID, SemesterID, ShiftID, Day, SlotID, CourseID, TeacherID, IsFree) VALUES (?,?,?,?,?,?,?,?,0)");
                            $stmtTemp->execute([$pID, $dID, $sID, $shID, $day, $slotMap[$per], $course['CourseID'], $course['TeacherID']]);
                            $generated[$day][$per]['TimetableID'] = $pdo->lastInsertId();
                            break 2;
                        }
                    }
                }
            }
            if(!$assigned) $unassigned[] = $course['Name'];
        }
        
        if (count($unassigned) > 0) {
            $error = "Could not assign all courses seamlessly without conflicts. Failed to assign: " . implode(', ', $unassigned);
            // Rollback generated temp inserts
            foreach($generated as $day => $pers) {
                 foreach($pers as $p => $d) {
                     if($d) {
                         $pdo->exec("DELETE FROM timetable WHERE TimetableID = " . $d['TimetableID']);
                     }
                 }
            }
        } else {
            // Fill remaining with Free Periods
            foreach($days as $day) {
                foreach($periods as $per) {
                    if($generated[$day][$per] === null) {
                        $pdo->prepare("INSERT INTO timetable (ProgramID, DepartmentID, SemesterID, ShiftID, Day, SlotID, IsFree) VALUES (?,?,?,?,?,?,1)")
                            ->execute([$pID, $dID, $sID, $shID, $day, $slotMap[$per]]);
                        $generated[$day][$per] = ['CourseName' => 'Free', 'IsFree' => 1];
                    }
                }
            }
            $message = "Auto Timetable Generated Successfully!";
            $previewData = $generated;
        }
    }
}

function cloneData($testData, $course) {
    $testData['CourseName'] = $course['Name'];
    $testData['TeacherID'] = $course['TeacherID'];
    return $testData;
}
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <!-- Sidebar -->
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Auto Generate Timetable</h2>
        
        <div class="bg-white p-6 shadow-md rounded mb-6">
            <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?php echo $error; ?></div><?php endif; ?>
            
            <form method="POST">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div>
                        <label class="block font-bold mb-1 text-sm">Program</label>
                        <select name="program_id" class="w-full border rounded p-2" required>
                            <option value="">--</option>
                            <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-sm">Dept/Group</label>
                        <select name="department_id" class="w-full border rounded p-2" required>
                            <option value="">--</option>
                            <?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}'>{$d['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-sm">Semester</label>
                        <select name="semester_id" class="w-full border rounded p-2" required>
                            <option value="">--</option>
                            <?php foreach($sems as $s): echo "<option value='{$s['SemesterID']}'>{$s['Label']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-sm">Shift</label>
                        <select name="shift_id" class="w-full border rounded p-2" required>
                            <option value="">--</option>
                            <option value="1">Morning</option>
                            <option value="2">Evening</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn-maroon px-6 py-2 rounded font-bold">Generate Timetable</button>
            </form>
        </div>
        
        <?php if($previewData): ?>
        <div class="bg-white p-6 shadow-md rounded">
            <h3 class="text-xl font-bold mb-4 border-b pb-2">Generated Timetable Preview</h3>
            <table class="w-full border-collapse border border-gray-300 text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border p-2">Period</th>
                        <?php foreach($days as $day): ?><th class="border p-2"><?php echo $day; ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($periods as $per): ?>
                    <tr>
                        <td class="border p-2 font-bold text-center"><?php echo $per; ?></td>
                        <?php foreach($days as $day): 
                            $pd = $previewData[$day][$per];
                        ?>
                        <td class="border p-2 text-center <?php echo ($pd['IsFree'] ?? 0) ? 'bg-gray-50 text-gray-400 italic' : ''; ?>">
                            <?php echo $pd['CourseName'] ?? '-'; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

