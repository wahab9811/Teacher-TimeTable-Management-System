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

// Unread Notifications
$stmtNotif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE TeacherID = ? AND IsRead = 0");
$stmtNotif->execute([$teacherId]);
$unreadNotif = $stmtNotif->fetchColumn();

?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4">
    <!-- Sidebar -->
    <?php include '../includes/teacher_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">My Dashboard</h2>
        

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Assigned Courses</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $coursesCount; ?></p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Morn Teaching (Today)</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $mTeach; ?></p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Morn Free (Today)</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $mFree; ?></p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Eve Teaching (Today)</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $eTeach; ?></p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Eve Free (Today)</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $eFree; ?></p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Weekly Load</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $weekAssigned; ?> / <?php echo $maxPeriods; ?></p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Pending Requests</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $pendingReq; ?></p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Pending Leaves</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $pendingLeave; ?></p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Unread Notifications</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $unreadNotif; ?></p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Substitute Status</h4>
                <p class="text-[20px] leading-[36px] font-bold"><?php echo $activeSubCountVal > 0 ? '<span class="text-maroon">' . $activeSubCountVal . ' Active Duty</span>' : '<span class="text-gray-800">None Active</span>'; ?></p>
            </div>
            
            <?php if($teacherData['IsHOD']): ?>
            <div class="bg-maroon text-white p-6 rounded-xl shadow-sm border border-red-900 hover:shadow-md transition-shadow">
                <h4 class="text-red-100 font-bold mb-1">HOD of Department</h4>
                <p class="text-xl font-bold text-white"><?php echo htmlspecialchars($teacherData['DeptName']); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h3>
            <div class="flex flex-wrap gap-3">
                <a href="timetable.php" class="bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 transition-colors px-4 py-2 rounded-md text-sm font-semibold">
                    View Timetable
                </a>
                <a href="request_leave.php" class="bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 transition-colors px-4 py-2 rounded-md text-sm font-semibold">
                    Apply for Leave
                </a>
                <a href="request_free_change.php" class="bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 transition-colors px-4 py-2 rounded-md text-sm font-semibold">
                    Free Period Change Request
                </a>

            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
