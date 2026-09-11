<?php
session_start();
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$dir_path = str_replace('\\', '/', __DIR__);
$base_url = str_replace($doc_root, '', $dir_path);
require_once __DIR__ . '/config/db.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = $success = '';
$row = null;
if ($token) {
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT ResetID, UserID FROM password_resets WHERE TokenHash = ? AND ExpiresAt > ?");
    $stmt->execute([$hash, date('Y-m-d H:i:s')]);
    $row = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$row) {
        $error = "This reset link is invalid or has expired. Please request a new one.";
    } else {
        $pw  = $_POST['password'] ?? '';
        $pw2 = $_POST['confirm_password'] ?? '';
        if (strlen($pw) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif ($pw !== $pw2) {
            $error = "Passwords do not match.";
        } else {
            $newHash = password_hash($pw, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET Password = ? WHERE UserID = ?")->execute([$newHash, $row['UserID']]);
            $pdo->prepare("DELETE FROM password_resets WHERE UserID = ?")->execute([$row['UserID']]);
            try { $pdo->prepare("DELETE FROM auth_tokens WHERE UserID = ?")->execute([$row['UserID']]); } catch (Exception $e) {}
            $success = "Password reset successfully. You can now log in with your new password.";
        }
    }
}
?>
<?php include 'includes/header.php'; ?>
<div class="max-w-[420px] mx-auto bg-white rounded-[24px] shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-8 sm:p-10 mt-12 mb-12 border border-gray-100/50">
    <h2 class="text-[26px] font-extrabold text-[#111827] mb-1">Set New Password</h2>
    <?php if($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6">
            <p class="text-[13px]"><?php echo htmlspecialchars($success); ?></p>
            <a href="<?php echo $base_url; ?>/login.php" class="font-bold underline text-[13px]">Go to Login</a>
        </div>
    <?php elseif(!$row): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6">
            <p class="text-[13px]"><?php echo $error ?: "This reset link is invalid or has expired."; ?></p>
            <a href="<?php echo $base_url; ?>/forgot_password.php" class="font-bold underline text-[13px]">Request a new link</a>
        </div>
    <?php else: ?>
        <p class="text-[14px] text-gray-500 mb-6">Choose a new password for your account.</p>
        <?php if($error): ?><div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6"><p class="text-[13px]"><?php echo htmlspecialchars($error); ?></p></div><?php endif; ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div>
                <label class="block text-[14px] font-bold mb-1.5">New Password</label>
                <input type="password" name="password" required class="w-full px-4 py-[11px] text-[14px] border border-gray-200 rounded-lg focus:ring-1 focus:ring-[#a60b26] focus:border-[#a60b26] outline-none">
            </div>
            <div>
                <label class="block text-[14px] font-bold mb-1.5">Confirm New Password</label>
                <input type="password" name="confirm_password" required class="w-full px-4 py-[11px] text-[14px] border border-gray-200 rounded-lg focus:ring-1 focus:ring-[#a60b26] focus:border-[#a60b26] outline-none">
            </div>
            <button type="submit" class="w-full py-[12px] rounded-[8px] text-[15px] font-bold text-white bg-[#a60b26] hover:bg-[#8a0a20] transition-all mt-2">Reset Password</button>
        </form>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
