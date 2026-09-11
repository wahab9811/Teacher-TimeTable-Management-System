<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';
$teacherId = $_SESSION['user_id'];
$message = $error = '';

// Update Personal Info
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_personal'])) {
    $q = "UPDATE users SET FatherName=?, Gender=?, DateOfBirth=?, CNIC=?, Phone=?, Address=? WHERE UserID=?";
    $arr = [
        $_POST['father_name'] ?: null,
        $_POST['gender'] ?: null,
        $_POST['dob'] ?: null,
        $_POST['cnic'] ?: null,
        $_POST['phone'] ?: null,
        $_POST['address'] ?: null,
        $teacherId
    ];
    $pdo->prepare($q)->execute($arr);
    $message = "Personal information updated successfully.";
}

// Upload Picture
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture'])) {
    if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['profile_pic']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if(in_array($ext, $allowed)) {
            $filename = 'profile_' . $teacherId . '_' . time() . '.' . $ext;
            $dest = __DIR__ . '/../uploads/profiles/' . $filename;
            if(move_uploaded_file($tmp, $dest)) {
                $pdo->prepare("UPDATE users SET ProfilePicture=? WHERE UserID=?")->execute([$filename, $teacherId]);
                $message = "Profile picture updated.";
            } else {
                $error = "Failed to move uploaded file.";
            }
        } else {
            $error = "Invalid file type. Only JPG, PNG, GIF allowed.";
        }
    } else {
        $error = "Please select a valid image file.";
    }
}

// Remove Picture
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_picture'])) {
    $stmt = $pdo->prepare("SELECT ProfilePicture FROM users WHERE UserID=?");
    $stmt->execute([$teacherId]);
    $pic = $stmt->fetchColumn();
    if($pic) {
        $path = __DIR__ . '/../uploads/profiles/' . $pic;
        if(file_exists($path)) {
            unlink($path);
        }
        $pdo->prepare("UPDATE users SET ProfilePicture=NULL WHERE UserID=?")->execute([$teacherId]);
        $message = "Profile picture removed.";
    }
}

// Change Password
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $conf = $_POST['conf_password'];
    
    $stmt = $pdo->prepare("SELECT Password FROM users WHERE UserID=?");
    $stmt->execute([$teacherId]);
    $hash = $stmt->fetchColumn();
    
    if(!password_verify($old, $hash)) {
        $error = "Old password incorrect.";
    } elseif($new !== $conf) {
        $error = "New passwords do not match.";
    } else {
        $pdo->prepare("UPDATE users SET Password=? WHERE UserID=?")->execute([password_hash($new, PASSWORD_DEFAULT), $teacherId]);
        $message = "Password updated successfully.";
    }
}

$stmtUser = $pdo->prepare("
    SELECT u.*, 
           d1.Name as DeptName, 
           d2.Name as HODDept 
    FROM users u 
    LEFT JOIN departments d1 ON u.DepartmentID = d1.DepartmentID
    LEFT JOIN departments d2 ON u.HOD_DepartmentID = d2.DepartmentID 
    WHERE UserID = ?
");
$stmtUser->execute([$teacherId]);
$tInfo = $stmtUser->fetch();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12 items-start">
    <?php include '../includes/teacher_sidebar.php'; ?>
    
    <div class="flex-1 min-w-0">
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 mb-4 font-bold border rounded"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 mb-4 font-bold border rounded"><?php echo $error; ?></div><?php endif; ?>
        
        <!-- Profile Info Card / Header -->
        <div class="mb-6 p-6 bg-gradient-to-r from-gray-50 to-white border border-gray-200 rounded-2xl shadow-[0_2px_15px_-3px_rgba(0,0,0,0.07)] flex flex-col sm:flex-row items-center sm:items-start gap-6 relative">
            
            <div class="absolute top-4 right-4 flex gap-2">
                <button onclick="document.getElementById('editProfileModal').style.display='flex'" class="flex items-center gap-1.5 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 hover:text-maroon px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    Edit Profile
                </button>
                <button onclick="document.getElementById('changePasswordModal').style.display='flex'" class="flex items-center gap-1.5 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 hover:text-maroon px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                    Password
                </button>
            </div>

            <!-- Avatar -->
            <div class="shrink-0 relative group mt-8 sm:mt-0">
                <form method="POST" enctype="multipart/form-data" id="profilePicForm" class="m-0">
                    <label for="profile_pic_input" class="cursor-pointer block relative rounded-full overflow-hidden w-32 h-32 border-4 border-white shadow-lg group-hover:shadow-[0_4px_15px_rgba(166,11,38,0.25)] group-hover:border-[#a60b26]/20 transition-all">
                        <?php if(!empty($tInfo['ProfilePicture'])): ?>
                            <img src="../uploads/profiles/<?php echo htmlspecialchars($tInfo['ProfilePicture']); ?>" class="w-full h-full object-cover" alt="Profile Picture">
                        <?php else: ?>
                            <div class="w-full h-full bg-gradient-to-br from-gray-200 to-gray-300 flex justify-center items-center text-gray-600 font-extrabold text-5xl">
                                <?php echo strtoupper(substr($tInfo['Name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="absolute inset-0 bg-black/40 flex flex-col justify-center items-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="w-8 h-8 text-white mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            <span class="text-white text-[11px] font-bold tracking-wider uppercase">Update</span>
                        </div>
                    </label>
                    <input type="file" name="profile_pic" id="profile_pic_input" accept="image/*" class="hidden" onchange="document.getElementById('profilePicForm').submit();">
                    <input type="hidden" name="upload_picture" value="1">
                </form>
                
                <?php if(!empty($tInfo['ProfilePicture'])): ?>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to remove your profile picture?');" class="absolute -top-1 -right-1 z-10 m-0">
                        <input type="hidden" name="remove_picture" value="1">
                        <button type="submit" class="bg-white hover:bg-red-50 text-red-500 rounded-full p-1.5 shadow-[0_2px_8px_rgba(0,0,0,0.15)] border border-red-100 hover:border-red-300 transition-colors" title="Remove Picture">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="flex-1 text-center sm:text-left mt-2 sm:mt-2">
                <div class="flex items-center justify-center sm:justify-start gap-3 mb-2">
                    <h3 class="text-3xl font-extrabold text-[#111827] tracking-tight"><?php echo htmlspecialchars($tInfo['Name']); ?></h3>
                    <!-- Status -->
                    <span class="mt-1 inline-flex items-center gap-1.5 <?php echo $tInfo['AccountStatus'] === 'Active' ? 'text-green-700 bg-green-50 border-green-200' : 'text-red-700 bg-red-50 border-red-200'; ?> px-2 py-0.5 rounded border text-xs font-bold shadow-sm">
                        <span class="w-1.5 h-1.5 rounded-full <?php echo $tInfo['AccountStatus'] === 'Active' ? 'bg-green-500' : 'bg-red-500'; ?>"></span>
                        <?php echo htmlspecialchars($tInfo['AccountStatus'] ?? 'Active'); ?>
                    </span>
                </div>
                
                <div class="flex flex-col sm:flex-row items-center sm:items-start justify-center sm:justify-start gap-4 text-[15px] text-gray-600 mb-4">
                    <div class="flex items-center gap-1.5 font-medium">
                        <svg class="w-[18px] h-[18px] text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <?php echo htmlspecialchars($tInfo['Email']); ?>
                    </div>
                    <?php if($tInfo['Phone']): ?>
                    <div class="flex items-center gap-1.5 font-medium">
                        <svg class="w-[18px] h-[18px] text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        <?php echo htmlspecialchars($tInfo['Phone']); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                    <?php if($tInfo['EmployeeID']): ?>
                    <div class="flex items-center gap-1 text-gray-700 bg-gray-100 px-3 py-1 rounded border border-gray-200 text-sm font-semibold">
                        ID: <?php echo htmlspecialchars($tInfo['EmployeeID']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if($tInfo['Designation']): ?>
                    <div class="flex items-center gap-1.5 text-[#a60b26] font-bold bg-[#a60b26]/5 px-3 py-1 rounded border border-[#a60b26]/10 text-sm">
                        <svg class="w-[16px] h-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <?php echo htmlspecialchars($tInfo['Designation']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if($tInfo['DeptName']): ?>
                    <div class="flex items-center gap-1.5 text-blue-700 font-bold bg-blue-50 px-3 py-1 rounded border border-blue-100 text-sm">
                        <?php echo htmlspecialchars($tInfo['DeptName']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if($tInfo['IsHOD']): ?>
                <div class="mt-4 inline-flex items-center gap-2 bg-gradient-to-r from-amber-50 to-amber-100 border border-amber-200 text-amber-800 px-4 py-2 rounded-lg text-sm font-bold shadow-sm">
                    <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                    Head of Department - <?php echo htmlspecialchars($tInfo['HODDept']); ?>
                </div>
                <?php endif; ?>
                
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Personal Info -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-200 px-5 py-3">
                    <h3 class="font-bold text-[#111827]">Personal Information</h3>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-2 text-[15px]">
                        <div class="text-gray-500 font-medium">Full Name</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tInfo['Name'] ?: 'N/A'); ?></div>
                        <div class="text-gray-500 font-medium">Father's Name</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tInfo['FatherName'] ?: 'N/A'); ?></div>
                        <div class="text-gray-500 font-medium">Gender</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tInfo['Gender'] ?: 'N/A'); ?></div>
                        <div class="text-gray-500 font-medium">Date of Birth</div>
                        <div class="font-semibold text-gray-900"><?php echo $tInfo['DateOfBirth'] ? date('d M Y', strtotime($tInfo['DateOfBirth'])) : 'N/A'; ?></div>
                        <div class="text-gray-500 font-medium">CNIC</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tInfo['CNIC'] ?: 'N/A'); ?></div>
                        <div class="text-gray-500 font-medium">Phone Number</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tInfo['Phone'] ?: 'N/A'); ?></div>
                        <div class="text-gray-500 font-medium">Email Address</div>
                        <div class="font-semibold text-gray-900 break-all"><?php echo htmlspecialchars($tInfo['Email'] ?: 'N/A'); ?></div>
                        <div class="text-gray-500 font-medium">Address</div>
                        <div class="font-semibold text-gray-900 sm:col-span-1"><?php echo htmlspecialchars($tInfo['Address'] ?: 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Professional Info -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-200 px-5 py-3 flex justify-between items-center">
                    <h3 class="font-bold text-[#111827]">Professional Information</h3>
                    <svg class="w-4 h-4 text-gray-400" title="Contact Admin to edit" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-2 text-[15px]">
                        <div class="text-gray-500 font-medium">Employee ID</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tInfo['EmployeeID'] ?: 'N/A'); ?></div>
                        <div class="text-gray-500 font-medium">Designation</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tInfo['Designation'] ?: 'N/A'); ?></div>
                        <div class="text-gray-500 font-medium">Department</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tInfo['DeptName'] ?: 'N/A'); ?></div>
                        <div class="text-gray-500 font-medium">Qualification</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tInfo['Qualification'] ?: 'N/A'); ?></div>
                        <div class="text-gray-500 font-medium">Specialization</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tInfo['Specialization'] ?: 'N/A'); ?></div>
                        <div class="text-gray-500 font-medium">Joining Date</div>
                        <div class="font-semibold text-gray-900"><?php echo $tInfo['JoiningDate'] ? date('d M Y', strtotime($tInfo['JoiningDate'])) : 'N/A'; ?></div>
                        <div class="text-gray-500 font-medium">Employment Type</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tInfo['EmploymentType'] ?: 'N/A'); ?></div>
                        <div class="text-gray-500 font-medium">Experience</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tInfo['Experience'] ?: 'N/A'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div id="editProfileModal" class="fixed inset-0 bg-black/50 flex justify-center items-center z-[100]" style="display: none;">
    <div class="bg-white p-6 rounded-2xl shadow-2xl w-full max-w-[650px] border border-gray-100 max-h-[95vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-5 border-b pb-3">
            <h3 class="font-bold text-xl text-[#111827]">Edit Personal Information</h3>
            <button onclick="document.getElementById('editProfileModal').style.display='none'" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <form method="POST" class="grid grid-cols-2 gap-5">
            <div class="col-span-2 sm:col-span-1">
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Father's Name</label>
                <input type="text" name="father_name" value="<?php echo htmlspecialchars($tInfo['FatherName'] ?? ''); ?>" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#a60b26]/30 focus:border-[#a60b26] transition-all">
            </div>
            
            <div class="col-span-2 sm:col-span-1">
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Gender</label>
                <select name="gender" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#a60b26]/30 focus:border-[#a60b26] transition-all">
                    <option value="">Select Gender</option>
                    <option value="Male" <?php echo ($tInfo['Gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($tInfo['Gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($tInfo['Gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <div class="col-span-2 sm:col-span-1">
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Date of Birth</label>
                <input type="date" name="dob" value="<?php echo htmlspecialchars($tInfo['DateOfBirth'] ?? ''); ?>" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#a60b26]/30 focus:border-[#a60b26] transition-all">
            </div>
            
            <div class="col-span-2 sm:col-span-1">
                <label class="block text-sm font-bold text-gray-700 mb-1.5">CNIC Number</label>
                <input type="text" name="cnic" placeholder="e.g. 12345-1234567-1" value="<?php echo htmlspecialchars($tInfo['CNIC'] ?? ''); ?>" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#a60b26]/30 focus:border-[#a60b26] transition-all">
            </div>

            <div class="col-span-2 sm:col-span-1">
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Phone Number</label>
                <input type="text" name="phone" placeholder="e.g. 03xx-xxxxxxx" value="<?php echo htmlspecialchars($tInfo['Phone'] ?? ''); ?>" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#a60b26]/30 focus:border-[#a60b26] transition-all">
            </div>
            
            <div class="col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Mailing Address</label>
                <textarea name="address" rows="3" class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#a60b26]/30 focus:border-[#a60b26] transition-all"><?php echo htmlspecialchars($tInfo['Address'] ?? ''); ?></textarea>
            </div>

            <div class="col-span-2 flex justify-end gap-3 mt-2 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('editProfileModal').style.display='none'" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold px-6 py-2.5 rounded-lg transition-colors">Cancel</button>
                <button type="submit" name="update_personal" class="bg-[#a60b26] hover:bg-[#8a0a20] text-white font-bold px-6 py-2.5 rounded-lg transition-colors shadow-sm">Save Complete Profile</button>
            </div>
        </form>
    </div>
</div>

<!-- Change Password Modal -->
<div id="changePasswordModal" class="fixed inset-0 bg-black/50 flex justify-center items-center z-[100]" style="display: none;">
    <div class="bg-white p-6 rounded-2xl shadow-2xl w-full max-w-[420px] border border-gray-100">
        <div class="flex justify-between items-center mb-5 border-b pb-3">
            <h3 class="font-bold text-xl text-[#111827]">Change Password</h3>
            <button onclick="document.getElementById('changePasswordModal').style.display='none'" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" class="flex flex-col gap-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Current Password</label>
                <input type="password" name="old_password" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#a60b26]/30 focus:border-[#a60b26] transition-all">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">New Password</label>
                <input type="password" name="new_password" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#a60b26]/30 focus:border-[#a60b26] transition-all">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Confirm New Password</label>
                <input type="password" name="conf_password" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#a60b26]/30 focus:border-[#a60b26] transition-all">
            </div>
            <div class="flex justify-end gap-3 mt-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('changePasswordModal').style.display='none'" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold px-5 py-2.5 rounded-lg transition-colors">Cancel</button>
                <button type="submit" name="change_password" class="bg-[#a60b26] hover:bg-[#8a0a20] text-white font-bold px-5 py-2.5 rounded-lg transition-colors shadow-sm">Update Security</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
