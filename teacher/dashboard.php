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
                         WHERE t.TeacherID = ? AND t.Day = ?");
$stmtTT->execute([$teacherId, $todayName]);
$todayTT = $stmtTT->fetchAll();

$mTeach = $mFree = $eTeach = $eFree = 0;
foreach($todayTT as $tt) {
    // Check if there is an active substitute for this slot
    $stmtSub = $pdo->prepare("SELECT 1 FROM substitute_assignments WHERE TimetableID = ? AND Status = 'active' AND ? BETWEEN FromDate AND ToDate");
    $stmtSub->execute([$tt['TimetableID'], $todayDate]);
    $hasSub = $stmtSub->fetchColumn();
    
    // If has sub, teacher is technically "free" from this responsibility but let's count actual timetable
    if ($tt['ShiftID'] == 1) {
        if ($tt['IsFree']) $mFree++; else $mTeach++;
    } else {
        if ($tt['IsFree']) $eFree++; else $eTeach++;
    }
}

// Total assigned periods this week (excluding free periods)
$stmtWeek = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE TeacherID = ? AND IsFree = 0");
$stmtWeek->execute([$teacherId]);
$weekAssigned = $stmtWeek->fetchColumn();

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
<div class="max-w-7xl mx-auto flex gap-6 mt-4">
    <!-- Sidebar -->
    <aside class="w-64 bg-white p-4 shadow-md rounded">
        <h3 class="text-lg font-bold text-maroon mb-4">Teacher Menu</h3>
        <ul class="space-y-2">
            <li><a href="dashboard.php" class="block p-2 bg-gray-100 rounded text-maroon font-semibold">Dashboard</a></li>
            <li><a href="timetable.php" class="block p-2 hover:bg-gray-100 rounded">My Timetable</a></li>
            <li><a href="request_free_change.php" class="block p-2 hover:bg-gray-100 rounded">Free Change Request</a></li>
            <li><a href="request_swap.php" class="block p-2 hover:bg-gray-100 rounded">Swap Request</a></li>
            <li><a href="inbox_swap.php" class="block p-2 hover:bg-gray-100 rounded">Swap Inbox</a></li>
            <li><a href="request_leave.php" class="block p-2 hover:bg-gray-100 rounded">Leave Request</a></li>
            <li><a href="history_requests.php" class="block p-2 hover:bg-gray-100 rounded">Request History</a></li>
            <li><a href="history_substitute.php" class="block p-2 hover:bg-gray-100 rounded">Substitute History</a></li>
            <li><a href="notifications.php" class="block p-2 hover:bg-gray-100 rounded">Notifications (<span class="text-red-500 font-bold"><?php echo $unreadNotif; ?></span>)</a></li>
            <li><a href="profile.php" class="block p-2 hover:bg-gray-100 rounded">Profile</a></li>
        </ul>
    </aside>
    
    <!-- Main Content -->
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Teacher Dashboard</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-blue-500">
                <h4 class="text-gray-500 font-bold text-xs uppercase">Assigned Courses</h4>
                <p class="text-2xl font-bold"><?php echo $coursesCount; ?></p>
            </div>
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-green-500">
                <h4 class="text-gray-500 font-bold text-xs uppercase">Morn Teaching (Today)</h4>
                <p class="text-2xl font-bold"><?php echo $mTeach; ?></p>
            </div>
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-gray-400">
                <h4 class="text-gray-500 font-bold text-xs uppercase">Morn Free (Today)</h4>
                <p class="text-2xl font-bold"><?php echo $mFree; ?></p>
            </div>
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-green-500">
                <h4 class="text-gray-500 font-bold text-xs uppercase">Eve Teaching (Today)</h4>
                <p class="text-2xl font-bold"><?php echo $eTeach; ?></p>
            </div>
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-gray-400">
                <h4 class="text-gray-500 font-bold text-xs uppercase">Eve Free (Today)</h4>
                <p class="text-2xl font-bold"><?php echo $eFree; ?></p>
            </div>
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-maroon">
                <h4 class="text-gray-500 font-bold text-xs uppercase">Weekly Load</h4>
                <p class="text-xl font-bold"><?php echo $weekAssigned; ?> / <?php echo $maxPeriods; ?></p>
            </div>
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-yellow-500">
                <h4 class="text-gray-500 font-bold text-xs uppercase">Pending Requests</h4>
                <p class="text-2xl font-bold"><?php echo $pendingReq; ?></p>
            </div>
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-yellow-500">
                <h4 class="text-gray-500 font-bold text-xs uppercase">Pending Leaves</h4>
                <p class="text-2xl font-bold"><?php echo $pendingLeave; ?></p>
            </div>
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-red-500">
                <h4 class="text-gray-500 font-bold text-xs uppercase">Unread Notifications</h4>
                <p class="text-2xl font-bold"><?php echo $unreadNotif; ?></p>
            </div>
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-purple-500">
                <h4 class="text-gray-500 font-bold text-xs uppercase">Substitute Status</h4>
                <p class="text-lg font-bold">None Active</p>
            </div>
            
            <?php if($teacherData['IsHOD']): ?>
            <div class="bg-maroon text-white p-4 shadow-md rounded border-t-4 border-yellow-400">
                <h4 class="font-bold text-xs uppercase">HOD of Department</h4>
                <p class="text-lg font-bold"><?php echo htmlspecialchars($teacherData['DeptName']); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
