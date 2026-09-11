<?php
session_start();
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$dir_path = str_replace('\\', '/', __DIR__);
$base_url = str_replace($doc_root, '', $dir_path);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/libs/PHPMailer/Exception.php';
require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/libs/PHPMailer/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $message = "If an account with that email exists, a password reset link has been sent. Please check your inbox and spam folder.";
    if ($email) {
        $stmt = $pdo->prepare("SELECT UserID, Name FROM users WHERE Email = ? AND Role = 'teacher' AND AccountStatus = 'Active'");
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if ($u) {
            $token   = bin2hex(random_bytes(32));
            $hash    = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $pdo->prepare("DELETE FROM password_resets WHERE UserID = ?")->execute([$u['UserID']]);
            $pdo->prepare("INSERT INTO password_resets (UserID, TokenHash, ExpiresAt) VALUES (?, ?, ?)")->execute([$u['UserID'], $hash, $expires]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $resetLink = $scheme . '://' . $_SERVER['HTTP_HOST'] . $base_url . '/reset_password.php?token=' . $token;

            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = MAIL_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = MAIL_USER;
                $mail->Password   = MAIL_PASS;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = MAIL_PORT;
                $mail->setFrom(MAIL_USER, MAIL_FROM_NAME);
                $mail->addAddress($email, $u['Name']);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset - GCB SKP Timetable';
                $mail->Body    = 'Hello ' . htmlspecialchars($u['Name']) . ',<br><br>Click the link below to reset your password. This link will expire in 1 hour.<br><br><a href="' . $resetLink . '">Reset My Password</a><br><br>If you did not request this, you can safely ignore this email.';
                $mail->AltBody = 'Reset your password (expires in 1 hour): ' . $resetLink;
                $mail->send();
            } catch (Exception $e) {
                // Silent: do not reveal whether the email exists or why sending failed.
            }
        }
    }
}
?>
<?php include 'includes/header.php'; ?>
<div class="max-w-[420px] mx-auto bg-white rounded-[24px] shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-8 sm:p-10 mt-12 mb-12 border border-gray-100/50">
    <h2 class="text-[26px] font-extrabold text-[#111827] mb-1">Forgot Password</h2>
    <p class="text-[14px] text-gray-500 mb-6">Enter your registered email and we'll send you a reset link.</p>
    <?php if($message): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded mb-6"><p class="text-[13px]"><?php echo htmlspecialchars($message); ?></p></div>
    <?php endif; ?>
    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-[14px] font-bold mb-1.5">Email Address</label>
            <input type="email" name="email" required class="w-full px-4 py-[11px] text-[14px] border border-gray-200 rounded-lg focus:ring-1 focus:ring-[#a60b26] focus:border-[#a60b26] outline-none">
        </div>
        <button type="submit" class="w-full py-[12px] rounded-[8px] text-[15px] font-bold text-white bg-[#a60b26] hover:bg-[#8a0a20] transition-all mt-2">Send Reset Link</button>
        <div class="text-center pt-2">
            <a href="<?php echo $base_url; ?>/login.php" class="text-[13px] text-gray-500 hover:text-[#a60b26]">Back to Login</a>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
