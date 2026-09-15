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
$activeSession      = $pdo->query("SELECT Title FROM academic_sessions WHERE IsActive = 1 ORDER BY SessionID DESC LIMIT 1")->fetchColumn();
$activeSessionCount = $pdo->query("SELECT COUNT(*) FROM academic_sessions WHERE IsActive = 1")->fetchColumn();


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
            <div class="text-gray-700 mb-6 font-semibold">
                Active Session: <?php echo htmlspecialchars($activeSession); ?>
                <?php if ($activeSessionCount > 1): ?>
                    <span class="text-red-600 text-sm ml-2">(Warning: <?php echo $activeSessionCount; ?> sessions are active, only one should be)</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Teachers Card -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Total Teachers</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $teachersCount; ?></p>
            </div>

            <!-- Programs Card -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Total Programs</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $programsCount; ?></p>
            </div>

            <!-- Courses Card -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Total Courses</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $coursesCount; ?></p>
            </div>

            <!-- Rooms Card -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Total Rooms & Labs</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $roomsCount; ?></p>
            </div>

            <!-- Pending Requests Card -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Pending Requests</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $pendingRequests; ?></p>
            </div>

            <!-- Active Substitutes Card -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <h4 class="text-gray-500 font-bold mb-1">Active Substitutes</h4>
                <p class="text-3xl font-bold text-gray-800"><?php echo $activeSubstitutes; ?></p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h3>
            <div class="flex flex-wrap gap-3">
                <a href="timetable_manual.php" class="bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 transition-colors px-4 py-2 rounded-md text-sm font-semibold">
                    Generate Timetable
                </a>
                <a href="teachers.php" class="bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 transition-colors px-4 py-2 rounded-md text-sm font-semibold">
                    Manage Teachers
                </a>
                <a href="courses.php" class="bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 transition-colors px-4 py-2 rounded-md text-sm font-semibold">
                    Add Course
                </a>
                <a href="announcements.php" class="bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 transition-colors px-4 py-2 rounded-md text-sm font-semibold">
                    Announcements
                </a>
            </div>
        </div>
        

    </div>
</div>
