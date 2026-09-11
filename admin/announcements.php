<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$message='';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['send'])) {
        $st = $_POST['scope_type'];
        $msg = $_POST['message'];
        $expiry = null;
        if($st === 'global' && !empty($_POST['duration'])) {
            $expiry = date('Y-m-d H:i:s', strtotime("+".intval($_POST['duration'])." days"));
        }
        
        $createdAt = date('Y-m-d H:i:s'); // uniform grouping key
        
        if ($st === 'global') {
            $pdo->prepare("INSERT INTO notifications (ScopeType, Message, ExpiryDate, CreatedAt) VALUES ('global', ?, ?, ?)")->execute([$msg, $expiry, $createdAt]);
        } elseif ($st === 'all_teachers') {
            $tList = $pdo->query("SELECT UserID FROM users WHERE Role='teacher' AND AccountStatus='Active'")->fetchAll();
            $stmt = $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message, CreatedAt) VALUES ('all_teachers', ?, ?, ?)");
            foreach($tList as $t) { $stmt->execute([$t['UserID'], $msg, $createdAt]); }
        } elseif ($st === 'all_hods') {
            $tList = $pdo->query("SELECT UserID FROM users WHERE Role='teacher' AND IsHOD=1 AND AccountStatus='Active'")->fetchAll();
            $stmt = $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message, CreatedAt) VALUES ('all_hods', ?, ?, ?)");
            foreach($tList as $t) { $stmt->execute([$t['UserID'], $msg, $createdAt]); }
        } elseif ($st === 'teacher') {
            $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message, CreatedAt) VALUES ('teacher', ?, ?, ?)")->execute([$_POST['tid'], $msg, $createdAt]);
        } elseif ($st === 'program') {
            $pid = $_POST['pid'];
            $tList = $pdo->prepare("SELECT UserID FROM users WHERE Role='teacher' AND AccountStatus='Active' AND DepartmentID IN (SELECT DepartmentID FROM departments WHERE ProgramID = ?)");
            $tList->execute([$pid]);
            $teachers = $tList->fetchAll();
            $stmt = $pdo->prepare("INSERT INTO notifications (ScopeType, ProgramID, TeacherID, Message, CreatedAt) VALUES ('program', ?, ?, ?, ?)");
            foreach($teachers as $t) { $stmt->execute([$pid, $t['UserID'], $msg, $createdAt]); }
        } elseif ($st === 'department') {
            $did = $_POST['did'];
            $tList = $pdo->prepare("SELECT UserID FROM users WHERE Role='teacher' AND AccountStatus='Active' AND DepartmentID = ?");
            $tList->execute([$did]);
            $teachers = $tList->fetchAll();
            $stmt = $pdo->prepare("INSERT INTO notifications (ScopeType, DepartmentID, TeacherID, Message, CreatedAt) VALUES ('department', ?, ?, ?, ?)");
            foreach($teachers as $t) { $stmt->execute([$did, $t['UserID'], $msg, $createdAt]); }
        }
        $message="Announcement dispatched successfully!";
    } elseif(isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM notifications WHERE Message=? AND CreatedAt=? AND ScopeType=?")->execute([$_POST['msg'], $_POST['createdAt'], $_POST['scope_type']]);
    }
}

$nots = $pdo->query("
    SELECT n.ScopeType, n.Message, n.CreatedAt, 
           COUNT(n.NotificationID) as TargetCount,
           GROUP_CONCAT(DISTINCT u.Name SEPARATOR ', ') as TN
    FROM notifications n 
    LEFT JOIN users u ON n.TeacherID=u.UserID 
    GROUP BY n.ScopeType, n.Message, n.CreatedAt 
    ORDER BY n.CreatedAt DESC
")->fetchAll();

$programs = $pdo->query("SELECT * FROM programs")->fetchAll();
$teachers = $pdo->query("SELECT * FROM users WHERE Role='teacher' AND AccountStatus='Active' ORDER BY Name")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments WHERE IsActive=1 ORDER BY Name")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <h2 class="text-2xl font-bold text-maroon mb-4">Announcements</h2>
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-2 mb-4 font-bold border rounded"><?php echo $message; ?></div><?php endif; ?>
        
        <form method="POST" class="bg-gray-50 p-4 border rounded mb-6 gap-4 grid grid-cols-2 shadow-sm">
            <div class="col-span-2">
                <label class="block font-bold mb-1 text-sm text-gray-700">Scope Type</label>
                <select name="scope_type" id="scope_t" class="border p-2 w-full rounded focus:ring-1 focus:ring-maroon outline-none" onchange="toggleScope(this.value)">
                    <option value="global">Global</option>
                    <option value="all_teachers">All Active Teachers</option>
                    <option value="all_hods">All HODs (Heads of Departments)</option>
                    <option value="teacher">Specific Teacher</option>
                    <option value="program">Specific Program's Teachers</option>
                    <option value="department">Specific Department's Teachers</option>
                </select>
            </div>
            
            <div id="global_opts" class="col-span-2">
                <label class="block mb-1 text-sm font-bold text-gray-700">Banner Duration <span class="text-xs text-gray-400 font-normal italic ml-1">(Days to show at the top of website)</span></label>
                <input type="number" name="duration" min="1" max="60" placeholder="e.g. 7" class="w-full border p-2 rounded outline-none focus:border-maroon focus:ring-1">
            </div>
            
            <div id="prog_opts" class="hidden col-span-2">
                <label class="block mb-1 text-sm font-bold text-gray-700">Select Program</label>
                <select name="pid" class="w-full border p-2 rounded focus:ring-1 focus:ring-maroon outline-none"><option value="">--</option><?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?></select>
            </div>
            
            <div id="dept_opts" class="hidden col-span-2">
                <label class="block mb-1 text-sm font-bold text-gray-700">Select Department</label>
                <select name="did" class="w-full border p-2 rounded focus:ring-1 focus:ring-maroon outline-none"><option value="">--</option><?php foreach($departments as $d): echo "<option value='{$d['DepartmentID']}'>{$d['Name']}</option>"; endforeach; ?></select>
            </div>
            
            <div id="teach_opts" class="hidden col-span-2">
                <label class="block mb-1 text-sm font-bold text-gray-700">Select Teacher</label>
                <select name="tid" class="w-full border p-2 rounded focus:ring-1 focus:ring-maroon outline-none"><option value="">--</option><?php foreach($teachers as $t): echo "<option value='{$t['UserID']}'>{$t['Name']}</option>"; endforeach; ?></select>
            </div>
            
            <div class="col-span-2">
                <label class="block font-bold mb-1 text-sm text-gray-700 mt-2">Message</label>
                <textarea name="message" id="msg_textarea" maxlength="150" class="w-full border p-2 rounded focus:ring-1 focus:ring-maroon outline-none" required rows="3"></textarea>
                <div class="text-xs text-gray-500 mt-1 text-right" id="char_count">Max 150 characters for Global announcements</div>
            </div>
            <button name="send" class="bg-maroon hover:bg-red-800 text-white p-2.5 rounded-lg col-span-2 mt-2 font-bold shadow transition-all">Send Announcement</button>
        </form>
        
        <h3 class="font-bold text-lg border-b pb-2 mb-4 text-gray-700">Sent History</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-3 border-b">Date</th>
                        <th class="p-3 border-b">Scope</th>
                        <th class="p-3 border-b">Target(s)</th>
                        <th class="p-3 border-b">Message</th>
                        <th class="p-3 border-b text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($nots as $n): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3 whitespace-nowrap text-xs text-gray-500"><?php echo date('Y-m-d H:i', strtotime($n['CreatedAt'])); ?></td>
                        <td class="p-3 uppercase font-bold text-xs text-maroon"><?php echo str_replace('_', ' ', $n['ScopeType']); ?></td>
                        <td class="p-3 text-sm whitespace-nowrap">
                            <?php 
                            if($n['ScopeType'] === 'global') echo '<span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded-full font-bold">Public Banner</span>';
                            elseif($n['ScopeType'] === 'all_teachers') echo '<span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full font-bold">'.$n['TargetCount'].' Teachers</span>';
                            elseif($n['ScopeType'] === 'all_hods') echo '<span class="text-xs bg-indigo-100 text-indigo-800 px-2 py-1 rounded-full font-bold">'.$n['TargetCount'].' HODs</span>';
                            elseif($n['ScopeType'] === 'program') echo '<span class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded-full font-bold">'.$n['TargetCount'].' (Program)</span>';
                            elseif($n['ScopeType'] === 'department') echo '<span class="text-xs bg-amber-100 text-amber-800 px-2 py-1 rounded-full font-bold">'.$n['TargetCount'].' (Dept)</span>';
                            else echo htmlspecialchars($n['TN'] ?? '-'); 
                            ?>
                        </td>
                        <td class="p-3"><?php echo nl2br(htmlspecialchars($n['Message'])); ?></td>
                        <td class="p-3 text-right">
                            <form method="POST" onsubmit="return confirm('Securely delete this message for everyone?');" class="inline">
                                <input type="hidden" name="msg" value="<?php echo htmlspecialchars($n['Message']); ?>">
                                <input type="hidden" name="createdAt" value="<?php echo $n['CreatedAt']; ?>">
                                <input type="hidden" name="scope_type" value="<?php echo $n['ScopeType']; ?>">
                                <button name="delete" class="text-red-500 font-bold hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1 rounded transition-colors text-xs">Del</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($nots)) echo "<tr><td colspan='5' class='text-center p-4 text-gray-500'>No sent history found.</td></tr>"; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
function toggleScope(val) {
    document.getElementById('global_opts').classList.add('hidden');
    document.getElementById('prog_opts').classList.add('hidden');
    document.getElementById('dept_opts').classList.add('hidden');
    document.getElementById('teach_opts').classList.add('hidden');
    
    if(val==='global') {
        document.getElementById('global_opts').classList.remove('hidden');
        document.getElementById('msg_textarea').maxLength = 150;
        document.getElementById('char_count').innerText = "Max 150 characters for Global announcements";
    } else {
        document.getElementById('msg_textarea').maxLength = 800;
        document.getElementById('char_count').innerText = "Max 800 characters";
    }
    
    if(val==='program') document.getElementById('prog_opts').classList.remove('hidden');
    if(val==='department') document.getElementById('dept_opts').classList.remove('hidden');
    if(val==='teacher') document.getElementById('teach_opts').classList.remove('hidden');
}
</script>

