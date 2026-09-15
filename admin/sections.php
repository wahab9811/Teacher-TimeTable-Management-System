<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO sections (ProgramID, DepartmentID, SemesterID, ShiftID, Name, InchargeID, HomeRoomID) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $incharge = empty($_POST['incharge_id']) ? null : $_POST['incharge_id'];
        $homeroom = empty($_POST['homeroom_id']) ? null : $_POST['homeroom_id'];
        $sec_name = empty(trim($_POST['name'])) ? 'None' : trim($_POST['name']);
        $stmt->execute([$_POST['program_id'], $_POST['dept_id'], $_POST['semester_id'], $_POST['shift_id'], $sec_name, $incharge, $homeroom]);
        $message = "Section added successfully.";
    } elseif ($_POST['action'] === 'delete') {
        $id = $_POST['id'];
        $crs = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE SectionID = ?"); $crs->execute([$id]);
        $tt = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE SectionID = ?"); $tt->execute([$id]);
        
        $total = $crs->fetchColumn() + $tt->fetchColumn();
        if ($total > 0) {
            $message = "Cannot delete, it has linked records. Use Toggle Status / reassign first.";
        } else {
            $pdo->prepare("DELETE FROM sections WHERE SectionID = ?")->execute([$id]);
            $message = "Section deleted successfully.";
        }
    } elseif ($_POST['action'] === 'edit') {
        $stmt = $pdo->prepare("UPDATE sections SET ProgramID = ?, DepartmentID = ?, SemesterID = ?, ShiftID = ?, Name = ?, InchargeID = ?, HomeRoomID = ? WHERE SectionID = ?");
        $incharge = empty($_POST['incharge_id']) ? null : $_POST['incharge_id'];
        $homeroom = empty($_POST['homeroom_id']) ? null : $_POST['homeroom_id'];
        $sec_name = empty(trim($_POST['name'])) ? 'None' : trim($_POST['name']);
        $stmt->execute([$_POST['program_id'], $_POST['dept_id'], $_POST['semester_id'], $_POST['shift_id'], $sec_name, $incharge, $homeroom, $_POST['id']]);
        $message = "Section updated successfully.";
    }
}

$programs = $pdo->query("SELECT * FROM programs WHERE IsActive = 1")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments WHERE IsActive = 1")->fetchAll();
$semesters = $pdo->query("SELECT * FROM semesters WHERE IsActive = 1")->fetchAll();
$shifts = $pdo->query("SELECT * FROM shifts")->fetchAll();
$teachers = $pdo->query("SELECT UserID, Name FROM users WHERE Role = 'teacher' AND AccountStatus = 'Active'")->fetchAll();
$classrooms = $pdo->query("SELECT RoomID, Name FROM rooms WHERE Type = 'Classroom' AND IsActive = 1 ORDER BY Name ASC")->fetchAll();

$query = "
    SELECT s.*, p.Name as ProgramName, d.Name as DeptName, sem.Label as SemesterName, u.Name as InchargeName, sh.Name as ShiftName, r.Name as RoomName
    FROM sections s
    JOIN programs p ON s.ProgramID = p.ProgramID
    JOIN departments d ON s.DepartmentID = d.DepartmentID
    JOIN semesters sem ON s.SemesterID = sem.SemesterID
    JOIN shifts sh ON s.ShiftID = sh.ShiftID
    LEFT JOIN users u ON s.InchargeID = u.UserID
    LEFT JOIN rooms r ON s.HomeRoomID = r.RoomID
    ORDER BY p.ProgramID, d.DepartmentID, sem.SemesterID, s.Name
";
$sections = $pdo->query($query)->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-maroon">Manage Classes / Sections</h2>
        </div>
        
        <?php if($message): ?>
            <div class="<?php echo strpos($message, 'Cannot delete') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-800'; ?> p-3 rounded mb-4 font-semibold text-sm shadow-sm border <?php echo strpos($message, 'Cannot delete') !== false ? 'border-red-200' : 'border-green-200'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-gray-50 border border-gray-200 p-5 rounded-xl mb-6 shadow-sm">
            <h3 class="font-bold mb-4 text-gray-700 text-sm uppercase tracking-wide border-b border-gray-200 pb-2">Add New Section / Class</h3>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <input type="hidden" name="action" value="add">
                
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Program</label>
                    <select name="program_id" id="prog_select" required class="w-full border border-gray-300 p-2 rounded bg-white focus:ring-1 focus:ring-maroon">
                        <option value="">Select...</option>
                        <?php foreach($programs as $p): ?>
                            <option value="<?php echo $p['ProgramID']; ?>"><?php echo htmlspecialchars($p['Name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Group / Dept</label>
                    <select name="dept_id" id="dept_select" required class="w-full border border-gray-300 p-2 rounded bg-white focus:ring-1 focus:ring-maroon">
                        <option value="">Select...</option>
                        <?php foreach($departments as $d): ?>
                            <option value="<?php echo $d['DepartmentID']; ?>" data-prog="<?php echo $d['ProgramID']; ?>"><?php echo htmlspecialchars($d['Name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Semester / Year</label>
                    <select name="semester_id" id="sem_select" required class="w-full border border-gray-300 p-2 rounded bg-white focus:ring-1 focus:ring-maroon">
                        <option value="">Select...</option>
                        <?php foreach($semesters as $s): ?>
                            <option value="<?php echo $s['SemesterID']; ?>" data-prog="<?php echo $s['ProgramID']; ?>"><?php echo htmlspecialchars($s['Label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Shift</label>
                    <select name="shift_id" required class="w-full border border-gray-300 p-2 rounded bg-white focus:ring-1 focus:ring-maroon">
                        <option value="">Select...</option>
                        <?php foreach($shifts as $sh): ?>
                            <option value="<?php echo $sh['ShiftID']; ?>"><?php echo htmlspecialchars($sh['Name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Section Name</label>
                    <input type="text" name="name" placeholder="None (Optional)" class="w-full border border-gray-300 p-2 rounded bg-white focus:ring-1 focus:ring-maroon">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Class Incharge</label>
                    <select name="incharge_id" class="w-full border border-gray-300 p-2 rounded bg-white focus:ring-1 focus:ring-maroon">
                        <option value="">None (Optional)</option>
                        <?php foreach($teachers as $t): ?>
                            <option value="<?php echo $t['UserID']; ?>"><?php echo htmlspecialchars($t['Name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Home Room</label>
                    <select name="homeroom_id" class="w-full border border-gray-300 p-2 rounded bg-white focus:ring-1 focus:ring-maroon">
                        <option value="">None (Optional)</option>
                        <?php foreach($classrooms as $r): ?>
                            <option value="<?php echo $r['RoomID']; ?>"><?php echo htmlspecialchars($r['Name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="w-full bg-[#a60b26] text-white px-2 py-2 rounded font-bold hover:bg-[#8a0a20] transition shadow-sm text-sm border border-transparent">Add</button>
                </div>
            </form>
        </div>
        
        <div class="bg-white p-6 shadow-md rounded-xl border border-gray-100">
            <table class="w-full text-left">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="p-3 text-xs text-gray-500 uppercase tracking-wider">Program</th>
                        <th class="p-3 text-xs text-gray-500 uppercase tracking-wider">Group/Dept</th>
                        <th class="p-3 text-xs text-gray-500 uppercase tracking-wider">Year/Sem</th>
                        <th class="p-3 text-xs text-center text-gray-500 uppercase tracking-wider">Sections</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grouped = [];
                    foreach($sections as $s) {
                        $key = $s['ProgramName'] . '|' . $s['DeptName'] . '|' . $s['SemesterName'];
                        if(!isset($grouped[$key])) $grouped[$key] = [];
                        $grouped[$key][] = $s;
                    }
                    
                    if(empty($grouped)): ?>
                        <tr><td colspan="4" class="p-4 text-center text-gray-500">No sections defined yet.</td></tr>
                    <?php else:
                    foreach($grouped as $key => $secs): 
                        list($prog, $dept, $sem) = explode('|', $key);
                    ?>
                    <tr class="border-b transition hover:bg-gray-50">
                        <td class="p-3 font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($prog); ?></td>
                        <td class="p-3 text-gray-700 text-sm"><?php echo htmlspecialchars($dept); ?></td>
                        <td class="p-3 text-gray-700 text-sm whitespace-nowrap"><?php echo htmlspecialchars($sem); ?></td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-2 justify-center">
                                <?php foreach($secs as $s): ?>
                                <div class="group relative flex flex-col items-center bg-white border border-gray-200 rounded-lg p-2 shadow-sm min-w-[85px] hover:border-maroon/40 transition hover:shadow-md cursor-default">
                                    <div class="flex items-center gap-1">
                                        <span class="text-maroon font-black text-lg">
                                            <?php echo htmlspecialchars($s['Name']); ?>
                                        </span>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-sm <?php echo $s['ShiftName'] == 'Morning' ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800'; ?>" title="<?php echo htmlspecialchars($s['ShiftName']); ?>">
                                            <?php echo substr($s['ShiftName'], 0, 1); ?>
                                        </span>
                                    </div>
                                    <div class="mt-1 border-t border-gray-100 pt-1 w-full text-center">
                                        <span class="text-[10px] text-gray-500 font-bold max-w-[80px]" title="Incharge: <?php echo htmlspecialchars($s['InchargeName'] ? $s['InchargeName'] : 'None'); ?>">
                                            <?php echo $s['InchargeName'] ? substr(htmlspecialchars($s['InchargeName']), 0, 10).'.' : 'No Inc.'; ?>
                                        </span>
                                        <?php if($s['RoomName']): ?>
                                        <div class="text-[10px] bg-green-50 text-green-700 font-bold mt-0.5 rounded px-1 max-w-[80px] mx-auto truncate" title="Home Room: <?php echo htmlspecialchars($s['RoomName']); ?>">
                                            <?php echo htmlspecialchars($s['RoomName']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Hover actions -->
                                    <div class="absolute -top-3 -right-2 hidden group-hover:flex bg-white shadow-md rounded-full border border-gray-200 p-0.5 z-10 gap-1">
                                        <button onclick='openEditModal(<?php echo json_encode([
                                            "id" => $s["SectionID"],
                                            "pid" => $s["ProgramID"],
                                            "did" => $s["DepartmentID"],
                                            "sid" => $s["SemesterID"],
                                            "shid" => $s["ShiftID"],
                                            "name" => $s["Name"],
                                            "incharge" => $s["InchargeID"],
                                            "homeroom" => $s["HomeRoomID"]
                                        ]); ?>)' type="button" class="text-blue-500 hover:text-blue-700 hover:bg-blue-50 p-1 rounded-full" title="Edit Section">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                                        </button>
                                        <form method="POST" class="inline m-0" onsubmit="return confirm('Delete permanently?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $s['SectionID']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 hover:bg-red-50 p-1 rounded-full" title="Delete Section">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 flex justify-center items-center z-[100]" style="display: none;">
    <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-[750px] border border-gray-100">
        <h3 class="font-bold text-lg mb-5 text-gray-800 border-b pb-2">Edit Section / Class</h3>
        <form method="POST" class="flex flex-col gap-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Program</label>
                    <select name="program_id" id="edit_prog" required class="w-full border border-gray-300 p-2.5 rounded focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                        <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Group/Dept</label>
                    <select name="dept_id" id="edit_dept" required class="w-full border border-gray-300 p-2.5 rounded focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                        <?php foreach($departments as $d): echo "<option value='{$d['DepartmentID']}' data-prog='{$d['ProgramID']}'>{$d['Name']}</option>"; endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Semester/Year</label>
                    <select name="semester_id" id="edit_sem" required class="w-full border border-gray-300 p-2.5 rounded focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                        <?php foreach($semesters as $s): echo "<option value='{$s['SemesterID']}' data-prog='{$s['ProgramID']}'>{$s['Label']}</option>"; endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Shift</label>
                    <select name="shift_id" id="edit_shift" required class="w-full border border-gray-300 p-2.5 rounded focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                        <?php foreach($shifts as $sh): echo "<option value='{$sh['ShiftID']}'>{$sh['Name']}</option>"; endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Sec Name</label>
                    <input type="text" name="name" id="edit_name" placeholder="None (Optional)" class="w-full border border-gray-300 p-2.5 rounded focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Home Room</label>
                    <select name="homeroom_id" id="edit_homeroom" class="w-full border border-gray-300 p-2.5 rounded focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                        <option value="">None</option>
                        <?php foreach($classrooms as $r): echo "<option value='{$r['RoomID']}'>{$r['Name']}</option>"; endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-gray-600 mb-1">Class Incharge</label>
                    <select name="incharge_id" id="edit_incharge" class="w-full border border-gray-300 p-2.5 rounded focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                        <option value="">None</option>
                        <?php foreach($teachers as $t): echo "<option value='{$t['UserID']}'>{$t['Name']}</option>"; endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-5 py-2 rounded transition-colors font-medium">Cancel</button>
                <button type="submit" class="bg-[#a60b26] hover:bg-[#8a0a20] text-white font-bold px-5 py-2 rounded transition-colors shadow-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_prog').value = data.pid;
    document.getElementById('edit_dept').value = data.did;
    document.getElementById('edit_sem').value = data.sid;
    document.getElementById('edit_shift').value = data.shid;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_incharge').value = data.incharge || '';
    document.getElementById('edit_homeroom').value = data.homeroom || '';
    
    document.getElementById('editModal').style.display = 'flex';
}

// Logic to filter Depts & Sems by Program selection in Add Form
document.getElementById('prog_select').addEventListener('change', function() {
    let pid = this.value;
    ['dept_select', 'sem_select'].forEach(id => {
        let select = document.getElementById(id);
        for(let i=1; i<select.options.length; i++) {
            let optProg = select.options[i].getAttribute('data-prog');
            select.options[i].style.display = (pid === "" || optProg === pid) ? 'block' : 'none';
        }
        select.value = "";
    });
});

// Logic to filter Depts & Sems by Program selection in Edit Form
document.getElementById('edit_prog').addEventListener('change', function() {
    let pid = this.value;
    ['edit_dept', 'edit_sem'].forEach(id => {
        let select = document.getElementById(id);
        for(let i=0; i<select.options.length; i++) {
            let optProg = select.options[i].getAttribute('data-prog');
            select.options[i].style.display = (pid === "" || optProg === pid) ? 'block' : 'none';
        }
        select.value = "";
    });
});
</script>

