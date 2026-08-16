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
$pendingRequests = $pdo->query("SELECT COUNT(*) FROM requests WHERE Status = 'pending_admin'")->fetchColumn();
$activeSubstitutes = $pdo->query("SELECT COUNT(*) FROM substitute_assignments WHERE Status = 'active'")->fetchColumn();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4">
    <!-- Sidebar -->
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Admin Dashboard</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-maroon">
                <h4 class="text-gray-500 font-bold">Total Teachers</h4>
                <p class="text-3xl font-bold"><?php echo $teachersCount; ?></p>
            </div>
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-maroon">
                <h4 class="text-gray-500 font-bold">Total Programs</h4>
                <p class="text-3xl font-bold"><?php echo $programsCount; ?></p>
            </div>
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-maroon">
                <h4 class="text-gray-500 font-bold">Total Courses</h4>
                <p class="text-3xl font-bold"><?php echo $coursesCount; ?></p>
            </div>
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-yellow-500">
                <h4 class="text-gray-500 font-bold">Pending Requests</h4>
                <p class="text-3xl font-bold"><?php echo $pendingRequests; ?></p>
            </div>
            <div class="bg-white p-4 shadow-md rounded border-t-4 border-blue-500">
                <h4 class="text-gray-500 font-bold">Active Substitutes</h4>
                <p class="text-3xl font-bold"><?php echo $activeSubstitutes; ?></p>
            </div>
        </div>
        
        <!-- Workload Summary (Basic view) -->
        <div class="bg-white p-4 shadow-md rounded">
            <h3 class="text-lg font-bold border-b pb-2 mb-2">Workload Summary (Teachers near limit)</h3>
            <!-- Placeholder for complex calculation logic shown on full workload page -->
            <p class="text-sm text-gray-600">See <a href="workload.php" class="text-blue-500 underline">Workload Report</a> for detailed analysis.</p>
        </div>
    </div>
</div>

