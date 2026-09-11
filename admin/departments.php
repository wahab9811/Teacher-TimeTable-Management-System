<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$msg = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['add'])) {
        $pdo->prepare("INSERT INTO departments (ProgramID, Name, ShortCode, Type) VALUES (?, ?, ?, ?)")
            ->execute([$_POST['program_id'], $_POST['name'], $_POST['short_code'], $_POST['type']]);
        $msg = "Department/Group added successfully.";
    } elseif(isset($_POST['toggle_status'])) {
        $pdo->prepare("UPDATE departments SET IsActive = NOT IsActive WHERE DepartmentID = ?")->execute([$_POST['id']]);
        $stmt = $pdo->prepare("SELECT IsActive FROM departments WHERE DepartmentID = ?");
        $stmt->execute([$_POST['id']]);
        $msg = $stmt->fetchColumn() ? "Status active successfully" : "Status inactive successfully";
    } elseif(isset($_POST['delete'])) {
        $id = $_POST['id'];
        $secs = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE DepartmentID = ?"); $secs->execute([$id]);
        $crs = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE DepartmentID = ?"); $crs->execute([$id]);
        $tt = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE DepartmentID = ?"); $tt->execute([$id]);
        
        $total = $secs->fetchColumn() + $crs->fetchColumn() + $tt->fetchColumn();
        if ($total > 0) {
            $msg = "Cannot delete, it has linked records. Use Toggle Status / reassign first.";
        } else {
            $pdo->prepare("DELETE FROM departments WHERE DepartmentID = ?")->execute([$id]);
            $msg = "Department deleted.";
        }
    } elseif(isset($_POST['edit'])) {
        $pdo->prepare("UPDATE departments SET ProgramID = ?, Name = ?, ShortCode = ?, Type = ? WHERE DepartmentID = ?")
            ->execute([$_POST['program_id'], $_POST['name'], $_POST['short_code'], $_POST['type'], $_POST['id']]);
        $msg = "Department updated.";
    }
}

// Fetch departments grouped with program names and check if they have a HOD in users table
$query = "SELECT d.*, p.Name as PName, u.Name as HODName 
          FROM departments d 
          JOIN programs p ON d.ProgramID = p.ProgramID 
          LEFT JOIN users u ON u.HOD_DepartmentID = d.DepartmentID 
          ORDER BY p.ProgramID ASC, d.Name ASC";
$departments = $pdo->query($query)->fetchAll();

// Group departments by program for cleaner UI display
$groupedDepartments = [];
foreach($departments as $d) {
    if(!isset($groupedDepartments[$d['PName']])) {
        $groupedDepartments[$d['PName']] = [];
    }
    $groupedDepartments[$d['PName']][] = $d;
}

$programs = $pdo->query("SELECT * FROM programs WHERE IsActive = 1")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <h2 class="text-2xl font-bold text-maroon mb-4">Manage Departments & Groups</h2>
        
        <?php if(!empty($msg)): ?>
            <div class="<?php echo strpos($msg, 'Cannot delete') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-800'; ?> p-3 rounded mb-4 font-semibold text-sm">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6 p-4 bg-gray-50 rounded border border-gray-200 shadow-sm items-end">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Select Program</label>
                <select name="program_id" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon" required>
                    <option value="">Choose Program...</option>
                    <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-1">
                <label class="block text-xs font-bold text-gray-600 mb-1">Department / Group Name</label>
                <input type="text" name="name" placeholder="e.g. Computer Science" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Short Code</label>
                <input type="text" name="short_code" placeholder="e.g. CS" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Type</label>
                <select name="type" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon" required>
                    <option value="department">Department</option><option value="group">Group</option>
                </select>
            </div>
            <div>
                <button name="add" class="w-full bg-[#a60b26] text-white px-4 py-2.5 rounded-lg font-bold hover:bg-[#8a0a20] transition shadow-sm text-sm">Add New</button>
            </div>
        </form>
        
        <div class="space-y-6">
            <?php if(empty($groupedDepartments)): ?>
                <p class="text-gray-500 text-center py-4">No departments found.</p>
            <?php else: foreach($groupedDepartments as $progName => $depts): ?>
            <div class="border border-gray-200 rounded-lg overflow-hidden mb-4">
                <h3 class="bg-gray-100 p-3 font-bold text-gray-800 border-b border-gray-200 flex justify-between items-center cursor-pointer hover:bg-gray-200 transition" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('.arrow-icon').classList.toggle('rotate-180');">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="arrow-icon h-5 w-5 text-gray-500 transition-transform duration-200 transform rotate-180" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                        <?php echo htmlspecialchars($progName); ?>
                    </div>
                </h3>
                <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
                    <table class="w-full text-left relative">
                        <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th class="p-3 text-xs text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="p-3 text-xs text-center text-gray-500 uppercase tracking-wider">Code</th>
                                <th class="p-3 text-xs text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="p-3 text-xs text-gray-500 uppercase tracking-wider">HOD / Head</th>
                                <th class="p-3 text-xs text-center text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="p-3 text-xs text-right text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($depts as $d): ?>
                            <tr class="border-b last:border-0 hover:bg-gray-50 transition">
                                <td class="p-3 font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($d['Name']); ?></td>
                                <td class="p-3 text-center font-bold text-maroon text-sm"><?php echo htmlspecialchars($d['ShortCode'] ?? '-'); ?></td>
                                <td class="p-3 text-sm text-gray-600 capitalize"><?php echo $d['Type']; ?></td>
                                <td class="p-3 text-sm">
                                    <?php if($d['HODName']): ?>
                                        <span class="text-blue-700 bg-blue-50 px-2 py-0.5 rounded font-medium border border-blue-100 flex items-center w-max gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg>
                                            <?php echo htmlspecialchars($d['HODName']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic text-xs">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-center">
                                    <?php if($d['IsActive']): ?>
                                        <span class="bg-green-100 text-green-800 border border-green-200 px-2 py-0.5 rounded text-xs font-bold">Active</span>
                                    <?php else: ?>
                                        <span class="bg-red-100 text-red-800 border border-red-200 px-2 py-0.5 rounded text-xs font-bold">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-right">
                                    <button type="button" onclick='openEditModal(<?php echo json_encode([
                                        "id" => $d["DepartmentID"],
                                        "pid" => $d["ProgramID"],
                                        "name" => $d["Name"],
                                        "short_code" => $d["ShortCode"],
                                        "type" => $d["Type"]
                                    ]); ?>)' class="bg-blue-50 text-blue-600 hover:bg-blue-100 font-bold px-3 py-1.5 rounded text-xs transition-colors mr-2">Edit</button>
                                    
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $d['DepartmentID']; ?>">
                                        <button name="toggle_status" class="bg-gray-50 text-gray-600 hover:bg-gray-100 font-bold px-3 py-1.5 rounded text-xs transition-colors mr-2">Toggle</button>
                                    </form>
                                    
                                    <form method="POST" onsubmit="return confirm('Deleting is risky if past records exist. Consider toggling status instead.\n\nDelete permanently?');" class="inline m-0">
                                        <input type="hidden" name="id" value="<?php echo $d['DepartmentID']; ?>">
                                        <button name="delete" class="bg-red-50 text-red-600 hover:bg-red-100 font-bold px-3 py-1.5 rounded text-xs transition-colors">Del</button>
                                    </form>
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
    <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-[500px] border border-gray-100">
        <h3 class="font-bold text-lg mb-4 text-[#111827]">Edit Department/Group</h3>
        <form method="POST" class="flex flex-col gap-4">
            <input type="hidden" name="id" id="edit_id">
            
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Program</label>
                <select name="program_id" id="edit_pid" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]" required>
                    <option value="">Choose Program...</option>
                    <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Name</label>
                <input type="text" name="name" id="edit_name" placeholder="Dept/Group Name" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Short Code</label>
                <input type="text" name="short_code" id="edit_scode" placeholder="e.g. CS" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Type</label>
                <select name="type" id="edit_type" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]" required>
                    <option value="department">Department</option><option value="group">Group</option>
                </select>
            </div>
            
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
    document.getElementById('edit_scode').value = data.short_code;
    document.getElementById('edit_type').value = data.type;
    
    document.getElementById('editModal').style.display = 'flex';
}
</script>


