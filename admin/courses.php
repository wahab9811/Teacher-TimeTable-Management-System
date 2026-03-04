<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$message='';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['add'])) {
        $pdo->prepare("INSERT INTO courses (Name, ProgramID, DepartmentID, SemesterID, ShiftID, SectionID, TeacherID, CreditHours, RoomType) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$_POST['name'], $_POST['pid'], $_POST['did'], $_POST['sid'], $_POST['shift'], $_POST['secid'] ?: null, $_POST['tid'], $_POST['cr'], $_POST['type']]);
        $message="Course added.";
    } elseif(isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM courses WHERE CourseID=?")->execute([$_POST['id']]); $message="Course deleted.";
    } elseif(isset($_POST['edit'])) {
        $pdo->prepare("UPDATE courses SET Name=?, ProgramID=?, DepartmentID=?, SemesterID=?, ShiftID=?, SectionID=?, TeacherID=?, CreditHours=?, RoomType=? WHERE CourseID=?")
            ->execute([$_POST['name'], $_POST['pid'], $_POST['did'], $_POST['sid'], $_POST['shift'], $_POST['secid'] ?: null, $_POST['tid'], $_POST['cr'], $_POST['type'], $_POST['id']]);
        $message="Course updated.";
    }
}
$courses = $pdo->query("SELECT c.*, p.Name as P, d.Name as D, s.Label as S, sh.Name as SH, sec.Name as SEC, u.Name as T FROM courses c JOIN programs p ON c.ProgramID=p.ProgramID JOIN departments d ON c.DepartmentID=d.DepartmentID JOIN semesters s ON c.SemesterID=s.SemesterID JOIN shifts sh ON c.ShiftID=sh.ShiftID LEFT JOIN sections sec ON c.SectionID=sec.SectionID JOIN users u ON c.TeacherID=u.UserID")->fetchAll();
$programs = $pdo->query("SELECT * FROM programs")->fetchAll();
$depts = $pdo->query("SELECT * FROM departments")->fetchAll();
$sems = $pdo->query("SELECT * FROM semesters")->fetchAll();
$teachers = $pdo->query("SELECT * FROM users WHERE Role='teacher'")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <h2 class="text-2xl font-bold text-maroon mb-4">Manage Courses</h2>
        <?php if($message): ?><div class="bg-gray-100 text-maroon p-2 mb-4 font-bold"><?php echo $message; ?></div><?php endif; ?>
        
        <form method="POST" class="grid grid-cols-5 gap-4 mb-6 border-b pb-6">
            <input type="text" name="name" placeholder="Course Name" required class="col-span-2 border p-2 rounded">
            
            <select name="pid" id="add_pid" required class="border p-2 rounded"><option value="">Program</option><?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?></select>
            <select name="did" id="add_did" required class="border p-2 rounded"><option value="">Dept/Group</option><?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}'>{$d['Name']}</option>"; endforeach; ?></select>
            <select name="sid" id="add_sid" required class="border p-2 rounded"><option value="">Sem/Year</option><?php foreach($sems as $s): echo "<option value='{$s['SemesterID']}'>{$s['Label']}</option>"; endforeach; ?></select>
            
            <select name="secid" id="add_secid" class="border p-2 rounded"><option value="">Select Section</option></select>
            <select name="shift" required class="border p-2 rounded"><option value="1">Morning</option><option value="2">Evening</option></select>
            
            <select name="tid" required class="border p-2 rounded"><option value="">Teacher</option><?php foreach($teachers as $t): echo "<option value='{$t['UserID']}'>{$t['Name']}</option>"; endforeach; ?></select>
            <input type="number" name="cr" placeholder="Credit Hours" required min="1" max="6" class="border p-2 rounded">
            <select name="type" required class="border p-2 rounded"><option value="Classroom">Classroom</option><option value="Lab">Lab</option></select>
            
            <button name="add" class="col-span-5 btn-maroon px-6 py-2 rounded font-bold mt-2">Add Course</button>
        </form>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap"><thead class="bg-gray-100"><tr><th class="p-2">Name</th><th class="p-2">Program</th><th class="p-2">Group</th><th class="p-2">Year/Section</th><th class="p-2">Teacher</th><th class="p-2">CR</th><th class="p-2">Action</th></tr></thead>
            <tbody>
                <?php foreach($courses as $c): ?>
                <tr class="border-b">
                    <td class="p-2 font-bold"><?php echo htmlspecialchars($c['Name']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($c['P']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($c['D']); ?></td>
                    <td class="p-2 font-semibold">
                        <?php echo htmlspecialchars($c['S']); ?> 
                        <?php if($c['SEC']): ?><span class="text-[#a60b26] bg-[#a60b26]/10 px-1 rounded">Sec <?php echo htmlspecialchars($c['SEC']); ?></span><?php endif; ?>
                        <span class="text-xs text-gray-500 block">(<?php echo htmlspecialchars($c['SH']); ?>)</span>
                    </td>
                    <td class="p-2"><?php echo htmlspecialchars($c['T']); ?></td>
                    <td class="p-2"><?php echo $c['CreditHours'].' ('.$c['RoomType'].')'; ?></td>
                    <td class="p-2 flex gap-3 items-center">
                        <button type="button" onclick='openEditModal(<?php echo json_encode([
                            "id" => $c["CourseID"],
                            "name" => $c["Name"],
                            "pid" => $c["ProgramID"],
                            "did" => $c["DepartmentID"],
                            "sid" => $c["SemesterID"],
                            "shift" => $c["ShiftID"],
                            "secid" => $c["SectionID"],
                            "tid" => $c["TeacherID"],
                            "cr" => $c["CreditHours"],
                            "type" => $c["RoomType"]
                        ]); ?>)' class="text-blue-600 hover:text-blue-800 font-medium">Edit</button>
                        <form method="POST" onsubmit="return confirm('Delete?');" class="inline m-0"><input type="hidden" name="id" value="<?php echo $c['CourseID']; ?>"><button name="delete" class="text-red-500 hover:text-red-700 font-medium">Del</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody></table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 flex justify-center items-center z-[100]" style="display: none;">
    <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-[600px] border border-gray-100 max-h-[90vh] overflow-y-auto">
        <h3 class="font-bold text-lg mb-4 text-[#111827]">Edit Course</h3>
        <form method="POST" class="grid grid-cols-2 gap-4">
            <input type="hidden" name="id" id="edit_id">
            <input type="text" name="name" id="edit_name" placeholder="Course Name" required class="border border-gray-300 p-2.5 rounded-lg col-span-2">
            
            <select name="pid" id="edit_pid" required class="border border-gray-300 p-2.5 rounded-lg"><option value="">Program</option><?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?></select>
            <select name="did" id="edit_did" required class="border border-gray-300 p-2.5 rounded-lg"><option value="">Dept</option><?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}'>{$d['Name']}</option>"; endforeach; ?></select>
            <select name="sid" id="edit_sid" required class="border border-gray-300 p-2.5 rounded-lg"><option value="">Sem/Year</option><?php foreach($sems as $s): echo "<option value='{$s['SemesterID']}'>{$s['Label']}</option>"; endforeach; ?></select>
            
            <select name="secid" id="edit_secid" class="border border-gray-300 p-2.5 rounded-lg"><option value="">Select Section</option></select>
            <select name="shift" id="edit_shift" required class="border border-gray-300 p-2.5 rounded-lg"><option value="1">Morning</option><option value="2">Evening</option></select>
            
            <select name="tid" id="edit_tid" required class="border border-gray-300 p-2.5 rounded-lg col-span-2"><option value="">Teacher</option><?php foreach($teachers as $t): echo "<option value='{$t['UserID']}'>{$t['Name']}</option>"; endforeach; ?></select>
            
            <input type="number" name="cr" id="edit_cr" placeholder="Credit Hours" required min="1" max="6" class="border border-gray-300 p-2.5 rounded-lg">
            <select name="type" id="edit_type" required class="border border-gray-300 p-2.5 rounded-lg"><option value="Classroom">Classroom</option><option value="Lab">Lab</option></select>

            <div class="col-span-2 flex justify-end gap-2 mt-4 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="bg-gray-100 px-5 py-2.5 rounded-lg font-bold">Cancel</button>
                <button type="submit" name="edit" class="bg-[#a60b26] text-white px-5 py-2.5 rounded-lg font-bold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Load sections via AJAX for ADD form
function loadSectionsAdd() {
    let pid = document.getElementById('add_pid').value;
    let did = document.getElementById('add_did').value;
    let sid = document.getElementById('add_sid').value;
    let secSelect = document.getElementById('add_secid');
    
    secSelect.innerHTML = '<option value="">Select Section</option>';
    if(pid && did && sid) {
        fetch(`../api/public.php?action=get_sections&program_id=${pid}&dept_id=${did}&semester_id=${sid}`)
        .then(r => r.json())
        .then(data => {
            data.forEach(sec => {
                let opt = document.createElement('option');
                opt.value = sec.SectionID; opt.textContent = 'Section ' + sec.Name;
                secSelect.appendChild(opt);
            });
        });
    }
}
document.getElementById('add_pid').addEventListener('change', loadSectionsAdd);
document.getElementById('add_did').addEventListener('change', loadSectionsAdd);
document.getElementById('add_sid').addEventListener('change', loadSectionsAdd);

// Load sections via AJAX for EDIT form
function loadSectionsEdit(preselectId = null) {
    let pid = document.getElementById('edit_pid').value;
    let did = document.getElementById('edit_did').value;
    let sid = document.getElementById('edit_sid').value;
    let secSelect = document.getElementById('edit_secid');
    
    secSelect.innerHTML = '<option value="">Select Section</option>';
    if(pid && did && sid) {
        fetch(`../api/public.php?action=get_sections&program_id=${pid}&dept_id=${did}&semester_id=${sid}`)
        .then(r => r.json())
        .then(data => {
            data.forEach(sec => {
                let opt = document.createElement('option');
                opt.value = sec.SectionID; opt.textContent = 'Section ' + sec.Name;
                secSelect.appendChild(opt);
            });
            if(preselectId) secSelect.value = preselectId;
        });
    }
}
document.getElementById('edit_pid').addEventListener('change', () => loadSectionsEdit());
document.getElementById('edit_did').addEventListener('change', () => loadSectionsEdit());
document.getElementById('edit_sid').addEventListener('change', () => loadSectionsEdit());

function openEditModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_pid').value = data.pid;
    document.getElementById('edit_did').value = data.did;
    document.getElementById('edit_sid').value = data.sid;
    document.getElementById('edit_shift').value = data.shift;
    document.getElementById('edit_tid').value = data.tid;
    document.getElementById('edit_cr').value = data.cr;
    document.getElementById('edit_type').value = data.type;
    
    // Load sections and wait to preselect
    loadSectionsEdit(data.secid);
    document.getElementById('editModal').style.display = 'flex';
}
</script>

<?php include '../includes/footer.php'; ?>
