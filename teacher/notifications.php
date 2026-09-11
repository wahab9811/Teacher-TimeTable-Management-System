<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';
$teacherId = $_SESSION['user_id'];

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all'])) {
    $pdo->prepare("UPDATE notifications SET IsRead = 1 WHERE TeacherID = ?")->execute([$teacherId]);
} elseif($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $pdo->prepare("UPDATE notifications SET IsRead = 1 WHERE NotificationID = ? AND TeacherID = ?")->execute([$_POST['id'], $teacherId]);
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE TeacherID = ? AND IsRead = 0 ORDER BY CreatedAt DESC");
$stmt->execute([$teacherId]);
$unread = $stmt->fetchAll();

$stmt2 = $pdo->prepare("SELECT * FROM notifications WHERE TeacherID = ? AND IsRead = 1 ORDER BY CreatedAt DESC LIMIT 20");
$stmt2->execute([$teacherId]);
$read = $stmt2->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/teacher_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-maroon">Notifications</h2>
            <form method="POST"><button name="mark_all" class="text-sm bg-gray-200 px-3 py-1 rounded">Mark all read</button></form>
        </div>
        
        <h3 class="font-bold mb-2">Unread</h3>
        <div class="space-y-2 mb-8">
            <?php foreach($unread as $n): ?>
            <div class="bg-red-50 border border-red-200 p-3 rounded flex justify-between items-center">
                <div><span class="text-xs text-gray-500 block mb-1"><?php echo $n['CreatedAt']; ?></span><?php echo $n['Message']; ?></div>
                <form method="POST" class="ml-4"><input type="hidden" name="id" value="<?php echo $n['NotificationID']; ?>"><button name="mark_read" class="bg-maroon text-white text-xs px-2 py-1 rounded">Mark Read</button></form>
            </div>
            <?php endforeach; ?>
            <?php if(empty($unread)) echo "<p class='text-gray-500 italic'>No unread notifications.</p>"; ?>
        </div>
        
        <h3 class="font-bold mb-2">Recent Read</h3>
        <div class="space-y-2">
            <?php foreach($read as $n): ?>
            <div class="bg-gray-50 border p-3 rounded">
                <span class="text-xs text-gray-500 block mb-1"><?php echo $n['CreatedAt']; ?></span>
                <?php echo $n['Message']; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
