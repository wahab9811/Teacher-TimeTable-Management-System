<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['add'])) {
        $pdo->prepare("INSERT INTO departments (ProgramID, Name, Type) VALUES (?, ?, ?)")->execute([$_POST['program_id'], $_POST['name'], $_POST['type']]);
    } elseif(isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM departments WHERE DepartmentID = ?")->execute([$_POST['id']]);
    } elseif(isset($_POST['edit'])) {
        $pdo->prepare("UPDATE departments SET ProgramID = ?, Name = ?, Type = ? WHERE DepartmentID = ?")
            ->execute([$_POST['program_id'], $_POST['name'], $_POST['type'], $_POST['id']]);
    }
}
$departments = $pdo->query("SELECT d.*, p.Name as PName FROM departments d JOIN programs p ON d.ProgramID = p.ProgramID")->fetchAll();
$programs = $pdo->query("SELECT * FROM programs")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <h2 class="text-2xl font-bold text-maroon mb-4">Manage Departments/Groups</h2>
        <form method="POST" class="grid grid-cols-4 gap-4 mb-6">
            <select name="program_id" class="border p-2 rounded" required>
                <option value="">Program...</option>
                <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
            </select>
            <input type="text" name="name" placeholder="Dept Name" required class="border p-2 rounded">
            <select name="type" class="border p-2 rounded" required>
                <option value="department">Department</option><option value="group">Group</option>
            </select>
            <button name="add" class="btn-maroon px-6 rounded font-bold">Add</button>
        </form>
        <table class="w-full text-left"><thead class="bg-gray-100"><tr><th class="p-2">Program</th><th class="p-2">Name</th><th class="p-2">Type</th><th class="p-2">Action</th></tr></thead>
        <tbody>
            <?php foreach($departments as $d): ?>
            <tr class="border-b"><td class="p-2"><?php echo $d['PName']; ?></td><td class="p-2"><?php echo $d['Name']; ?></td><td class="p-2"><?php echo $d['Type']; ?></td>
            <td class="p-2 flex gap-3 items-center">
                <button type="button" onclick='openEditModal(<?php echo json_encode([
                    "id" => $d["DepartmentID"],
                    "pid" => $d["ProgramID"],
                    "name" => $d["Name"],
                    "type" => $d["Type"]
                ]); ?>)' class="text-blue-600 hover:text-blue-800 font-medium">Edit</button>
                <form method="POST" onsubmit="return confirm('Delete?');" class="inline m-0"><input type="hidden" name="id" value="<?php echo $d['DepartmentID']; ?>"><button name="delete" class="text-red-500 hover:text-red-700 font-medium">Delete</button></form>
            </td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 flex justify-center items-center z-[100]" style="display: none;">
    <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-[500px] border border-gray-100">
        <h3 class="font-bold text-lg mb-4 text-[#111827]">Edit Department/Group</h3>
        <form method="POST" class="flex flex-col gap-4">
            <input type="hidden" name="id" id="edit_id">
            
            <select name="program_id" id="edit_pid" class="border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]" required>
                <option value="">Program...</option>
                <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
            </select>
            
            <input type="text" name="name" id="edit_name" placeholder="Dept/Group Name" required class="border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
            
            <select name="type" id="edit_type" class="border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]" required>
                <option value="department">Department</option><option value="group">Group</option>
            </select>
            
            <div class="flex justify-end gap-2 mt-2 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold px-5 py-2.5 rounded-lg transition-colors">Cancel</button>
                <button type="submit" name="edit" class="bg-[#a60b26] hover:bg-[#8a0a20] text-white font-bold px-5 py-2.5 rounded-lg transition-colors">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_pid').value = data.pid;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_type').value = data.type;
    
    document.getElementById('editModal').style.display = 'flex';
}
</script>


