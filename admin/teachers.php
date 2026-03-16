<?php
// admin/teachers.php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$message = '';
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if($_POST['action'] === 'add') {
        $pwd = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $hod = isset($_POST['is_hod']) ? 1 : 0;
        $pdo->prepare("INSERT INTO users (Name, Email, Password, Role, Designation, IsHOD, HOD_DepartmentID) VALUES (?, ?, ?, 'teacher', ?, ?, ?)")
            ->execute([$_POST['name'], $_POST['email'], $pwd, $_POST['designation'], $hod, $_POST['dept_id'] ?: null]);
        $message = "Teacher added.";
    } elseif ($_POST['action'] === 'edit') {
        $hod = isset($_POST['is_hod']) ? 1 : 0;
        $hod_dept = $_POST['hod_dept_id'] ?: null;
        
        $emp_id = $_POST['employee_id'] ?: null;
        $work_dept = $_POST['work_dept_id'] ?: null;
        $qual = $_POST['qualification'] ?: null;
        $spec = $_POST['specialization'] ?: null;
        $join = $_POST['joining_date'] ?: null;
        $emp_type = $_POST['employment_type'] ?: null;
        $exp = $_POST['experience'] ?: null;
        $status = $_POST['account_status'] ?: 'Active';

        if (!empty($_POST['password'])) {
            $pwd = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET Name=?, Email=?, Password=?, Designation=?, IsHOD=?, HOD_DepartmentID=?, EmployeeID=?, DepartmentID=?, Qualification=?, Specialization=?, JoiningDate=?, EmploymentType=?, Experience=?, AccountStatus=? WHERE UserID=?")
                ->execute([
                    $_POST['name'], $_POST['email'], $pwd, $_POST['designation'], $hod, $hod_dept, 
                    $emp_id, $work_dept, $qual, $spec, $join, $emp_type, $exp, $status, 
                    $_POST['id']
                ]);
        } else {
            $pdo->prepare("UPDATE users SET Name=?, Email=?, Designation=?, IsHOD=?, HOD_DepartmentID=?, EmployeeID=?, DepartmentID=?, Qualification=?, Specialization=?, JoiningDate=?, EmploymentType=?, Experience=?, AccountStatus=? WHERE UserID=?")
                ->execute([
                    $_POST['name'], $_POST['email'], $_POST['designation'], $hod, $hod_dept, 
                    $emp_id, $work_dept, $qual, $spec, $join, $emp_type, $exp, $status, 
                    $_POST['id']
                ]);
        }
        $message = "Teacher updated.";
    } elseif ($_POST['action'] === 'delete') {
        // Only if no active timetable assignments, enforced by constraint or query
        $chk = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE TeacherID = ?");
        $chk->execute([$_POST['id']]);
        if($chk->fetchColumn() > 0) $message = "Cannot delete: Teacher has active timetable assignments.";
        else {
            $pdo->prepare("DELETE FROM users WHERE UserID = ?")->execute([$_POST['id']]);
            $message = "Teacher deleted.";
        }
    }
}

$teachers = $pdo->query("SELECT u.*, d.Name as HODDept, w.Name as WorkDept FROM users u LEFT JOIN departments d ON u.HOD_DepartmentID=d.DepartmentID LEFT JOIN departments w ON u.DepartmentID=w.DepartmentID WHERE Role='teacher'")->fetchAll();
$depts = $pdo->query("SELECT * FROM departments")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Manage Teachers</h2>
        <?php if($message): ?><div class="bg-gray-100 text-maroon font-bold p-3 rounded mb-4"><?php echo $message; ?></div><?php endif; ?>
        
        <div class="bg-white p-6 shadow-md rounded mb-6">
            <h3 class="font-bold mb-4">Add Teacher (Basic)</h3>
            <form method="POST" class="grid grid-cols-2 gap-4">
                <input type="hidden" name="action" value="add">
                <input type="text" name="name" placeholder="Name" required class="border p-2 rounded">
                <input type="email" name="email" placeholder="Email" required class="border p-2 rounded">
                <input type="text" name="password" placeholder="Password" required class="border p-2 rounded">
                <select name="designation" required class="border p-2 rounded">
                    <option value="Lecturer">Lecturer</option>
                    <option value="Assistant Professor">Assistant Professor</option>
                    <option value="Associate Professor">Associate Professor</option>
                    <option value="Professor">Professor</option>
                </select>
                <div class="flex items-center space-x-2">
                    <input type="checkbox" name="is_hod" value="1" onchange="document.getElementById('hod_d').style.display = this.checked ? 'block' : 'none'">
                    <span>Is HOD?</span>
                </div>
                <select name="dept_id" id="hod_d" style="display:none;" class="border p-2 rounded">
                    <option value="">Select HOD Department</option>
                    <?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}'>{$d['Name']}</option>"; endforeach; ?>
                </select>
                <button class="btn-maroon p-2 rounded col-span-2">Add Teacher</button>
            </form>
        </div>
        
        <div class="bg-white p-6 shadow-md rounded">
            <table class="w-full text-left">
                <thead class="bg-gray-100"><tr><th class="p-2">Name</th><th class="p-2">Dept</th><th class="p-2">Designation</th><th class="p-2">Account</th><th class="p-2">Action</th></tr></thead>
                <tbody>
                    <?php foreach($teachers as $t): ?>
                    <tr class="border-b">
                        <td class="p-2">
                            <div class="font-bold text-gray-800"><?php echo htmlspecialchars($t['Name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($t['EmployeeID'] ?: 'No App ID'); ?></div>
                        </td>
                        <td class="p-2"><?php echo htmlspecialchars($t['WorkDept'] ?: '-'); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($t['Designation']); ?>
                            <?php if($t['IsHOD']): ?><span class="text-xs bg-amber-100 text-amber-800 px-1 rounded ml-1 font-bold">HOD</span><?php endif; ?>
                        </td>
                        <td class="p-2">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold <?php echo $t['AccountStatus']==='Active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                <?php echo htmlspecialchars($t['AccountStatus'] ?: 'Active'); ?>
                            </span>
                        </td>
                        <td class="p-2 flex gap-3 items-center pt-4">
                            <button type="button" onclick='openEditModal(<?php echo json_encode([
                                "id" => $t["UserID"],
                                "name" => $t["Name"],
                                "email" => $t["Email"],
                                "designation" => $t["Designation"],
                                "is_hod" => $t["IsHOD"],
                                "hod_dept_id" => $t["HOD_DepartmentID"],
                                "employee_id" => $t["EmployeeID"],
                                "work_dept_id" => $t["DepartmentID"],
                                "qualification" => $t["Qualification"],
                                "specialization" => $t["Specialization"],
                                "joining_date" => $t["JoiningDate"],
                                "employment_type" => $t["EmploymentType"],
                                "experience" => $t["Experience"],
                                "account_status" => $t["AccountStatus"]
                            ]); ?>)' class="text-blue-600 hover:text-blue-800 font-medium">Edit</button>
                            <form method="POST" onsubmit="return confirm('Delete?');" class="inline m-0">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $t['UserID']; ?>">
                                <button class="text-red-500 hover:text-red-700 font-medium">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Super Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 flex justify-center items-center z-[100]" style="display: none;">
    <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-[800px] border border-gray-100 max-h-[90vh] overflow-y-auto">
        <h3 class="font-bold text-xl mb-4 text-[#111827]">Edit Teacher & Professional Info</h3>
        <form method="POST" class="grid grid-cols-2 gap-x-6 gap-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="col-span-2">
                <h4 class="font-bold text-gray-700 border-b pb-1 mb-2">Basic & Access Info</h4>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Name</label>
                <input type="text" name="name" id="edit_name" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-[#a60b26]">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Email</label>
                <input type="email" name="email" id="edit_email" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-[#a60b26]">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Account Status</label>
                <select name="account_status" id="edit_account_status" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-[#a60b26]">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Reset Password (leave blank to keep)</label>
                <input type="text" name="password" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-[#a60b26]">
            </div>

            <div class="col-span-2 mt-2">
                <h4 class="font-bold text-gray-700 border-b pb-1 mb-2">Professional Profile Info</h4>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Employee ID</label>
                <input type="text" name="employee_id" id="edit_employee_id" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-[#a60b26]">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Designation</label>
                <select name="designation" id="edit_designation" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-[#a60b26]">
                    <option value="Lecturer">Lecturer</option>
                    <option value="Assistant Professor">Assistant Professor</option>
                    <option value="Associate Professor">Associate Professor</option>
                    <option value="Professor">Professor</option>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Working Department</label>
                <select name="work_dept_id" id="edit_work_dept_id" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-[#a60b26]">
                    <option value="">Select Department</option>
                    <?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}'>{$d['Name']}</option>"; endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Qualification</label>
                <input type="text" name="qualification" id="edit_qualification" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-[#a60b26]">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Specialization</label>
                <input type="text" name="specialization" id="edit_specialization" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-[#a60b26]">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Joining Date</label>
                <input type="date" name="joining_date" id="edit_joining_date" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-[#a60b26]">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Employment Type</label>
                <select name="employment_type" id="edit_employment_type" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-[#a60b26]">
                    <option value="">Select Type</option>
                    <option value="Permanent">Permanent</option>
                    <option value="Contract">Contract</option>
                    <option value="Visiting">Visiting</option>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Experience (e.g. 5 Years)</label>
                <input type="text" name="experience" id="edit_experience" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-[#a60b26]">
            </div>
            
            <!-- HOD Status (Special Case) -->
            <div class="col-span-2 mt-2 pt-2 border-t border-gray-100">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" name="is_hod" id="edit_is_hod" value="1" onchange="document.getElementById('edit_hod_d').style.display = this.checked ? 'block' : 'none'" class="h-4 w-4 text-[#a60b26]">
                    <span class="text-gray-700 font-bold">Assign as Head of Department?</span>
                </div>
                <select name="hod_dept_id" id="edit_hod_d" style="display:none;" class="mt-2 w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26]">
                    <option value="">Select HOD Department</option>
                    <?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}'>{$d['Name']}</option>"; endforeach; ?>
                </select>
            </div>

            <div class="col-span-2 flex justify-end gap-2 mt-4 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold px-5 py-2.5 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="bg-[#a60b26] hover:bg-[#8a0a20] text-white font-bold px-5 py-2.5 rounded-lg transition-colors">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_email').value = data.email;
    document.getElementById('edit_account_status').value = data.account_status || 'Active';
    
    document.getElementById('edit_employee_id').value = data.employee_id || '';
    document.getElementById('edit_designation').value = data.designation;
    document.getElementById('edit_work_dept_id').value = data.work_dept_id || '';
    document.getElementById('edit_qualification').value = data.qualification || '';
    document.getElementById('edit_specialization').value = data.specialization || '';
    document.getElementById('edit_joining_date').value = data.joining_date || '';
    document.getElementById('edit_employment_type').value = data.employment_type || '';
    document.getElementById('edit_experience').value = data.experience || '';
    
    document.getElementById('edit_is_hod').checked = (data.is_hod == 1);
    document.getElementById('edit_hod_d').style.display = (data.is_hod == 1) ? 'block' : 'none';
    document.getElementById('edit_hod_d').value = data.hod_dept_id || '';
    
    document.getElementById('editModal').style.display = 'flex';
}
</script>

<?php include '../includes/footer.php'; ?>
