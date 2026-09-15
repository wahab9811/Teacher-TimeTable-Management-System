<?php
require_once __DIR__ . '/config/db.php';

// Fetch all calendar events
$stmt = $pdo->query("SELECT * FROM college_calendar ORDER BY Date ASC");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);



$calendarData = [];
$upcomingEvents = [];
$upcoming30Days = [];
$overviewStats = [
    'holiday' => 0,
    'academic' => 0,
    'examination' => 0,
    'event' => 0,
    'meeting' => 0,
    'other' => 0
];

$todayDt = date('Y-m-d');
$thirtyDaysFromNow = date('Y-m-d', strtotime('+30 days'));
$currentMonth = date('Y-m');

foreach ($events as $event) {
    // Generate dates array
    $dates = [$event['Date']];
    if (!empty($event['EndDate']) && $event['EndDate'] !== $event['Date']) {
        $start = new DateTime($event['Date']);
        $end = new DateTime($event['EndDate']);
        $end->modify('+1 day'); // include end date
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        $dates = [];
        foreach($period as $dt) {
            $dates[] = $dt->format('Y-m-d');
        }
    }

    foreach($dates as $d) {
        if(!isset($calendarData[$d])) {
            $calendarData[$d] = [];
        }
        $calendarData[$d][] = [
            'title' => $event['Title'],
            'category' => $event['Category'],
            'time' => $event['EventTime'],
            'desc' => $event['Description'],
            'image' => $event['ImagePath'],
            'original_start' => $event['Date'],
            'original_end' => $event['EndDate']
        ];
    }
    
    $latestDate = !empty($event['EndDate']) ? $event['EndDate'] : $event['Date'];
    
    // Pick upcoming events for right sidebar (max 4)
    if($latestDate >= $todayDt && count($upcomingEvents) < 4) {
        $upcomingEvents[] = $event;
    }
    
    // Pick upcoming events for table (next 30 days)
    if($latestDate >= $todayDt && $event['Date'] <= $thirtyDaysFromNow) {
        $upcoming30Days[] = $event;
    }
    
    // Count stats for current month Overview
    if(substr($event['Date'], 0, 7) === $currentMonth) {
        $overviewStats[$event['Category']]++;
    }
}
$calendarJson = json_encode($calendarData);
?>
<?php include 'includes/header.php'; ?>

<!-- Top Nav Spacing -->
<div class="h-8"></div>

<div class="max-w-[1400px] w-[96%] mx-auto mb-16 font-sans text-gray-800">
    
    <!-- Top Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <div class="flex items-center gap-5">
            <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center shrink-0">
                <svg class="w-8 h-8 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <div>
                <h1 class="text-[26px] font-bold text-gray-900 tracking-tight leading-tight mb-2">Academic Calendar</h1>
                <p class="text-gray-500 text-[15px]">Stay updated with all academic events, holidays and important dates.</p>
            </div>
        </div>

    </div>

    <!-- Main Grid Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        <!-- Left Column: Calendar Main Grid (Spans 3 cols) -->
        <div class="lg:col-span-3 flex flex-col gap-6">
            
            <!-- Calendar Card -->
            <div class="bg-white border border-gray-200 rounded shadow-md p-6">
                <!-- Toolbar -->
                <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
                    <div class="flex items-center gap-2">
                        <button id="prev-month" class="px-3 py-2 border border-gray-200 rounded-md bg-white hover:bg-gray-50 text-gray-600 focus:outline-none shadow-sm transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <button id="today-btn" class="px-4 py-2 border border-gray-200 rounded-md bg-white hover:bg-gray-50 text-gray-700 text-sm font-bold focus:outline-none shadow-sm transition-colors">Today</button>
                        <button id="next-month" class="px-3 py-2 border border-gray-200 rounded-md bg-white hover:bg-gray-50 text-gray-600 focus:outline-none shadow-sm transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                    
                    <div class="text-xl font-bold text-gray-800 flex items-center relative gap-1">
                        <input type="month" id="month-picker" class="font-bold text-gray-800 bg-transparent border-none text-center cursor-pointer focus:ring-0 outline-none hover:text-maroon transition-colors" />
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <div class="flex rounded-md shadow-sm border border-gray-200 overflow-hidden" id="viewToggleButtons">
                            <button id="btnViewMonth" onclick="setViewMode('month')" class="px-4 py-2 text-sm font-bold bg-[#991b1b] text-white focus:outline-none">Month</button>
                            <button id="btnViewWeek" onclick="setViewMode('week')" class="px-4 py-2 text-sm font-bold bg-white text-gray-600 hover:bg-gray-50 border-l border-gray-200 focus:outline-none">Week</button>
                            <button id="btnViewList" onclick="setViewMode('list')" class="px-4 py-2 text-sm font-bold bg-white text-gray-600 hover:bg-gray-50 border-l border-gray-200 focus:outline-none">List</button>
                        </div>
                        <div class="relative" id="filterDropdownContainer">
                            <button onclick="document.getElementById('filterDropdown').classList.toggle('hidden')" class="p-2 border border-gray-200 rounded-md bg-white hover:bg-gray-50 text-gray-600 focus:outline-none shadow-sm transition-colors relative z-10">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                            </button>
                            <div id="filterDropdown" class="hidden absolute right-0 top-full mt-2 w-48 bg-white border border-gray-100 rounded-xl shadow-[0_4px_25px_-5px_rgba(0,0,0,0.15)] z-50 overflow-hidden text-left py-2">
                                <a href="javascript:void(0)" onclick="setCategoryFilter('all')" class="block px-4 py-2.5 text-sm font-bold text-gray-800 hover:bg-gray-50 border-l-4 border-transparent hover:border-gray-500 transition-colors">Show All Events</a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="javascript:void(0)" onclick="setCategoryFilter('holiday')" class="block px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-50 border-l-4 border-transparent hover:border-red-500 transition-colors">Holidays</a>
                                <a href="javascript:void(0)" onclick="setCategoryFilter('academic')" class="block px-4 py-2 text-sm font-bold text-green-600 hover:bg-green-50 border-l-4 border-transparent hover:border-green-500 transition-colors">Academic</a>
                                <a href="javascript:void(0)" onclick="setCategoryFilter('examination')" class="block px-4 py-2 text-sm font-bold text-purple-600 hover:bg-purple-50 border-l-4 border-transparent hover:border-purple-500 transition-colors">Examinations</a>
                                <a href="javascript:void(0)" onclick="setCategoryFilter('event')" class="block px-4 py-2 text-sm font-bold text-blue-600 hover:bg-blue-50 border-l-4 border-transparent hover:border-blue-500 transition-colors">Events</a>
                                <a href="javascript:void(0)" onclick="setCategoryFilter('meeting')" class="block px-4 py-2 text-sm font-bold text-orange-500 hover:bg-orange-50 border-l-4 border-transparent hover:border-orange-500 transition-colors">Meetings</a>
                                <a href="javascript:void(0)" onclick="setCategoryFilter('other')" class="block px-4 py-2 text-sm font-bold text-yellow-600 hover:bg-yellow-50 border-l-4 border-transparent hover:border-yellow-500 transition-colors">Others</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grid Days -->
                <div id="calendar-days-header" class="grid grid-cols-7 border-t border-l border-gray-200">
                    <?php $days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']; foreach($days as $day): ?>
                    <div class="py-3 text-center text-sm font-bold text-gray-700 bg-white border-r border-b border-gray-200"><?php echo $day; ?></div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Grid Cells (Dynamic) -->
                <div id="calendar-grid" class="grid grid-cols-7 border-l border-gray-200"></div>

                <!-- Legends -->
                <div class="flex flex-wrap items-center justify-start gap-6 mt-6 pb-2 px-2">
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-red-600"></span><span class="text-xs font-bold text-gray-600">Holiday</span></div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-green-500"></span><span class="text-xs font-bold text-gray-600">Academic</span></div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-purple-500"></span><span class="text-xs font-bold text-gray-600">Examination</span></div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-blue-500"></span><span class="text-xs font-bold text-gray-600">Event</span></div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-orange-400"></span><span class="text-xs font-bold text-gray-600">Meeting</span></div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-yellow-400"></span><span class="text-xs font-bold text-gray-600">Other</span></div>
                </div>
            </div>
            

        </div>

        <!-- Right Column: Sidebar (Spans 1 col) -->
        <div class="lg:col-span-1 flex flex-col gap-6">
            
            <!-- Upcoming Events Sidebar -->
            <div class="bg-white border border-gray-200 rounded shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-base font-bold text-gray-900">Upcoming Events</h3>
                    <a href="#" class="text-[13px] font-bold text-red-600 hover:text-red-800">View All</a>
                </div>
                <div class="space-y-4">
                    <?php 
                        $sideBorder = [
                            'holiday' => 'border-red-500',
                            'academic' => 'border-green-500',
                            'examination' => 'border-purple-500',
                            'event' => 'border-blue-500',
                            'meeting' => 'border-orange-400',
                            'other' => 'border-yellow-400'
                        ];
                        $sideText = [
                            'holiday' => 'text-red-500',
                            'academic' => 'text-green-500',
                            'examination' => 'text-purple-500',
                            'event' => 'text-blue-500',
                            'meeting' => 'text-orange-500',
                            'other' => 'text-yellow-500'
                        ];
                        foreach($upcomingEvents as $ev): 
                        $border = $sideBorder[$ev['Category']] ?? 'border-blue-500';
                        $tColor = $sideText[$ev['Category']] ?? 'text-blue-500';
                        $d = strtotime($ev['Date']);
                        
                        $monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                        $fullDate = $monthNames[date('n', $d) - 1] . ' ' . date('j', $d) . ', ' . date('Y', $d);
                        $shortDate = date('D, M d, Y', $d);
                        
                        if(!empty($ev['EndDate']) && $ev['EndDate'] !== $ev['Date']) {
                            $ed = strtotime($ev['EndDate']);
                            $fullDate .= ' - ' . $monthNames[date('n', $ed) - 1] . ' ' . date('j', $ed) . ', ' . date('Y', $ed);
                            $shortDate .= ' - ' . date('M d', $ed);
                        }
                        $evObj = [
                            'title' => $ev['Title'],
                            'type' => $ev['Category'],
                            'desc' => $ev['Description'],
                            'image' => $ev['ImagePath'],
                            'fullDate' => $fullDate
                        ];
                    ?>
                    <div onclick='openModal(<?php echo json_encode($evObj); ?>)' class="relative bg-white border border-gray-100 rounded-lg p-3 pl-4 flex gap-4 cursor-pointer hover:shadow-md transition-shadow group overflow-hidden">
                        <!-- Left color strip -->
                        <div class="absolute left-0 top-0 bottom-0 w-1 <?php echo $border; ?> bg-white border-l-4"></div>
                        
                        <!-- Date Block -->
                        <div class="flex flex-col items-center justify-center shrink-0 w-10">
                            <span class="text-[10px] font-bold <?php echo $tColor; ?> uppercase tracking-wider"><?php echo date('M', $d); ?></span>
                            <span class="text-xl font-extrabold <?php echo $tColor; ?> leading-none mt-0.5"><?php echo date('d', $d); ?></span>
                        </div>
                        
                        <!-- Details -->
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-[13px] text-gray-900 leading-tight truncate group-hover:text-maroon transition-colors"><?php echo htmlspecialchars($ev['Title']); ?></h4>
                            <div class="flex justify-between items-end mt-1.5">
                                <span class="text-[11px] text-gray-500 font-medium whitespace-nowrap overflow-hidden text-ellipsis mr-2"><?php echo $shortDate; ?></span>
                                <span class="text-[11px] font-bold text-gray-600 bg-gray-50 px-1.5 py-0.5 rounded whitespace-nowrap shrink-0"><?php echo htmlspecialchars($ev['EventTime']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if(empty($upcomingEvents)): ?>
                    <p class="text-sm text-gray-500 text-center py-4">No upcoming events.</p>
                    <?php endif; ?>
                </div>
            </div>





        </div>
    </div>
</div>

<!-- Modal -->
<div id="eventModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-4 transition-opacity opacity-0 duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden transform scale-95 transition-transform duration-300 relative" id="eventModalContent">
        <div class="h-2 w-full" id="modalTopBar"></div>
        <div id="modalImageContainer" class="relative group hidden bg-gray-50 md:h-[300px]">
            <img id="modalImage" src="" class="w-full h-56 md:h-full object-contain" alt="Event Image">
            <button id="modalPrevBtn" class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/80 text-white rounded-full p-2 hidden transition-colors" onclick="prevModalImage()">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <button id="modalNextBtn" class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/80 text-white rounded-full p-2 hidden transition-colors" onclick="nextModalImage()">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
            <div id="modalDotsContainer" class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-2"></div>
        </div>
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span id="modalTypeBadge" class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-md mb-2 inline-block"></span>
                    <h3 class="text-2xl font-bold text-gray-900 leading-tight" id="modalTitle"></h3>
                    <p class="text-[14px] text-gray-500 font-medium mt-1.5" id="modalDate"></p>
                </div>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 rounded-lg p-2 transition-colors ml-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="text-[15px] leading-relaxed text-gray-700 bg-gray-50/80 border border-gray-100 p-5 rounded-xl whitespace-pre-wrap max-h-72 overflow-y-auto" id="modalDesc"></div>
        </div>
    </div>
</div>

<script>
const eventData = <?php echo $calendarJson; ?>;
let currentDate = new Date();
let currentViewMode = 'month';
let currentCategoryFilter = 'all';

function setCategoryFilter(cat) {
    currentCategoryFilter = cat;
    document.getElementById('filterDropdown').classList.add('hidden');
    renderCalendar();
}

// Close Dropdown when clicking outside
document.addEventListener('click', (e) => {
    const container = document.getElementById('filterDropdownContainer');
    if(container && !container.contains(e.target)) {
        document.getElementById('filterDropdown').classList.add('hidden');
    }
});

function setViewMode(mode) {
    currentViewMode = mode;
    const btnM = document.getElementById('btnViewMonth');
    const btnW = document.getElementById('btnViewWeek');
    const btnL = document.getElementById('btnViewList');
    const inactiveClass = 'px-4 py-2 text-sm font-bold bg-white text-gray-600 hover:bg-gray-50 border-l border-gray-200 focus:outline-none';
    const activeClass = 'px-4 py-2 text-sm font-bold bg-[#991b1b] text-white focus:outline-none';
    
    btnM.className = inactiveClass;
    btnW.className = inactiveClass;
    btnL.className = inactiveClass;
    btnM.classList.remove('border-l', 'border-gray-200'); // Month never has left border
    
    if(mode === 'month') { btnM.className = activeClass; btnM.classList.remove('border-l', 'border-gray-200'); }
    if(mode === 'week') btnW.className = activeClass;
    if(mode === 'list') btnL.className = activeClass;
    
    renderCalendar();
}

function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const today = new Date();
    
    // Formatting Title
    document.getElementById('month-picker').value = `${year}-${String(month+1).padStart(2, '0')}`;
    
    // For JS to match PHP categories config
    const categoryConfig = {
        'holiday': { dot: 'bg-red-600', border: 'border-red-600 bg-red-50 text-red-700', isPill: true },
        'academic': { dot: 'bg-green-500', border: 'border-green-500 bg-green-50 text-green-700', isPill: false },
        'examination': { dot: 'bg-purple-500', border: 'border-purple-400 bg-purple-50 text-purple-700', isPill: true },
        'event': { dot: 'bg-blue-500', border: 'border-blue-400 bg-blue-50 text-blue-700', isPill: true },
        'meeting': { dot: 'bg-orange-400', border: 'border-orange-400 bg-orange-50 text-orange-700', isPill: false },
        'other': { dot: 'bg-yellow-400', border: 'border-yellow-400 bg-yellow-50 text-yellow-700', isPill: false }
    };
    
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    const grid = document.getElementById('calendar-grid');
    grid.innerHTML = '';
    
    const header = document.getElementById('calendar-days-header');
    
    if (currentViewMode === 'list') {
        header.style.display = 'none';
        grid.className = 'flex flex-col border-l border-t border-gray-200 bg-white rounded-b-md mb-8';
        let hasEvents = false;
        
        Object.keys(eventData).sort().forEach(dateStr => {
            const [evY, evM, evD] = dateStr.split('-');
            if (parseInt(evY) === year && parseInt(evM) === month + 1) {
                eventData[dateStr].forEach(ev => {
                    let cat = ev.category || 'event';
                    if(currentCategoryFilter !== 'all' && cat !== currentCategoryFilter) return;
                    hasEvents = true;
                    let row = document.createElement('div');
                    row.className = 'flex flex-col md:flex-row p-4 border-b border-r border-gray-200 hover:bg-gray-50 transition-colors cursor-pointer gap-4';
                    
                    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                    
                    let originStart = new Date(ev.original_start);
                    let fullDateText = `${monthNames[originStart.getMonth()]} ${originStart.getDate()}, ${originStart.getFullYear()}`;
                    if(ev.original_end && ev.original_end !== ev.original_start) {
                        let originEnd = new Date(ev.original_end);
                        fullDateText += ` - ${monthNames[originEnd.getMonth()]} ${originEnd.getDate()}, ${originEnd.getFullYear()}`;
                    }
                    
                    let evObj = {...ev, fullDate: fullDateText};
                    row.onclick = () => openModal(evObj);
                    
                    let dateBox = document.createElement('div');
                    dateBox.className = 'min-w-[90px] flex flex-col items-center justify-center bg-gray-50 rounded-md border border-gray-100 p-2 shrink-0 h-20';
                    let rowDate = new Date(evY, parseInt(evM)-1, evD);
                    dateBox.innerHTML = `<span class="text-[11px] font-bold text-gray-500 uppercase tracking-widest">${rowDate.toLocaleDateString('en-US',{weekday:'short'})}</span><span class="text-2xl font-extrabold text-maroon">${evD}</span>`;
                    
                    let contentBox = document.createElement('div');
                    contentBox.className = 'flex-1';
                    
                    let conf = categoryConfig[ev.category || 'event'];
                    let badge = `<span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider mb-2 inline-block ${conf.border}">${ev.category}</span>`;
                    
                    contentBox.innerHTML = `${badge}<h4 class="text-base font-bold text-gray-900 leading-tight">${ev.title}</h4>
                        <p class="text-[13px] font-medium text-gray-500 mt-1 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            ${ev.time || 'All Day'}
                        </p>`;
                    
                    row.appendChild(dateBox);
                    row.appendChild(contentBox);
                    grid.appendChild(row);
                });
            }
        });
        
        if (!hasEvents) {
            grid.innerHTML = '<div class="p-10 text-center text-gray-500 font-medium border-b border-r border-gray-200">No events scheduled for this month.</div>';
        }
        return;
    }
    
    header.style.display = 'grid';
    grid.className = 'grid grid-cols-7 border-l border-gray-200';
    
    if (currentViewMode === 'week') {
        let weekStart = new Date(currentDate);
        weekStart.setDate(weekStart.getDate() - weekStart.getDay());
        
        for (let i = 0; i < 7; i++) {
            let currentCellDate = new Date(weekStart);
            currentCellDate.setDate(weekStart.getDate() + i);
            let cYear = currentCellDate.getFullYear();
            let cMonth = currentCellDate.getMonth();
            let cD = currentCellDate.getDate();
            
            let dateStr = `${cYear}-${String(cMonth+1).padStart(2,'0')}-${String(cD).padStart(2,'0')}`;
            let isTodayStr = (cYear === today.getFullYear() && cMonth === today.getMonth() && cD === today.getDate());
            
            let cell = document.createElement('div');
            cell.className = 'min-h-[300px] bg-white p-2 hover:bg-gray-50 transition-colors flex flex-col relative group border-r border-b border-gray-200 cursor-default';
            
            let dateContainer = document.createElement('div');
            dateContainer.className = 'flex items-center justify-between mb-3 border-b border-gray-100 pb-2';
            let dateSpan = document.createElement('span');
            dateSpan.className = `text-[15px] font-bold w-8 h-8 flex items-center justify-center rounded-full ${isTodayStr ? 'bg-red-600 text-white shadow' : 'text-gray-900 group-hover:bg-gray-100'}`;
            dateSpan.innerText = cD;
            
            let displayMonth = document.createElement('span');
            displayMonth.className = 'text-[11px] font-bold text-gray-400 uppercase tracking-wide mr-1';
            displayMonth.innerText = currentCellDate.toLocaleDateString('en-US', {month:'short'});
            
            dateContainer.appendChild(dateSpan);
            dateContainer.appendChild(displayMonth);
            cell.appendChild(dateContainer);
            
            let eventsContainer = document.createElement('div');
            eventsContainer.className = 'flex flex-col gap-2 flex-1 w-full';
            
            if (eventData[dateStr]) {
                eventData[dateStr].forEach(ev => {
                    let cat = ev.category || 'event';
                    if(currentCategoryFilter !== 'all' && cat !== currentCategoryFilter) return;
                    let badge = document.createElement('div');
                    let conf = categoryConfig[cat];
                    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                    
                    let originStart = new Date(ev.original_start);
                    let fullDateText = `${monthNames[originStart.getMonth()]} ${originStart.getDate()}, ${originStart.getFullYear()}`;
                    if(ev.original_end && ev.original_end !== ev.original_start) {
                        let originEnd = new Date(ev.original_end);
                        fullDateText += ` - ${monthNames[originEnd.getMonth()]} ${originEnd.getDate()}, ${originEnd.getFullYear()}`;
                    }
                    let evObj = {...ev, fullDate: fullDateText};
                    
                    if (conf.isPill) {
                        badge.className = `text-[11px] font-bold px-2 py-1.5 rounded shadow-sm transform hover:scale-[1.02] cursor-pointer transition-transform ${conf.border}`;
                        badge.innerText = ev.title;
                    } else {
                        badge.className = `flex items-start gap-1.5 px-1 py-1 cursor-pointer transform hover:scale-[1.02] transition-transform`; 
                        badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full shrink-0 mt-1.5 ${conf.dot}"></span><span class="text-[12px] font-bold text-gray-700 leading-tight">${ev.title}</span>`;
                    }
                    badge.title = "Click to view event details";
                    badge.onclick = () => openModal(evObj);
                    eventsContainer.appendChild(badge);
                });
            }
            cell.appendChild(eventsContainer);
            grid.appendChild(cell);
        }
        return;
    }
    
    // Fill empty start days
    for(let i=0; i<firstDay; i++) {
        let emptyDiv = document.createElement('div');
        emptyDiv.className = 'min-h-[120px] bg-gray-50/50 p-2 border-r border-b border-gray-200';
        grid.appendChild(emptyDiv);
    }
    
    // Fill valid days
    for(let d=1; d<=daysInMonth; d++) {
        let cell = document.createElement('div');
        cell.className = 'min-h-[120px] bg-white p-2 hover:bg-gray-50 transition-colors flex flex-col relative group border-r border-b border-gray-200 cursor-default';
        
        let isToday = (year === today.getFullYear() && month === today.getMonth() && d === today.getDate());
        let dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        
        // Date Number rendering
        let dateContainer = document.createElement('div');
        dateContainer.className = 'flex items-center gap-1 mb-2';
        
        // If today, use a solid colored circle like the reference red
        let dateSpan = document.createElement('span');
        dateSpan.className = `text-[13px] font-bold w-6 h-6 flex items-center justify-center rounded-full ${
            isToday ? 'bg-red-600 text-white shadow' : 'text-gray-900 group-hover:text-red-700'
        }`;
        dateSpan.innerText = d;
        dateContainer.appendChild(dateSpan);
        cell.appendChild(dateContainer);
        
        // Events rendering container
        let eventsContainer = document.createElement('div');
        eventsContainer.className = 'flex flex-col gap-1.5 flex-1 w-full';
        
        // Tiny dots container underneath date (only if we wanted dots next to date, but the reference shows pill / dot mixed in cell body)
        if (eventData[dateStr]) {
            eventData[dateStr].forEach(ev => {
                let cat = ev.category || 'event';
                if(currentCategoryFilter !== 'all' && cat !== currentCategoryFilter) return;
                
                let badge = document.createElement('div');
                let conf = categoryConfig[cat];
                
                const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                let originStart = new Date(ev.original_start);
                let fullDateText = `${monthNames[originStart.getMonth()]} ${originStart.getDate()}, ${originStart.getFullYear()}`;
                if(ev.original_end && ev.original_end !== ev.original_start) {
                    let originEnd = new Date(ev.original_end);
                    fullDateText += ` - ${monthNames[originEnd.getMonth()]} ${originEnd.getDate()}, ${originEnd.getFullYear()}`;
                }
                let evObj = {...ev, fullDate: fullDateText};
                
                if (conf.isPill) {
                    // Pill Style (like Mid Term Exams, Sports Gala)
                    badge.className = `text-[10px] font-bold px-2 py-1 rounded truncate shadow-sm transform hover:scale-[1.02] cursor-pointer transition-transform ${conf.border}`;
                    badge.innerText = ev.title;
                } else {
                    // Dot Style (just a dot on its own line like Academic or Meeting)
                    badge.className = `flex items-center gap-1.5 px-1 py-0.5 cursor-pointer transform hover:scale-[1.02] transition-transform truncate`;
                    badge.innerHTML = `<span class="w-[5px] h-[5px] rounded-full shrink-0 ${conf.dot}"></span><span class="text-[11px] font-bold text-gray-700 truncate leading-tight">${ev.title}</span>`;
                }
                
                badge.title = "Click to view event details";
                badge.onclick = () => openModal(evObj);
                
                eventsContainer.appendChild(badge);
            });
        }
        
        cell.appendChild(eventsContainer);
        grid.appendChild(cell);
    }
    
    // Fill empty end days
    const totalCells = firstDay + daysInMonth;
    const remaining = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
    for(let i=0; i<remaining; i++) {
        let emptyDiv = document.createElement('div');
        emptyDiv.className = 'min-h-[120px] bg-gray-50/50 p-2 border-r border-b border-gray-200';
        grid.appendChild(emptyDiv);
    }
}

// Modal handling logic
const modal = document.getElementById('eventModal');
const modalContent = document.getElementById('eventModalContent');
let currentModalImages = [];
let currentModalImageIndex = 0;

function updateModalImageDisplay() {
    const imgElement = document.getElementById('modalImage');
    const prevBtn = document.getElementById('modalPrevBtn');
    const nextBtn = document.getElementById('modalNextBtn');
    const dotsContainer = document.getElementById('modalDotsContainer');
    
    imgElement.src = currentModalImages[currentModalImageIndex];
    if(currentModalImages.length > 1) {
        prevBtn.classList.remove('hidden');
        nextBtn.classList.remove('hidden');
    } else {
        prevBtn.classList.add('hidden');
        nextBtn.classList.add('hidden');
    }
    dotsContainer.innerHTML = '';
    if(currentModalImages.length > 1) {
        currentModalImages.forEach((_, idx) => {
            const dot = document.createElement('div');
            dot.className = `w-2 h-2 rounded-full cursor-pointer transition-colors ${idx === currentModalImageIndex ? 'bg-white' : 'bg-white/50 hover:bg-white/80'}`;
            dot.onclick = () => {
                currentModalImageIndex = idx;
                updateModalImageDisplay();
            };
            dotsContainer.appendChild(dot);
        });
    }
}

function prevModalImage() {
    currentModalImageIndex = (currentModalImageIndex - 1 + currentModalImages.length) % currentModalImages.length;
    updateModalImageDisplay();
}

function nextModalImage() {
    currentModalImageIndex = (currentModalImageIndex + 1) % currentModalImages.length;
    updateModalImageDisplay();
}

function openModal(ev) {
    const isRed = (ev.category === 'holiday');
    document.getElementById('modalTopBar').className = `h-2 w-full ${isRed ? 'bg-red-600' : 'bg-[#1e1e1e]'}`;
    
    const badge = document.getElementById('modalTypeBadge');
    badge.innerText = ev.category || 'Event';
    badge.className = `px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-md mb-3 inline-block ${isRed ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-700'}`;
    
    document.getElementById('modalTitle').innerText = ev.title;
    document.getElementById('modalDate').innerText = ev.fullDate + (ev.time ? ' • ' + ev.time : '');
    document.getElementById('modalDesc').innerText = ev.desc || '-';
    
    const imgContainer = document.getElementById('modalImageContainer');
    if(ev.image) {
        currentModalImages = ev.image.split(',');
        currentModalImageIndex = 0;
        updateModalImageDisplay();
        imgContainer.classList.remove('hidden');
    } else {
        currentModalImages = [];
        imgContainer.classList.add('hidden');
    }
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modalContent.classList.remove('scale-95');
    }, 10);
}

function closeModal() {
    modal.classList.add('opacity-0');
    modalContent.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }, 300);
}

modal.addEventListener('click', (e) => {
    if(e.target === modal) closeModal();
});

document.getElementById('prev-month').addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar();
});
document.getElementById('next-month').addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar();
});
document.getElementById('today-btn').addEventListener('click', () => {
    currentDate = new Date();
    renderCalendar();
});
document.getElementById('month-picker').addEventListener('change', (e) => {
    if (e.target.value) {
        const [y, m] = e.target.value.split('-');
        currentDate = new Date(y, m - 1, 1);
        renderCalendar();
    }
});

// Initial Render
renderCalendar();
</script>
<?php include 'includes/footer.php'; ?>
