<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$dir_path = str_replace('\\', '/', __DIR__);
$base_url = str_replace($doc_root, '', $dir_path);
$base_url = str_replace('/api', '', $base_url);

$action = $_GET['action'] ?? '';

if ($action == 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE Email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['Password'])) {
        $role_type = $_POST['role_type'] ?? '';
        if ($role_type && $user['Role'] !== $role_type) {
            header("Location: " . $base_url . "/login.php?error=" . urlencode("Access denied. Please check your selected role."));
            exit;
        }

        $_SESSION['user_id'] = $user['UserID'];
        $_SESSION['user_name'] = $user['Name'];
        $_SESSION['role'] = $user['Role'];

        // --- Remember Me: create persistent token ---
        if (!empty($_POST['remember'])) {
            $selector  = bin2hex(random_bytes(8));
            $validator = bin2hex(random_bytes(32));
            $expires   = date('Y-m-d H:i:s', time() + 30 * 24 * 3600);
            $ins = $pdo->prepare("INSERT INTO auth_tokens (UserID, Selector, ValidatorHash, ExpiresAt) VALUES (?, ?, ?, ?)");
            $ins->execute([$user['UserID'], $selector, hash('sha256', $validator), $expires]);
            setcookie('remember', $selector . ':' . $validator, time() + 30 * 24 * 3600, '/', '', false, true);
        }
        
        if ($user['Role'] === 'admin') {
            header("Location: " . $base_url . "/admin/dashboard.php");
        } else {
            header("Location: " . $base_url . "/teacher/dashboard.php");
        }
        exit;
    } else {
        header("Location: " . $base_url . "/login.php?error=" . urlencode("Invalid email or password"));
        exit;
    }
}

if ($action == 'logout') {
    if (!empty($_COOKIE['remember'])) {
        $parts = explode(':', $_COOKIE['remember'], 2);
        if (count($parts) === 2) {
            $pdo->prepare("DELETE FROM auth_tokens WHERE Selector = ?")->execute([$parts[0]]);
        }
        setcookie('remember', '', time() - 3600, '/', '', false, true);
    }
    session_destroy();
    header("Location: " . $base_url . "/index.php");
    exit;
}
?>
