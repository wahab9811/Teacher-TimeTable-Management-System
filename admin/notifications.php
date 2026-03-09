<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$message='';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['send'])) {
        $st = $_POST['scope_type'];
        $msg = $_POST['message'];
        $pdo->prepare("INSERT INTO notifications (ScopeType, ProgramID, DepartmentID, SemesterID, ShiftID, TeacherID, Message) VALUES (?,?,?,?,?,?,?)")
            ->execute([$st, $_POST['pid']?:null, $_POST['did']?:null, $_POST['sid']?:null, $_POST['shid']?:null, $_POST['tid']?:null, $msg]);
        $message="Notification dispatched!";
    } elseif(isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM notifications WHERE NotificationID=?")->execute([$_POST['id']]);
    }
}
$nots = $pdo->query("SELECT n.*, u.Name as TN FROM notifications n LEFT JOIN users u ON n.TeacherID=u.UserID ORDER BY CreatedAt DESC")->fetchAll();
$programs = $pdo->query("SELECT * FROM programs")->fetchAll();
$teachers = $pdo->query("SELECT * FROM users WHERE Role='teacher'")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <h2 class="text-2xl font-bold text-maroon mb-4">Dispatcher</h2>
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-2 mb-4 font-bold border rounded"><?php echo $message; ?></div><?php endif; ?>
        
        <form method="POST" class="bg-gray-50 p-4 border rounded mb-6 gap-4 grid grid-cols-2">
            <div class="col-span-2">
                <label class="block font-bold mb-1">Scope</label>
                <select name="scope_type" id="scope_t" class="border p-2 w-full rounded" onchange="toggleScope(this.value)">
                    <option value="global">Global (All)</option><option value="program">Program specific</option><option value="class">Class specific</option><option value="teacher">Teacher specific</option>
                </select>
            </div>
            
            <div id="prog_opts" class="hidden col-span-2"><label class="block">Program</label><select name="pid" class="w-full border p-2"><option value="">--</option><?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?></select></div>
            <div id="class_opts" class="hidden col-span-2 grid grid-cols-2 gap-2 text-sm italic text-gray-500">Provide IDs manually or use generic classes...</div>
            <div id="teach_opts" class="hidden col-span-2"><label class="block">Teacher</label><select name="tid" class="w-full border p-2"><option value="">--</option><?php foreach($teachers as $t): echo "<option value='{$t['UserID']}'>{$t['Name']}</option>"; endforeach; ?></select></div>
            
            <div class="col-span-2"><label class="block font-bold mt-2">Message</label><textarea name="message" class="w-full border p-2 rounded" required rows="3"></textarea></div>
            <button name="send" class="btn-maroon p-2 rounded col-span-2 mt-2 font-bold">Send Notification</button>
        </form>
        
        <table class="w-full text-left text-sm"><thead class="bg-gray-100"><tr><th class="p-2">Date</th><th class="p-2">Scope</th><th class="p-2">Target</th><th class="p-2">Message</th><th class="p-2">Action</th></tr></thead>
        <tbody>
            <?php foreach($nots as $n): ?>
            <tr class="border-b"><td class="p-2"><?php echo $n['CreatedAt']; ?></td><td class="p-2 uppercase font-bold"><?php echo $n['ScopeType']; ?></td><td class="p-2"><?php echo $n['TN'] ?? '-'; ?></td><td class="p-2"><?php echo $n['Message']; ?></td>
            <td class="p-2"><form method="POST"><input type="hidden" name="id" value="<?php echo $n['NotificationID']; ?>"><button name="delete" class="text-red-500">Del</button></form></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
</div>
<script>
function toggleScope(val) {
    document.getElementById('prog_opts').classList.add('hidden');
    document.getElementById('class_opts').classList.add('hidden');
    document.getElementById('teach_opts').classList.add('hidden');
    if(val==='program') document.getElementById('prog_opts').classList.remove('hidden');
    if(val==='class') document.getElementById('class_opts').classList.remove('hidden');
    if(val==='teacher') document.getElementById('teach_opts').classList.remove('hidden');
}
</script>
<?php include '../includes/footer.php'; ?>
