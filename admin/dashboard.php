<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';

// Stats for dashboard
$teachersCount = $pdo->query("SELECT COUNT(*) FROM users WHERE Role = 'teacher'")->fetchColumn();
$programsCount = $pdo->query("SELECT COUNT(*) FROM programs")->fetchColumn();
$coursesCount = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$roomsCount = $pdo->query("SELECT COUNT(*) FROM rooms WHERE IsActive = 1")->fetchColumn();
$pendingRequests = $pdo->query("SELECT COUNT(*) FROM requests WHERE Status = 'pending_admin'")->fetchColumn();
$activeSubstitutes = $pdo->query("SELECT COUNT(*) FROM substitute_assignments WHERE Status = 'active'")->fetchColumn();

// Active Session
$activeSessionRow = $pdo->query("SELECT SessionID, Title FROM academic_sessions WHERE IsActive = 1 ORDER BY SessionID DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$activeSession      = $activeSessionRow ? $activeSessionRow['Title'] : 'None';
$activeSessionId    = $activeSessionRow ? $activeSessionRow['SessionID'] : null;
$activeSessionCount = $pdo->query("SELECT COUNT(*) FROM academic_sessions WHERE IsActive = 1")->fetchColumn();

// Today's Timetable Data
$todayDay = date('l');
$todayDateDisplay = date('j F Y');
$todayTimetable = [];

if ($activeSessionId) {
    $stmt = $pdo->prepare("
        SELECT 
            ts.StartTime, ts.EndTime, ts.FridayStartTime, ts.FridayEndTime,
            p.ShortCode as ProgramCode,
            sem.Label as SemesterName,
            sec.Name as SectionName,
            u.Name as TeacherName,
            r.Name as RoomName
        FROM timetable t
        JOIN time_slots ts ON t.SlotID = ts.SlotID
        LEFT JOIN programs p ON t.ProgramID = p.ProgramID
        LEFT JOIN semesters sem ON t.SemesterID = sem.SemesterID
        LEFT JOIN sections sec ON t.SectionID = sec.SectionID
        LEFT JOIN users u ON t.TeacherID = u.UserID
        LEFT JOIN rooms r ON t.RoomID = r.RoomID
        WHERE t.SessionID = ? AND t.Day = ? AND t.IsFree = 0
        ORDER BY ts.StartTime ASC
        LIMIT 10
    ");
    $stmt->execute([$activeSessionId, $todayDay]);
    $todayTimetable = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Pseudo-logger to fetch dynamic Recent Activity without structural DB changes
$recentFeed = [];
if (!empty($activeSession)) {
    $recentFeed[] = ['time' => time(), 'text' => "Academic session ".htmlspecialchars($activeSession)." is currently active."];
}

// Fetch newest teachers
$tList = $pdo->query("SELECT Name, CreatedAt FROM users WHERE Role='teacher' ORDER BY UserID DESC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
foreach($tList as $t) { $recentFeed[] = ['time' => strtotime($t['CreatedAt']), 'text' => "New teacher profile (" . htmlspecialchars($t['Name']) . ") added."]; }

// Fetch newest timetable requests
$rList = $pdo->query("SELECT Type, CreatedAt FROM requests ORDER BY RequestID DESC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
foreach($rList as $r) { 
    $tType = $r['Type'] === 'swap' ? 'swap' : 'free period change';
    $recentFeed[] = ['time' => strtotime($r['CreatedAt']), 'text' => "A new timetable $tType request was submitted."]; 
}

// Sort newest first
usort($recentFeed, function($a, $b) { return $b['time'] <=> $a['time']; });
$recentFeed = array_slice($recentFeed, 0, 5); // Keep top 5 latest
?>

<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4">
    <!-- Sidebar -->
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Dashboard</h2>
        
        <?php if ($activeSessionCount == 0): ?>
            <div class="bg-red-100 text-red-700 border border-red-300 rounded p-3 mb-6 font-semibold">
                ⚠ No active academic session is set. The public timetable and all views will show nothing until you activate a session in Manage Sessions.
            </div>
        <?php else: ?>
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-2 text-gray-700">
                <span class="font-semibold">Academic Session:</span>
                <div class="flex items-center gap-2">
                    <span class="text-gray-800 font-bold text-lg leading-none"><?php echo htmlspecialchars($activeSession); ?></span>
                    <span class="bg-green-100 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider">Active</span>
                    
                    <?php if ($activeSessionCount > 1): ?>
                        <span class="text-red-600 text-sm font-semibold bg-red-50 border border-red-200 px-3 py-1 rounded inline-flex items-center gap-1.5 ml-2">
                            ⚠ <?php echo $activeSessionCount; ?> sessions are active, only one should be
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics & Operations Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
            <!-- Teachers Card -->
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Total Teachers</h4>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $teachersCount; ?></p>
                </div>
                <div class="text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                </div>
            </div>

            <!-- Programs Card -->
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Total Programs</h4>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $programsCount; ?></p>
                </div>
                <div class="text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                </div>
            </div>

            <!-- Courses Card -->
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Total Courses</h4>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $coursesCount; ?></p>
                </div>
                <div class="text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                </div>
            </div>

            <!-- Rooms Card -->
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Total Rooms & Labs</h4>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $roomsCount; ?></p>
                </div>
                <div class="text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                </div>
            </div>

            <!-- Pending Requests Card -->
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Pending Requests</h4>
                    <p class="text-3xl font-bold <?= $pendingRequests > 0 ? 'text-red-600' : 'text-gray-800' ?>"><?php echo $pendingRequests; ?></p>
                </div>
                <div class="<?= $pendingRequests > 0 ? 'text-red-400' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                </div>
            </div>

            <!-- Active Substitutes Card -->
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Active Substitutes</h4>
                    <p class="text-3xl font-bold <?= $activeSubstitutes > 0 ? 'text-blue-600' : 'text-gray-800' ?>"><?php echo $activeSubstitutes; ?></p>
                </div>
                <div class="<?= $activeSubstitutes > 0 ? 'text-blue-400' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h3>
            <div class="flex flex-wrap items-center gap-3">
                <a href="teachers.php" class="bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 hover:border-gray-300 transition-colors px-4 py-2 rounded-md text-sm font-semibold flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                    Add Teacher
                </a>
                <a href="courses.php" class="bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 hover:border-gray-300 transition-colors px-4 py-2 rounded-md text-sm font-semibold flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                    Add Course
                </a>
                <a href="sections.php" class="bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 hover:border-gray-300 transition-colors px-4 py-2 rounded-md text-sm font-semibold flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    Add Section
                </a>
                <a href="timetable_manual.php" class="bg-[#a60b26] text-white border border-transparent hover:bg-[#8a0a20] shadow-sm transition-all sm:ml-auto px-5 py-2 rounded-md text-sm font-semibold flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    Generate Timetable
                </a>
            </div>
        </div>

        <!-- Today's Schedule -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-1">Today's Schedule &mdash; <?php echo $todayDateDisplay; ?></h3>
            <p class="text-sm text-gray-500 mb-6">Real-time classes scheduled for today.</p>
            
            <?php if (empty($todayTimetable)): ?>
                <div class="text-center py-10 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                    <p class="text-gray-500 font-semibold">No timetable has been generated for today.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-600 text-sm border-b border-t border-gray-200">
                                <th class="py-3 px-4 font-semibold w-1/4">Time</th>
                                <th class="py-3 px-4 font-semibold w-1/3">Class / Section</th>
                                <th class="py-3 px-4 font-semibold w-1/4">Teacher</th>
                                <th class="py-3 px-4 font-semibold">Room</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                            <?php foreach ($todayTimetable as $row): 
                                $timeStart = ($todayDay === 'Friday' && !empty($row['FridayStartTime'])) ? $row['FridayStartTime'] : $row['StartTime'];
                                $timeEnd = ($todayDay === 'Friday' && !empty($row['FridayEndTime'])) ? $row['FridayEndTime'] : $row['EndTime'];
                                $timeDisplay = date('H:i', strtotime($timeStart)) . '–' . date('H:i', strtotime($timeEnd));
                                
                                $classParts = [];
                                if (!empty($row['ProgramCode'])) $classParts[] = $row['ProgramCode'];
                                if (!empty($row['SemesterName'])) $classParts[] = $row['SemesterName'];
                                $classDisplay = implode(' - ', $classParts);
                                if (!empty($row['SectionName']) && $row['SectionName'] !== 'No Section (General)') {
                                    $classDisplay .= ' (' . $row['SectionName'] . ')';
                                }
                            ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="py-3 px-4 whitespace-nowrap text-gray-700 font-medium"><?php echo $timeDisplay; ?></td>
                                    <td class="py-3 px-4 text-gray-800 font-medium"><?php echo htmlspecialchars($classDisplay ?: '—'); ?></td>
                                    <td class="py-3 px-4 text-gray-600"><?php echo htmlspecialchars($row['TeacherName'] ?: '—'); ?></td>
                                    <td class="py-3 px-4 text-gray-600"><?php echo htmlspecialchars($row['RoomName'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Recent Activity</h3>
            <ul class="space-y-3">
                <?php if (empty($recentFeed)): ?>
                    <li class="text-sm text-gray-500">No recent activity found.</li>
                <?php else: ?>
                    <?php foreach ($recentFeed as $activity): ?>
                        <li class="flex items-start gap-3 text-sm text-gray-600">
                            <span class="mt-1.5 w-1.5 h-1.5 bg-gray-400 rounded-full flex-shrink-0"></span>
                            <div class="flex-1">
                                <span><?php echo $activity['text']; ?></span>
                                <span class="block text-xs text-gray-400 mt-0.5"><?php echo date('d M, Y h:i A', $activity['time']); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

    </div>
</div>
