<?php
// login.php
session_start();
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$dir_path = str_replace('\\', '/', __DIR__);
$base_url = str_replace($doc_root, '', $dir_path);

// --- Remember Me: auto-login from cookie ---
if (!isset($_SESSION['user_id']) && !empty($_COOKIE['remember'])) {
    require_once __DIR__ . '/config/db.php';
    $parts = explode(':', $_COOKIE['remember'], 2);
    if (count($parts) === 2) {
        list($selector, $validator) = $parts;
        $stmt = $pdo->prepare("SELECT t.ValidatorHash, u.UserID, u.Name, u.Role FROM auth_tokens t JOIN users u ON u.UserID = t.UserID WHERE t.Selector = ? AND t.ExpiresAt > ?");
        $stmt->execute([$selector, date('Y-m-d H:i:s')]);
        $tok = $stmt->fetch();
        if ($tok && hash_equals($tok['ValidatorHash'], hash('sha256', $validator))) {
            $_SESSION['user_id']   = $tok['UserID'];
            $_SESSION['user_name'] = $tok['Name'];
            $_SESSION['role']      = $tok['Role'];
        } else {
            setcookie('remember', '', time() - 3600, '/', '', false, true);
        }
    }
}

if(isset($_SESSION['user_id'])) {
    if($_SESSION['role'] === 'admin') header("Location: " . $base_url . "/admin/dashboard.php");
    else header("Location: " . $base_url . "/teacher/dashboard.php");
    exit;
}

$error = $_GET['error'] ?? '';
?>
<?php include 'includes/header.php'; ?>

<!-- Back to Home Button -->
<a href="<?php echo $base_url; ?>/index.php" class="absolute top-6 left-6 md:top-8 md:left-8 flex items-center gap-2 text-gray-500 hover:text-[#a60b26] transition-all font-medium bg-white px-4 py-2.5 rounded-xl shadow-[0_2px_10px_rgba(0,0,0,0.04)] border border-gray-100 z-50">
    <svg class="w-4 h-4 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
    <span class="text-[14px]">Back to Home</span>
</a>

<div class="max-w-[420px] mx-auto bg-white rounded-[24px] shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-8 sm:p-10 relative mt-12 mb-12 border border-gray-100/50">
    <!-- Top Tabs Segmented Control -->
    <div class="flex p-[5px] bg-[#f8f9fa] rounded-xl border border-gray-100 mb-8 mx-auto shadow-inner">
        <!-- Teacher Tab -->
        <button type="button" id="tab-teacher" onclick="switchTab('teacher')" class="relative w-1/2 py-2.5 text-center text-[14px] font-bold rounded-lg flex justify-center items-center bg-white shadow-[0_2px_8px_rgba(0,0,0,0.04)] border-b-[3px] border-[#a60b26] transition-all text-[#a60b26]">
            <span id="text-tab-teacher">Teacher</span>
        </button>
        <!-- Admin Tab -->
        <button type="button" id="tab-admin" onclick="switchTab('admin')" class="relative w-1/2 py-2.5 text-center text-[14px] font-bold rounded-lg flex justify-center items-center text-gray-500 hover:text-gray-700 transition-all border-b-[3px] border-transparent">
            <span id="text-tab-admin">Admin</span>
        </button>
    </div>

    <!-- Main Icon & Title -->
    <div class="text-center mb-8">
        <h2 id="login-title" class="text-[28px] font-extrabold text-[#111827] tracking-tight mb-2">Teacher Login</h2>
        <p class="text-[14px] text-gray-500">Sign in to securely access your account</p>
    </div>

    <!-- Error Block -->
    <?php if($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6" role="alert">
            <p class="font-bold text-[14px]">Login Failed</p>
            <p class="text-[13px]"><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form action="<?php echo $base_url; ?>/api/auth.php?action=login" method="POST" class="space-y-5">
        <input type="hidden" name="role_type" id="role_type" value="teacher">
        
        <!-- Email -->
        <div>
            <label class="block text-[#111827] text-[14px] font-bold mb-1.5">Email Address</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                    <svg class="h-[18px] w-[18px] text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <input type="email" name="email" required placeholder="Enter your email" class="w-full pl-[40px] pr-4 py-[11px] text-[14px] border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26] focus:border-[#a60b26] transition-colors placeholder-gray-400 text-gray-700 font-medium bg-white">
            </div>
        </div>

        <!-- Password -->
        <div>
            <label class="block text-[#111827] text-[14px] font-bold mb-1.5">Password</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                    <svg class="h-[18px] w-[18px] text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <input id="password-input" type="password" name="password" required placeholder="Enter your password" class="w-full pl-[40px] pr-10 py-[11px] text-[14px] border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#a60b26] focus:border-[#a60b26] transition-colors placeholder-gray-400 text-gray-700 font-medium bg-white">
                <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center cursor-pointer" onclick="togglePassword()">
                    <svg id="eye-icon" class="h-[18px] w-[18px] text-gray-400 hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Remember & Forgot -->
        <div class="flex items-center justify-between pt-2">
            <div class="flex items-center">
                <input id="remember_me" name="remember" value="1" type="checkbox" class="h-4 w-4 text-[#a60b26] focus:ring-[#a60b26] border-gray-300 rounded cursor-pointer">
                <label for="remember_me" class="ml-2 block text-[13px] text-gray-500 font-medium cursor-pointer">Remember me</label>
            </div>
            <div class="text-[13px]" id="forgot-password-container">
                <a href="<?php echo $base_url; ?>/forgot_password.php" class="font-semibold text-[#a60b26] hover:text-[#7a081c] transition-colors">Forgot password?</a>
            </div>
        </div>

        <!-- Button -->
        <button type="submit" class="w-full flex justify-center items-center py-[12px] px-4 border border-transparent rounded-[8px] shadow-sm text-[15px] font-bold text-white bg-[#a60b26] hover:bg-[#8a0a20] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#a60b26] transition-all duration-300 mt-6 !mb-2">
            Sign In
        </button>
    </form>
    

</div>

<script>
const activeTabClass = "relative w-1/2 py-2.5 text-center text-[14px] font-bold rounded-lg flex justify-center items-center bg-white shadow-[0_2px_8px_rgba(0,0,0,0.04)] border-b-[3px] border-[#a60b26] transition-all text-[#a60b26]";
const inactiveTabClass = "relative w-1/2 py-2.5 text-center text-[14px] font-bold rounded-lg flex justify-center items-center text-gray-500 hover:text-gray-700 transition-all border-b-[3px] border-transparent";
const activeTextClass = "text-[#a60b26]";
const inactiveTextClass = "text-gray-500";

function switchTab(role) {
    document.getElementById('role_type').value = role;
    const tabTeacher = document.getElementById('tab-teacher');
    const tabAdmin = document.getElementById('tab-admin');
    
    const textTeacher = document.getElementById('text-tab-teacher');
    const textAdmin = document.getElementById('text-tab-admin');

    const loginTitle = document.getElementById('login-title');
    const forgotPassword = document.getElementById('forgot-password-container');
    
    if (role === 'teacher') {
        tabTeacher.className = activeTabClass;
        tabAdmin.className = inactiveTabClass;
        
        loginTitle.textContent = "Teacher Login";
        forgotPassword.style.display = 'block';
    } else {
        tabAdmin.className = activeTabClass;
        tabTeacher.className = inactiveTabClass;
        
        loginTitle.textContent = "Admin Login";
        forgotPassword.style.display = 'none';
    }
}

function togglePassword() {
    const pwd = document.getElementById('password-input');
    const eye = document.getElementById('eye-icon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        eye.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>';
    } else {
        pwd.type = 'password';
        eye.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
    }
}
</script>
<?php include 'includes/footer.php'; ?>
