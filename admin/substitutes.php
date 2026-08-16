<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/timetable_rules.php';

$message=$error='';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    $origId = $_POST['orig_id'];
    $subId = $_POST['sub_id'];
    $range = explode(' to ', trim($_POST['date_range']));
    $ttId = $_POST['tt_id'];
    
    if(count($range) === 2 && $origId && $subId) {
        $from = $range[0]; $to = $range[1];
        $pdo->prepare("INSERT INTO substitute_assignments (TimetableID, OriginalTeacherID, SubstituteTeacherID, FromDate, ToDate, Status) VALUES (?,?,?,?,?,?)")
            ->execute([$ttId, $origId, $subId, $from, $to, 'active']);
        $message = "Substitute Assigned Successfully.";
        
        // Notify
        $msg = "You have been assigned as a substitute.";
        $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher',?,?)")->execute([$subId, $msg]);
    } else {
        $error = "Detailed inputs required.";
    }
}

// Fetch all active assignments
$subs = $pdo->query("SELECT s.*, u1.Name as Orig, u2.Name as Sub, t.Day, ts.PeriodNumber, c.Name as CN FROM substitute_assignments s JOIN users u1 ON s.OriginalTeacherID=u1.UserID JOIN users u2 ON s.SubstituteTeacherID=u2.UserID JOIN timetable t ON s.TimetableID=t.TimetableID JOIN time_slots ts ON t.SlotID=ts.SlotID LEFT JOIN courses c ON t.CourseID=c.CourseID ORDER BY s.CreatedAt DESC")->fetchAll();
$teachers = $pdo->query("SELECT * FROM users WHERE Role='teacher'")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <h2 class="text-2xl font-bold text-maroon mb-4">Manage Substitutes</h2>
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-2 mb-4 font-bold border rounded"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 text-red-700 p-2 mb-4 font-bold border rounded"><?php echo $error; ?></div><?php endif; ?>
        
        <div class="bg-yellow-50 p-4 border border-yellow-200 mb-6 rounded">
            <h3 class="font-bold mb-2">Assign Substitute</h3>
            <form method="POST" class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm">Original Teacher</label><select name="orig_id" id="orig_id" class="w-full border p-2" onchange="fetchElig()">
                    <option value="">-</option><?php foreach($teachers as $t): echo "<option value='{$t['UserID']}'>{$t['Name']}</option>"; endforeach; ?>
                </select></div>
                <div><label class="block text-sm">Date Range (YYYY-MM-DD to YYYY-MM-DD)</label><input type="text" id="drange" name="date_range" class="w-full border p-2" placeholder="2026-08-10 to 2026-08-15" onchange="fetchElig()"></div>
                <div><label class="block text-sm">Slot to Substitute</label><select name="tt_id" id="tt_id" class="w-full border p-2 text-gray-400" disabled><option>Select Original Teacher First</option></select></div>
                <div><label class="block text-sm">Select Sub Teacher</label><select name="sub_id" class="w-full border p-2"><option>--</option>
                    <?php foreach($teachers as $t): echo "<option value='{$t['UserID']}'>{$t['Name']}</option>"; endforeach; ?>
                </select></div>
                
                <button type="submit" name="assign" class="btn-maroon p-2 font-bold rounded col-span-2">Assign Substitute</button>
            </form>
        </div>
        
        <h3 class="text-xl font-bold mt-8 mb-4">Active & Past Substitutes</h3>
        <table class="w-full text-left text-sm"><thead class="bg-gray-100"><tr><th class="p-2">Dates</th><th class="p-2">Original &rarr; Sub</th><th class="p-2">Slot</th><th class="p-2">Status</th></tr></thead>
        <tbody>
            <?php foreach($subs as $s): ?>
            <tr class="border-b"><td class="p-2"><?php echo $s['FromDate'].' to '.$s['ToDate']; ?></td><td class="p-2 font-bold"><?php echo $s['Orig'].' &rarr; '.$s['Sub']; ?></td><td class="p-2"><?php echo $s['Day'].' P'.$s['PeriodNumber'].' ('.$s['CN'].')'; ?></td><td class="p-2"><span class="px-2 py-1 rounded text-white <?php echo $s['Status']=='active'?'bg-green-500':'bg-gray-500'; ?>"><?php echo $s['Status']; ?></span></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
</div>
<script>
function fetchElig() {
    let o = document.getElementById('orig_id').value;
    let tt = document.getElementById('tt_id');
    if(!o) return;
    fetch(`/api/public.php?action=get_teacher_slots&t_id=${o}`).then(r=>r.json()).then(data=>{
        tt.disabled=false; tt.classList.remove('text-gray-400');
        tt.innerHTML='';
        data.forEach(s => { tt.innerHTML+=`<option value="${s.TimetableID}">${s.Day} P${s.PeriodNumber} - ${s.CourseName}</option>`; });
    });
}
</script>

