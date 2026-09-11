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
    $leaveType = $_POST['leave_type'] ?? '';
    $shift = $_POST['shift'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $suggSub = $_POST['suggested_sub_id'] ?? null;
    if ($suggSub === '') $suggSub = null;

    if ($fromDate && $toDate && $leaveType && $shift && $reason) {
        if (strtotime($toDate) < strtotime($fromDate)) {
            $error = "To Date cannot be earlier than From Date.";
        } else {
            // Check overlaps
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE TeacherID = ? AND Status IN ('pending', 'approved') AND ((FromDate <= ? AND ToDate >= ?) OR (FromDate <= ? AND ToDate >= ?))");
            $stmt->execute([$teacherId, $toDate, $fromDate, $fromDate, $toDate]); // Overlap logic
            
            if ($stmt->fetchColumn() > 0) {
                $error = "You already have an overlapping leave request during these dates.";
            } else {
                $insert = $pdo->prepare("INSERT INTO leave_requests (TeacherID, LeaveType, FromDate, ToDate, Shift, Reason, Status, SuggestedSubstituteID) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
                $insert->execute([$teacherId, $leaveType, $fromDate, $toDate, $shift, $reason, $suggSub]);
                
                $message = "Leave request submitted successfully. Waiting for admin approval.";
                
                // Notify admin
                $msg = "New leave request from teacher ID $teacherId for $fromDate to $toDate.";
                $pdo->prepare("INSERT INTO notifications (ScopeType, Message) VALUES ('admin', ?)")->execute([$msg]);
            }
        }
    } else {
        $error = "All fields are required.";
    }
}

$teachers = $pdo->prepare("SELECT UserID, Name FROM users WHERE Role='teacher' AND AccountStatus='Active' AND UserID != ? ORDER BY Name ASC");
$teachers->execute([$teacherId]);
$teacherList = $teachers->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4">
    <!-- Sidebar -->
    <?php include '../includes/teacher_sidebar.php'; ?>
    
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
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block font-bold mb-1">Leave Type</label>
                        <select name="leave_type" required class="w-full border rounded p-2 focus:ring-maroon focus:border-maroon">
                            <option value="Casual Leave">Casual Leave</option>
                            <option value="Medical Leave">Medical Leave</option>
                            <option value="Official Duty">Official Duty</option>
                            <option value="Short Leave">Short Leave</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1">Shift Selection</label>
                        <select name="shift" required class="w-full border rounded p-2 focus:ring-maroon focus:border-maroon">
                            <option value="Both">Both (Full Day)</option>
                            <option value="Morning">Morning</option>
                            <option value="Evening">Evening</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4 bg-gray-50 p-4 border rounded">
                    <label class="block font-bold mb-1 text-gray-700">Recommend Substitute Teacher (Optional)</label>
                    <p class="text-sm text-gray-500 mb-2">If you have discussed your absence with a colleague and they agreed to take your classes, select them here.</p>
                    <select name="suggested_sub_id" class="w-full border rounded p-2 focus:ring-maroon focus:border-maroon bg-white text-gray-800">
                        <option value="">-- No Recommendation --</option>
                        <?php foreach($teacherList as $t): ?>
                            <option value="<?php echo $t['UserID']; ?>"><?php echo htmlspecialchars($t['Name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <div class="flex justify-between items-end mb-1">
                        <label class="block font-bold text-gray-800">Reason 
                            <span class="text-xs font-normal text-gray-500 ml-2">(Max 150 characters)</span>
                        </label>
                        <div id="charCount" class="text-xs font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded">0 / 150</div>
                    </div>
                    <textarea name="reason" id="leaveReason" rows="3" maxlength="150" required class="w-full border border-gray-300 focus:border-maroon focus:ring-1 focus:ring-maroon rounded p-3 transition-all text-sm outline-none shadow-inner" placeholder="Briefly explain the reason for leave..."></textarea>
                </div>
                
                <button type="submit" class="btn-maroon px-5 py-2.5 rounded-lg font-bold shadow-md hover:shadow-lg transition-all w-full md:w-auto">Submit Request</button>
            </form>
        </div>
    </div>
</div>

<script>
    const reasonInput = document.getElementById('leaveReason');
    const charCount = document.getElementById('charCount');
    if(reasonInput && charCount) {
        reasonInput.addEventListener('input', function() {
            const currentLen = this.value.length;
            charCount.textContent = currentLen + ' / 150';
            if(currentLen >= 140) {
                charCount.classList.remove('text-gray-400', 'bg-gray-100');
                charCount.classList.add('text-red-600', 'bg-red-50');
            } else {
                charCount.classList.remove('text-red-600', 'bg-red-50');
                charCount.classList.add('text-gray-400', 'bg-gray-100');
            }
        });
    }
</script>
<?php include '../includes/footer.php'; ?>
