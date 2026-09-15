<?php
// admin/workload.php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

// Pagination logic
$limit = 10;
$currentPageNum = isset($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($currentPageNum - 1) * $limit;

// Count total records
$totalStmt = $pdo->query("SELECT COUNT(*) FROM users u JOIN designation_workload d ON u.Designation=d.Designation WHERE Role='teacher'");
$totalRows = $totalStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Teacher Workload Report logic
$stmt = $pdo->query("SELECT u.UserID, u.Name, u.Designation, u.IsHOD, d.MaxWeeklyPeriods, d.HODWeeklyPeriods, dep.Name as DeptName 
                     FROM users u 
                     JOIN designation_workload d ON u.Designation=d.Designation 
                     LEFT JOIN departments dep ON u.HOD_DepartmentID=dep.DepartmentID WHERE Role='teacher'
                     ORDER BY u.Name ASC
                     LIMIT $limit OFFSET $offset");
$teachers = $stmt->fetchAll();

foreach($teachers as &$t) {
    $t['MaxAllowed'] = $t['IsHOD'] ? $t['HODWeeklyPeriods'] : $t['MaxWeeklyPeriods'];
    // Count assigned
    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE TeacherID = ? AND IsFree = 0");
    $stmtC->execute([$t['UserID']]);
    $t['Assigned'] = $stmtC->fetchColumn();
    $t['Remaining'] = $t['MaxAllowed'] - $t['Assigned'];
    
    $perc = $t['MaxAllowed'] > 0 ? ($t['Assigned'] / $t['MaxAllowed']) * 100 : 0;
    
    if ($t['Assigned'] >= $t['MaxAllowed']) {
        $t['StatusBadge'] = 'Over Limit';
        $t['BadgeClass']  = 'badge-over-limit';
    } elseif ($perc >= 80) {
        $t['StatusBadge'] = 'Near Limit';
        $t['BadgeClass']  = 'badge-near-limit';
    } else {
        $t['StatusBadge'] = 'Normal Load';
        $t['BadgeClass']  = 'badge-normal';
    }
}
unset($t); // Fix: Unset reference to prevent overwriting the last element in the next loop
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1">
        <div class="flex justify-between items-center bg-white p-6 shadow-md rounded mb-4">
        <h2 class="text-2xl font-bold text-maroon">Teacher Workload Report</h2>
        <button onclick="downloadWorkloadPDF()" class="bg-gray-800 text-white px-4 py-2 rounded">Export PDF</button>
    </div>
    
    <div class="bg-white p-6 shadow-md rounded" id="workload_container">
        <table class="w-full text-left">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2">Name</th><th class="p-2">Designation</th><th class="p-2">HOD</th>
                    <th class="p-2 text-center">Max Weekly</th><th class="p-2 text-center">Assigned</th><th class="p-2 text-center">Remaining</th><th class="p-2">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($teachers as $t): ?>
                <tr class="border-b">
                    <td class="p-2 font-bold"><?php echo htmlspecialchars($t['Name']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($t['Designation']); ?></td>
                    <td class="p-2 text-sm text-gray-500"><?php echo $t['IsHOD'] ? htmlspecialchars($t['DeptName']) : 'No'; ?></td>
                    <td class="p-2 text-center"><?php echo $t['MaxAllowed']; ?></td>
                    <td class="p-2 text-center font-bold"><?php echo $t['Assigned']; ?></td>
                    <td class="p-2 text-center text-gray-500"><?php echo $t['Remaining']; ?></td>
                    <td class="p-2"><span class="px-2 py-1 rounded text-xs <?php echo $t['BadgeClass']; ?>"><?php echo $t['StatusBadge']; ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination Controls -->
        <?php if($totalPages > 1): ?>
        <div class="flex justify-between items-center mt-6 border-t pt-4">
            <span class="text-sm text-gray-600 font-semibold">Showing page <?php echo $currentPageNum; ?> of <?php echo $totalPages; ?></span>
            <div class="flex gap-1">
                <?php if($currentPageNum > 1): ?>
                    <a href="?page=<?php echo $currentPageNum - 1; ?>" class="px-3 py-1 bg-gray-100 border rounded text-gray-700 hover:bg-gray-200">Previous</a>
                <?php endif; ?>
                
                <?php
                $startPg = max(1, $currentPageNum - 2);
                $endPg = min($totalPages, $currentPageNum + 2);
                
                if ($startPg > 1) {
                    echo '<a href="?page=1" class="px-3 py-1 bg-gray-100 border rounded text-gray-700 hover:bg-gray-200">1</a>';
                    if ($startPg > 2) echo '<span class="px-2 py-1 text-gray-400">...</span>';
                }
                
                for($i = $startPg; $i <= $endPg; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="px-3 py-1 border rounded <?php echo $i === $currentPageNum ? 'bg-maroon text-white font-bold' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($endPg < $totalPages): ?>
                    <?php if ($endPg < $totalPages - 1) echo '<span class="px-2 py-1 text-gray-400">...</span>'; ?>
                    <a href="?page=<?php echo $totalPages; ?>" class="px-3 py-1 bg-gray-100 border rounded text-gray-700 hover:bg-gray-200"><?php echo $totalPages; ?></a>
                <?php endif; ?>
                
                <?php if($currentPageNum < $totalPages): ?>
                    <a href="?page=<?php echo $currentPageNum + 1; ?>" class="px-3 py-1 bg-gray-100 border rounded text-gray-700 hover:bg-gray-200">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        </div>
    </div>
</div>
<script>
function downloadWorkloadPDF() {
    downloadPDF('workload_container', 'Workload_Report.pdf', false);
}
</script>

