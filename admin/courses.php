<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$message='';
$isError = false;
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['add'])) {
        $secid = empty($_POST['secid']) ? null : $_POST['secid'];
        $shift = $_POST['shift'];
        
        $valid = true;
        if($secid) {
            $s = $pdo->prepare("SELECT ShiftID FROM sections WHERE SectionID=?");
            $s->execute([$secid]);
            if($s->fetchColumn() != $shift) {
                $valid = false;
                $message="Cannot map course! The chosen Shift MUST strictly match the assigned Section's Shift.";
            }
        }
        
        $cr = (int)$_POST['cr'];
        if ($cr < 1 || $cr > 6) {
            $valid = false;
            $message = "Cannot map course! Credit Hours / Weekly Periods must be between 1 and 6.";
        }
        
        if($valid) {
            $check = $pdo->prepare("SELECT CourseID FROM courses WHERE Name=? AND ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SectionID <=> ?");
            $check->execute([$_POST['name'], $_POST['pid'], $_POST['did'], $_POST['sid'], $shift, $secid]);
            if($check->fetchColumn()) {
                $valid = false;
                $message = "Duplicate Course! This course is already mapped for the exact same Program, Dept, Sem, Shift, and Section.";
            }
        }
        
        if($valid) {
            $pdo->prepare("INSERT INTO courses (Name, ProgramID, DepartmentID, SemesterID, ShiftID, SectionID, TeacherID, CreditHours, RoomType) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$_POST['name'], $_POST['pid'], $_POST['did'], $_POST['sid'], $shift, $secid, $_POST['tid'], $_POST['cr'], $_POST['type']]);
            $message="Course mapped successfully.";
        } else {
            $isError = true;
        }
    } elseif(isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM courses WHERE CourseID=?")->execute([$_POST['id']]); 
    } elseif(isset($_POST['clone'])) {
        $sourceId = $_POST['id'];
        $newShift = $_POST['shift'];
        $newTeacher = $_POST['tid'];
        $newSecid = empty($_POST['secid']) ? null : $_POST['secid'];
        
        $sc = $pdo->prepare("SELECT * FROM courses WHERE CourseID=?");
        $sc->execute([$sourceId]);
        $src = $sc->fetch();
        
        if($src) {
            $valid = true;
            if($newSecid) {
                $s = $pdo->prepare("SELECT ShiftID FROM sections WHERE SectionID=?");
                $s->execute([$newSecid]);
                if($s->fetchColumn() != $newShift) {
                    $valid = false;
                    $message="Cannot clone! The chosen Shift MUST strictly match the assigned Section's Shift.";
                    $isError = true;
                }
            }
            if($valid) {
                $check = $pdo->prepare("SELECT CourseID FROM courses WHERE Name=? AND ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SectionID <=> ?");
                $check->execute([$src['Name'], $src['ProgramID'], $src['DepartmentID'], $src['SemesterID'], $newShift, $newSecid]);
                if($check->fetchColumn()) {
                    $valid = false;
                    $message = "Duplicate Course! This course has already been cloned or exists in the target shift and section.";
                    $isError = true;
                }
            }
            if($valid) {
                $pdo->prepare("INSERT INTO courses (Name, ProgramID, DepartmentID, SemesterID, ShiftID, SectionID, TeacherID, CreditHours, RoomType) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$src['Name'], $src['ProgramID'], $src['DepartmentID'], $src['SemesterID'], $newShift, $newSecid, $newTeacher, $src['CreditHours'], $src['RoomType']]);
                $message="Course cloned successfully for the selected new shift.";
            }
        }
    } elseif(isset($_POST['edit'])) {
        $secid = empty($_POST['secid']) ? null : $_POST['secid'];
        $shift = $_POST['shift'];
        
        $valid = true;
        if($secid) {
            $s = $pdo->prepare("SELECT ShiftID FROM sections WHERE SectionID=?");
            $s->execute([$secid]);
            if($s->fetchColumn() != $shift) {
                $valid = false;
                $message="Cannot update course! The chosen Shift MUST strictly match the assigned Section's Shift.";
            }
        }
        
        $cr = (int)$_POST['cr'];
        if ($cr < 1 || $cr > 6) {
            $valid = false;
            $message = "Cannot update course! Credit Hours / Weekly Periods must be between 1 and 6.";
        }
        
        if($valid) {
            $check = $pdo->prepare("SELECT CourseID FROM courses WHERE Name=? AND ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SectionID <=> ? AND CourseID != ?");
            $check->execute([$_POST['name'], $_POST['pid'], $_POST['did'], $_POST['sid'], $shift, $secid, $_POST['id']]);
            if($check->fetchColumn()) {
                $valid = false;
                $message = "Duplicate Course! This modification results in a duplicate course.";
                $isError = true;
            }
        }
        
        if($valid) {
            $pdo->prepare("UPDATE courses SET Name=?, ProgramID=?, DepartmentID=?, SemesterID=?, ShiftID=?, SectionID=?, TeacherID=?, CreditHours=?, RoomType=? WHERE CourseID=?")
                ->execute([$_POST['name'], $_POST['pid'], $_POST['did'], $_POST['sid'], $shift, $secid, $_POST['tid'], $_POST['cr'], $_POST['type'], $_POST['id']]);
            $message="Course updated successfully.";
        } else {
            $isError = true;
        }
    }
}
$courses = $pdo->query("
    SELECT c.*, p.Name as P, d.Name as D, s.Label as S, sh.Name as SH, sec.Name as SEC, u.Name as T 
    FROM courses c 
    JOIN programs p ON c.ProgramID=p.ProgramID 
    JOIN departments d ON c.DepartmentID=d.DepartmentID 
    JOIN semesters s ON c.SemesterID=s.SemesterID 
    JOIN shifts sh ON c.ShiftID=sh.ShiftID 
    LEFT JOIN sections sec ON c.SectionID=sec.SectionID 
    JOIN users u ON c.TeacherID=u.UserID
    ORDER BY p.ProgramID, d.DepartmentID, s.SemesterID, c.Name
")->fetchAll();

// Grouping courses by Program + Dept
$grouped = [];
foreach($courses as $c) {
    $key = $c['P'] . ' - ' . $c['D'];
    if(!isset($grouped[$key])) $grouped[$key] = [];
    $grouped[$key][] = $c;
}

$programs = $pdo->query("SELECT * FROM programs WHERE IsActive = 1")->fetchAll();
$depts = $pdo->query("SELECT * FROM departments WHERE IsActive = 1")->fetchAll();
$sems = $pdo->query("SELECT * FROM semesters WHERE IsActive = 1")->fetchAll();
$teachers = $pdo->query("SELECT UserID, Name FROM users WHERE Role='teacher' AND AccountStatus='Active'")->fetchAll();
$shifts = $pdo->query("SELECT * FROM shifts")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-maroon">Manage Courses / Subjects</h2>
        </div>
        
        <?php if($message): ?>
            <div class="<?php echo $isError ? 'bg-red-100 text-red-800 border-red-200' : 'bg-green-100 text-green-800 border-green-200'; ?> p-3 rounded mb-4 font-semibold text-sm border">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Add Course Form -->
        <div class="bg-gray-50 border border-gray-200 p-5 rounded-xl mb-6 shadow-sm">
            <h3 class="font-black mb-4 text-[#111827] uppercase tracking-wide border-b border-gray-200 pb-2">Map a New Course</h3>
            <form method="POST" class="flex flex-col gap-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="lg:col-span-1">
                        <label class="block text-xs font-bold text-gray-600 mb-1">Course Name</label>
                        <input type="text" name="name" placeholder="e.g. Intro to Computer Science" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Program</label>
                        <select name="pid" id="add_pid" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                            <option value="">Select...</option>
                            <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Department / Group</label>
                        <select name="did" id="add_did" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                            <option value="">Select...</option>
                            <?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}' data-prog='{$d['ProgramID']}'>{$d['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Semester / Year</label>
                        <select name="sid" id="add_sid" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                            <option value="">Select...</option>
                            <?php foreach($sems as $s): echo "<option value='{$s['SemesterID']}' data-prog='{$s['ProgramID']}'>{$s['Label']}</option>"; endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-600 mb-1">Shift</label>
                        <select name="shift" id="add_shift" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                            <option value="">Select...</option>
                            <?php foreach($shifts as $sh): echo "<option value='{$sh['ShiftID']}'>{$sh['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-gray-600 mb-1">Section</label>
                        <select name="secid" id="add_secid" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                            <option value="">General (No Section)</option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-gray-600 mb-1">Assigned Teacher</label>
                        <select name="tid" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                            <option value="">Select Teacher...</option>
                            <?php foreach($teachers as $t): echo "<option value='{$t['UserID']}'>{$t['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-600 mb-1" title="For BS: Credit Hours. For Inter: Weekly Periods. Max 6 allowed per course based on shift capabilities.">Lectures (Cr.Hrs/Periods)</label>
                        <input type="number" name="cr" value="3" required min="1" max="6" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-600 mb-1">Room Type</label>
                        <select name="type" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                            <option value="Classroom">Classroom</option>
                            <option value="Lab">Lab (Practical)</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end mt-2">
                    <button name="add" class="bg-[#a60b26] text-white px-8 py-2.5 rounded-lg font-bold hover:bg-[#8a0a20] transition shadow-sm">Save Course</button>
                </div>
            </form>
        </div>
        
        <!-- Courses List -->
        <div class="flex flex-col gap-6">
            <?php if(empty($grouped)): ?>
                <div class="bg-white p-6 rounded-xl border border-gray-200 text-center text-gray-500">No courses have been mapped yet.</div>
            <?php else: foreach($grouped as $groupName => $crs): ?>
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm mb-4">
                <div class="bg-gray-100 p-3 border-b border-gray-200 flex justify-between items-center cursor-pointer hover:bg-gray-200 transition" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('.arrow-icon').classList.toggle('rotate-180');">
                    <h3 class="font-black text-gray-800 tracking-wide flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="arrow-icon h-5 w-5 text-gray-500 transition-transform duration-200 transform rotate-180" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                        <?php echo htmlspecialchars($groupName); ?>
                    </h3>
                    <span class="text-xs font-bold text-gray-500 bg-white border px-2 py-1 rounded-full"><?php echo count($crs); ?> Courses</span>
                </div>
                
                <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th class="p-3 font-semibold">Course Name</th>
                                <th class="p-3 font-semibold">Semester/Year</th>
                                <th class="p-3 font-semibold text-center">Section</th>
                                <th class="p-3 font-semibold text-center">Shift</th>
                                <th class="p-3 font-semibold">Teacher</th>
                                <th class="p-3 font-semibold text-center">Config</th>
                                <th class="p-3 font-semibold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($crs as $c): ?>
                            <tr class="border-b last:border-0 hover:bg-red-50/30 transition group">
                                <td class="p-3 font-bold text-gray-800"><?php echo htmlspecialchars($c['Name']); ?></td>
                                <td class="p-3 text-gray-700 font-semibold"><?php echo htmlspecialchars($c['S']); ?></td>
                                <td class="p-3 text-center">
                                    <?php if($c['SEC']): ?>
                                        <span class="text-[#a60b26] bg-[#a60b26]/10 px-2 py-0.5 rounded-full font-bold text-xs">Sec <?php echo htmlspecialchars($c['SEC']); ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs italic">General</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-center text-xs font-bold text-gray-600">
                                    <?php echo htmlspecialchars($c['SH']); ?>
                                </td>
                                <td class="p-3 text-gray-700 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg>
                                    <?php echo htmlspecialchars($c['T']); ?>
                                </td>
                                <td class="p-3 text-center text-xs font-semibold text-gray-600">
                                    <?php echo $c['CreditHours']; ?> CR &bull; <?php echo $c['RoomType']; ?>
                                </td>
                                <td class="p-3 text-right">
                                    <div class="flex items-center justify-end gap-2 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button type="button" onclick='openCloneModal(<?php echo json_encode([
                                            "id" => $c["CourseID"], "name" => $c["Name"], "pid" => $c["ProgramID"], 
                                            "did" => $c["DepartmentID"], "sid" => $c["SemesterID"]
                                        ]); ?>)' class="text-green-600 hover:text-green-800 bg-green-50 hover:bg-green-100 p-1.5 rounded-md transition" title="Clone Course to Another Shift">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                               <path d="M7 9a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9z" />
                                               <path d="M5 3a2 2 0 00-2 2v6a2 2 0 002 2V5h8a2 2 0 00-2-2H5z" />
                                            </svg>
                                        </button>
                                        <button type="button" onclick='openEditModal(<?php echo json_encode([
                                            "id" => $c["CourseID"], "name" => $c["Name"], "pid" => $c["ProgramID"], 
                                            "did" => $c["DepartmentID"], "sid" => $c["SemesterID"], "shift" => $c["ShiftID"], 
                                            "secid" => $c["SectionID"], "tid" => $c["TeacherID"], "cr" => $c["CreditHours"], "type" => $c["RoomType"]
                                        ]); ?>)' class="text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 p-1.5 rounded-md transition" title="Edit Course">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Delete this course permanently? This will remove all timetable mappings for this specific course!');" class="m-0 inline">
                                            <input type="hidden" name="id" value="<?php echo $c['CourseID']; ?>">
                                            <button name="delete" type="submit" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-1.5 rounded-md transition" title="Delete Course">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Clone Modal -->
<div id="cloneModal" class="fixed inset-0 bg-black/50 justify-center items-center z-[100]" style="display: none;">
    <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-[400px] border border-gray-100">
        <h3 class="font-bold text-lg mb-4 text-[#111827] flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-green-600" viewBox="0 0 20 20" fill="currentColor"><path d="M7 9a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9z" /><path d="M5 3a2 2 0 00-2 2v6a2 2 0 002 2V5h8a2 2 0 00-2-2H5z" /></svg>
            Clone Course
        </h3>
        <p class="text-sm text-gray-500 mb-4 bg-gray-50 p-2 border rounded">Cloning: <span id="clone_name_display" class="font-bold text-gray-800"></span></p>
        <form method="POST" class="flex flex-col gap-4 shadow-sm">
            <input type="hidden" name="id" id="clone_id">
            <input type="hidden" id="clone_pid">
            <input type="hidden" id="clone_did">
            <input type="hidden" id="clone_sid">
            
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Select New Shift</label>
                <select name="shift" id="clone_shift" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-green-500 focus:outline-none bg-gray-50 hover:bg-white transition-colors">
                    <option value="">-Select-</option>
                    <?php foreach($shifts as $sh): echo "<option value='{$sh['ShiftID']}'>{$sh['Name']}</option>"; endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Target Section (Optional)</label>
                <select name="secid" id="clone_secid" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-green-500 focus:outline-none bg-gray-50 hover:bg-white transition-colors">
                    <option value="">General Class (No Sec)</option>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Select Assigned Teacher</label>
                <select name="tid" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-green-500 focus:outline-none bg-gray-50 hover:bg-white transition-colors">
                    <option value="">-Select Teacher-</option>
                    <?php foreach($teachers as $t): echo "<option value='{$t['UserID']}'>{$t['Name']}</option>"; endforeach; ?>
                </select>
            </div>

            <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('cloneModal').style.display='none'" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold px-5 py-2.5 rounded-lg transition-colors">Cancel</button>
                <button type="submit" name="clone" class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2.5 rounded-lg transition-colors shadow-[0_4px_14px_0_rgba(22,163,74,0.39)]">Clone Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 flex justify-center items-center z-[100]" style="display: none;">
    <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-[600px] border border-gray-100 max-h-[90vh] overflow-y-auto">
        <h3 class="font-bold text-lg mb-4 text-[#111827]">Edit Course</h3>
        <form method="POST" class="flex flex-col gap-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="lg:col-span-1">
                    <label class="block text-xs font-bold text-gray-600 mb-1">Course Name</label>
                    <input type="text" name="name" id="edit_name" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Program</label>
                    <select name="pid" id="edit_pid" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                        <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Dept</label>
                    <select name="did" id="edit_did" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                        <?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}' data-prog='{$d['ProgramID']}'>{$d['Name']}</option>"; endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Semester</label>
                    <select name="sid" id="edit_sid" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                        <?php foreach($sems as $s): echo "<option value='{$s['SemesterID']}' data-prog='{$s['ProgramID']}'>{$s['Label']}</option>"; endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Shift</label>
                    <select name="shift" id="edit_shift" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                        <option value="">Select...</option>
                        <?php foreach($shifts as $sh): echo "<option value='{$sh['ShiftID']}'>{$sh['Name']}</option>"; endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-1">
                    <label class="block text-xs font-bold text-gray-600 mb-1">Section</label>
                    <select name="secid" id="edit_secid" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                        <option value="">General</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-gray-600 mb-1">Teacher</label>
                    <select name="tid" id="edit_tid" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                        <?php foreach($teachers as $t): echo "<option value='{$t['UserID']}'>{$t['Name']}</option>"; endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1" title="For BS: Credit Hours. For Inter: Weekly Periods. Max 6 allowed per course based on shift capabilities.">Lectures (Cr.Hrs/Periods)</label>
                    <input type="number" name="cr" id="edit_cr" required min="1" max="6" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Room Type</label>
                    <select name="type" id="edit_type" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                        <option value="Classroom">Classroom</option>
                        <option value="Lab">Lab (Practical)</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold px-5 py-2.5 rounded-lg transition-colors">Cancel</button>
                <button type="submit" name="edit" class="bg-[#a60b26] hover:bg-[#8a0a20] text-white font-bold px-5 py-2.5 rounded-lg transition-colors">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Filter Depts and Semesters based on Program (for both Add and Edit forms)
function filterByProgram(pid, deptSelectId, semSelectId) {
    [deptSelectId, semSelectId].forEach(id => {
        let select = document.getElementById(id);
        for(let i=1; i<select.options.length; i++) {
            let optProg = select.options[i].getAttribute('data-prog');
            select.options[i].style.display = (pid === "" || optProg === pid) ? 'block' : 'none';
        }
        select.value = "";
    });
}

// Ensure Sections API call works for Add Form
function loadSectionsAdd() {
    let pid = document.getElementById('add_pid').value;
    let did = document.getElementById('add_did').value;
    let sid = document.getElementById('add_sid').value;
    let sh = document.getElementById('add_shift').value;
    let secSelect = document.getElementById('add_secid');
    
    secSelect.innerHTML = '<option value="">General (No Section)</option>';
    if(pid && did && sid && sh) {
        fetch(`../api/public.php?action=get_sections&program_id=${pid}&dept_id=${did}&semester_id=${sid}&shift_id=${sh}`)
        .then(r => r.json())
        .then(data => {
            data.forEach(sec => {
                let opt = document.createElement('option');
                opt.value = sec.SectionID; opt.textContent = 'Section ' + sec.Name;
                secSelect.appendChild(opt);
            });
        });
    }
}

document.getElementById('add_pid').addEventListener('change', function() {
    filterByProgram(this.value, 'add_did', 'add_sid');
    loadSectionsAdd();
});
document.getElementById('add_did').addEventListener('change', loadSectionsAdd);
document.getElementById('add_sid').addEventListener('change', loadSectionsAdd);
document.getElementById('add_shift').addEventListener('change', loadSectionsAdd);

// Ensure Sections API call works for Edit Form
function loadSectionsEdit(preselectId = null) {
    let pid = document.getElementById('edit_pid').value;
    let did = document.getElementById('edit_did').value;
    let sid = document.getElementById('edit_sid').value;
    let sh = document.getElementById('edit_shift').value;
    let secSelect = document.getElementById('edit_secid');
    
    secSelect.innerHTML = '<option value="">General (No Section)</option>';
    if(pid && did && sid && sh) {
        fetch(`../api/public.php?action=get_sections&program_id=${pid}&dept_id=${did}&semester_id=${sid}&shift_id=${sh}`)
        .then(r => r.json())
        .then(data => {
            data.forEach(sec => {
                let opt = document.createElement('option');
                opt.value = sec.SectionID; opt.textContent = 'Section ' + sec.Name;
                secSelect.appendChild(opt);
            });
            if(preselectId) {
                secSelect.value = preselectId;
            }
        });
    }
}

document.getElementById('edit_pid').addEventListener('change', function() {
    filterByProgram(this.value, 'edit_did', 'edit_sid');
    loadSectionsEdit();
});
document.getElementById('edit_did').addEventListener('change', () => loadSectionsEdit());
document.getElementById('edit_sid').addEventListener('change', () => loadSectionsEdit());
document.getElementById('edit_shift').addEventListener('change', () => loadSectionsEdit());

function openEditModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_pid').value = data.pid;
    
    // Ensure visibility of options when editing
    filterByProgram(data.pid, 'edit_did', 'edit_sid');
    
    document.getElementById('edit_did').value = data.did;
    document.getElementById('edit_sid').value = data.sid;
    document.getElementById('edit_shift').value = data.shift;
    document.getElementById('edit_tid').value = data.tid;
    document.getElementById('edit_cr').value = data.cr;
    document.getElementById('edit_type').value = data.type;
    
    // Load sections and wait to preselect
    loadSectionsEdit(data.secid);
    document.getElementById('editModal').style.display = 'flex';
}
function openCloneModal(data) {
    document.getElementById('clone_id').value = data.id;
    document.getElementById('clone_pid').value = data.pid;
    document.getElementById('clone_did').value = data.did;
    document.getElementById('clone_sid').value = data.sid;
    document.getElementById('clone_name_display').textContent = data.name;
    
    let secSelect = document.getElementById('clone_secid');
    secSelect.innerHTML = '<option value="">General Class (No Sec)</option>';
    document.getElementById('clone_shift').value = '';

    document.getElementById('cloneModal').style.display = 'flex';
}

function loadSectionsClone() {
    let pid = document.getElementById('clone_pid').value;
    let did = document.getElementById('clone_did').value;
    let sid = document.getElementById('clone_sid').value;
    let sh = document.getElementById('clone_shift').value;
    let secSelect = document.getElementById('clone_secid');
    
    secSelect.innerHTML = '<option value="">General Class (No Sec)</option>';
    if(pid && did && sid && sh) {
        fetch(`../api/public.php?action=get_sections&program_id=${pid}&dept_id=${did}&semester_id=${sid}&shift_id=${sh}`)
        .then(r => r.json())
        .then(data => {
            data.forEach(sec => {
                let opt = document.createElement('option');
                opt.value = sec.SectionID; opt.textContent = 'Section ' + sec.Name;
                secSelect.appendChild(opt);
            });
        });
    }
}
document.getElementById('clone_shift').addEventListener('change', loadSectionsClone);

</script>


