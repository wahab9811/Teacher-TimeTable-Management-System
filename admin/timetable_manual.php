<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/timetable_rules.php';

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_timetable'])) {
    $sessID = $_POST['session_id'];
    $pID = $_POST['program_id'];
    $dID = $_POST['department_id'];
    $sID = $_POST['semester_id'];
    $shID = $_POST['shift_id'];
    $secID = $_POST['section_id'] !== '' ? $_POST['section_id'] : null;
    $day = $_POST['day'];
    
    $slots = $_POST['slots'] ?? [];
    
    $pdo->beginTransaction();
    try {
        foreach($slots as $period => $d) {
            $isFree = isset($d['is_free']) ? 1 : 0;
            $courseId = $isFree ? null : ($d['course_id'] ?: null);
            $teacherId = $isFree ? null : ($d['teacher_id'] ?: null);
            $roomId = $isFree ? null : ($d['room_id'] ?: null);
            
            $slotQuery = $pdo->prepare("SELECT SlotID FROM time_slots WHERE ShiftID = ? AND PeriodNumber = ?");
            $slotQuery->execute([$shID, $period]);
            $slotId = $slotQuery->fetchColumn();
            
            if(!$slotId) continue;
            
            if(!$isFree && (!$courseId || !$teacherId || !$roomId)) {
                throw new Exception("Period $period requires Course, Teacher, and Room unless marked Free.");
            }
    
            $testData = [
                'ProgramID' => $pID,
                'DepartmentID' => $dID,
                'SemesterID' => $sID,
                'ShiftID' => $shID,
                'SessionID' => $sessID,
                'SectionID' => $secID,
                'Day' => $day,
                'SlotID' => $slotId,
                'CourseID' => $courseId,
                'TeacherID' => $teacherId,
                'RoomID' => $roomId,
                'IsFree' => $isFree
            ];
            
            if ($secID) {
                $exist = $pdo->prepare("SELECT TimetableID FROM timetable WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SessionID=? AND SectionID=? AND Day=? AND SlotID=?");
                $exist->execute([$pID, $dID, $sID, $shID, $sessID, $secID, $day, $slotId]);
            } else {
                $exist = $pdo->prepare("SELECT TimetableID FROM timetable WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SessionID=? AND SectionID IS NULL AND Day=? AND SlotID=?");
                $exist->execute([$pID, $dID, $sID, $shID, $sessID, $day, $slotId]);
            }
            $existingID = $exist->fetchColumn();
            
            $valid = validateTimetableSlot($pdo, $testData, $existingID);
            if ($valid !== true) {
                throw new Exception("Period $period Conflict: $valid");
            }
            
            // Insert/Update immediately so subsequent loops see this DB state natively
            if ($existingID) {
                $upd = $pdo->prepare("UPDATE timetable SET CourseID=?, TeacherID=?, RoomID=?, IsFree=? WHERE TimetableID=?");
                $upd->execute([$testData['CourseID'], $testData['TeacherID'], $testData['RoomID'], $testData['IsFree'], $existingID]);
            } else {
                $ins = $pdo->prepare("INSERT INTO timetable (ProgramID, DepartmentID, SemesterID, ShiftID, SessionID, SectionID, Day, SlotID, CourseID, TeacherID, RoomID, IsFree) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$testData['ProgramID'], $testData['DepartmentID'], $testData['SemesterID'], $testData['ShiftID'], $testData['SessionID'], $testData['SectionID'], $testData['Day'], $testData['SlotID'], $testData['CourseID'], $testData['TeacherID'], $testData['RoomID'], $testData['IsFree']]);
            }
            
            if (!$testData['IsFree'] && $testData['TeacherID']) {
                $msg = "Your timetable was assigned/updated for $day Period $period.";
                $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$testData['TeacherID'], $msg]);
            }
        }
        $pdo->commit();
        $message = "Timetable saved successfully.";
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$programs = $pdo->query("SELECT * FROM programs WHERE IsActive = 1")->fetchAll();
$depts = $pdo->query("SELECT * FROM departments WHERE IsActive = 1")->fetchAll();
$sems = $pdo->query("SELECT * FROM semesters WHERE IsActive = 1")->fetchAll();
$sessionsList = $pdo->query("SELECT * FROM academic_sessions ORDER BY IsActive DESC, SessionID DESC")->fetchAll();
$teachers = $pdo->query("SELECT UserID, Name FROM users WHERE Role='teacher'")->fetchAll();
$rooms = $pdo->query("SELECT RoomID, Name, Type FROM rooms WHERE IsActive = 1")->fetchAll();

$max_periods = $pdo->query("SELECT MAX(PeriodNumber) FROM time_slots")->fetchColumn() ?: 6;
$shift_periods = $pdo->query("SELECT ShiftID, MAX(PeriodNumber) as MaxPeriod FROM time_slots GROUP BY ShiftID")->fetchAll(PDO::FETCH_KEY_PAIR);

?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="flex-1 min-w-0">
        <h2 class="text-2xl font-bold text-maroon mb-4">Manual Timetable Entry</h2>
        
        <div class="bg-white p-6 shadow-md rounded">
            <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4 font-bold border"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4 font-bold border"><?php echo $error; ?></div><?php endif; ?>
            
            <form method="POST" id="tt_form">
                <div class="grid grid-cols-2 md:grid-cols-7 gap-3 mb-6 border-b pb-4">
                    <div>
                        <label class="block font-bold mb-1 text-sm text-gray-700">Session</label>
                        <select name="session_id" id="sessid" class="w-full border rounded p-1.5 focus:ring-1" onchange="fetchCourses()" required>
                            <?php foreach($sessionsList as $sl): echo "<option value='{$sl['SessionID']}' ".($sl['IsActive'] ? 'selected':'').">{$sl['Title']}".($sl['IsActive'] ? ' (Active)':'')."</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-sm">Program</label>
                        <select name="program_id" id="pid" class="w-full border rounded p-1.5 focus:ring-1" required>
                            <option value="">-Select-</option>
                            <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-sm">Dept/Group</label>
                        <select name="department_id" id="did" class="w-full border rounded p-1.5 focus:ring-1" required>
                            <option value="">-Select-</option>
                            <?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}'>{$d['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-sm">Semester/Year</label>
                        <select name="semester_id" id="sid" class="w-full border rounded p-1.5 focus:ring-1" required>
                            <option value="">-Select-</option>
                            <?php foreach($sems as $s): echo "<option value='{$s['SemesterID']}'>{$s['Label']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-sm">Shift</label>
                        <select name="shift_id" id="shid" class="w-full border rounded p-1.5 focus:ring-1" onchange="fetchCourses()" required>
                            <option value="">-Select-</option>
                            <option value="1">Morning</option>
                            <option value="2">Evening</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-sm text-gray-700">Section</label>
                        <select name="section_id" id="secid" class="w-full border rounded p-1.5 focus:ring-1" onchange="fetchCourses()">
                            <option value="">No Section / General</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-sm">Day</label>
                        <select name="day" id="dayid" class="w-full border rounded p-1.5 focus:ring-1" onchange="fetchCourses()" required>
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </select>
                    </div>
                </div>
                
                    <div id="grid_area" class="hidden">
                        <h3 class="font-bold mb-2">Slot Assignments</h3>
                        <div class="flex flex-col space-y-2">
                            <?php for($i=1; $i<=$max_periods; $i++): ?>
                            <div id="slot_row_<?php echo $i; ?>" class="flex items-center space-x-2 border p-2 bg-gray-50 rounded shadow-sm hover:shadow transition-shadow">
                                <div class="w-16 text-center font-bold text-gray-700">P <?php echo $i; ?></div>
                                <select name="slots[<?php echo $i; ?>][course_id]" class="flex-1 border border-gray-300 rounded p-1.5 course_select" onchange="autoSelectTeacher(this)">
                                    <option value="">Select Course</option>
                                </select>
                                <select name="slots[<?php echo $i; ?>][teacher_id]" class="flex-1 border border-gray-300 rounded p-1.5 teacher_select">
                                    <option value="">Select Teacher</option>
                                    <?php foreach($teachers as $t): echo "<option value='{$t['UserID']}'>{$t['Name']}</option>"; endforeach; ?>
                                </select>
                                <select name="slots[<?php echo $i; ?>][room_id]" class="flex-1 border border-gray-300 rounded p-1.5 room_select">
                                    <option value="">Select Room</option>
                                    <?php foreach($rooms as $r): echo "<option value='{$r['RoomID']}'>{$r['Name']} ({$r['Type']})</option>"; endforeach; ?>
                                </select>
                                <div class="flex items-center px-2">
                                    <input type="checkbox" name="slots[<?php echo $i; ?>][is_free]" value="1" class="w-4 h-4 mr-1 accent-[#a60b26]" onchange="toggleFree(this, <?php echo $i; ?>)">
                                    <span class="text-sm font-semibold text-gray-700">Free</span>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    <div class="mt-4 flex justify-end">
                        <button type="submit" name="save_timetable" class="bg-[#a60b26] hover:bg-[#8a0a20] text-white px-8 py-2.5 rounded-lg font-bold shadow-md transition-colors">Save Assignments</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const depts = <?php echo json_encode($depts); ?>;
const sems = <?php echo json_encode($sems); ?>;

document.getElementById('pid').addEventListener('change', function() {
    let pid = this.value;
    
    let deptSel = document.getElementById('did');
    let semSel = document.getElementById('sid');
    let currentDept = deptSel.value;
    let currentSem = semSel.value;
    
    deptSel.innerHTML = '<option value="">-Select-</option>';
    if (pid) {
        depts.forEach(d => {
            if(d.ProgramID == pid) {
                let opt = new Option(d.Name, d.DepartmentID);
                if(d.DepartmentID == currentDept) opt.selected = true;
                deptSel.add(opt);
            }
        });
    }

    semSel.innerHTML = '<option value="">-Select-</option>';
    if (pid) {
        sems.forEach(s => {
            if(s.ProgramID == pid) {
                let opt = new Option(s.Label, s.SemesterID);
                if(s.SemesterID == currentSem) opt.selected = true;
                semSel.add(opt);
            }
        });
    }
    
    loadSectionsForTT();
});

// Load sections via AJAX when filtering starts
function loadSectionsForTT() {
    let pid = document.getElementById('pid').value;
    let did = document.getElementById('did').value;
    let sid = document.getElementById('sid').value;
    let sh = document.getElementById('shid').value;
    let secSelect = document.getElementById('secid');
    
    let currentVal = secSelect.value;
    secSelect.innerHTML = '<option value="">No Section / General</option>';
    
    if(pid && did && sid && sh) {
        fetch(`../api/public.php?action=get_sections&program_id=${pid}&dept_id=${did}&semester_id=${sid}&shift_id=${sh}`)
        .then(r => r.json())
        .then(data => {
            data.forEach(sec => {
                let opt = document.createElement('option');
                opt.value = sec.SectionID; opt.textContent = 'Section ' + sec.Name;
                secSelect.appendChild(opt);
            });
            if([...secSelect.options].some(o => o.value === currentVal)) {
                secSelect.value = currentVal;
            }
            fetchCourses();
        });
    } else {
        fetchCourses();
    }
}

document.getElementById('did').addEventListener('change', loadSectionsForTT);
document.getElementById('sid').addEventListener('change', loadSectionsForTT);
document.getElementById('shid').addEventListener('change', loadSectionsForTT);

const shiftPeriods = <?php echo json_encode($shift_periods); ?>;
const maxOverall = <?php echo $max_periods; ?>;

function fetchCourses() {
    let sess = document.getElementById('sessid').value;
    let p = document.getElementById('pid').value;
    let d = document.getElementById('did').value;
    let s = document.getElementById('sid').value;
    let sec = document.getElementById('secid').value;
    let sh = document.getElementById('shid').value;
    let day = document.getElementById('dayid').value;
    
    if(sess && p && d && s && sh && day) {
        let maxP = shiftPeriods[sh] || 0;
        for(let i=1; i<=maxOverall; i++) {
            let row = document.getElementById('slot_row_' + i);
            if(row) {
                if(i <= maxP) {
                    row.style.display = 'flex';
                } else {
                    row.style.display = 'none';
                    // clear hidden inputs
                    row.querySelectorAll('select').forEach(sel => sel.value = '');
                    let cb = row.querySelector('input[type="checkbox"]');
                    if(cb) cb.checked = false;
                }
            }
        }
        
        document.getElementById('grid_area').classList.remove('hidden');
        
        // Fetch courses assigned to this section (or general)
        fetch(`<?php echo $base_url; ?>/api/admin.php?action=get_class_courses&p=${p}&d=${d}&s=${s}&sh=${sh}&sec=${sec}`)
        .then(res => res.json())
        .then(data => {
            let classes = document.querySelectorAll('.course_select');
            classes.forEach(sel => {
                let currentVal = sel.value;
                let html = '<option value="">Select Course</option>';
                data.forEach(c => {
                    html += `<option value="${c.CourseID}" data-teacher-id="${c.TeacherID}">${c.Name} (${c.CreditHours}cr)</option>`;
                });
                sel.innerHTML = html;
                if([...sel.options].some(o => o.value === currentVal)) {
                    sel.value = currentVal;
                }
            });
            
            // Now fetch existing timetable data (filtered by session)
            fetch(`<?php echo $base_url; ?>/api/admin.php?action=get_existing_timetable&sess=${sess}&p=${p}&d=${d}&s=${s}&sh=${sh}&sec=${sec}&day=${day}`)
            .then(res2 => res2.json())
            .then(existingData => {
                populateExistingTimetable(existingData);
            });
            
        }).catch(err => {
            console.error(err);
        });
    } else {
        document.getElementById('grid_area').classList.add('hidden');
    }
}

function populateExistingTimetable(data) {
    for(let i=1; i<=maxOverall; i++) {
        let courseSel = document.querySelector(`select[name="slots[${i}][course_id]"]`);
        let teacherSel = document.querySelector(`select[name="slots[${i}][teacher_id]"]`);
        let roomSel = document.querySelector(`select[name="slots[${i}][room_id]"]`);
        let freeCb = document.querySelector(`input[name="slots[${i}][is_free]"]`);
        
        if(courseSel) courseSel.value = '';
        if(teacherSel) teacherSel.value = '';
        if(roomSel) roomSel.value = '';
        if(freeCb) { freeCb.checked = false; toggleFree(freeCb, i); }
    }
    
    data.forEach(slot => {
        let period = slot.PeriodNumber;
        let courseSel = document.querySelector(`select[name="slots[${period}][course_id]"]`);
        let teacherSel = document.querySelector(`select[name="slots[${period}][teacher_id]"]`);
        let roomSel = document.querySelector(`select[name="slots[${period}][room_id]"]`);
        let freeCb = document.querySelector(`input[name="slots[${period}][is_free]"]`);
        
        if (slot.IsFree == 1) {
            if (courseSel && courseSel.value !== '') return;
            if(freeCb) { freeCb.checked = true; toggleFree(freeCb, period); }
        } else {
            if(freeCb && freeCb.checked) { freeCb.checked = false; toggleFree(freeCb, period); }
            if(courseSel) courseSel.value = slot.CourseID;
            if(teacherSel) teacherSel.value = slot.TeacherID;
            if(roomSel) roomSel.value = slot.RoomID;
        }
    });
}

function autoSelectTeacher(courseSelect) {
    let selectedOption = courseSelect.options[courseSelect.selectedIndex];
    if(selectedOption) {
        let teacherId = selectedOption.getAttribute('data-teacher-id');
        if (teacherId) {
            let teacherSelect = courseSelect.parentElement.querySelector('.teacher_select');
            if (teacherSelect) teacherSelect.value = teacherId;
        }
    }
}

function toggleFree(cb, idx) {
    let inputs = cb.parentElement.parentElement.querySelectorAll('select');
    if(cb.checked) {
        inputs.forEach(i => { i.disabled = true; i.value = ''; });
    } else {
        inputs.forEach(i => { i.disabled = false; });
    }
}
</script>

