<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$message = '';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['add'])) {
        $pdo->prepare("INSERT INTO rooms (Name, Type, Capacity) VALUES (?, ?, ?)")
            ->execute([$_POST['name'], $_POST['type'], $_POST['capacity']]);
        $message = "Room added successfully.";
    } elseif(isset($_POST['toggle_status'])) {
        $pdo->prepare("UPDATE rooms SET IsActive = NOT IsActive WHERE RoomID = ?")->execute([$_POST['id']]);
        $message = "Room status toggled.";
    } elseif(isset($_POST['delete'])) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE RoomID = ?");
        $chk->execute([$_POST['id']]);
        if($chk->fetchColumn() > 0) {
            $message = "Cannot delete: Room is currently used in the timetable. Please toggle its status to 'Inactive' instead.";
        } else { 
            $pdo->prepare("DELETE FROM rooms WHERE RoomID = ?")->execute([$_POST['id']]); 
            $message = "Room deleted."; 
        }
    } elseif(isset($_POST['edit'])) {
        $pdo->prepare("UPDATE rooms SET Name = ?, Type = ?, Capacity = ? WHERE RoomID = ?")
            ->execute([$_POST['name'], $_POST['type'], $_POST['capacity'], $_POST['id']]);
        $message = "Room updated.";
    }
}

// Fetch and group rooms by Type
$query = "SELECT * FROM rooms ORDER BY Type ASC, Name ASC";
$rooms = $pdo->query($query)->fetchAll();

$groupedRooms = [];
foreach($rooms as $r) {
    if(!isset($groupedRooms[$r['Type']])) $groupedRooms[$r['Type']] = [];
    $groupedRooms[$r['Type']][] = $r;
}
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-maroon">Manage Rooms & Labs</h2>
        </div>
        
        <?php if($message): ?>
            <div class="<?php echo strpos($message, 'Cannot delete') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-800'; ?> p-3 rounded mb-4 font-semibold text-sm">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="flex flex-col md:flex-row gap-3 mb-6 p-4 bg-gray-50 rounded border border-gray-200 shadow-sm items-end">
            <div class="w-full md:w-1/3">
                <label class="block text-xs font-bold text-gray-600 mb-1">Room Name</label>
                <input type="text" name="name" placeholder="e.g. Room 101 or IT Lab" required class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-maroon">
            </div>
            <div class="w-full md:w-1/4">
                <label class="block text-xs font-bold text-gray-600 mb-1">Room Type</label>
                <select name="type" class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-maroon" required>
                    <option value="Classroom">Classroom</option>
                    <option value="Lab">Lab</option>
                </select>
            </div>
            <div class="w-full md:w-1/4">
                <label class="block text-xs font-bold text-gray-600 mb-1">Capacity</label>
                <input type="number" name="capacity" value="50" min="1" required class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-maroon">
            </div>
            <div class="w-full md:w-auto flex-1">
                <button name="add" class="w-full bg-[#a60b26] text-white px-6 py-2 rounded-lg font-bold hover:bg-[#8a0a20] transition shadow-sm text-sm">Add New</button>
            </div>
        </form>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php if(empty($groupedRooms)): ?>
                <p class="text-gray-500 col-span-2">No rooms found.</p>
            <?php else: foreach($groupedRooms as $type => $rms): ?>
            <div class="border border-gray-200 rounded-lg overflow-hidden flex flex-col mb-4">
                <h3 class="bg-gray-100 p-3 font-bold text-gray-800 border-b border-gray-200 flex justify-between items-center cursor-pointer hover:bg-gray-200 transition" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('.arrow-icon').classList.toggle('rotate-180');">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="arrow-icon h-5 w-5 text-gray-500 transition-transform duration-200 transform rotate-180" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                        <?php echo htmlspecialchars($type); ?>s
                    </div>
                    <span class="text-xs bg-white border text-gray-600 px-2 py-1 rounded-full"><?php echo count($rms); ?> Items</span>
                </h3>
                <div class="overflow-x-auto max-h-[300px] overflow-y-auto flex-1">
                    <table class="w-full text-left relative">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th class="p-3 font-semibold">Room Name</th>
                                <th class="p-3 font-semibold text-center">Capacity</th>
                                <th class="p-3 font-semibold text-center">Status</th>
                                <th class="p-3 font-semibold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($rms as $r): ?>
                            <tr class="border-b last:border-0 hover:bg-gray-50 transition group">
                                <td class="p-3 font-semibold text-gray-800 flex items-center gap-2">
                                    <?php echo htmlspecialchars($r['Name']); ?>
                                </td>
                                <td class="p-3 text-center text-xs text-gray-500 font-bold whitespace-nowrap">
                                    <?php echo $r['Capacity']; ?> Seats
                                </td>
                                <td class="p-3 text-center w-24">
                                    <?php if($r['IsActive']): ?>
                                        <span class="bg-green-100 text-green-800 px-2 py-0.5 rounded text-xs font-bold border border-green-200">Active</span>
                                    <?php else: ?>
                                        <span class="bg-red-100 text-red-800 px-2 py-0.5 rounded text-xs font-bold border border-red-200">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-right opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                    <button type="button" onclick='openEditModal(<?php echo json_encode(["id"=>$r["RoomID"], "name"=>$r["Name"], "type"=>$r["Type"], "capacity"=>$r["Capacity"]]); ?>)' class="bg-blue-50 text-blue-600 hover:bg-blue-100 font-bold px-3 py-1.5 rounded text-xs transition-colors mr-2">Edit</button>
                                    <form method="POST" class="inline"><input type="hidden" name="id" value="<?php echo $r['RoomID']; ?>"><button name="toggle_status" class="bg-gray-50 text-gray-600 hover:bg-gray-100 font-bold px-3 py-1.5 rounded text-xs transition-colors mr-2" title="Toggle Visibility">TGL</button></form>
                                    <form method="POST" onsubmit="return confirm('Warning: Deleting might affect history. Consider Toggling instead.\nDelete permanently?');" class="inline"><input type="hidden" name="id" value="<?php echo $r['RoomID']; ?>"><button name="delete" class="bg-red-50 text-red-600 hover:bg-red-100 font-bold px-3 py-1.5 rounded text-xs transition-colors">Del</button></form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 flex justify-center items-center z-[100]" style="display: none;">
    <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-[400px] border border-gray-100">
        <h3 class="font-bold text-lg mb-4 text-[#111827]">Edit Room</h3>
        <form method="POST" class="flex flex-col gap-4">
            <input type="hidden" name="id" id="edit_id">
            
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Room Name</label>
                <input type="text" name="name" id="edit_name" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Room Type</label>
                    <select name="type" id="edit_type" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]" required>
                        <option value="Classroom">Classroom</option>
                        <option value="Lab">Lab</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Capacity</label>
                    <input type="number" name="capacity" id="edit_capacity" min="1" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                </div>
            </div>
            
            <div class="flex justify-end gap-2 mt-2 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold px-5 py-2.5 rounded-lg transition-colors">Cancel</button>
                <button type="submit" name="edit" class="bg-[#a60b26] hover:bg-[#8a0a20] text-white font-bold px-5 py-2.5 rounded-lg transition-colors">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_type').value = data.type;
    document.getElementById('edit_capacity').value = data.capacity;
    
    document.getElementById('editModal').style.display = 'flex';
}
</script>


