<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$message = '';
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if($_POST['action'] === 'add') {
        $pwd = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $hod = isset($_POST['is_hod']) ? 1 : 0;
        try {
            if ($hod == 1 && !empty($_POST['hod_dept_id'])) {
                // Ensure uniqueness: demote existing HOD for this department
                $pdo->prepare("UPDATE users SET IsHOD = 0, HOD_DepartmentID = NULL WHERE HOD_DepartmentID = ?")->execute([$_POST['hod_dept_id']]);
            }
            $avail = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : null;
            $pdo->prepare("INSERT INTO users (Name, Email, Password, Role, Designation, IsHOD, HOD_DepartmentID, DepartmentID, AvailableDays) VALUES (?, ?, ?, 'teacher', ?, ?, ?, ?, ?)")
                ->execute([$_POST['name'], $_POST['email'], $pwd, $_POST['designation'], $hod, empty($_POST['hod_dept_id']) ? null : $_POST['hod_dept_id'], empty($_POST['work_dept_id']) ? null : $_POST['work_dept_id'], $avail]);
            $message = "Teacher added successfully.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = "Cannot add: This email already exists in the system.";
            } else {
                $message = "Database Error: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'edit') {
        $hod = isset($_POST['is_hod']) ? 1 : 0;
        $hod_dept = empty($_POST['hod_dept_id']) ? null : $_POST['hod_dept_id'];
        
        $emp_id = empty($_POST['employee_id']) ? null : $_POST['employee_id'];
        $work_dept = empty($_POST['work_dept_id']) ? null : $_POST['work_dept_id'];
        $qual = empty($_POST['qualification']) ? null : $_POST['qualification'];
        $spec = empty($_POST['specialization']) ? null : $_POST['specialization'];
        $join = empty($_POST['joining_date']) ? null : $_POST['joining_date'];
        $emp_type = empty($_POST['employment_type']) ? null : $_POST['employment_type'];
        $exp = empty($_POST['experience']) ? null : $_POST['experience'];
        $status = $_POST['account_status'] ?: 'Active';

        $avail = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : null;
        try {
            if ($hod == 1 && $hod_dept) {
                // Ensure uniqueness: demote existing HOD for this department
                $pdo->prepare("UPDATE users SET IsHOD = 0, HOD_DepartmentID = NULL WHERE HOD_DepartmentID = ? AND UserID != ?")->execute([$hod_dept, $_POST['id']]);
            }
            
            if (!empty($_POST['password'])) {
                $pwd = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET Name=?, Email=?, Password=?, Designation=?, IsHOD=?, HOD_DepartmentID=?, EmployeeID=?, DepartmentID=?, Qualification=?, Specialization=?, JoiningDate=?, EmploymentType=?, Experience=?, AccountStatus=?, AvailableDays=? WHERE UserID=?")
                    ->execute([
                        $_POST['name'], $_POST['email'], $pwd, $_POST['designation'], $hod, $hod_dept, 
                        $emp_id, $work_dept, $qual, $spec, $join, $emp_type, $exp, $status, $avail,
                        $_POST['id']
                    ]);
            } else {
                $pdo->prepare("UPDATE users SET Name=?, Email=?, Designation=?, IsHOD=?, HOD_DepartmentID=?, EmployeeID=?, DepartmentID=?, Qualification=?, Specialization=?, JoiningDate=?, EmploymentType=?, Experience=?, AccountStatus=?, AvailableDays=? WHERE UserID=?")
                    ->execute([
                        $_POST['name'], $_POST['email'], $_POST['designation'], $hod, $hod_dept, 
                        $emp_id, $work_dept, $qual, $spec, $join, $emp_type, $exp, $status, $avail,
                        $_POST['id']
                    ]);
            }
            $message = "Teacher profile updated.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = "Cannot update: This Email or Employee ID already exists in the system.";
            } else {
                $message = "Database Error: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = $_POST['id'];
        $crs = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE TeacherID = ?"); $crs->execute([$id]);
        $tt = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE TeacherID = ?"); $tt->execute([$id]);
        $req = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE RequestedBy = ? OR TargetTeacherID = ?"); $req->execute([$id, $id]);
        $sub = $pdo->prepare("SELECT COUNT(*) FROM substitute_assignments WHERE SubstituteTeacherID = ? OR OriginalTeacherID = ?"); $sub->execute([$id, $id]);
        
        $total = $crs->fetchColumn() + $tt->fetchColumn() + $req->fetchColumn() + $sub->fetchColumn();
        if ($total > 0) {
            $message = "Cannot delete, it has linked records. Use Toggle Status / reassign first.";
        } else {
            $pdo->prepare("DELETE FROM users WHERE UserID = ?")->execute([$id]);
            $message = "Teacher removed successfully.";
        }
    }
}

$limit = 5;
$currentPageNum = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if($currentPageNum < 1) $currentPageNum = 1;
$offset = ($currentPageNum - 1) * $limit;

$totalTeachers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE Role='teacher'")->fetchColumn();
$totalPages = ceil($totalTeachers / $limit);

$teachers = $pdo->query("
    SELECT u.*, 
           hd.Name as HODDept, hp.Name as HODProg,
           wd.Name as WorkDept, wp.Name as WorkProg
    FROM users u 
    LEFT JOIN departments hd ON u.HOD_DepartmentID = hd.DepartmentID 
    LEFT JOIN programs hp ON hd.ProgramID = hp.ProgramID
    LEFT JOIN departments wd ON u.DepartmentID = wd.DepartmentID 
    LEFT JOIN programs wp ON wd.ProgramID = wp.ProgramID
    WHERE Role='teacher'
    ORDER BY u.Name
    LIMIT $limit OFFSET $offset
")->fetchAll();

$depts = $pdo->query("
    SELECT d.DepartmentID, d.Name as DName, p.Name as PName 
    FROM departments d 
    JOIN programs p ON d.ProgramID = p.ProgramID 
    WHERE d.IsActive = 1
    ORDER BY p.Name, d.Name
")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-[#a60b26]">Faculty Management</h2>
        </div>
        
        <?php if($message): ?>
            <div class="<?php echo strpos($message, 'Cannot') !== false ? 'bg-red-100 text-red-800 border-red-200' : 'bg-green-100 text-green-800 border-green-200'; ?> p-3 rounded-lg mb-6 font-semibold text-sm border shadow-sm">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Add Teacher Form -->
        <div class="bg-gray-50 border border-gray-200 p-5 rounded-xl mb-6 shadow-sm">
            <h3 class="font-bold mb-4 text-lg text-gray-800 border-b border-gray-200 pb-2">Register New Faculty</h3>
            <form method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="add">
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-gray-600 mb-1">Full Name</label>
                        <input type="text" name="name" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-gray-600 mb-1">Email Address</label>
                        <input type="email" name="email" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-gray-600 mb-1">Temporary Password</label>
                        <input type="text" name="password" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-gray-600 mb-1">Designation</label>
                        <select name="designation" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                            <option value="Lecturer">Lecturer</option>
                            <option value="Assistant Professor">Assistant Professor</option>
                            <option value="Associate Professor">Associate Professor</option>
                            <option value="Professor">Professor</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-2">
                    <div class="md:col-span-5">
                        <label class="block text-xs font-bold text-gray-600 mb-1">Home Department (Base Program)</label>
                        <select name="work_dept_id" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-1 focus:ring-maroon focus:outline-none">
                            <option value="">No Base Department (Floating)</option>
                            <?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}'>{$d['PName']} — {$d['DName']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="md:col-span-7 rounded-lg pt-6 flex flex-col sm:flex-row items-center gap-4">
                        <div class="flex items-center space-x-2 whitespace-nowrap">
                            <input type="checkbox" name="is_hod" value="1" id="add_is_hod" class="h-4 w-4 text-[#a60b26]" onchange="document.getElementById('add_hod_d').style.display = this.checked ? 'block' : 'none'">
                            <label for="add_is_hod" class="text-sm font-bold text-gray-700 cursor-pointer">Assign as HOD?</label>
                        </div>
                        <select name="hod_dept_id" id="add_hod_d" style="display:none;" class="w-full border border-gray-300 p-2 rounded-md focus:ring-1 focus:ring-[#a60b26] text-sm">
                            <option value="">Select HOD Department...</option>
                            <?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}'>{$d['PName']} — {$d['DName']}</option>"; endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-span-1 md:col-span-12 mt-2 bg-white border border-gray-200 p-3 rounded-lg">
                    <label class="block text-xs font-bold text-gray-600 mb-2">Available Working Days <span class="text-gray-400 font-normal">(Leave all unchecked to declare availability on ALL active days)</span></label>
                    <div class="flex flex-wrap gap-4">
                        <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day): ?>
                        <label class="flex items-center space-x-1.5 cursor-pointer">
                            <input type="checkbox" name="available_days[]" value="<?php echo $day; ?>" class="h-4 w-4 text-[#a60b26] focus:ring-[#a60b26] border-gray-300 rounded">
                            <span class="text-sm text-gray-700 font-medium"><?php echo $day; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="flex justify-end mt-2">
                    <button class="bg-[#a60b26] text-white px-8 py-2.5 rounded-lg font-bold hover:bg-[#8a0a20] transition shadow-sm">Save Teacher</button>
                </div>
            </form>
        </div>
        
        <!-- Teachers List -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <div class="bg-gray-100 p-3 border-b border-gray-200 flex justify-between items-center">
                <h3 class="font-bold text-gray-800 text-lg">Faculty Directory</h3>
                <span class="text-xs font-bold text-gray-500 bg-gray-200 px-2 py-1 rounded-full"><?php echo $totalTeachers; ?> Registered</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="p-3 font-semibold">Profile</th>
                            <th class="p-3 font-semibold">Home Department</th>
                            <th class="p-3 font-semibold">Roles & Config</th>
                            <th class="p-3 font-semibold text-center">Status</th>
                            <th class="p-3 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($teachers as $t): ?>
                        <tr class="border-b last:border-0 hover:bg-gray-50/50 transition">
                            <td class="p-3">
                                <div class="font-bold text-gray-800 text-base"><?php echo htmlspecialchars($t['Name']); ?></div>
                                <div class="text-xs text-blue-600 font-semibold"><?php echo htmlspecialchars($t['EmployeeID'] ?: 'ID Not Assigned'); ?></div>
                            </td>
                            <td class="p-3">
                                <?php if($t['WorkDept']): ?>
                                    <div class="text-gray-800 font-medium"><?php echo htmlspecialchars($t['WorkDept']); ?></div>
                                    <div class="text-xs text-gray-500 font-bold"><?php echo htmlspecialchars($t['WorkProg']); ?></div>
                                <?php else: ?>
                                    <span class="text-gray-400 italic text-xs">Floating Faculty</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3">
                                <div class="text-gray-700 font-semibold mb-1"><?php echo htmlspecialchars($t['Designation']); ?></div>
                                <?php if($t['IsHOD'] && $t['HODDept']): ?>
                                    <span class="inline-flex bg-amber-100 text-amber-800 px-2 py-0.5 rounded text-xs font-bold border border-amber-200">
                                        Head of <?php echo htmlspecialchars($t['HODDept']); ?> (<?php echo htmlspecialchars($t['HODProg']); ?>)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-center">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold <?php echo $t['AccountStatus']==='Active' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
                                    <?php echo htmlspecialchars($t['AccountStatus'] ?: 'Active'); ?>
                                </span>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" onclick='openEditModal(<?php echo json_encode([
                                        "id" => $t["UserID"], "name" => $t["Name"], "email" => $t["Email"],
                                        "designation" => $t["Designation"], "is_hod" => $t["IsHOD"],
                                        "hod_dept_id" => $t["HOD_DepartmentID"], "employee_id" => $t["EmployeeID"],
                                        "work_dept_id" => $t["DepartmentID"], "qualification" => $t["Qualification"],
                                        "specialization" => $t["Specialization"], "joining_date" => $t["JoiningDate"],
                                        "employment_type" => $t["EmploymentType"], "experience" => $t["Experience"],
                                        "account_status" => $t["AccountStatus"], "available_days" => $t["AvailableDays"]
                                    ]); ?>)' class="text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-md transition font-bold text-xs">Profile</button>
                                    <form method="POST" onsubmit="return confirm('Remove this faculty member?');" class="inline m-0">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $t['UserID']; ?>">
                                        <button class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-md transition font-bold text-xs">Del</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($totalPages > 1): ?>
            <div class="bg-gray-50 p-4 border-t border-gray-200 flex justify-between items-center sm:flex-row flex-col gap-4">
                <span class="text-sm font-semibold text-gray-600">Showing page <?php echo $currentPageNum; ?> of <?php echo $totalPages; ?></span>
                <div class="flex items-center gap-1">
                    <?php if($currentPageNum > 1): ?>
                        <a href="?page=<?php echo $currentPageNum - 1; ?>" class="px-3 py-1.5 border border-gray-300 rounded text-sm font-bold text-gray-700 bg-white hover:bg-gray-100">&laquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $currentPageNum - 2);
                    $end_page = min($totalPages, $currentPageNum + 2);
                    for($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>" class="px-3 py-1.5 border <?php echo $i == $currentPageNum ? 'border-[#a60b26] bg-[#a60b26] text-white' : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-100'; ?> rounded text-sm font-bold"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if($currentPageNum < $totalPages): ?>
                        <a href="?page=<?php echo $currentPageNum + 1; ?>" class="px-3 py-1.5 border border-gray-300 rounded text-sm font-bold text-gray-700 bg-white hover:bg-gray-100">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<!-- Super Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/60 flex justify-center items-center z-[100] backdrop-blur-sm" style="display: none;">
    <div class="bg-white p-6 rounded-xl shadow-2xl w-full max-w-[800px] border border-gray-100 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-black text-xl text-[#111827]">Edit Professional Profile</h3>
            <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        
        <form method="POST" class="flex flex-col gap-6">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <!-- Basic Section -->
            <div class="bg-gray-50 border border-gray-200 p-4 rounded-lg">
                <h4 class="font-bold text-gray-800 border-b border-gray-200 pb-2 mb-4 text-sm uppercase tracking-wider">Access Info</h4>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-4">
                        <label class="block text-xs font-bold text-gray-500">Name</label>
                        <input type="text" name="name" id="edit_name" required class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-[#a60b26] outline-none">
                    </div>
                    <div class="md:col-span-5">
                        <label class="block text-xs font-bold text-gray-500">Email</label>
                        <input type="email" name="email" id="edit_email" required class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-[#a60b26] outline-none">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-gray-500">Account Status</label>
                        <select name="account_status" id="edit_account_status" class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-[#a60b26] outline-none">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive (Suspended)</option>
                        </select>
                    </div>
                    <div class="md:col-span-12">
                        <label class="block text-xs font-bold text-gray-500">Reset Password <span class="text-gray-400 font-normal">(Leave blank to keep existing)</span></label>
                        <input type="text" name="password" class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-[#a60b26] outline-none">
                    </div>
                </div>
            </div>

            <!-- Professional Section -->
            <div>
                <h4 class="font-bold text-gray-800 border-b border-gray-200 pb-2 mb-4 text-sm uppercase tracking-wider">Academic & Professional</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500">Employee ID</label>
                        <input type="text" name="employee_id" id="edit_employee_id" class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-[#a60b26] outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500">Designation</label>
                        <select name="designation" id="edit_designation" required class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-[#a60b26] outline-none">
                            <option value="Lecturer">Lecturer</option>
                            <option value="Assistant Professor">Assistant Professor</option>
                            <option value="Associate Professor">Associate Professor</option>
                            <option value="Professor">Professor</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500">Home Department</label>
                        <select name="work_dept_id" id="edit_work_dept_id" class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-[#a60b26] outline-none text-sm">
                            <option value="">No Base Dept</option>
                            <?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}'>{$d['PName']} — {$d['DName']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500">Qualification</label>
                        <input type="text" name="qualification" id="edit_qualification" class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-[#a60b26] outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500">Specialization</label>
                        <input type="text" name="specialization" id="edit_specialization" class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-[#a60b26] outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500">Joining Date</label>
                        <input type="date" name="joining_date" id="edit_joining_date" class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-[#a60b26] outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500">Emp. Type</label>
                        <select name="employment_type" id="edit_employment_type" class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-[#a60b26] outline-none">
                            <option value="">Select...</option>
                            <option value="Permanent">Permanent</option>
                            <option value="Contract">Contract</option>
                            <option value="Visiting">Visiting</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-500">Experience</label>
                        <input type="text" name="experience" id="edit_experience" placeholder="e.g. 5 Years" class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-[#a60b26] outline-none">
                    </div>
                </div>
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <label class="block text-xs font-bold text-gray-500 mb-2">Available Days <span class="text-gray-400 font-normal">(Leave all unchecked for 'All Days')</span></label>
                    <div class="flex flex-wrap gap-3">
                        <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day): ?>
                        <label class="flex items-center space-x-1 cursor-pointer">
                            <input type="checkbox" name="available_days[]" value="<?php echo $day; ?>" id="edit_day_<?php echo $day; ?>" class="h-4 w-4 text-[#a60b26] rounded focus:ring-[#a60b26] border-gray-300">
                            <span class="text-xs text-gray-700 font-bold"><?php echo $day; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- HOD Status -->
            <div class="border border-gray-200 p-4 rounded-lg bg-gray-50/50">
                <div class="flex items-center space-x-2 mb-2">
                    <input type="checkbox" name="is_hod" id="edit_is_hod" value="1" onchange="document.getElementById('edit_hod_d').style.display = this.checked ? 'block' : 'none'" class="h-4 w-4 text-[#a60b26]">
                    <label for="edit_is_hod" class="text-sm font-bold text-gray-700 cursor-pointer">Head of Department / Group Incharge</label>
                </div>
                <select name="hod_dept_id" id="edit_hod_d" style="display:none;" class="w-full border border-gray-300 p-2 rounded focus:outline-none focus:ring-1 focus:ring-[#a60b26] text-sm">
                    <option value="">Select Assigned HOD Department...</option>
                    <?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}'>{$d['PName']} — {$d['DName']}</option>"; endforeach; ?>
                </select>
            </div>

            <div class="flex justify-end gap-2 mt-2 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold px-6 py-2.5 rounded-lg transition-colors shadow-sm">Cancel</button>
                <button type="submit" class="bg-[#a60b26] hover:bg-[#8a0a20] text-white font-bold px-6 py-2.5 rounded-lg transition-colors shadow-sm">Save Complete Profile</button>
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
    
    // Handle days checkboxes
    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    days.forEach(d => document.getElementById('edit_day_' + d).checked = false);
    
    if (data.available_days) {
        let selectedDays = data.available_days.split(',');
        selectedDays.forEach(d => {
            let chk = document.getElementById('edit_day_' + d.trim());
            if (chk) chk.checked = true;
        });
    }
    
    document.getElementById('editModal').style.display = 'flex';
}
</script>


