<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$tab = $_GET['tab'] ?? 'class';

$programs = $pdo->query("SELECT ProgramID, Name FROM programs WHERE IsActive=1 ORDER BY Name")->fetchAll();
$depts = $pdo->query("SELECT DepartmentID, Name, ProgramID FROM departments WHERE IsActive=1 ORDER BY Name")->fetchAll();
$sems = $pdo->query("SELECT SemesterID, Label, ProgramID FROM semesters WHERE IsActive=1 ORDER BY Label")->fetchAll();
$shifts = $pdo->query("SELECT ShiftID, Name FROM shifts ORDER BY ShiftID")->fetchAll();

$teachers = $pdo->query("SELECT UserID, Name, Designation FROM users WHERE Role='teacher' ORDER BY Name")->fetchAll();
$rooms = $pdo->query("SELECT RoomID, Name, Type FROM rooms WHERE IsActive=1 ORDER BY Name")->fetchAll();

// Teacher tab logic
$teacher_tt = [];
if ($tab === 'teacher' && isset($_GET['teacher_id'])) {
    $teacherId = (int)$_GET['teacher_id'];
    $stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, 
                           IF(t.Day='Friday' AND ts.FridayStartTime IS NOT NULL, ts.FridayStartTime, ts.StartTime) as StartTime,
                           IF(t.Day='Friday' AND ts.FridayEndTime IS NOT NULL, ts.FridayEndTime, ts.EndTime) as EndTime,
                           c.Name as CourseName, r.Name as RoomName,
                           p.Name as ProgName, d.Name as DeptName, s.Label as SemName
                           FROM timetable t 
                           JOIN time_slots ts ON t.SlotID=ts.SlotID
                           LEFT JOIN courses c ON t.CourseID=c.CourseID
                           LEFT JOIN rooms r ON t.RoomID=r.RoomID
                           LEFT JOIN programs p ON t.ProgramID=p.ProgramID
                           LEFT JOIN departments d ON t.DepartmentID=d.DepartmentID
                           LEFT JOIN semesters s ON t.SemesterID=s.SemesterID
                           WHERE t.TeacherID=? 
                           ORDER BY t.Day, ts.PeriodNumber");
    $stmt->execute([$teacherId]);
    $tt = $stmt->fetchAll();

    $stmtSub = $pdo->prepare("SELECT t.*, ts.PeriodNumber, 
                           IF(t.Day='Friday' AND ts.FridayStartTime IS NOT NULL, ts.FridayStartTime, ts.StartTime) as StartTime,
                           IF(t.Day='Friday' AND ts.FridayEndTime IS NOT NULL, ts.FridayEndTime, ts.EndTime) as EndTime,
                           c.Name as CourseName, r.Name as RoomName,
                           p.Name as ProgName, d.Name as DeptName, s.Label as SemName,
                           1 as IsSubstitute
                           FROM substitute_assignments sa
                           JOIN timetable t ON sa.TimetableID = t.TimetableID
                           JOIN time_slots ts ON t.SlotID=ts.SlotID
                           LEFT JOIN courses c ON t.CourseID=c.CourseID
                           LEFT JOIN rooms r ON t.RoomID=r.RoomID
                           LEFT JOIN programs p ON t.ProgramID=p.ProgramID
                           LEFT JOIN departments d ON t.DepartmentID=d.DepartmentID
                           LEFT JOIN semesters s ON t.SemesterID=s.SemesterID
                           WHERE sa.SubstituteTeacherID=? 
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
    $stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, c.Name AS CourseName, u.Name AS TeacherName, sec.Name AS SectionName,
           p.Name AS ProgName, d.Name AS DeptName, s.Label AS SemName,
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
        WHERE t.RoomID = ? AND sess.IsActive = 1 AND t.IsFree = 0
        ORDER BY FIELD(t.Day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), ts.PeriodNumber");
    $stmt->execute([$roomId]);
    $room_tt = $stmt->fetchAll();
}
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 min-w-0">
        <h2 class="text-2xl font-bold text-maroon mb-4">Timetable Viewer</h2>
        
        <div class="bg-white p-4 rounded shadow-sm border border-gray-200 mb-6 flex gap-4">
            <a href="?tab=class" class="px-4 py-2 font-semibold rounded <?= $tab==='class' ? 'bg-maroon text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Class-wise</a>
            <a href="?tab=teacher" class="px-4 py-2 font-semibold rounded <?= $tab==='teacher' ? 'bg-maroon text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Teacher-wise</a>
            <a href="?tab=room" class="px-4 py-2 font-semibold rounded <?= $tab==='room' ? 'bg-maroon text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Room-wise</a>
        </div>
        
        <div class="bg-white p-6 shadow-md rounded border border-gray-100">
            <?php if ($tab === 'class'): ?>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end mb-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Program</label>
                        <select id="p_id" class="w-full border p-2 rounded" onchange="loadSections()">
                            <option value="">Select</option>
                            <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Dept</label>
                        <select id="d_id" class="w-full border p-2 rounded" onchange="loadSections()">
                            <option value="">Select</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Semester</label>
                        <select id="sem_id" class="w-full border p-2 rounded" onchange="loadSections()">
                            <option value="">Select</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Shift</label>
                        <select id="sh_id" class="w-full border p-2 rounded" onchange="loadSections()">
                            <?php foreach($shifts as $sh): echo "<option value='{$sh['ShiftID']}'>{$sh['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Section</label>
                        <select id="sec_id" class="w-full border p-2 rounded">
                            <option value="">General Class (No Sec)</option>
                        </select>
                    </div>
                </div>
                <div class="mb-4">
                    <button onclick="loadClassTimetable()" class="bg-blue-600 text-white px-4 py-2 rounded shadow font-bold hover:bg-blue-700">Show Timetable</button>
                    <button onclick="if(document.getElementById('class_tt_container').innerHTML.trim()) downloadPDF('class_tt_container', 'Class_Timetable.pdf', true)" class="download-pdf-btn bg-gray-600 text-white px-4 py-2 rounded shadow font-bold hover:bg-gray-700 ml-2">Export PDF</button>
                </div>
                
                <div id="class_msg" class="text-red-500 font-bold mb-4"></div>
                
                <div id="class_tt_container" class="overflow-x-auto w-full"></div>
                
                <script>
                const depts = <?php echo json_encode($depts); ?>;
                const sems = <?php echo json_encode($sems); ?>;
                
                document.getElementById('p_id').addEventListener('change', function() {
                    let pid = this.value;
                    let deptSel = document.getElementById('d_id');
                    let semSel = document.getElementById('sem_id');
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
                            data.forEach(sec => secSel.innerHTML += `<option value="${sec.SectionID}">${sec.Name}</option>`);
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
                    msg.innerHTML = '';
                    c.innerHTML = '<p class="text-gray-500">Loading...</p>';
                    
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
                        
                        let html = `
                        <div class="bg-white p-2">
                            <h3 class="text-xl font-bold mb-4 text-maroon text-center">Class Timetable</h3>
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
                    });
                }
                </script>
            <?php endif; ?>
            
            <?php if ($tab === 'teacher'): ?>
                <form method="GET" class="flex gap-4 items-end mb-6">
                    <input type="hidden" name="tab" value="teacher">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Select Teacher</label>
                        <select name="teacher_id" class="w-64 border p-2 rounded" onchange="this.form.submit()">
                            <option value="">-- Choose --</option>
                            <?php foreach($teachers as $t): ?>
                                <option value="<?= $t['UserID'] ?>" <?= (isset($_GET['teacher_id']) && $_GET['teacher_id']==$t['UserID']) ? 'selected' : '' ?>><?= htmlspecialchars($t['Name']) ?> (<?= htmlspecialchars($t['Designation']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if(!empty($teacher_tt)): ?>
                        <button type="button" onclick="downloadPDF('teacher_tt_container', 'Teacher_Timetable.pdf', true)" class="download-pdf-btn bg-gray-600 text-white px-4 py-2 rounded shadow font-bold hover:bg-gray-700">Export PDF</button>
                    <?php endif; ?>
                </form>
                
                <?php if(isset($_GET['teacher_id'])): ?>
                    <?php
                    $selTeach = array_filter($teachers, function($x) { return $x['UserID'] == $_GET['teacher_id']; });
                    $selTeach = !empty($selTeach) ? array_shift($selTeach) : null;
                    ?>
                    <?php if($selTeach): ?>
                    <div id="teacher_tt_container" class="overflow-x-auto w-full bg-white p-2">
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
                                                <div class="text-[11px] text-gray-600 my-1"><?php echo htmlspecialchars(($slot['ProgName'] ?? '').' - '.($slot['DeptName'] ?? '')); ?></div>
                                                <div class="text-[12px] italic font-semibold text-gray-500">
                                                    <?php echo htmlspecialchars($slot['RoomName'] ?? ''); ?>
                                                    <?php if(!empty($slot['IsSubstitute'])) echo '<br><span class="text-orange-500">(Substitute)</span>'; ?>
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
                        <select name="room_id" class="w-64 border p-2 rounded" onchange="this.form.submit()">
                            <option value="">-- Choose --</option>
                            <?php foreach($rooms as $r): ?>
                                <option value="<?= $r['RoomID'] ?>" <?= (isset($_GET['room_id']) && $_GET['room_id']==$r['RoomID']) ? 'selected' : '' ?>><?= htmlspecialchars($r['Name']) ?> (<?= htmlspecialchars($r['Type']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if(!empty($room_tt)): ?>
                        <button type="button" onclick="downloadPDF('room_tt_container', 'Room_Timetable.pdf', true)" class="download-pdf-btn bg-gray-600 text-white px-4 py-2 rounded shadow font-bold hover:bg-gray-700">Export PDF</button>
                    <?php endif; ?>
                </form>
                
                <?php if(isset($_GET['room_id'])): ?>
                    <?php
                    $selRoom = array_filter($rooms, function($x) { return $x['RoomID'] == $_GET['room_id']; });
                    $selRoom = !empty($selRoom) ? array_shift($selRoom) : null;
                    ?>
                    <?php if($selRoom): ?>
                    <div id="room_tt_container" class="overflow-x-auto w-full bg-white p-2">
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
                                                <div class="text-[11px] text-gray-600 my-1"><?php echo htmlspecialchars($slot['TeacherName'] ?? ''); ?></div>
                                                <div class="text-[12px] italic font-semibold text-gray-500">
                                                    <?php echo htmlspecialchars(($slot['ProgName'] ?? '').' - '.($slot['DeptName'] ?? '').' - '.($slot['SemName'] ?? '')); ?>
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
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-gray-500 font-bold p-4 bg-gray-50 text-center rounded border">Please select a room.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
