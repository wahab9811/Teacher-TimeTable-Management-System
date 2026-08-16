<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$message = '';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['add'])) {
        $pdo->prepare("INSERT INTO rooms (Name, Type) VALUES (?, ?)")->execute([$_POST['name'], $_POST['type']]);
        $message = "Room added.";
    } elseif(isset($_POST['delete'])) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE RoomID = ?");
        $chk->execute([$_POST['id']]);
        if($chk->fetchColumn() > 0) $message = "Cannot delete: Room is in use.";
        else { $pdo->prepare("DELETE FROM rooms WHERE RoomID = ?")->execute([$_POST['id']]); $message = "Room deleted."; }
    } elseif(isset($_POST['edit'])) {
        $pdo->prepare("UPDATE rooms SET Name = ?, Type = ? WHERE RoomID = ?")->execute([$_POST['name'], $_POST['type'], $_POST['id']]);
        $message = "Room updated.";
    }
}
$rooms = $pdo->query("SELECT * FROM rooms")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <h2 class="text-2xl font-bold text-maroon mb-4">Manage Rooms</h2>
        <?php if($message): ?><div class="bg-gray-100 text-maroon p-2 mb-4 font-bold"><?php echo $message; ?></div><?php endif; ?>
        <form method="POST" class="flex gap-4 mb-6">
            <input type="text" name="name" placeholder="Room Name (e.g. Room 101)" required class="border p-2 rounded flex-1">
            <select name="type" class="border p-2 rounded" required>
                <option value="Classroom">Classroom</option><option value="Lab">Lab</option>
            </select>
            <button name="add" class="btn-maroon px-6 rounded font-bold">Add Room</button>
        </form>
        <table class="w-full text-left"><thead class="bg-gray-100"><tr><th class="p-2">Name</th><th class="p-2">Type</th><th class="p-2">Action</th></tr></thead>
        <tbody>
            <?php foreach($rooms as $r): ?>
            <tr class="border-b"><td class="p-2"><?php echo $r['Name']; ?></td><td class="p-2"><?php echo $r['Type']; ?></td>
            <td class="p-2 flex gap-3 items-center">
                <button type="button" onclick='openEditModal(<?php echo json_encode([
                    "id" => $r["RoomID"],
                    "name" => $r["Name"],
                    "type" => $r["Type"]
                ]); ?>)' class="text-blue-600 hover:text-blue-800 font-medium">Edit</button>
                <form method="POST" onsubmit="return confirm('Delete?');" class="inline m-0"><input type="hidden" name="id" value="<?php echo $r['RoomID']; ?>"><button name="delete" class="text-red-500 hover:text-red-700 font-medium">Delete</button></form>
            </td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 flex justify-center items-center z-[100]" style="display: none;">
    <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-[500px] border border-gray-100">
        <h3 class="font-bold text-lg mb-4 text-[#111827]">Edit Room</h3>
        <form method="POST" class="flex flex-col gap-4">
            <input type="hidden" name="id" id="edit_id">
            
            <input type="text" name="name" id="edit_name" placeholder="Room Name (e.g. Room 101)" required class="border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
            
            <select name="type" id="edit_type" class="border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]" required>
                <option value="Classroom">Classroom</option><option value="Lab">Lab</option>
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
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_type').value = data.type;
    
    document.getElementById('editModal').style.display = 'flex';
}
</script>


