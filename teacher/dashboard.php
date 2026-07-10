<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';
$teacherId = $_SESSION['user_id'];

// Get today's target date
$todayName = date('l'); 
$todayDate = date('Y-m-d');

// Fetch basic teacher data
$stmtUser = $pdo->prepare("SELECT u.*, d.MaxWeeklyPeriods, d.HODWeeklyPeriods, dep.Name as DeptName 
                           FROM users u 
                           LEFT JOIN designation_workload d ON u.Designation = d.Designation
                           LEFT JOIN departments dep ON u.HOD_DepartmentID = dep.DepartmentID
                           WHERE u.UserID = ?");
$stmtUser->execute([$teacherId]);
$teacherData = $stmtUser->fetch();
$maxPeriods = $teacherData['IsHOD'] ? $teacherData['HODWeeklyPeriods'] : $teacherData['MaxWeeklyPeriods'];

// 1. Total assigned courses
$coursesCount = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE TeacherID = ?")->execute([$teacherId]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
// Note: accurate query
$stmtC = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE TeacherID = ?");
$stmtC->execute([$teacherId]);
$coursesCount = $stmtC->fetchColumn();

// Fetch today's timetable
$stmtTT = $pdo->prepare("SELECT t.ShiftID, t.IsFree, t.TimetableID 
                         FROM timetable t 
                         WHERE t.TeacherID = ? AND t.Day = ? AND t.Status = 'published'");
$stmtTT->execute([$teacherId, $todayName]);
$todayTT = $stmtTT->fetchAll();

$mTeach = $mFree = $eTeach = $eFree = 0;
foreach($todayTT as $tt) {
    // Check if there is an active substitute for this slot
    $stmtSub = $pdo->prepare("SELECT 1 FROM substitute_assignments WHERE TimetableID = ? AND Status = 'active' AND ? BETWEEN FromDate AND ToDate");
    $stmtSub->execute([$tt['TimetableID'], $todayDate]);
    $hasSub = $stmtSub->fetchColumn();
    
    // If has sub, teacher is technically "free" from this responsibility
    if ($tt['ShiftID'] == 1) {
        if ($tt['IsFree'] || $hasSub) $mFree++; else $mTeach++;
    } else {
        if ($tt['IsFree'] || $hasSub) $eFree++; else $eTeach++;
    }
}

// Check if this teacher is actively substituting for OTHERS today
$stmtSubDuties = $pdo->prepare("SELECT t.ShiftID 
    FROM substitute_assignments sa
    JOIN timetable t ON sa.TimetableID = t.TimetableID
    WHERE sa.SubstituteTeacherID = ? AND sa.Status = 'active' 
    AND t.Day = ? AND ? BETWEEN sa.FromDate AND sa.ToDate");
$stmtSubDuties->execute([$teacherId, $todayName, $todayDate]);
$todaySubDuties = $stmtSubDuties->fetchAll();

foreach($todaySubDuties as $stt) {
    if ($stt['ShiftID'] == 1) {
        $mTeach++; $mFree = max(0, $mFree - 1);
    } else {
        $eTeach++; $eFree = max(0, $eFree - 1);
    }
}

$activeSubCount = $pdo->prepare("SELECT COUNT(*) FROM substitute_assignments WHERE SubstituteTeacherID = ? AND Status = 'active' AND ? BETWEEN FromDate AND ToDate");
$activeSubCount->execute([$teacherId, $todayDate]);
$activeSubCountVal = $activeSubCount->fetchColumn();

// Total assigned periods this week (excluding free periods)
$stmtWeek = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE TeacherID = ? AND IsFree = 0 AND Status = 'published'");
$stmtWeek->execute([$teacherId]);
$weekAssigned = (int)$stmtWeek->fetchColumn();

// Add their substitute duties to their weekly assigned count
$stmtWeekSub = $pdo->prepare("SELECT COUNT(*) FROM substitute_assignments sa
    JOIN timetable t ON sa.TimetableID = t.TimetableID
    WHERE sa.SubstituteTeacherID = ? AND sa.Status = 'active' 
    AND ? BETWEEN sa.FromDate AND sa.ToDate");
$stmtWeekSub->execute([$teacherId, $todayDate]);
$weekAssigned += (int)$stmtWeekSub->fetchColumn();

// Pending requests
$stmtReq = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE RequestedBy = ? AND Status IN ('pending_teacher', 'pending_admin')");
$stmtReq->execute([$teacherId]);
$pendingReq = $stmtReq->fetchColumn();

// Pending Leaves
$stmtLeave = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE TeacherID = ? AND Status = 'pending'");
$stmtLeave->execute([$teacherId]);
$pendingLeave = $stmtLeave->fetchColumn();

?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4">
    <!-- Sidebar -->
    <?php include '../includes/teacher_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Dashboard</h2>
        

        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Assigned Courses</h4>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $coursesCount; ?></p>
                </div>
                <div class="text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Morn Teaching (Today)</h4>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $mTeach; ?></p>
                </div>
                <div class="text-yellow-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Morn Free (Today)</h4>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $mFree; ?></p>
                </div>
                <div class="text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Eve Teaching (Today)</h4>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $eTeach; ?></p>
                </div>
                <div class="text-indigo-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Eve Free (Today)</h4>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $eFree; ?></p>
                </div>
                <div class="text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Weekly Load</h4>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $weekAssigned; ?> <span class="text-xl text-gray-500 font-medium">/ <?php echo $maxPeriods; ?></span></p>
                </div>
                <div class="text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Pending Requests</h4>
                    <p class="text-3xl font-bold <?= $pendingReq > 0 ? 'text-red-600' : 'text-gray-800' ?>"><?php echo $pendingReq; ?></p>
                </div>
                <div class="<?= $pendingReq > 0 ? 'text-red-400' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Pending Leaves</h4>
                    <p class="text-3xl font-bold <?= $pendingLeave > 0 ? 'text-amber-600' : 'text-gray-800' ?>"><?php echo $pendingLeave; ?></p>
                </div>
                <div class="<?= $pendingLeave > 0 ? 'text-amber-400' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                </div>
            </div>
            

            
            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-gray-500 text-sm font-semibold mb-1">Substitute Status</h4>
                    <p class="text-[20px] font-bold <?= $activeSubCountVal > 0 ? 'text-blue-600' : 'text-gray-800' ?>"><?php echo $activeSubCountVal > 0 ? $activeSubCountVal . ' Active Duty' : 'None Active'; ?></p>
                </div>
                <div class="<?= $activeSubCountVal > 0 ? 'text-blue-400' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                </div>
            </div>
            
            <?php if($teacherData['IsHOD']): ?>
            <div class="bg-[#a60b26] p-4 rounded-lg border border-[#8a0a20] shadow-sm flex justify-between items-center md:col-span-2">
                <div>
                    <h4 class="text-red-100 text-sm font-semibold mb-1">HOD of Department</h4>
                    <p class="text-2xl font-bold text-white"><?php echo htmlspecialchars($teacherData['DeptName']); ?></p>
                </div>
                <div class="text-red-200 opacity-80">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h3>
            <div class="flex flex-wrap items-center gap-3">
                <a href="timetable.php" class="bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 hover:border-gray-300 transition-colors px-4 py-2 rounded-md text-sm font-semibold flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    View Timetable
                </a>
                <a href="request_leave.php" class="bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 hover:border-gray-300 transition-colors px-4 py-2 rounded-md text-sm font-semibold flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                    Apply for Leave
                </a>
                <a href="request_free_change.php" class="bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 hover:border-gray-300 transition-colors px-4 py-2 rounded-md text-sm font-semibold flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                    Free Period Change Request
                </a>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
