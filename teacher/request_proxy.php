<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';

$teacherId = $_SESSION['user_id'];
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $myTT = $_POST['my_tt_id'] ?? 0;
    $targetTeacherID = $_POST['target_teacher_id'] ?? 0;
    $proxyDate = $_POST['proxy_date'] ?? '';

    if ($myTT && $targetTeacherID && $proxyDate) {
        $curr = $pdo->prepare("SELECT * FROM timetable WHERE TimetableID = ? AND TeacherID = ? AND IsFree = 0 AND Status = 'published'");
        $curr->execute([$myTT, $teacherId]);
        $cData = $curr->fetch();
        
        $targ = $pdo->prepare("SELECT * FROM users WHERE UserID = ? AND Role='teacher' AND AccountStatus='Active'");
        $targ->execute([$targetTeacherID]);
        $tData = $targ->fetch();

        if (!$cData || !$tData) {
            $error = "Invalid selection.";
        } else {
            // Check if target teacher is free at that slot on that date
            // We can just rely on basic schedule check
            $busy = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE TeacherID = ? AND Day = ? AND SlotID = ? AND IsFree = 0 AND Status = 'published'");
            $busy->execute([$targetTeacherID, $cData['Day'], $cData['SlotID']]);
            
            if ($busy->fetchColumn() > 0) {
                $error = "The selected teacher is already scheduled for a class at this time.";
            } else {
                $deadline = date('Y-m-d H:i:s', strtotime('+48 hours'));
                
                $insert = $pdo->prepare("INSERT INTO requests (Type, RequestedBy, TargetTeacherID, TimetableID, ProxyDate, Status, TeacherBStatus, Deadline) VALUES ('proxy', ?, ?, ?, ?, 'pending_teacher', 'pending', ?)");
                $insert->execute([$teacherId, $targetTeacherID, $myTT, $proxyDate, $deadline]);
                
                $message = "Proxy request sent to target teacher successfully.";
                
                // Notify Teacher B
                $msg = "New Proxy/Substitute Request from " . $_SESSION['user_name'];
                $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$targetTeacherID, $msg]);
            }
        }
    } else {
        $error = "Please fill all fields.";
    }
}

$stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, c.Name as CourseName, sec.Name as SectionName 
                       FROM timetable t 
                       JOIN time_slots ts ON t.SlotID=ts.SlotID 
                       JOIN courses c ON t.CourseID=c.CourseID 
                       LEFT JOIN sections sec ON t.SectionID=sec.SectionID
                       WHERE t.TeacherID=? AND t.IsFree=0 AND t.Status='published' ORDER BY FIELD(t.Day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), ts.PeriodNumber");
$stmt->execute([$teacherId]);
$mySlots = $stmt->fetchAll();

$allTeachers = $pdo->prepare("SELECT UserID, Name FROM users WHERE Role='teacher' AND UserID != ? AND AccountStatus='Active' ORDER BY Name ASC");
$allTeachers->execute([$teacherId]);
$teachers = $allTeachers->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/teacher_sidebar.php'; ?>
    
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-6 border-b pb-2">Request Proxy / Substitute</h2>
        
        <div class="bg-white p-6 shadow-md rounded border border-gray-100">
            <?php if($message): ?><div class="bg-green-100 text-green-700 p-4 rounded mb-6 font-medium shadow-sm border border-green-200"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if($error): ?><div class="bg-red-100 text-red-700 p-4 rounded mb-6 font-medium shadow-sm border border-red-200"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            
            <p class="text-gray-600 mb-6 bg-blue-50 p-3 rounded text-sm border border-blue-100">
                <i class="fas fa-info-circle text-blue-500 mr-2"></i> Use this form to ask a colleague to act as a substitute (proxy) for a specific class on a specific date. They must accept before it goes to the Admin for approval.
            </p>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block font-bold mb-2 text-gray-800">Date of Proxy</label>
                    <input type="date" name="proxy_date" required min="<?php echo date('Y-m-d'); ?>" class="w-full border border-gray-300 rounded p-3 focus:ring-maroon focus:border-maroon transition-colors bg-gray-50 hover:bg-white text-gray-800">
                </div>

                <div>
                    <label class="block font-bold mb-2 text-gray-800">My Teaching Class Details</label>
                    <select name="my_tt_id" required class="w-full border border-gray-300 rounded p-3 focus:ring-maroon focus:border-maroon transition-colors bg-gray-50 hover:bg-white text-gray-800">
                        <option value="">-- Select Class --</option>
                        <?php foreach($mySlots as $s): ?>
                            <option value="<?php echo $s['TimetableID']; ?>">
                                <?php echo "{$s['Day']} P{$s['PeriodNumber']} - " . htmlspecialchars($s['CourseName']) . " (Sec: " . htmlspecialchars($s['SectionName'] ?? 'N/A') . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block font-bold mb-2 text-gray-800">Select Substitute Colleague</label>
                    <select name="target_teacher_id" required class="w-full border border-gray-300 rounded p-3 focus:ring-maroon focus:border-maroon transition-colors bg-gray-50 hover:bg-white text-gray-800">
                        <option value="">-- Select Colleague --</option>
                        <?php foreach($teachers as $t): ?>
                            <option value="<?php echo $t['UserID']; ?>"><?php echo htmlspecialchars($t['Name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="pt-4 border-t">
                    <button type="submit" class="bg-maroon hover:bg-maroon-dark text-white px-6 py-2.5 rounded font-bold transition-colors shadow-md">Send Proxy Request</button>
                    <a href="dashboard.php" class="ml-4 text-gray-600 hover:text-gray-800 font-medium transition-colors">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
