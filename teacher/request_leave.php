<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';

$teacherId = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromDate = $_POST['from_date'] ?? '';
    $toDate = $_POST['to_date'] ?? '';
    $shift = $_POST['shift'] ?? '';
    $reason = $_POST['reason'] ?? '';

    if ($fromDate && $toDate && $shift && $reason) {
        if (strtotime($toDate) < strtotime($fromDate)) {
            $error = "To Date cannot be earlier than From Date.";
        } else {
            // Check overlaps
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE TeacherID = ? AND Status IN ('pending', 'approved') AND ((FromDate <= ? AND ToDate >= ?) OR (FromDate <= ? AND ToDate >= ?))");
            $stmt->execute([$teacherId, $toDate, $fromDate, $fromDate, $toDate]); // Overlap logic
            
            if ($stmt->fetchColumn() > 0) {
                $error = "You already have an overlapping leave request during these dates.";
            } else {
                $insert = $pdo->prepare("INSERT INTO leave_requests (TeacherID, FromDate, ToDate, Shift, Reason, Status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $insert->execute([$teacherId, $fromDate, $toDate, $shift, $reason]);
                $message = "Leave request submitted successfully. Waiting for admin approval.";
                
                // Notify admin
                $msg = "New leave request from teacher ID $teacherId for $fromDate to $toDate.";
                $pdo->prepare("INSERT INTO notifications (ScopeType, Message) VALUES ('global', ?)")->execute([$msg]);
            }
        }
    } else {
        $error = "All fields are required.";
    }
}
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4">
    <!-- Sidebar -->
    <aside class="w-64 bg-white p-4 shadow-md rounded">
        <h3 class="text-lg font-bold text-maroon mb-4">Teacher Menu</h3>
        <ul class="space-y-2">
            <li><a href="dashboard.php" class="block p-2 hover:bg-gray-100 rounded">Dashboard</a></li>
            <li><a href="timetable.php" class="block p-2 hover:bg-gray-100 rounded">My Timetable</a></li>
            <li><a href="request_free_change.php" class="block p-2 hover:bg-gray-100 rounded">Free Change Request</a></li>
            <li><a href="request_swap.php" class="block p-2 hover:bg-gray-100 rounded">Swap Request</a></li>
            <li><a href="inbox_swap.php" class="block p-2 hover:bg-gray-100 rounded">Swap Inbox</a></li>
            <li><a href="request_leave.php" class="block p-2 bg-gray-100 rounded text-maroon font-semibold">Leave Request</a></li>
            <li><a href="history_requests.php" class="block p-2 hover:bg-gray-100 rounded">Request History</a></li>
            <li><a href="history_substitute.php" class="block p-2 hover:bg-gray-100 rounded">Substitute History</a></li>
        </ul>
    </aside>
    
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Request Leave</h2>
        
        <div class="bg-white p-6 shadow-md rounded">
            <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?php echo $error; ?></div><?php endif; ?>
            
            <form method="POST">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block font-bold mb-1">From Date</label>
                        <input type="date" name="from_date" required class="w-full border rounded p-2" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="block font-bold mb-1">To Date</label>
                        <input type="date" name="to_date" required class="w-full border rounded p-2" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block font-bold mb-1">Shift</label>
                    <select name="shift" required class="w-full border rounded p-2">
                        <option value="Morning">Morning</option>
                        <option value="Evening">Evening</option>
                        <option value="Both">Both</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block font-bold mb-1">Reason</label>
                    <textarea name="reason" rows="3" required class="w-full border rounded p-2"></textarea>
                </div>
                
                <button type="submit" class="btn-maroon px-4 py-2 rounded font-bold">Submit Request</button>
            </form>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
