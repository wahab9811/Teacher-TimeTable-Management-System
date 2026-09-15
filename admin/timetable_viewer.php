<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$delete_msg = $delete_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_timetable'])) {
    $p1 = $_POST['program_id'];
    $d1 = $_POST['department_id'];
    $s1 = $_POST['sem_id'];
    $sh1 = $_POST['shift_id'];
    $sec1 = !empty($_POST['sec_id']) ? $_POST['sec_id'] : null;

    try {
        $reqSess = $pdo->query("SELECT SessionID FROM academic_sessions WHERE IsActive=1 LIMIT 1")->fetchColumn();
        if(!$reqSess) throw new Exception("No active academic session found.");
        
        $pdo->beginTransaction();
        $stmtDel = $pdo->prepare("DELETE FROM timetable WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SessionID=? AND SectionID <=> ?");
        $stmtDel->execute([$p1, $d1, $s1, $sh1, $reqSess, $sec1]);
        $pdo->commit();
        $delete_msg = "Timetable successfully deleted.";
    } catch(Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $delete_error = "Error: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_timetable'])) {
    $p1 = $_POST['program_id'];
    $d1 = $_POST['department_id'];
    $s1 = $_POST['sem_id'];
    $sh1 = $_POST['shift_id'];
    $sec1 = !empty($_POST['sec_id']) ? $_POST['sec_id'] : null;

    try {
        $reqSess = $pdo->query("SELECT SessionID FROM academic_sessions WHERE IsActive=1 LIMIT 1")->fetchColumn();
        if(!$reqSess) throw new Exception("No active academic session found.");
        
        $pdo->beginTransaction();
        
        // Find teachers affected by this publish
        $stmtTeachers = $pdo->prepare("SELECT DISTINCT TeacherID FROM timetable WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SessionID=? AND SectionID <=> ? AND Status = 'draft' AND IsFree = 0 AND TeacherID IS NOT NULL");
        $stmtTeachers->execute([$p1, $d1, $s1, $sh1, $reqSess, $sec1]);
        $teachersToNotify = $stmtTeachers->fetchAll(PDO::FETCH_COLUMN);

        $stmtPub = $pdo->prepare("UPDATE timetable SET Status = 'published' WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SessionID=? AND SectionID <=> ? AND Status = 'draft'");
        $stmtPub->execute([$p1, $d1, $s1, $sh1, $reqSess, $sec1]);
        $count = $stmtPub->rowCount();
        
        // Send notifications
        if ($count > 0 && !empty($teachersToNotify)) {
            $msg = "A new drafted timetable has been published and assigned to you. Please check your dashboard.";
            $stmtNotif = $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)");
            foreach ($teachersToNotify as $tId) {
                $stmtNotif->execute([$tId, $msg]);
            }
        }
        
        $pdo->commit();
        $delete_msg = $count > 0 ? "Successfully published $count drafted periods." : "No draft periods found to publish.";
    } catch(Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $delete_error = "Error: " . $e->getMessage();
    }
}

$tab = $_GET['tab'] ?? 'class';

$programs = $pdo->query("SELECT ProgramID, Name, PeriodUnit FROM programs WHERE IsActive=1 ORDER BY Name")->fetchAll();
$depts = $pdo->query("SELECT DepartmentID, Name, ProgramID FROM departments WHERE IsActive=1 ORDER BY Name")->fetchAll();
$sems = $pdo->query("SELECT SemesterID, Label, ProgramID FROM semesters WHERE IsActive=1 ORDER BY Label")->fetchAll();
$shifts = $pdo->query("SELECT ShiftID, Name FROM shifts ORDER BY ShiftID")->fetchAll();

$teachers = $pdo->query("SELECT UserID, Name, Designation FROM users WHERE Role='teacher' ORDER BY Name")->fetchAll();
$rooms = $pdo->query("SELECT RoomID, Name, Type FROM rooms WHERE IsActive=1 ORDER BY Name")->fetchAll();

// Teacher tab logic
$teacher_tt = [];
if ($tab === 'teacher' && isset($_GET['teacher_id'])) {
    $teacherId = (int)$_GET['teacher_id'];
    $shiftFilter = !empty($_GET['shift_id']) ? (int)$_GET['shift_id'] : null;
    $shiftSql = $shiftFilter ? " AND t.ShiftID = " . $shiftFilter : "";

    $stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, 
                           IF(t.Day='Friday' AND ts.FridayStartTime IS NOT NULL, ts.FridayStartTime, ts.StartTime) as StartTime,
                           IF(t.Day='Friday' AND ts.FridayEndTime IS NOT NULL, ts.FridayEndTime, ts.EndTime) as EndTime,
                           c.Name as CourseName, r.Name as RoomName, sh.Name as ShiftName,
                           p.Name as ProgName, d.Name as DeptName, s.Label as SemName, sec.Name as SectionName
                           FROM timetable t 
                           JOIN time_slots ts ON t.SlotID=ts.SlotID
                           LEFT JOIN courses c ON t.CourseID=c.CourseID
                           LEFT JOIN rooms r ON t.RoomID=r.RoomID
                           LEFT JOIN programs p ON t.ProgramID=p.ProgramID
                           LEFT JOIN departments d ON t.DepartmentID=d.DepartmentID
                           LEFT JOIN semesters s ON t.SemesterID=s.SemesterID
                           LEFT JOIN sections sec ON t.SectionID=sec.SectionID
                           LEFT JOIN shifts sh ON t.ShiftID=sh.ShiftID
                           WHERE t.TeacherID=? $shiftSql
                           ORDER BY t.Day, ts.PeriodNumber");
    $stmt->execute([$teacherId]);
    $tt = $stmt->fetchAll();

    $stmtSub = $pdo->prepare("SELECT t.*, ts.PeriodNumber, 
                           IF(t.Day='Friday' AND ts.FridayStartTime IS NOT NULL, ts.FridayStartTime, ts.StartTime) as StartTime,
                           IF(t.Day='Friday' AND ts.FridayEndTime IS NOT NULL, ts.FridayEndTime, ts.EndTime) as EndTime,
                           c.Name as CourseName, r.Name as RoomName, sh.Name as ShiftName,
                           p.Name as ProgName, d.Name as DeptName, s.Label as SemName, sec.Name as SectionName,
                           1 as IsSubstitute
                           FROM substitute_assignments sa
                           JOIN timetable t ON sa.TimetableID = t.TimetableID
                           JOIN time_slots ts ON t.SlotID=ts.SlotID
                           LEFT JOIN courses c ON t.CourseID=c.CourseID
                           LEFT JOIN rooms r ON t.RoomID=r.RoomID
                           LEFT JOIN programs p ON t.ProgramID=p.ProgramID
                           LEFT JOIN departments d ON t.DepartmentID=d.DepartmentID
                           LEFT JOIN semesters s ON t.SemesterID=s.SemesterID
                           LEFT JOIN sections sec ON t.SectionID=sec.SectionID
                           LEFT JOIN shifts sh ON t.ShiftID=sh.ShiftID
                           WHERE sa.SubstituteTeacherID=? $shiftSql
                           AND sa.Status='active'
                           AND CURDATE() BETWEEN sa.FromDate AND sa.ToDate");
    $stmtSub->execute([$teacherId]);
    $subTT = $stmtSub->fetchAll();

    $teacher_tt = array_merge($tt, $subTT);
}

// Room tab logic
$room_tt = [];
if ($tab === 'room' && isset($_GET['room_id'])) {
    $roomId = (int)$_GET['room_id'];
    $shiftFilter = !empty($_GET['shift_id']) ? (int)$_GET['shift_id'] : null;
    $shiftSql = $shiftFilter ? " AND t.ShiftID = " . $shiftFilter : "";
    
    $stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, c.Name AS CourseName, u.Name AS TeacherName, sec.Name AS SectionName,
           p.Name AS ProgName, d.Name AS DeptName, s.Label AS SemName, sh.Name as ShiftName,
           IF(t.Day='Friday' AND ts.FridayStartTime IS NOT NULL, ts.FridayStartTime, ts.StartTime) AS StartTime,
           IF(t.Day='Friday' AND ts.FridayEndTime  IS NOT NULL, ts.FridayEndTime,  ts.EndTime)  AS EndTime
        FROM timetable t
        JOIN time_slots ts ON t.SlotID=ts.SlotID
        JOIN academic_sessions sess ON t.SessionID=sess.SessionID
        LEFT JOIN courses c ON t.CourseID=c.CourseID
        LEFT JOIN users u ON t.TeacherID=u.UserID
        LEFT JOIN programs p ON t.ProgramID=p.ProgramID
        LEFT JOIN departments d ON t.DepartmentID=d.DepartmentID
        LEFT JOIN semesters s ON t.SemesterID=s.SemesterID
        LEFT JOIN sections sec ON t.SectionID=sec.SectionID
        LEFT JOIN shifts sh ON t.ShiftID=sh.ShiftID
        WHERE t.RoomID = ? AND sess.IsActive = 1 AND t.IsFree = 0 $shiftSql
        ORDER BY FIELD(t.Day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), ts.PeriodNumber");
    $stmt->execute([$roomId]);
    $room_tt = $stmt->fetchAll();
}

// Department tab logic
$dept_tt = [];
if ($tab === 'department' && isset($_GET['dept_id']) && isset($_GET['shift_id'])) {
    $deptId = (int)$_GET['dept_id'];
    $shiftId = (int)$_GET['shift_id'];

    // Pre-load all semesters for this department's program
    $deptInfo = $pdo->prepare("SELECT d.ProgramID, p.PeriodUnit FROM departments d JOIN programs p ON d.ProgramID = p.ProgramID WHERE d.DepartmentID = ?");
    $deptInfo->execute([$deptId]);
    $deptData = $deptInfo->fetch(PDO::FETCH_ASSOC);
    $progId = $deptData['ProgramID'] ?? null;
    $periodUnit = $deptData['PeriodUnit'] ?? 'semester';

    if ($progId) {
        $allSems = $pdo->prepare("SELECT SemesterID, Label FROM semesters WHERE ProgramID = ? AND IsActive = 1 ORDER BY Label");
        $allSems->execute([$progId]);
        $semestersData = $allSems->fetchAll();

        // Fetch sections for this program/department/shift
        $allSecs = $pdo->prepare("SELECT SectionID, Name, SemesterID FROM sections WHERE ProgramID=? AND DepartmentID=? AND ShiftID=? ORDER BY Name");
        $allSecs->execute([$progId, $deptId, $shiftId]);
        $sectionsData = $allSecs->fetchAll();
        
        // Check if the program uses sections at all in the database
        $progSecCheck = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE ProgramID=?");
        $progSecCheck->execute([$progId]);
        $progHasAnySections = ($progSecCheck->fetchColumn() > 0);

        foreach($semestersData as $sem) {
            $semId = $sem['SemesterID'];
            $semSecs = array_filter($sectionsData, function($s) use ($semId) {
                return $s['SemesterID'] == $semId;
            });

            if (empty($semSecs)) {
                // If program uses sections in general, but none for this semester/shift, skip pre-loading empty grid
                if ($progHasAnySections) {
                    continue; 
                }
                
                $dept_tt[$semId . '_0'] = [
                    'SemName' => $sem['Label'],
                    'SecName' => null,
                    'data' => []
                ];
            } else {
                foreach($semSecs as $sec) {
                    $dept_tt[$semId . '_' . $sec['SectionID']] = [
                        'SemName' => $sem['Label'],
                        'SecName' => $sec['Name'],
                        'data' => []
                    ];
                }
            }
        }
    }

    $stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, c.Name AS CourseName, u.Name AS TeacherName, r.Name AS RoomName, sec.Name AS SectionName,
           s.Label AS SemName, s.SemesterID, t.SectionID,
           IF(t.Day='Friday' AND ts.FridayStartTime IS NOT NULL, ts.FridayStartTime, ts.StartTime) AS StartTime,
           IF(t.Day='Friday' AND ts.FridayEndTime  IS NOT NULL, ts.FridayEndTime,  ts.EndTime)  AS EndTime
        FROM timetable t
        JOIN time_slots ts ON t.SlotID=ts.SlotID
        JOIN academic_sessions sess ON t.SessionID=sess.SessionID
        LEFT JOIN courses c ON t.CourseID=c.CourseID
        LEFT JOIN users u ON t.TeacherID=u.UserID
        LEFT JOIN rooms r ON t.RoomID=r.RoomID
        LEFT JOIN semesters s ON t.SemesterID=s.SemesterID
        LEFT JOIN sections sec ON t.SectionID=sec.SectionID
        WHERE t.DepartmentID = ? AND t.ShiftID = ? AND sess.IsActive = 1 AND t.IsFree = 0
        ORDER BY s.SemesterID, FIELD(t.Day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), ts.PeriodNumber");
    $stmt->execute([$deptId, $shiftId]);
    $result = $stmt->fetchAll();
    
    // Group by Semester and Section
    foreach($result as $row) {
        $semId = $row['SemesterID'];
        $secId = $row['SectionID'] ?: '0';
        $key = $semId . '_' . $secId;
        if(!$semId) continue;
        
        if(!isset($dept_tt[$key])) {
            $dept_tt[$key] = [
                'SemName' => $row['SemName'],
                'SecName' => $row['SectionID'] ? $row['SectionName'] : null,
                'data' => []
            ];
        }
        $dept_tt[$key]['data'][] = $row;
    }
}
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 min-w-0">
        <h2 class="text-2xl font-bold text-maroon mb-4">Manage Timetables</h2>
        
        <div class="bg-white p-4 rounded shadow-sm border border-gray-200 mb-6 flex gap-4">
            <a href="?tab=class" class="px-4 py-2 font-semibold rounded <?= $tab==='class' ? 'bg-maroon text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Class-wise</a>
            <a href="?tab=teacher" class="px-4 py-2 font-semibold rounded <?= $tab==='teacher' ? 'bg-maroon text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Teacher-wise</a>
            <a href="?tab=room" class="px-4 py-2 <?= $tab === 'room' ? 'bg-[#a60b26] text-white rounded' : 'bg-gray-100 text-gray-700 rounded hover:bg-gray-200' ?> font-bold text-sm">Room-wise</a>
            <a href="?tab=department" class="px-4 py-2 <?= $tab === 'department' ? 'bg-[#a60b26] text-white rounded' : 'bg-gray-100 text-gray-700 rounded hover:bg-gray-200' ?> font-bold text-sm">Department/Group-wise</a>
        </div>
        
        <div class="bg-white p-6 shadow-md rounded border border-gray-100">
            <?php if($delete_msg): ?><div class="bg-green-100 text-green-700 font-bold p-3 rounded mb-4 border"><?= $delete_msg ?></div><?php endif; ?>
            <?php if($delete_error): ?><div class="bg-red-100 text-red-700 font-bold p-3 rounded mb-4 border"><?= $delete_error ?></div><?php endif; ?>

            <script>
            const depts = <?php echo json_encode($depts); ?>;
            const sems = <?php echo json_encode($sems); ?>;
            const allPrograms = <?php echo json_encode($programs); ?>;
            </script>
            
            <?php if ($tab === 'class'): ?>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end mb-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Select Program</label>
                        <select id="p_id" class="w-full border p-2 rounded" onchange="loadSections()">
                            <option value="">Select</option>
                            <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label id="lbl_dept" class="block text-xs font-bold text-gray-500 mb-1">Select Department</label>
                        <select id="d_id" class="w-full border p-2 rounded" onchange="loadSections()">
                            <option value="">Select</option>
                        </select>
                    </div>
                    <div>
                        <label id="lbl_sem" class="block text-xs font-bold text-gray-500 mb-1">Select Semester</label>
                        <select id="sem_id" class="w-full border p-2 rounded" onchange="loadSections()">
                            <option value="">Select</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Select Shift</label>
                        <select id="sh_id" class="w-full border p-2 rounded" onchange="loadSections()">
                            <?php foreach($shifts as $sh): echo "<option value='{$sh['ShiftID']}'>{$sh['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Select Section</label>
                        <select id="sec_id" class="w-full border p-2 rounded">
                            <option value="">General Class (No Sec)</option>
                        </select>
                    </div>
                </div>
                <div class="mb-4">
                    <button onclick="loadClassTimetable()" class="bg-blue-600 text-white px-4 py-2 rounded shadow font-bold hover:bg-blue-700">Show Timetable</button>
                    <button id="export_pdf_btn" style="display: none;" onclick="if(document.getElementById('class_tt_container').innerHTML.trim()) downloadPDF('class_tt_container', 'Class_Timetable.pdf', true)" class="download-pdf-btn bg-gray-600 text-white px-4 py-2 rounded shadow font-bold hover:bg-gray-700 ml-2">Export PDF</button>
                </div>
                
                <div id="class_msg" class="text-red-500 font-bold mb-4"></div>
                
                <div id="class_tt_container" class="overflow-x-auto w-full"></div>
                
                <script>
                
                document.getElementById('p_id').addEventListener('change', function() {
                    let pid = this.value;
                    let deptSel = document.getElementById('d_id');
                    let semSel = document.getElementById('sem_id');
                    let pData = allPrograms.find(x => x.ProgramID == pid);
                    
                    if(pData && pData.PeriodUnit === 'year') {
                        document.getElementById('lbl_dept').innerText = 'Select Group';
                        document.getElementById('lbl_sem').innerText = 'Select Year';
                    } else {
                        document.getElementById('lbl_dept').innerText = 'Select Department';
                        document.getElementById('lbl_sem').innerText = 'Select Semester';
                    }

                    deptSel.innerHTML = '<option value="">Select</option>';
                    semSel.innerHTML = '<option value="">Select</option>';
                    depts.filter(d => d.ProgramID == pid).forEach(d => deptSel.innerHTML += `<option value="${d.DepartmentID}">${d.Name}</option>`);
                    sems.filter(s => s.ProgramID == pid).forEach(s => semSel.innerHTML += `<option value="${s.SemesterID}">${s.Label}</option>`);
                    loadSections();
                });
                
                function loadSections() {
                    let p = document.getElementById('p_id').value;
                    let d = document.getElementById('d_id').value;
                    let s = document.getElementById('sem_id').value;
                    let sh = document.getElementById('sh_id').value;
                    let secSel = document.getElementById('sec_id');
                    secSel.innerHTML = '<option value="">General Class (No Sec)</option>';
                    if(p && d && s && sh) {
                        fetch(`../api/public.php?action=get_sections&program_id=${p}&dept_id=${d}&semester_id=${s}&shift_id=${sh}`)
                        .then(r => r.json())
                        .then(data => {
                            data.forEach(sec => {
                                let nameLow = sec.Name.toLowerCase();
                                if(nameLow === 'none' || nameLow === 'main' || nameLow === 'general') {
                                    secSel.options[0].value = sec.SectionID;
                                } else {
                                    secSel.innerHTML += `<option value="${sec.SectionID}">Section ${sec.Name}</option>`;
                                }
                            });
                        });
                    }
                }
                
                function loadClassTimetable() {
                    let p = document.getElementById('p_id').value;
                    let d = document.getElementById('d_id').value;
                    let s = document.getElementById('sem_id').value;
                    let sh = document.getElementById('sh_id').value;
                    let sec = document.getElementById('sec_id').value;
                    let msg = document.getElementById('class_msg');
                    let c = document.getElementById('class_tt_container');
                    let pdfBtn = document.getElementById('export_pdf_btn');
                    msg.innerHTML = '';
                    c.innerHTML = '<p class="text-gray-500">Loading...</p>';
                    pdfBtn.style.display = 'none';
                    
                    if(!p || !d || !s || !sh) {
                        msg.innerHTML = 'Please select Program, Department, Semester, and Shift.';
                        c.innerHTML = '';
                        return;
                    }
                    
                    fetch(`../api/public.php?action=get_timetable&program_id=${p}&dept_id=${d}&sem_id=${s}&shift_id=${sh}&sec_id=${sec}&view=week`)
                    .then(r => r.json())
                    .then(res => {
                        if(res.error) {
                            msg.innerHTML = res.error === 'no_active_session' ? 'No active session found.' : res.error;
                            c.innerHTML = '';
                            return;
                        }
                        if(res.empty || !res.data || res.data.length === 0) {
                            c.innerHTML = '<p class="text-gray-500 font-bold p-4 bg-gray-50 text-center rounded border">No timetable found.</p>';
                            return;
                        }
                        let editLink = `timetable_manual.php?pid=${p}&did=${d}&sid=${s}&shid=${sh}&secid=${sec}`;
                        let html = `
                        <div class="bg-white p-2 relative">
                            <h3 class="text-xl font-bold mb-4 text-maroon text-center pr-48">Class Timetable</h3>
                            <form method="POST" class="absolute top-2 right-2 flex gap-2">
                                <input type="hidden" name="program_id" value="${p}">
                                <input type="hidden" name="department_id" value="${d}">
                                <input type="hidden" name="sem_id" value="${s}">
                                <input type="hidden" name="shift_id" value="${sh}">
                                <input type="hidden" name="sec_id" value="${sec}">
                                <a href="${editLink}" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm font-bold shadow-sm flex items-center">Edit</a>
                                <button type="submit" name="publish_timetable" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-sm font-bold shadow-sm flex items-center" onclick="return confirm('Publish all drafted periods for this class?')">Publish Drafts</button>
                                <button type="submit" name="delete_timetable" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded text-sm font-bold shadow-sm flex items-center" onclick="return confirm('Are you sure you want to COMPLETELY delete this timetable?')">Delete</button>
                            </form>
                            <table class="w-full text-left border">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="border p-2 text-center w-16">Period</th>
                                        <th class="border p-2 text-center">Time</th>
                                        ${['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'].map(day => `<th class='border p-2 text-center min-w-[120px]'>${day}</th>`).join('')}
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                        for(let i=1; i<=6; i++) {
                            let timeStr = "--";
                            let firstSlot = res.data.find(x => x.PeriodNumber == i && x.StartTime);
                            if(firstSlot) timeStr = firstSlot.StartTime.substring(0,5) + ' - ' + firstSlot.EndTime.substring(0,5);
                            html += `<tr><td class="border p-2 text-center font-bold bg-gray-50">${i}</td><td class="border p-2 text-center text-[13px] bg-gray-50 whitespace-nowrap text-gray-600 font-semibold">${timeStr}</td>`;
                            
                            ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'].forEach(day => {
                                let slot = res.data.find(x => x.Day === day && x.PeriodNumber == i);
                                html += `<td class="border p-2 text-center text-sm ${(!slot || slot.IsFree) ? 'bg-gray-50' : ''}">`;
                                if(slot && !slot.IsFree) {
                                    if(day === 'Friday' && slot.StartTime) {
                                        html += `<div class="text-[10px] text-maroon flex justify-center font-bold mb-1 border-b border-gray-200 pb-1">${slot.StartTime.substring(0,5)} - ${slot.EndTime.substring(0,5)}</div>`;
                                    }
                                    if(slot.Status === 'draft') {
                                        html += `<div class="text-[10px] text-white bg-orange-500 rounded-sm inline-block px-1 mb-1 font-bold">DRAFT</div>`;
                                    }
                                    html += `<div class="font-bold text-maroon">${slot.CourseName || ''}</div>
                                             <div class="text-[11px] text-gray-600 my-1">${slot.TeacherName || ''}</div>
                                             <div class="text-[12px] italic font-semibold text-gray-500">${slot.RoomName || ''}</div>`;
                                } else {
                                    html += `<span class="text-gray-300">-</span>`;
                                }
                                html += `</td>`;
                            });
                            html += `</tr>`;
                        }
                        html += `</tbody></table></div>`;
                        c.innerHTML = html;
                        pdfBtn.style.display = 'inline-block';
                    });
                }
                </script>
            <?php endif; ?>
            
            <?php if ($tab === 'teacher'): ?>
                <form method="GET" class="flex gap-4 items-end mb-6">
                    <input type="hidden" name="tab" value="teacher">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Select Teacher</label>
                        <select name="teacher_id" class="w-64 border p-2 rounded" required>
                            <option value="">-- Choose --</option>
                            <?php foreach($teachers as $t): ?>
                                <option value="<?= $t['UserID'] ?>" <?= (isset($_GET['teacher_id']) && $_GET['teacher_id']==$t['UserID']) ? 'selected' : '' ?>><?= htmlspecialchars($t['Name']) ?> (<?= htmlspecialchars($t['Designation']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Select Shift</label>
                        <select name="shift_id" class="w-40 border p-2 rounded">
                            <option value="">-- Select --</option>
                            <?php foreach($shifts as $sh): ?>
                                <option value="<?= $sh['ShiftID'] ?>" <?= (isset($_GET['shift_id']) && $_GET['shift_id']==$sh['ShiftID']) ? 'selected' : '' ?>><?= htmlspecialchars($sh['Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded shadow font-bold hover:bg-blue-700">Show Timetable</button>
                    <?php if(!empty($teacher_tt)): ?>
                        <button type="button" onclick="downloadPDF('teacher_tt_container', 'Teacher_Timetable.pdf', true)" class="download-pdf-btn bg-gray-600 text-white px-4 py-2 rounded shadow font-bold hover:bg-gray-700">Export PDF</button>
                    <?php endif; ?>
                </form>
                
                <?php if(isset($_GET['teacher_id'])): ?>
                    <?php
                    $selTeach = array_filter($teachers, function($x) { return $x['UserID'] == $_GET['teacher_id']; });
                    $selTeach = !empty($selTeach) ? array_shift($selTeach) : null;
                    ?>
                    <?php
                    $hasActualClasses = false;
                    foreach ($teacher_tt as $slot) {
                        if (empty($slot['IsFree'])) {
                            $hasActualClasses = true;
                            break;
                        }
                    }
                    if (!$hasActualClasses) {
                        $teacher_tt = [];
                    }
                    ?>
                    <?php if($selTeach && !empty($teacher_tt)): ?>
                    <div id="teacher_tt_container" class="overflow-x-auto w-full bg-white p-2 mt-2">
                        <h3 class="text-xl font-bold mb-4 text-maroon text-center">Timetable: <?= htmlspecialchars($selTeach['Name']) ?> <span class="text-gray-500 text-sm">(<?= htmlspecialchars($selTeach['Designation']) ?>)</span></h3>
                        <table class="w-full text-left border">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border p-2 text-center w-16">Period</th>
                                    <th class="border p-2 text-center">Time</th>
                                    <?php 
                                    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; 
                                    foreach($days as $d) echo "<th class='border p-2 text-center min-w-[120px]'>$d</th>"; 
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for($i=1; $i<=6; $i++): ?>
                                <tr>
                                    <td class="border p-2 text-center font-bold bg-gray-50"><?php echo $i; ?></td>
                                    <?php 
                                        $timeStr = "--";
                                        $anySlot = array_filter($teacher_tt, function($item) use ($i) { 
                                            return $item['PeriodNumber'] == $i && !empty($item['StartTime']); 
                                        });
                                        if (!empty($anySlot)) {
                                            $firstSlot = array_values($anySlot)[0];
                                            $timeStr = substr($firstSlot['StartTime'], 0, 5) . ' - ' . substr($firstSlot['EndTime'], 0, 5);
                                        }
                                    ?>
                                    <td class="border p-2 text-center text-[13px] bg-gray-50 whitespace-nowrap text-gray-600 font-semibold"><?php echo $timeStr; ?></td>
                                    <?php foreach($days as $d): 
                                        $slot = array_filter($teacher_tt, function($item) use ($d, $i) { return $item['Day'] === $d && $item['PeriodNumber'] == $i; });
                                        $slot = array_shift($slot);
                                    ?>
                                        <td class="border p-2 text-center text-sm <?php echo (!$slot || !empty($slot['IsFree'])) ? 'bg-gray-50' : ''; ?>">
                                            <?php if($slot && empty($slot['IsFree'])): ?>
                                                <?php if($d === 'Friday' && !empty($slot['StartTime'])): ?>
                                                    <div class="text-[10px] text-maroon flex justify-center font-bold mb-1 border-b border-gray-200 pb-1"><?php echo substr($slot['StartTime'],0,5).' - '.substr($slot['EndTime'],0,5); ?></div>
                                                <?php endif; ?>
                                                <div class="font-bold text-maroon"><?php echo htmlspecialchars($slot['CourseName'] ?? ''); ?></div>
                                                <div class="text-[11px] text-gray-800 my-1 font-semibold leading-tight">
                                                    <?php echo htmlspecialchars(($slot['ProgName'] ?? '').' - '.($slot['DeptName'] ?? '')); ?><br>
                                                    <span class="text-gray-500"><?php echo htmlspecialchars($slot['SemName'] ?? ''); ?><?php echo !empty($slot['SectionName']) ? ' ('.htmlspecialchars($slot['SectionName']).')' : ''; ?></span><br>
                                                    <?php if(empty($_GET['shift_id'])): ?>
                                                        <span class="text-blue-600 mt-[2px] block"><?php echo htmlspecialchars($slot['ShiftName'] ?? ''); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-[12px] italic font-bold text-gray-500 mt-1">
                                                    <?php echo htmlspecialchars($slot['RoomName'] ?? ''); ?>
                                                    <?php if(!empty($slot['IsSubstitute'])) echo '<br><span class="text-orange-500 not-italic">(Substitute)</span>'; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-300">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php elseif($selTeach): ?>
                        <div class="bg-white p-6 shadow-md rounded border border-gray-100 text-center text-gray-500 font-bold mt-4">
                            No timetable data found for this teacher.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-gray-500 font-bold p-4 bg-gray-50 text-center rounded border">Please select a teacher.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($tab === 'room'): ?>
                <form method="GET" class="flex gap-4 items-end mb-6">
                    <input type="hidden" name="tab" value="room">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Select Room</label>
                        <select name="room_id" class="w-64 border p-2 rounded" required>
                            <option value="">-- Choose --</option>
                            <?php foreach($rooms as $r): ?>
                                <option value="<?= $r['RoomID'] ?>" <?= (isset($_GET['room_id']) && $_GET['room_id']==$r['RoomID']) ? 'selected' : '' ?>><?= htmlspecialchars($r['Name']) ?> (<?= htmlspecialchars($r['Type']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Select Shift</label>
                        <select name="shift_id" class="w-40 border p-2 rounded">
                            <option value="">-- Select --</option>
                            <?php foreach($shifts as $sh): ?>
                                <option value="<?= $sh['ShiftID'] ?>" <?= (isset($_GET['shift_id']) && $_GET['shift_id']==$sh['ShiftID']) ? 'selected' : '' ?>><?= htmlspecialchars($sh['Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded shadow font-bold hover:bg-blue-700">Show Timetable</button>
                    <?php if(!empty($room_tt)): ?>
                        <button type="button" onclick="downloadPDF('room_tt_container', 'Room_Timetable.pdf', true)" class="download-pdf-btn bg-gray-600 text-white px-4 py-2 rounded shadow font-bold hover:bg-gray-700">Export PDF</button>
                    <?php endif; ?>
                </form>
                
                <?php if(isset($_GET['room_id'])): ?>
                    <?php
                    $selRoom = array_filter($rooms, function($x) { return $x['RoomID'] == $_GET['room_id']; });
                    $selRoom = !empty($selRoom) ? array_shift($selRoom) : null;
                    ?>
                    <?php if($selRoom && !empty($room_tt)): ?>
                    <div id="room_tt_container" class="overflow-x-auto w-full bg-white p-2 mt-2">
                        <h3 class="text-xl font-bold mb-4 text-maroon text-center">Timetable: <?= htmlspecialchars($selRoom['Name']) ?> <span class="text-gray-500 text-sm">(<?= htmlspecialchars($selRoom['Type']) ?>)</span></h3>
                        <table class="w-full text-left border">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border p-2 text-center w-16">Period</th>
                                    <th class="border p-2 text-center">Time</th>
                                    <?php 
                                    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; 
                                    foreach($days as $d) echo "<th class='border p-2 text-center min-w-[120px]'>$d</th>"; 
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for($i=1; $i<=6; $i++): ?>
                                <tr>
                                    <td class="border p-2 text-center font-bold bg-gray-50"><?php echo $i; ?></td>
                                    <?php 
                                        $timeStr = "--";
                                        $anySlot = array_filter($room_tt, function($item) use ($i) { 
                                            return $item['PeriodNumber'] == $i && !empty($item['StartTime']); 
                                        });
                                        if (!empty($anySlot)) {
                                            $firstSlot = array_values($anySlot)[0];
                                            $timeStr = substr($firstSlot['StartTime'], 0, 5) . ' - ' . substr($firstSlot['EndTime'], 0, 5);
                                        }
                                    ?>
                                    <td class="border p-2 text-center text-[13px] bg-gray-50 whitespace-nowrap text-gray-600 font-semibold"><?php echo $timeStr; ?></td>
                                    <?php foreach($days as $d): 
                                        $slot = array_filter($room_tt, function($item) use ($d, $i) { return $item['Day'] === $d && $item['PeriodNumber'] == $i; });
                                        $slot = array_shift($slot);
                                    ?>
                                        <td class="border p-2 text-center text-sm <?php echo (!$slot || !empty($slot['IsFree'])) ? 'bg-gray-50' : ''; ?>">
                                            <?php if($slot && empty($slot['IsFree'])): ?>
                                                <?php if($d === 'Friday' && !empty($slot['StartTime'])): ?>
                                                    <div class="text-[10px] text-maroon flex justify-center font-bold mb-1 border-b border-gray-200 pb-1"><?php echo substr($slot['StartTime'],0,5).' - '.substr($slot['EndTime'],0,5); ?></div>
                                                <?php endif; ?>
                                                <div class="font-bold text-maroon"><?php echo htmlspecialchars($slot['CourseName'] ?? ''); ?></div>
                                                <div class="text-[11px] text-gray-600 my-1 font-bold"><?php echo htmlspecialchars($slot['TeacherName'] ?? ''); ?></div>
                                                <div class="text-[11px] text-gray-800 font-semibold leading-tight">
                                                    <?php echo htmlspecialchars(($slot['ProgName'] ?? '').' - '.($slot['DeptName'] ?? '')); ?><br>
                                                    <span class="text-gray-500"><?php echo htmlspecialchars($slot['SemName'] ?? ''); ?><?php echo !empty($slot['SectionName']) ? ' ('.htmlspecialchars($slot['SectionName']).')' : ''; ?></span><br>
                                                    <?php if(empty($_GET['shift_id'])): ?>
                                                        <span class="text-blue-600 mt-[2px] block"><?php echo htmlspecialchars($slot['ShiftName'] ?? ''); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-300">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php elseif($selRoom): ?>
                        <div class="bg-white p-6 shadow-md rounded border border-gray-100 text-center text-gray-500 font-bold mt-4">
                            No active classes scheduled for this room.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-gray-500 font-bold p-4 bg-gray-50 text-center rounded border">Please select a room.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($tab === 'department'): ?>
                <form method="GET" class="mb-6 bg-gray-50 p-4 border rounded">
                    <input type="hidden" name="tab" value="department">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Select Program</label>
                            <select id="p_id_dept" class="w-full border p-2 rounded" onchange="loadDeptTabDepts()">
                                <option value="">-- Choose --</option>
                                <?php foreach($programs as $p): ?>
                                    <option value="<?= $p['ProgramID'] ?>"><?= htmlspecialchars($p['Name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label id="lbl_dept_dept" class="block text-xs font-bold text-gray-500 mb-1">Select Department</label>
                            <select name="dept_id" id="d_id_dept" class="w-full border p-2 rounded" required>
                                <option value="">-- Choose --</option>
                                <?php
                                if(isset($_GET['dept_id'])) {
                                    $selDept = array_filter($depts, function($d) { return $d['DepartmentID'] == $_GET['dept_id']; });
                                    if($selDept) {
                                        $d = array_shift($selDept);
                                        echo "<option value='{$d['DepartmentID']}' selected>{$d['Name']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Select Shift</label>
                            <select name="shift_id" class="w-full border p-2 rounded" required>
                                <option value="">-- Choose --</option>
                                <?php foreach($shifts as $sh): ?>
                                    <option value="<?= $sh['ShiftID'] ?>" <?= (isset($_GET['shift_id']) && $_GET['shift_id']==$sh['ShiftID']) ? 'selected' : '' ?>><?= htmlspecialchars($sh['Name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 flex gap-4">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded font-bold hover:bg-blue-700">Show Timetables</button>
                        <?php if(isset($_GET['dept_id']) && isset($_GET['shift_id']) && !empty($dept_tt)): ?>
                            <button type="button" onclick="downloadPDF('dept_tt_container', 'Department_Timetable.pdf', true)" class="download-pdf-btn bg-gray-600 text-white px-6 py-2 rounded shadow font-bold hover:bg-gray-700">Export All to PDF</button>
                        <?php endif; ?>
                    </div>
                </form>
                <script>
                const programsData = <?php echo json_encode($programs); ?>;
                function loadDeptTabDepts() {
                    let pid = document.getElementById('p_id_dept').value;
                    let deptSel = document.getElementById('d_id_dept');
                    let lblDept = document.getElementById('lbl_dept_dept');
                    
                    let pData = programsData.find(x => x.ProgramID == pid);
                    if(pData && pData.PeriodUnit === 'year') {
                        lblDept.innerText = 'Select Group';
                    } else {
                        lblDept.innerText = 'Select Department';
                    }

                    deptSel.innerHTML = '<option value="">-- Choose --</option>';
                    if(!pid) return;
                    depts.filter(d => d.ProgramID == pid).forEach(d => deptSel.innerHTML += `<option value="${d.DepartmentID}">${d.Name}</option>`);
                }
                window.addEventListener('DOMContentLoaded', () => {
                    let did = new URLSearchParams(window.location.search).get('dept_id');
                    if(did) {
                        let dept = depts.find(d => d.DepartmentID == did);
                        if(dept) {
                            document.getElementById('p_id_dept').value = dept.ProgramID;
                            loadDeptTabDepts();
                            document.getElementById('d_id_dept').value = did;
                        }
                    }
                });
                </script>
                
                <?php if(isset($_GET['dept_id']) && isset($_GET['shift_id'])): ?>
                    <?php
                    $selDept = array_filter($depts, function($x) { return $x['DepartmentID'] == $_GET['dept_id']; });
                    $selDept = !empty($selDept) ? array_shift($selDept) : null;
                    $selShift = array_filter($shifts, function($x) { return $x['ShiftID'] == $_GET['shift_id']; });
                    $selShift = !empty($selShift) ? array_shift($selShift) : null;
                    ?>
                    <?php
                    // Filter out sections with no data
                    $dept_tt = array_filter($dept_tt, function($item) {
                        return !empty($item['data']);
                    });
                    ?>
                    <?php if($selDept && $selShift && !empty($dept_tt)): ?>
                    <div id="dept_tt_container" class="w-full bg-white p-2">
                        <div class="text-center mb-6">
                            <h2 class="text-2xl font-bold text-maroon uppercase">
                                <?= $periodUnit === 'year' ? 'Group of ' : 'Department of ' ?><?= htmlspecialchars($selDept['Name']) ?>
                            </h2>
                            <p class="text-gray-600 font-bold"><?= htmlspecialchars($selShift['Name']) ?> Shift - Consolidated Timetable</p>
                        </div>
                        <?php foreach($dept_tt as $key => $semData): 
                            list($rSemId, $rSecId) = explode('_', $key);
                            $rSecId = $rSecId === '0' ? '' : $rSecId;
                        ?>
                            <div class="mb-10 page-break-after">
                                <h3 class="text-lg font-bold mb-3 text-white bg-[#a60b26] p-2 rounded text-center relative pr-48">
                                    <?php
                                    if ($periodUnit === 'year') {
                                        echo $semData['SecName'] ? 'Section ' . htmlspecialchars($semData['SecName']) . ' - ' . htmlspecialchars($semData['SemName']) 
                                                                 : 'Class/Year: ' . htmlspecialchars($semData['SemName']);
                                    } else {
                                        echo htmlspecialchars($semData['SemName']);
                                        if ($semData['SecName']) echo ' (Section ' . htmlspecialchars($semData['SecName']) . ')';
                                    }
                                    ?>
                                    <form method="POST" class="absolute top-1/2 -translate-y-1/2 right-2 flex gap-2 text-sm z-10 m-0 hide-on-print">
                                        <input type="hidden" name="program_id" value="<?= $progId ?>">
                                        <input type="hidden" name="department_id" value="<?= $deptId ?>">
                                        <input type="hidden" name="sem_id" value="<?= $rSemId ?>">
                                        <input type="hidden" name="shift_id" value="<?= $shiftId ?>">
                                        <input type="hidden" name="sec_id" value="<?= $rSecId ?>">
                                        <a href="timetable_manual.php?pid=<?= $progId ?>&did=<?= $deptId ?>&sid=<?= $rSemId ?>&shid=<?= $shiftId ?>&secid=<?= $rSecId ?>" class="bg-white text-blue-700 hover:bg-gray-50 hover:text-blue-800 px-3 py-1 rounded shadow-sm font-bold flex items-center">Edit</a>
                                        <button type="submit" name="publish_timetable" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded shadow-sm font-bold flex items-center border border-green-700 ml-1" onclick="return confirm('Publish all drafted periods for this class?')">Publish</button>
                                        <button type="submit" name="delete_timetable" class="bg-white hover:bg-red-50 text-red-700 px-3 py-1 rounded shadow-sm font-bold flex items-center border border-red-200 ml-1" onclick="return confirm('Are you sure you want to COMPLETELY delete this timetable footprint?')">Delete</button>
                                    </form>
                                </h3>
                                <div class="overflow-x-auto">
                                <table class="w-full text-left border">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="border p-2 text-center w-16">Period</th>
                                            <th class="border p-2 text-center">Time</th>
                                            <?php 
                                            $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; 
                                            foreach($days as $d) echo "<th class='border p-2 text-center min-w-[120px]'>$d</th>"; 
                                            ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php for($i=1; $i<=6; $i++): ?>
                                        <tr>
                                            <td class="border p-2 text-center font-bold bg-gray-50"><?php echo $i; ?></td>
                                            <?php 
                                                $timeStr = "--";
                                                $anySlot = array_filter($semData['data'], function($item) use ($i) { 
                                                    return $item['PeriodNumber'] == $i && !empty($item['StartTime']); 
                                                });
                                                if (!empty($anySlot)) {
                                                    $firstSlot = array_values($anySlot)[0];
                                                    $timeStr = substr($firstSlot['StartTime'], 0, 5) . ' - ' . substr($firstSlot['EndTime'], 0, 5);
                                                }
                                            ?>
                                            <td class="border p-2 text-center text-[13px] bg-gray-50 whitespace-nowrap text-gray-600 font-semibold"><?php echo $timeStr; ?></td>
                                            <?php foreach($days as $d): 
                                                $slot = array_filter($semData['data'], function($item) use ($d, $i) { return $item['Day'] === $d && $item['PeriodNumber'] == $i; });
                                                $slot = array_shift($slot);
                                            ?>
                                                <td class="border p-2 text-center text-sm <?php echo (!$slot) ? 'bg-gray-50' : ''; ?>">
                                                    <?php if($slot): ?>
                                                        <?php if($d === 'Friday' && !empty($slot['StartTime'])): ?>
                                                            <div class="text-[10px] text-maroon flex justify-center font-bold mb-1 border-b border-gray-200 pb-1"><?php echo substr($slot['StartTime'],0,5).' - '.substr($slot['EndTime'],0,5); ?></div>
                                                        <?php endif; ?>
                                                        <?php if(isset($slot['Status']) && $slot['Status'] === 'draft'): ?>
                                                            <div class="text-[10px] text-white bg-orange-500 rounded-sm inline-block px-1 mb-1 font-bold">DRAFT</div>
                                                        <?php endif; ?>
                                                        <div class="font-bold text-maroon"><?php echo htmlspecialchars($slot['CourseName'] ?? ''); ?></div>
                                                        <div class="text-[11px] text-gray-600 my-1 font-bold"><?php echo htmlspecialchars($slot['TeacherName'] ?? ''); ?></div>
                                                        <div class="text-[12px] italic text-gray-500">
                                                            <?php echo htmlspecialchars($slot['RoomName'] ?? ''); ?>
                                                            <?php if(!empty($slot['SectionName'])) echo ' <b>('.htmlspecialchars($slot['SectionName']).')</b>'; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-gray-300">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php elseif(isset($_GET['dept_id']) && isset($_GET['shift_id'])): ?>
                        <div class="bg-white p-6 shadow-md rounded border border-gray-100 text-center text-gray-500 font-bold mt-4">
                            No timetable data found for this <?= (isset($periodUnit) && $periodUnit === 'year') ? 'group' : 'department' ?> and shift.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
