<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$msg = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['add'])) {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM semesters WHERE ProgramID = ? AND Label = ?");
        $stmt_check->execute([$_POST['program_id'], $_POST['label']]);
        if ($stmt_check->fetchColumn() > 0) {
            $msg = "Cannot add! This semester/year label already exists for the selected program.";
        } else {
            $pdo->prepare("INSERT INTO semesters (ProgramID, Label) VALUES (?, ?)")->execute([$_POST['program_id'], $_POST['label']]);
            $msg = "Semester added successfully.";
        }
    } elseif(isset($_POST['toggle_status'])) {
        $pdo->prepare("UPDATE semesters SET IsActive = NOT IsActive WHERE SemesterID = ?")->execute([$_POST['id']]);
        $stmt = $pdo->prepare("SELECT IsActive FROM semesters WHERE SemesterID = ?");
        $stmt->execute([$_POST['id']]);
        $msg = $stmt->fetchColumn() ? "Status active successfully" : "Status inactive successfully";
    } elseif(isset($_POST['delete'])) {
        $id = $_POST['id'];
        $secs = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE SemesterID = ?"); $secs->execute([$id]);
        $crs = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE SemesterID = ?"); $crs->execute([$id]);
        $tt = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE SemesterID = ?"); $tt->execute([$id]);
        
        $total = $secs->fetchColumn() + $crs->fetchColumn() + $tt->fetchColumn();
        if ($total > 0) {
            $msg = "Cannot delete, it has linked records. Use Toggle Status / reassign first.";
        } else {
            $pdo->prepare("DELETE FROM semesters WHERE SemesterID = ?")->execute([$id]);
            $msg = "Semester deleted.";
        }
    } elseif(isset($_POST['edit'])) {
        $pdo->prepare("UPDATE semesters SET ProgramID = ?, Label = ? WHERE SemesterID = ?")
            ->execute([$_POST['program_id'], $_POST['label'], $_POST['id']]);
        $msg = "Semester updated.";
    }
}

// Fetch and group semesters
$query = "SELECT s.*, p.Name as PName FROM semesters s JOIN programs p ON s.ProgramID = p.ProgramID ORDER BY p.ProgramID ASC, s.SemesterID ASC";
$semesters = $pdo->query($query)->fetchAll();

$groupedSemesters = [];
foreach($semesters as $s) {
    if(!isset($groupedSemesters[$s['PName']])) $groupedSemesters[$s['PName']] = [];
    $groupedSemesters[$s['PName']][] = $s;
}

$programs = $pdo->query("SELECT * FROM programs WHERE IsActive = 1")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-maroon">Manage Semesters & Years</h2>
        </div>
        
        <?php if(!empty($msg)): ?>
            <div class="<?php echo strpos($msg, 'Cannot delete') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-800'; ?> p-3 rounded mb-4 font-semibold text-sm">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6 p-4 bg-gray-50 rounded border border-gray-200 shadow-sm items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-1">Select Program</label>
                <select name="program_id" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-maroon" required>
                    <option value="">Choose Program...</option>
                    <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-1">Semester / Year Label</label>
                <input type="text" name="label" placeholder="e.g. Semester 1 / 1st Year" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-maroon">
            </div>
            <div class="md:col-span-1">
                <button name="add" class="w-full bg-[#a60b26] text-white px-6 py-2.5 rounded-lg font-bold hover:bg-[#8a0a20] transition shadow-sm text-sm h-full max-h-[46px]">Add New</button>
            </div>
        </form>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php if(empty($groupedSemesters)): ?>
                <p class="text-gray-500 col-span-2">No semesters found.</p>
            <?php else: foreach($groupedSemesters as $progName => $sems): ?>
            <div class="border border-gray-200 rounded-lg overflow-hidden flex flex-col">
                <h3 class="bg-gray-100 p-3 font-bold text-gray-800 border-b border-gray-200 flex justify-between items-center">
                    <?php echo htmlspecialchars($progName); ?>
                    <span class="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded-full"><?php echo count($sems); ?> Items</span>
                </h3>
                <div class="overflow-hidden flex-1">
                    <table class="w-full text-left">
                        <tbody>
                            <?php foreach($sems as $s): ?>
                            <tr class="border-b last:border-0 hover:bg-gray-50 transition group">
                                <td class="p-3 font-semibold text-gray-800 flex items-center gap-2">
                                    <?php echo htmlspecialchars($s['Label']); ?>
                                </td>
                                <td class="p-3 text-center w-24">
                                    <?php if($s['IsActive']): ?>
                                        <span class="bg-green-100 text-green-800 px-2 py-0.5 rounded text-xs font-bold border border-green-200">Active</span>
                                    <?php else: ?>
                                        <span class="bg-red-100 text-red-800 px-2 py-0.5 rounded text-xs font-bold border border-red-200">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-right w-32 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                    <button type="button" onclick='openEditModal(<?php echo json_encode(["id"=>$s["SemesterID"], "pid"=>$s["ProgramID"], "label"=>$s["Label"]]); ?>)' class="bg-blue-50 text-blue-600 hover:bg-blue-100 font-bold px-3 py-1.5 rounded text-xs transition-colors mr-2">Edit</button>
                                    <form method="POST" class="inline"><input type="hidden" name="id" value="<?php echo $s['SemesterID']; ?>"><button name="toggle_status" class="bg-gray-50 text-gray-600 hover:bg-gray-100 font-bold px-3 py-1.5 rounded text-xs transition-colors mr-2" title="Toggle Status">TGL</button></form>
                                    <form method="POST" onsubmit="return confirm('Delete permanently?');" class="inline"><input type="hidden" name="id" value="<?php echo $s['SemesterID']; ?>"><button name="delete" class="bg-red-50 text-red-600 hover:bg-red-100 font-bold px-3 py-1.5 rounded text-xs transition-colors">Del</button></form>
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
        <h3 class="font-bold text-lg mb-4 text-[#111827]">Edit Semester</h3>
        <form method="POST" class="flex flex-col gap-4">
            <input type="hidden" name="id" id="edit_id">
            
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Program</label>
                <select name="program_id" id="edit_pid" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]" required>
                    <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Label</label>
                <input type="text" name="label" id="edit_label" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
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
    document.getElementById('edit_pid').value = data.pid;
    document.getElementById('edit_label').value = data.label;
    document.getElementById('editModal').style.display = 'flex';
}
</script>
