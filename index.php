<?php
require_once __DIR__ . '/config/db.php';
$activeSession = $pdo->query("SELECT Title FROM academic_sessions WHERE IsActive = 1 LIMIT 1")->fetchColumn();
if(!$activeSession) $activeSession = "Current Session";

$report_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO student_reports (StudentName, ProgramDept, IssueType, Message) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['student_name'], 
            $_POST['program_details'], 
            $_POST['issue_type'], 
            $_POST['message']
        ]);
        $report_msg = "<div class='max-w-[1024px] w-[88%] mx-auto bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mt-4 shadow-sm font-semibold'>Thank you! Your issue has been safely reported to the administration.</div>";
    } catch (PDOException $e) {
        $report_msg = "<div class='max-w-[1024px] w-[88%] mx-auto bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4 shadow-sm font-semibold'>Error submitting report. Please try again.</div>";
    }
}
?>
<?php include 'includes/header.php'; ?>
<?= $report_msg ?>


<div class="max-w-[1024px] w-[88%] mx-auto bg-white p-8 md:p-12 rounded shadow-md mt-4 relative z-10">
    
    <!-- Header Section -->
    <div class="flex items-center gap-5 mb-10 border-b border-gray-50 pb-8">
        <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center shrink-0">
            <svg class="w-8 h-8 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        <div class="flex-1">
            <h2 class="text-[26px] font-bold text-gray-900 tracking-tight leading-tight mb-2">Student Timetable Viewer <span class="text-sm font-semibold text-white bg-[#a60b26] px-2 py-1 rounded ml-2 align-middle"><?php echo htmlspecialchars($activeSession); ?></span></h2>
            <p class="text-gray-500 text-[15px]">Select your preferences to view the timetable for the active academic session</p>
        </div>
        <button type="button" class="flex items-center gap-2 bg-white text-[#a60b26] border border-gray-200 px-4 py-2 rounded-md text-sm font-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#reportIssueModal">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            Report Issue
        </button>
    </div>

    <!-- Form Grid Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-8 mb-10">
        
        <!-- Select Program -->
        <div>
            <label class="block text-[13px] font-bold text-gray-700 mb-2">Select Program</label>
            <div class="relative group">
                <select id="program_id" class="w-full border border-gray-200 bg-[#fcfdff] text-gray-700 font-medium rounded-xl py-3 pl-4 pr-4 appearance-none focus:outline-none focus:border-[#a60b26] focus:ring-1 focus:ring-[#a60b26] transition-all hover:bg-white" onchange="updateFilters()">
                    <option value="">-- Select --</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
            </div>
        </div>

        <!-- Select Group/Department -->
        <div>
            <label id="lbl_dept" class="block text-[13px] font-bold text-gray-700 mb-2">Select Group/Department</label>
            <div class="relative group">
                <select id="dept_id" class="w-full border border-gray-200 bg-[#fcfdff] text-gray-700 font-medium rounded-xl py-3 pl-4 pr-4 appearance-none focus:outline-none focus:border-[#a60b26] focus:ring-1 focus:ring-[#a60b26] transition-all hover:bg-white">
                    <option value="">-- Select --</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
            </div>
        </div>

        <!-- Select Year/Semester -->
        <div>
            <label id="lbl_sem" class="block text-[13px] font-bold text-gray-700 mb-2">Select Year/Semester</label>
            <div class="relative group">
                <select id="sem_id" class="w-full border border-gray-200 bg-[#fcfdff] text-gray-700 font-medium rounded-xl py-3 pl-4 pr-4 appearance-none focus:outline-none focus:border-[#a60b26] focus:ring-1 focus:ring-[#a60b26] transition-all hover:bg-white">
                    <option value="">-- Select --</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
            </div>
        </div>

        <!-- Select Shift -->
        <div>
            <label class="block text-[13px] font-bold text-gray-700 mb-2">Select Shift</label>
            <div class="relative group">
                <select id="shift" class="w-full border border-gray-200 bg-[#fcfdff] text-gray-700 font-medium rounded-xl py-3 pl-4 pr-4 appearance-none focus:outline-none focus:border-[#a60b26] focus:ring-1 focus:ring-[#a60b26] transition-all hover:bg-white">
                    <option value="">-- Select --</option>
                    <option value="1">Morning</option>
                    <option value="2">Evening</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
            </div>
        </div>

        <!-- Select Section -->
        <div>
            <label class="block text-[13px] font-bold text-gray-700 mb-2">Select Section</label>
            <div class="relative group">
                <select id="sec_id" class="w-full border border-gray-200 bg-[#fcfdff] text-gray-700 font-medium rounded-xl py-3 pl-4 pr-4 appearance-none focus:outline-none focus:border-[#a60b26] focus:ring-1 focus:ring-[#a60b26] transition-all hover:bg-white">
                    <option value="">No Section (General)</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
            </div>
        </div>

        <!-- View Type Segment -->
        <div>
            <label class="block text-[13px] font-bold text-gray-700 mb-2">View Type</label>
            <div class="bg-[#f0f2f5] p-1 rounded-[14px] flex">
                <button type="button" id="btn_day" class="bg-[#a60b26] text-white flex-1 flex items-center justify-center gap-2 py-2 rounded-[10px] text-[14px] font-bold shadow-sm transition-all" onclick="setViewType('day')">
                    Day-wise
                </button>
                <button type="button" id="btn_week" class="text-gray-600 flex-1 flex items-center justify-center gap-2 py-2 rounded-[10px] text-[14px] font-bold transition-all hover:bg-white/50" onclick="setViewType('week')">
                    Week-wise
                </button>
            </div>
            <input type="hidden" id="view_type" value="day">
        </div>

        <!-- Select Day -->
        <div id="day_selector_wrapper">
            <label class="block text-[13px] font-bold text-gray-700 mb-2">Select Day</label>
            <div class="relative group">
                <select id="day" class="w-full border border-gray-200 bg-[#fcfdff] text-gray-700 font-medium rounded-xl py-3 pl-4 pr-4 appearance-none focus:outline-none focus:border-[#a60b26] focus:ring-1 focus:ring-[#a60b26] transition-all hover:bg-white">
                    <option value="Today">Today</option>
                    <option value="Tomorrow">Tomorrow</option>
                    <option value="Yesterday">Yesterday</option>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
            </div>
        </div>

    </div>

    <!-- Divider Line -->
    <hr class="border-gray-100 mb-8 mt-2">

    <!-- View Button -->
    <div class="flex justify-center">
        <button id="view_btn" class="bg-[#a60b26] text-white px-8 py-3.5 rounded-xl font-bold flex items-center gap-2 text-[15.5px] transition-all duration-300 hover:bg-[#8a0a20] hover:shadow-[0_4px_15px_rgba(166,11,38,0.25)] opacity-50 cursor-not-allowed" disabled onclick="fetchTimetable()">
            View Timetable
        </button>
    </div>
</div>

<div id="timetable_container" class="max-w-7xl mx-auto mt-6 hidden">
    <div id="holiday_message" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <strong id="hol_title" class="font-bold"></strong> - <span id="hol_desc"></span>
    </div>
    
    <!-- PDF Header template (hidden by default) -->
    <div id="pdf-header-template" style="display:none;" class="mb-4 text-center">
        <h1 class="text-2xl font-bold text-maroon">GCB SKP</h1>
        <p>Govt. Graduate College, Civil Lines, Sheikhupura</p>
        <p>Email: info@gcbskp.edu.pk | Phone: +92-56-3783030</p>
        <hr class="my-2 border-maroon">
    </div>

    <div id="printable_area" class="bg-white p-6 rounded shadow-md">
        <h3 id="tt_title" class="text-xl font-bold text-maroon mb-4">Timetable</h3>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse border border-gray-300" id="tt_table">
                <!-- Injected via JS -->
            </table>
        </div>
    </div>
    
    <div id="pdf_btn_wrapper" class="mt-4 flex justify-end">
        <button class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700 download-pdf-btn" onclick="downloadTimetablePDF()">Download PDF</button>
    </div>
</div>

<script>
let stateData = { programs: [], departments: [], semesters: [] };

document.addEventListener('DOMContentLoaded', () => {
    fetch('<?php echo $base_url; ?>/api/public.php?action=get_filters')
        .then(res => res.json())
        .then(data => {
            stateData = data;
            let progSelect = document.getElementById('program_id');
            data.programs.forEach(p => {
                let opt = document.createElement('option');
                opt.value = p.ProgramID;
                opt.textContent = p.Name;
                progSelect.appendChild(opt);
            });
        });
        checkFormValidity();
        document.querySelectorAll('select').forEach(s => {
            s.addEventListener('change', checkFormValidity);
        });
        
        document.getElementById('program_id').addEventListener('change', updateSections);
        document.getElementById('dept_id').addEventListener('change', updateSections);
        document.getElementById('sem_id').addEventListener('change', updateSections);
        document.getElementById('shift').addEventListener('change', updateSections);
});

function updateFilters() {
    let progId = document.getElementById('program_id').value;
    let prog = stateData.programs.find(p => p.ProgramID == progId);
    
    let deptSelect = document.getElementById('dept_id');
    let semSelect = document.getElementById('sem_id');
    deptSelect.innerHTML = '<option value="">-- Select --</option>';
    semSelect.innerHTML = '<option value="">-- Select --</option>';
    
    if(!prog) return;
    
    document.getElementById('lbl_dept').innerText = prog.PeriodUnit === 'year' ? 'Select Group' : 'Select Department';
    document.getElementById('lbl_sem').innerText = prog.PeriodUnit === 'year' ? 'Select Year' : 'Select Semester';

    stateData.departments.filter(d => d.ProgramID == progId).forEach(d => {
        let opt = document.createElement('option');
        opt.value = d.DepartmentID;
        opt.textContent = d.Name;
        deptSelect.appendChild(opt);
    });

    stateData.semesters.filter(s => s.ProgramID == progId).forEach(s => {
        let opt = document.createElement('option');
        opt.value = s.SemesterID;
        opt.textContent = s.Label;
        semSelect.appendChild(opt);
    });
}

function updateSections() {
    let pid = document.getElementById('program_id').value;
    let did = document.getElementById('dept_id').value;
    let sid = document.getElementById('sem_id').value;
    let sh = document.getElementById('shift').value;
    let secSelect = document.getElementById('sec_id');
    
    secSelect.innerHTML = '<option value="">No Section (General)</option>';
    
    if(pid && did && sid && sh) {
        fetch(`<?php echo $base_url; ?>/api/public.php?action=get_sections&program_id=${pid}&dept_id=${did}&semester_id=${sid}&shift_id=${sh}`)
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

function setViewType(type) {
    document.getElementById('view_type').value = type;
    if(type === 'day') {
        document.getElementById('btn_day').className = "bg-[#a60b26] text-white flex-1 flex items-center justify-center gap-2 py-2 rounded-[10px] text-[14px] font-bold shadow-sm transition-all";
        document.getElementById('btn_week').className = "text-gray-600 flex-1 flex items-center justify-center gap-2 py-2 rounded-[10px] text-[14px] font-bold transition-all hover:bg-white/50";
        document.getElementById('day_selector_wrapper').classList.remove('hidden');
    } else {
        document.getElementById('btn_week').className = "bg-[#a60b26] text-white flex-1 flex items-center justify-center gap-2 py-2 rounded-[10px] text-[14px] font-bold shadow-sm transition-all";
        document.getElementById('btn_day').className = "text-gray-600 flex-1 flex items-center justify-center gap-2 py-2 rounded-[10px] text-[14px] font-bold transition-all hover:bg-white/50";
        document.getElementById('day_selector_wrapper').classList.add('hidden');
    }
}

function checkFormValidity() {
    let p = document.getElementById('program_id').value;
    let d = document.getElementById('dept_id').value;
    let s = document.getElementById('sem_id').value;
    let sh = document.getElementById('shift').value;
    
    let btn = document.getElementById('view_btn');
    if(p && d && s && sh) {
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
    }
}

function fetchTimetable() {
    let p = document.getElementById('program_id').value;
    let d = document.getElementById('dept_id').value;
    let s = document.getElementById('sem_id').value;
    let sh = document.getElementById('shift').value;
    let sec = document.getElementById('sec_id').value;
    let v = document.getElementById('view_type').value;
    let day = document.getElementById('day').value;
    
    fetch(`<?php echo $base_url; ?>/api/public.php?action=get_timetable&program_id=${p}&dept_id=${d}&sem_id=${s}&shift_id=${sh}&sec_id=${sec}&view=${v}&day=${day}`)
    .then(res => res.json())
    .then(data => {
        document.getElementById('timetable_container').classList.remove('hidden');
        if(data.error === 'holiday') {
            document.getElementById('holiday_message').classList.remove('hidden');
            document.getElementById('holiday_message').className = "bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4";
            document.getElementById('printable_area').classList.add('hidden');
            document.getElementById('pdf_btn_wrapper').classList.add('hidden');
            document.getElementById('hol_title').innerText = data.message.split(' - ')[0];
            document.getElementById('hol_desc').innerText = data.message.split(' - ')[1];
        } else if(data.error === 'no_active_session') {
            document.getElementById('holiday_message').classList.remove('hidden');
            document.getElementById('holiday_message').className = "bg-amber-100 border border-amber-400 text-amber-700 px-4 py-3 rounded mb-4 shadow-sm";
            document.getElementById('printable_area').classList.add('hidden');
            document.getElementById('pdf_btn_wrapper').classList.add('hidden');
            document.getElementById('hol_title').innerText = "System Notice";
            document.getElementById('hol_desc').innerText = data.message;
        } else if(data.empty === true) {
            document.getElementById('holiday_message').classList.remove('hidden');
            document.getElementById('holiday_message').className = "bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4 shadow-sm";
            document.getElementById('printable_area').classList.add('hidden');
            document.getElementById('pdf_btn_wrapper').classList.add('hidden');
            document.getElementById('hol_title').innerText = "Empty Timetable";
            document.getElementById('hol_desc').innerText = "Timetable not published yet for this class.";
        } else {
            document.getElementById('holiday_message').classList.add('hidden');
            document.getElementById('printable_area').classList.remove('hidden');
            document.getElementById('pdf_btn_wrapper').classList.remove('hidden');
            renderTable(data);
        }
    });
}

function renderTable(data) {
    let table = document.getElementById('tt_table');
    
    let prog = document.getElementById('program_id').options[document.getElementById('program_id').selectedIndex]?.text || '';
    let dept = document.getElementById('dept_id').options[document.getElementById('dept_id').selectedIndex]?.text || '';
    let sem = document.getElementById('sem_id').options[document.getElementById('sem_id').selectedIndex]?.text || '';
    let shift = document.getElementById('shift').options[document.getElementById('shift').selectedIndex]?.text || '';
    let sec = document.getElementById('sec_id').options[document.getElementById('sec_id').selectedIndex]?.text || '';
    
    let secStr = (sec && !sec.includes('No Section')) ? ` ${sec}` : '';
    let fullTitle = `${prog} ${dept} ${sem} (${shift})${secStr} - ${data.view === 'day' ? data.day : 'Weekly'} Timetable`;
    
    document.getElementById('tt_title').innerText = fullTitle;
    
    if(data.view === 'day') {
        let html = `<thead><tr class="bg-gray-100"><th class="border p-2">Period</th><th class="border p-2">Time</th><th class="border p-2">Course</th><th class="border p-2">Teacher</th><th class="border p-2">Room</th></tr></thead><tbody>`;
        let shiftSlots = document.getElementById('shift').value == 1 ? [1,2,3,4,5,6] : [1,2,3,4,5,6]; // simplified
        // The time_slots should preferably matched via API, mapping here for simplicity based on prompt PRD.
        
        let periods = data.data; // assume ordered by period
        let maxPeriod = 6; // default minimum
        periods.forEach(p => { if (parseInt(p.PeriodNumber) > maxPeriod) maxPeriod = parseInt(p.PeriodNumber); });
        for(let i = 1; i <= maxPeriod; i++) {
            let pData = periods.find(p => p.PeriodNumber == i);
            if(pData) {
                if(pData.IsFree == 1) {
                    html += `<tr class="free-period-row"><td class="border p-2 text-center">${i}</td><td class="border p-2">${pData.StartTime.substring(0,5)} - ${pData.EndTime.substring(0,5)}</td><td class="border p-2" colspan="3">Free</td></tr>`;
                } else {
                    html += `<tr><td class="border p-2 text-center">${i}</td><td class="border p-2">${pData.StartTime.substring(0,5)} - ${pData.EndTime.substring(0,5)}</td><td class="border p-2">${pData.CourseName}</td><td class="border p-2">${pData.TeacherName}</td><td class="border p-2">${pData.RoomName}</td></tr>`;
                }
            } else {
                html += `<tr class="free-period-row"><td class="border p-2 text-center">${i}</td><td class="border p-2">--</td><td class="border p-2" colspan="3">Not Assigned</td></tr>`;
            }
        }
        html += `</tbody>`;
        table.innerHTML = html;
    } else {
        // Week view
        let days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        let html = `<thead><tr class="bg-gray-100"><th class="border p-2">Period</th><th class="border p-2">Time</th>`;
        days.forEach(d => html += `<th class="border p-2">${d}</th>`);
        html += `</tr></thead><tbody>`;
        
        let periods = data.data;
        let maxPeriod = 6;
        periods.forEach(p => { if (parseInt(p.PeriodNumber) > maxPeriod) maxPeriod = parseInt(p.PeriodNumber); });
        for(let i = 1; i <= maxPeriod; i++) {
            let periodTime = "--";
            let anySlot = periods.find(p => p.PeriodNumber == i);
            if(anySlot && anySlot.StartTime && anySlot.EndTime) {
                periodTime = `${anySlot.StartTime.substring(0,5)} - ${anySlot.EndTime.substring(0,5)}`;
            }
            html += `<tr><td class="border p-2 text-center font-bold">${i}</td><td class="border p-2 whitespace-nowrap text-sm">${periodTime}</td>`;
            
            days.forEach(day => {
                let pData = periods.find(p => p.PeriodNumber == i && p.Day == day);
                if(pData) {
                    if(pData.IsFree == 1) {
                        html += `<td class="border p-2 free-period-row text-center">Free</td>`;
                    } else {
                        let timeStr = "";
                        if (day === 'Friday' && pData.StartTime && pData.EndTime) {
                            timeStr = `<div class="text-[10px] text-maroon font-bold mb-1 border-b border-gray-200 pb-1">${pData.StartTime.substring(0,5)} - ${pData.EndTime.substring(0,5)}</div>`;
                        }
                        html += `<td class="border p-2 text-sm">${timeStr}<b>${pData.CourseName}</b><br>${pData.TeacherName}<br><i>${pData.RoomName}</i></td>`;
                    }
                } else {
                        html += `<td class="border p-2 bg-gray-50 text-center text-gray-400">-</td>`;
                }
            });
            html += `</tr>`;
        }
        html += `</tbody>`;
        table.innerHTML = html;
    }
}

function downloadTimetablePDF() {
    let view = document.getElementById('view_type').value;
    downloadPDF('timetable_container', 'Timetable.pdf', view === 'week');
}
</script>
<!-- Report Issue Modal -->
<div class="modal fade" id="reportIssueModal" tabindex="-1" aria-labelledby="reportIssueModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
        <form method="POST">
            <div class="modal-header bg-gray-50 border-b border-gray-100">
                <h5 class="modal-title font-bold text-gray-800" id="reportIssueModalLabel">Report Timetable Issue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-5 text-left">
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Your Name</label>
                    <input type="text" name="student_name" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-[#a60b26] focus:border-[#a60b26] outline-none">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Class / Department</label>
                    <input type="text" name="program_details" placeholder="e.g. BSCS 3rd Semester Morning" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-[#a60b26] focus:border-[#a60b26] outline-none">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Issue Type</label>
                    <select name="issue_type" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-[#a60b26] focus:border-[#a60b26] outline-none">
                        <option value="">-- Select --</option>
                        <option value="Clash (Two classes at same time)">Clash (Two classes at same time)</option>
                        <option value="Missing Class">Missing Class</option>
                        <option value="Wrong Room/Teacher">Wrong Room/Teacher</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Message / Details</label>
                    <textarea name="message" required rows="3" maxlength="500" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-[#a60b26] focus:border-[#a60b26] outline-none resize-y" placeholder="Describe the issue... (Max 500 characters)"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-gray-50 border-t border-gray-100">
                <button type="button" class="px-4 py-2 bg-gray-200 text-gray-700 font-bold rounded hover:bg-gray-300 transition-colors" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="submit_report" class="px-4 py-2 bg-[#a60b26] text-white font-bold rounded hover:bg-red-800 transition-colors">Submit Report</button>
            </div>
        </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
