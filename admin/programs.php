<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$msg = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['add'])) {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE Name = ? AND ShortCode = ?");
        $stmt_check->execute([$_POST['name'], $_POST['short_code']]);
        if ($stmt_check->fetchColumn() > 0) {
            $msg = "Cannot add program! A program with this exact Name and Short Code already exists.";
        } else {
            $input_duration = (float)$_POST['duration'];
            $unit = $_POST['unit'];
            $actual_segments = ($unit === 'semester') ? (int)($input_duration * 2) : (int)$input_duration;
            
            $pdo->prepare("INSERT INTO programs (Name, ShortCode, PeriodUnit, TotalDuration, AllowedShifts) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_POST['name'], $_POST['short_code'], $unit, $actual_segments, $_POST['shifts']]);
            
            $newProgId = $pdo->lastInsertId();
            
            if ($actual_segments > 0) {
                $semStmt = $pdo->prepare("INSERT INTO semesters (ProgramID, Label) VALUES (?, ?)");
                for ($i = 1; $i <= $actual_segments; $i++) {
                    $label = ($unit === 'year') ? "Part $i" : "Semester $i";
                    $semStmt->execute([$newProgId, $label]);
                }
            }
            $msg = "Program added successfully and its " . $unit . "s mapped!";
        }
    } elseif(isset($_POST['toggle_status'])) {
        $pdo->prepare("UPDATE programs SET IsActive = NOT IsActive WHERE ProgramID = ?")->execute([$_POST['id']]);
        $stmt = $pdo->prepare("SELECT IsActive FROM programs WHERE ProgramID = ?");
        $stmt->execute([$_POST['id']]);
        $msg = $stmt->fetchColumn() ? "Program status Active successfully" : "Program status Inactive successfully";
    } elseif(isset($_POST['delete'])) {
        $id = $_POST['id'];
        $deps = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE ProgramID = ?"); $deps->execute([$id]);
        $secs = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE ProgramID = ?"); $secs->execute([$id]);
        $crs = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE ProgramID = ?"); $crs->execute([$id]);
        $tt = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE ProgramID = ?"); $tt->execute([$id]);
        
        $total = $deps->fetchColumn() + $secs->fetchColumn() + $crs->fetchColumn() + $tt->fetchColumn();
        if ($total > 0) {
            $msg = "Cannot delete, it has linked departments, courses, or sections. Use Toggle Status first.";
        } else {
            $pdo->prepare("DELETE FROM semesters WHERE ProgramID = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM programs WHERE ProgramID = ?")->execute([$id]);
            $msg = "Program deleted.";
        }
    } elseif(isset($_POST['edit'])) {
        $input_duration = (float)$_POST['duration'];
        $unit = $_POST['unit'];
        $edit_segments = ($unit === 'semester') ? (int)($input_duration * 2) : (int)$input_duration;
        
        $pdo->prepare("UPDATE programs SET Name = ?, ShortCode = ?, PeriodUnit = ?, TotalDuration = ?, AllowedShifts = ? WHERE ProgramID = ?")
            ->execute([$_POST['name'], $_POST['short_code'], $unit, $edit_segments, $_POST['shifts'], $_POST['id']]);
        $msg = "Program details updated.";
    }
}
$programs = $pdo->query("SELECT * FROM programs")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <h2 class="text-2xl font-bold text-maroon mb-4">Manage Programs</h2>
        <?php if(!empty($msg)): ?>
            <div class="<?php echo strpos($msg, 'Cannot delete') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-800'; ?> p-3 rounded mb-4 font-semibold text-sm">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6 p-4 bg-gray-50 rounded border">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Program Name</label>
                <input type="text" name="name" placeholder="e.g. BS 4 Years" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-maroon">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Short Code</label>
                <input type="text" name="short_code" placeholder="e.g. BS" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-maroon">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Unit</label>
                <select name="unit" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-maroon" required>
                    <option value="semester">Semester</option><option value="year">Year</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Duration (Years)</label>
                <input type="number" step="0.5" name="duration" placeholder="e.g. 4" min="0.5" max="12" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-maroon">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Shifts</label>
                <select name="shifts" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-maroon" required>
                    <option value="Both">Both</option><option value="Morning">Morning</option><option value="Evening">Evening</option>
                </select>
            </div>
            <div class="col-span-2 md:col-span-5 flex justify-end mt-2">
                <button name="add" class="bg-[#a60b26] text-white px-6 py-2.5 rounded-lg font-bold hover:bg-[#8a0a20] transition text-sm shadow-sm">Add Program & Map Segments</button>
            </div>
        </form>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-100 border-b">
                    <tr><th class="p-3 text-sm">S.No.</th><th class="p-3 text-sm">Name</th><th class="p-3 text-sm">Code</th><th class="p-3 text-center text-sm">Duration</th><th class="p-3 text-center text-sm">Shifts</th><th class="p-3 text-center text-sm">Status</th><th class="p-3 text-right text-sm">Action</th></tr>
                </thead>
                <tbody>
                    <?php $sn = 1; foreach($programs as $p): ?>
                    <tr class="border-b hover:bg-gray-50 transition">
                        <td class="p-3 font-bold text-gray-500 text-sm"><?php echo $sn++; ?></td>
                        <td class="p-3 font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($p['Name']); ?></td>
                        <td class="p-3 text-sm font-semibold text-maroon"><?php echo htmlspecialchars($p['ShortCode'] ?? '-'); ?></td>
                        <td class="p-3 text-center text-sm text-gray-600">
                            <span class="bg-gray-200 px-2 py-1 rounded text-xs font-bold">
                                <?php 
                                if (strtolower($p['PeriodUnit']) === 'semester') {
                                    $years = $p['TotalDuration'] / 2;
                                    echo $years . ' Years (' . $p['TotalDuration'] . ' Sems)';
                                } else {
                                    echo $p['TotalDuration'] . ' ' . ucfirst($p['PeriodUnit']) . 's';
                                }
                                ?>
                            </span>
                        </td>
                        <td class="p-3 text-center text-sm text-gray-600 font-semibold"><?php echo $p['AllowedShifts']; ?></td>
                        <td class="p-3 text-center">
                            <?php if($p['IsActive']): ?>
                                <span class="bg-green-100 text-green-800 border border-green-200 px-2 py-0.5 rounded text-xs font-bold">Active</span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-800 border border-red-200 px-2 py-0.5 rounded text-xs font-bold">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-right">
                            <button type="button" onclick='openEditModal(<?php echo json_encode([
                                "id" => $p["ProgramID"],
                                "name" => $p["Name"],
                                "shortcode" => $p["ShortCode"],
                                "unit" => $p["PeriodUnit"],
                                "duration" => (strtolower($p["PeriodUnit"]) === 'semester') ? ($p["TotalDuration"] / 2) : $p["TotalDuration"],
                                "shifts" => $p["AllowedShifts"]
                            ]); ?>)' class="bg-blue-50 text-blue-600 hover:bg-blue-100 font-bold px-3 py-1.5 rounded text-xs transition-colors mr-2">Edit</button>
                            <form method="POST" class="inline">
                                <input type="hidden" name="id" value="<?php echo $p['ProgramID']; ?>">
                                <button name="toggle_status" class="bg-gray-50 text-gray-600 hover:bg-gray-100 font-bold px-3 py-1.5 rounded text-xs transition-colors mr-2">Toggle</button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Warning: Deleting a program removes related historical records. We recommend Togging its status to INACTIVE instead.\n\nAre you sure you want to Delete?');" class="inline">
                                <input type="hidden" name="id" value="<?php echo $p['ProgramID']; ?>">
                                <button name="delete" class="bg-red-50 text-red-600 hover:bg-red-100 font-bold px-3 py-1.5 rounded text-xs transition-colors">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($programs)): ?>
                    <tr><td colspan="7" class="p-4 text-center text-gray-500">No programs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 flex justify-center items-center z-[100]" style="display: none;">
    <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-[500px] border border-gray-100">
        <h3 class="font-bold text-lg mb-4 text-[#111827]">Edit Program</h3>
        <p class="text-xs text-gray-500 mb-4 bg-gray-50 p-2 border border-gray-200 rounded">Note: Changing the Period Unit or Total Duration will NOT automatically rewrite existing mapped semesters. Only use this to fix textual mistakes.</p>
        <form method="POST" class="flex flex-col gap-4">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Program Name</label>
                    <input type="text" name="name" id="edit_name" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Short Code</label>
                    <input type="text" name="short_code" id="edit_scode" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                </div>
            </div>
            
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Unit</label>
                    <select name="unit" id="edit_unit" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                        <option value="semester">Semester</option><option value="year">Year</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Duration (Years)</label>
                    <input type="number" step="0.5" name="duration" id="edit_duration" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Shifts</label>
                    <select name="shifts" id="edit_shifts" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                        <option value="Both">Both</option><option value="Morning">Morning</option><option value="Evening">Evening</option>
                    </select>
                </div>
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
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_scode').value = data.shortcode;
    document.getElementById('edit_unit').value = data.unit;
    document.getElementById('edit_duration').value = data.duration;
    document.getElementById('edit_shifts').value = data.shifts;
    
    document.getElementById('editModal').style.display = 'flex';
}
</script>
