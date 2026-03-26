<?php
require_once __DIR__ . '/config/db.php';
$activeSession = $pdo->query("SELECT Title FROM academic_sessions WHERE IsActive = 1 LIMIT 1")->fetchColumn();
if(!$activeSession) $activeSession = "Current Session";
?>
<?php include 'includes/header.php'; ?>
<!-- Decorative Dots Pattern Background -->
<div class="absolute inset-0 pointer-events-none flex justify-between z-0 overflow-hidden" style="opacity: 0.4;">
    <div class="w-[200px] h-[300px] mt-32 -ml-10" style="background-image: radial-gradient(#a60b26 1.5px, transparent 1.5px); background-size: 20px 20px;"></div>
    <div class="w-[200px] h-[300px] mt-[400px] -mr-10" style="background-image: radial-gradient(#a60b26 1.5px, transparent 1.5px); background-size: 20px 20px;"></div>
</div>

<!-- Decorative Soft Shape -->
<div class="fixed bottom-0 right-0 w-[600px] h-[600px] bg-red-50 rounded-full blur-[100px] pointer-events-none opacity-50 z-0 transform translate-x-1/3 translate-y-1/3"></div>

<div class="max-w-[1024px] w-[88%] mx-auto bg-white p-8 md:p-12 rounded-[24px] shadow-[0_10px_40px_rgb(0,0,0,0.04)] mt-10 relative z-10 border border-gray-50">
    
    <!-- Header Section -->
    <div class="flex items-center gap-5 mb-10 border-b border-gray-50 pb-8">
        <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center shrink-0">
            <svg class="w-8 h-8 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        <div>
            <h2 class="text-[26px] font-bold text-gray-900 tracking-tight leading-tight mb-2">Student Timetable Viewer <span class="text-sm font-semibold text-white bg-[#a60b26] px-2 py-1 rounded ml-2 align-middle"><?php echo htmlspecialchars($activeSession); ?></span></h2>
            <p class="text-gray-500 text-[15px]">Select your preferences to view the timetable for the active academic session</p>
        </div>
    </div>

    <!-- Form Grid Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-8 mb-10">
        
        <!-- Select Program -->
        <div>
            <label class="block text-[13px] font-bold text-gray-700 mb-2">Select Program</label>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-[#a60b26]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/></svg>
                </div>
                <select id="program_id" class="w-full border border-gray-200 bg-[#fcfdff] text-gray-700 font-medium rounded-xl py-3 pl-11 pr-4 appearance-none focus:outline-none focus:border-[#a60b26] focus:ring-1 focus:ring-[#a60b26] transition-all hover:bg-white" onchange="updateFilters()">
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
                <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-[#a60b26]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <select id="dept_id" class="w-full border border-gray-200 bg-[#fcfdff] text-gray-700 font-medium rounded-xl py-3 pl-11 pr-4 appearance-none focus:outline-none focus:border-[#a60b26] focus:ring-1 focus:ring-[#a60b26] transition-all hover:bg-white">
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
                <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-[#a60b26]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                </div>
                <select id="sem_id" class="w-full border border-gray-200 bg-[#fcfdff] text-gray-700 font-medium rounded-xl py-3 pl-11 pr-4 appearance-none focus:outline-none focus:border-[#a60b26] focus:ring-1 focus:ring-[#a60b26] transition-all hover:bg-white">
                    <option value="">-- Select --</option>
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
                <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-[#a60b26]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                </div>
                <select id="sec_id" class="w-full border border-gray-200 bg-[#fcfdff] text-gray-700 font-medium rounded-xl py-3 pl-11 pr-4 appearance-none focus:outline-none focus:border-[#a60b26] focus:ring-1 focus:ring-[#a60b26] transition-all hover:bg-white">
                    <option value="">No Section (General)</option>
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
                <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-[#a60b26]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <select id="shift" class="w-full border border-gray-200 bg-[#fcfdff] text-gray-700 font-medium rounded-xl py-3 pl-11 pr-4 appearance-none focus:outline-none focus:border-[#a60b26] focus:ring-1 focus:ring-[#a60b26] transition-all hover:bg-white">
                    <option value="">-- Select --</option>
                    <option value="1">Morning</option>
                    <option value="2">Evening</option>
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
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    Day-wise
                </button>
                <button type="button" id="btn_week" class="text-gray-600 flex-1 flex items-center justify-center gap-2 py-2 rounded-[10px] text-[14px] font-bold transition-all hover:bg-white/50" onclick="setViewType('week')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                    Week-wise
                </button>
            </div>
            <input type="hidden" id="view_type" value="day">
        </div>

        <!-- Select Day -->
        <div id="day_selector_wrapper">
            <label class="block text-[13px] font-bold text-gray-700 mb-2">Select Day</label>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-[#a60b26]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <select id="day" class="w-full border border-gray-200 bg-[#fcfdff] text-gray-700 font-medium rounded-xl py-3 pl-11 pr-4 appearance-none focus:outline-none focus:border-[#a60b26] focus:ring-1 focus:ring-[#a60b26] transition-all hover:bg-white">
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
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
            View Timetable
            <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
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
    
    <div class="mt-4 flex justify-end">
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
    let secSelect = document.getElementById('sec_id');
    
    secSelect.innerHTML = '<option value="">No Section (General)</option>';
    
    if(pid && did && sid) {
        fetch(`<?php echo $base_url; ?>/api/public.php?action=get_sections&program_id=${pid}&dept_id=${did}&semester_id=${sid}`)
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
            document.getElementById('printable_area').classList.add('hidden');
            document.getElementById('hol_title').innerText = data.message.split(' - ')[0];
            document.getElementById('hol_desc').innerText = data.message.split(' - ')[1];
        } else {
            document.getElementById('holiday_message').classList.add('hidden');
            document.getElementById('printable_area').classList.remove('hidden');
            renderTable(data);
        }
    });
}

function renderTable(data) {
    let table = document.getElementById('tt_table');
    document.getElementById('tt_title').innerText = `Timetable - ${data.view === 'day' ? data.day : 'Weekly'}`;
    
    if(data.view === 'day') {
        let html = `<thead><tr class="bg-gray-100"><th class="border p-2">Period</th><th class="border p-2">Time</th><th class="border p-2">Course</th><th class="border p-2">Teacher</th><th class="border p-2">Room</th></tr></thead><tbody>`;
        let shiftSlots = document.getElementById('shift').value == 1 ? [1,2,3,4,5,6] : [1,2,3,4,5,6]; // simplified
        // The time_slots should preferably matched via API, mapping here for simplicity based on prompt PRD.
        
        let periods = data.data; // assume ordered by period
        for(let i = 1; i <= 8; i++) {
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
        for(let i = 1; i <= 8; i++) {
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
                        html += `<td class="border p-2 text-sm"><b>${pData.CourseName}</b><br>${pData.TeacherName}<br><i>${pData.RoomName}</i></td>`;
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
<?php include 'includes/footer.php'; ?>
