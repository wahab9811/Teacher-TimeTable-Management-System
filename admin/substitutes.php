<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/timetable_rules.php';

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign'])) {
        $origId = $_POST['orig_id'] ?? '';
        $subId = $_POST['sub_id'] ?? '';
        $from = $_POST['from_date'] ?? '';
        $to = $_POST['to_date'] ?? '';
        $ttId = $_POST['tt_id'] ?? '';
        
        if ($origId && $subId && $from && $to && $ttId) {
            // Validate Dates
            if (strtotime($to) >= strtotime($from)) {
                $eligibility = checkSubstituteEligibility($pdo, $ttId, $subId, $from, $to);
                
                if ($eligibility !== true) {
                    $error = $eligibility;
                } else {
                    // Safe to insert
                    $pdo->prepare("INSERT INTO substitute_assignments (TimetableID, OriginalTeacherID, SubstituteTeacherID, FromDate, ToDate, Status) VALUES (?,?,?,?,?,?)")
                        ->execute([$ttId, $origId, $subId, $from, $to, 'active']);
                    $message = "Substitute Assigned Successfully.";
                    
                    // Notify
                    $msg = "You have been assigned as a substitute class from {$from} to {$to}.";
                    $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher',?,?)")->execute([$subId, $msg]);
                }
            } else {
                $error = "End date cannot be earlier than Start date.";
            }
        } else {
            $error = "Please fill all required inputs.";
        }
    }
    elseif (isset($_POST['cancel_sub'])) {
        $cancelId = $_POST['cancel_id'];
        if ($cancelId) {
            $pdo->prepare("UPDATE substitute_assignments SET Status='expired' WHERE SubstituteID=?")->execute([$cancelId]);
            $message = "Assignment cancelled (expired).";
            
            // Fetch for notification
            $cq = $pdo->prepare("SELECT SubstituteTeacherID, OriginalTeacherID FROM substitute_assignments WHERE SubstituteID=?");
            $cq->execute([$cancelId]);
            $cData = $cq->fetch();
            if($cData) {
                // Notify
                $msg = "Your substitution assignment has been cancelled by administration.";
                $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher',?,?)")->execute([$cData['SubstituteTeacherID'], $msg]);
            }
        }
    }
}

// Fetch all assignments
$subsQ = "
SELECT s.*, 
       u1.Name as OrigName, u2.Name as SubName, 
       t.Day, ts.PeriodNumber, ts.StartTime, 
       c.Name as CourseName 
FROM substitute_assignments s 
JOIN users u1 ON s.OriginalTeacherID=u1.UserID 
JOIN users u2 ON s.SubstituteTeacherID=u2.UserID 
JOIN timetable t ON s.TimetableID=t.TimetableID 
JOIN time_slots ts ON t.SlotID=ts.SlotID 
LEFT JOIN courses c ON t.CourseID=c.CourseID 
ORDER BY s.Status='active' DESC, s.CreatedAt DESC
";
$subs = $pdo->query($subsQ)->fetchAll();

// Fetch Teachers
$teachers = $pdo->query("SELECT UserID, Name FROM users WHERE Role='teacher' AND AccountStatus='Active' ORDER BY Name ASC")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <!-- Sidebar -->
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <h2 class="text-2xl font-bold text-maroon mb-6">Manage Substitutes</h2>
        
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 mb-6 shadow-sm border border-green-200 rounded"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 mb-6 shadow-sm border border-red-200 rounded"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        
        <div class="bg-gray-50 p-6 border border-gray-200 mb-8 rounded shadow-sm">
            <h3 class="font-bold text-lg mb-4 text-gray-800">Assign Substitute</h3>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Original Teacher</label>
                    <select name="orig_id" id="orig_id" class="w-full border border-gray-300 rounded p-2 focus:ring-maroon focus:border-maroon" onchange="fetchElig()" required>
                        <option value="">-- Select Teacher --</option>
                        <?php foreach($teachers as $t): echo "<option value='{$t['UserID']}'>".htmlspecialchars($t['Name'])."</option>"; endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Slot to Substitute</label>
                    <select name="tt_id" id="tt_id" class="w-full border border-gray-300 rounded p-2 text-gray-400 bg-gray-50" disabled required>
                        <option>Select Original Teacher First</option>
                    </select>
                </div>
                
                <div class="flex space-x-2">
                    <div class="w-1/2">
                        <label class="block text-sm font-medium mb-1">From Date</label>
                        <input type="date" name="from_date" required class="w-full border border-gray-300 rounded p-2 focus:ring-maroon focus:border-maroon">
                    </div>
                    <div class="w-1/2">
                        <label class="block text-sm font-medium mb-1">To Date</label>
                        <input type="date" name="to_date" required class="w-full border border-gray-300 rounded p-2 focus:ring-maroon focus:border-maroon">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Substitute Teacher</label>
                    <select name="sub_id" class="w-full border border-gray-300 rounded p-2 focus:ring-maroon focus:border-maroon" required>
                        <option value="">-- Select Substitute --</option>
                        <?php foreach($teachers as $t): echo "<option value='{$t['UserID']}'>".htmlspecialchars($t['Name'])."</option>"; endforeach; ?>
                    </select>
                </div>
                
                <div class="col-span-1 md:col-span-2 pt-2">
                    <button type="submit" name="assign" class="bg-maroon hover:bg-maroon-dark text-white p-2 px-6 font-bold rounded shadow transition-colors">Assign Substitute</button>
                </div>
            </form>
        </div>
        
        <h3 class="text-xl font-bold mb-4 text-gray-800">Assignments History</h3>
        <div class="overflow-x-auto border border-gray-200 rounded">
            <table class="w-full text-left text-sm border-collapse">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="p-3 border-b">Dates</th>
                        <th class="p-3 border-b">Teachers Map</th>
                        <th class="p-3 border-b">Details</th>
                        <th class="p-3 border-b text-center">Status</th>
                        <th class="p-3 border-b text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($subs)): ?>
                    <tr><td colspan="5" class="p-4 text-center text-gray-500">No substitutes assigned yet.</td></tr>
                    <?php else: ?>
                    <?php foreach($subs as $s): ?>
                    <tr class="border-b hover:bg-gray-50 transition-colors">
                        <td class="p-3 whitespace-nowrap">
                            <?php echo date('d M', strtotime($s['FromDate'])); ?> - <?php echo date('d M Y', strtotime($s['ToDate'])); ?>
                        </td>
                        <td class="p-3">
                            <div class="flex items-center space-x-2">
                                <span class="font-medium text-gray-800 w-24 truncate text-right border-r pr-2" title="<?php echo htmlspecialchars($s['OrigName']); ?>"><?php echo htmlspecialchars($s['OrigName']); ?></span>
                                <span class="font-bold text-blue-700 pl-1"><?php echo htmlspecialchars($s['SubName']); ?></span>
                            </div>
                        </td>
                        <td class="p-3">
                            <span class="bg-gray-100 px-2 py-1 rounded text-gray-800 text-xs shadow-sm border border-gray-200 inline-block">
                                <?php echo $s['Day']; ?> (<?php echo date('H:i', strtotime($s['StartTime'])); ?>)
                                - <?php echo htmlspecialchars($s['CourseName'] ?? 'Lab'); ?>
                            </span>
                        </td>
                        <td class="p-3 text-center">
                            <?php if($s['Status'] === 'active'): ?>
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-bold border border-green-200">Active</span>
                            <?php else: ?>
                                <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-xs border border-gray-200">Expired</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-center">
                            <?php if($s['Status'] === 'active'): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this assignment?');">
                                <input type="hidden" name="cancel_id" value="<?php echo $s['SubstituteID']; ?>">
                                <button type="submit" name="cancel_sub" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1 rounded text-xs transition-colors font-medium border border-transparent hover:border-red-200">Cancel</button>
                            </form>
                            <?php else: ?>
                                <span class="text-gray-400 text-xs">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function fetchElig() {
    let o = document.getElementById('orig_id').value;
    let tt = document.getElementById('tt_id');
    
    if(!o) {
        tt.disabled = true;
        tt.classList.add('text-gray-400', 'bg-gray-50');
        tt.innerHTML = '<option>Select Original Teacher First</option>';
        return;
    }
    
    tt.innerHTML = '<option>Loading slots...</option>';
    
    fetch(`/api/public.php?action=get_teacher_slots&t_id=${o}`)
    .then(r => r.json())
    .then(data => {
        tt.disabled = false; 
        tt.classList.remove('text-gray-400', 'bg-gray-50');
        tt.innerHTML = '<option value="">-- Select Time Slot --</option>';
        
        if (data && data.length > 0) {
            data.forEach(s => { 
                let t_start = s.StartTime ? s.StartTime.slice(0,5) : ''; 
                tt.innerHTML += `<option value="${s.TimetableID}">${s.Day} (${t_start}) - ${s.CourseName}</option>`; 
            });
        } else {
            tt.innerHTML = '<option value="">No classes found</option>';
            tt.disabled = true;
            tt.classList.add('text-gray-400', 'bg-gray-50');
        }
    })
    .catch(err => {
        tt.innerHTML = '<option value="">Error loading</option>';
    });
}
</script>

