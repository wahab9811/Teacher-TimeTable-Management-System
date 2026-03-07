<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';

$message = $error = '';
$uploadDir = __DIR__ . '/../uploads/notices/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_notice'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $imagePath = null;
        if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if(in_array($ext, $allowed)) {
                $newName = uniqid() . '.' . $ext;
                if(move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newName)) {
                    $imagePath = 'uploads/notices/' . $newName;
                } else {
                    $error = "Failed to upload image.";
                }
            } else {
                $error = "Invalid image format. Allowed: JPG, PNG, WEBP, GIF.";
            }
        }
        
        if(!$error && $title) {
            $stmt = $pdo->prepare("INSERT INTO notices (Title, Content, ImagePath, IsActive) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $content, $imagePath, $isActive]);
            $message = "Notice published successfully!";
        }
    } elseif (isset($_POST['delete_notice'])) {
        $id = $_POST['notice_id'];
        $stmt = $pdo->prepare("SELECT ImagePath FROM notices WHERE NoticeID = ?");
        $stmt->execute([$id]);
        $path = $stmt->fetchColumn();
        
        if($path && file_exists(__DIR__ . '/../' . $path)) {
            unlink(__DIR__ . '/../' . $path);
        }
        
        $pdo->prepare("DELETE FROM notices WHERE NoticeID = ?")->execute([$id]);
        $message = "Notice deleted.";
    }
}

$notices = $pdo->query("SELECT * FROM notices ORDER BY CreatedAt DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="flex-1 min-w-0">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-maroon">Manage Notice Board</h2>
            <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-maroon hover:bg-red-800 text-white px-4 py-2 rounded shadow flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Post New Notice
            </button>
        </div>
        
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4 font-bold border"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4 font-bold border"><?php echo $error; ?></div><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach($notices as $n): ?>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden flex flex-col">
                <?php if($n['ImagePath']): ?>
                    <img src="../<?php echo $n['ImagePath']; ?>" class="w-full h-48 object-cover border-b">
                <?php else: ?>
                    <div class="w-full h-12 bg-gray-50 border-b flex items-center justify-center text-gray-400 text-sm">No Image</div>
                <?php endif; ?>
                <div class="p-4 flex-1 flex flex-col">
                    <h3 class="font-bold text-lg text-gray-900 mb-1" dir="auto"><?php echo htmlspecialchars($n['Title']); ?></h3>
                    <p class="text-xs text-gray-400 mb-3"><?php echo date('d M Y, h:i A', strtotime($n['CreatedAt'])); ?></p>
                    <div class="text-sm text-gray-700 whitespace-pre-wrap mb-4 flex-1" dir="auto" style="line-height: 1.6;"><?php echo htmlspecialchars($n['Content']); ?></div>
                    <div class="flex justify-between items-center pt-4 border-t border-gray-100 mt-auto">
                        <span class="<?php echo $n['IsActive'] ? 'text-green-600' : 'text-gray-400'; ?> text-sm font-bold">
                            <?php echo $n['IsActive'] ? 'Live' : 'Hidden'; ?>
                        </span>
                        <form method="POST" onsubmit="return confirm('Delete this post permanently?');">
                            <input type="hidden" name="notice_id" value="<?php echo $n['NoticeID']; ?>">
                            <button type="submit" name="delete_notice" class="text-red-500 hover:text-red-700 text-sm font-bold">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($notices)): ?>
                <div class="col-span-2 text-center text-gray-500 py-12">No notices posted yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 flex overflow-y-auto">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-xl overflow-hidden m-4">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-lg text-gray-800">Create New Post</h3>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Title (Urdu/English) *</label>
                <input type="text" name="title" dir="auto" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Upload Image Flyer/Poster</label>
                <input type="file" name="image" accept="image/*" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div class="mb-5">
                <label class="block text-sm font-bold text-gray-700 mb-1">Details (Urdu/English)</label>
                <textarea name="content" dir="auto" rows="6" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-maroon focus:ring-1 focus:ring-maroon"></textarea>
            </div>
            <div class="mb-6">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" name="is_active" checked class="w-5 h-5 accent-maroon">
                    <span class="text-sm font-bold text-gray-700">Publish immediately to Student Feed</span>
                </label>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 border rounded font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" name="add_notice" class="px-4 py-2 bg-maroon shadow hover:bg-red-800 text-white rounded font-bold transition-all">Post Notice</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
