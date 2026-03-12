<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_session'])) {
        $title = trim($_POST['title'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if($title) {
            try {
                if($isActive) {
                    $pdo->exec("UPDATE academic_sessions SET IsActive = 0");
                }
                $stmt = $pdo->prepare("INSERT INTO academic_sessions (Title, IsActive) VALUES (?, ?)");
                $stmt->execute([$title, $isActive]);
                $message = "Academic Session added successfully.";
            } catch(PDOException $e) {
                $error = "Error adding session.";
            }
        }
    } elseif (isset($_POST['set_active'])) {
        $sid = $_POST['session_id'];
        $pdo->exec("UPDATE academic_sessions SET IsActive = 0");
        $stmt = $pdo->prepare("UPDATE academic_sessions SET IsActive = 1 WHERE SessionID = ?");
        $stmt->execute([$sid]);
        $message = "Active session updated successfully.";
    } elseif (isset($_POST['delete_session'])) {
        $sid = $_POST['session_id'];
        $stmt = $pdo->prepare("DELETE FROM academic_sessions WHERE SessionID = ?");
        $stmt->execute([$sid]);
        $message = "Academic Session deleted successfully.";
    }
}

$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY SessionID DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="flex-1 min-w-0">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-maroon">Manage Academic Sessions</h2>
            <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-maroon hover:bg-red-800 text-white px-4 py-2 rounded shadow flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Session
            </button>
        </div>
        
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4 font-bold border"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4 font-bold border"><?php echo $error; ?></div><?php endif; ?>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-sm border-b">
                        <th class="p-4 font-semibold">ID</th>
                        <th class="p-4 font-semibold">Title</th>
                        <th class="p-4 font-semibold">Status</th>
                        <th class="p-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach($sessions as $s): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="p-4 text-gray-500 font-medium">#<?php echo $s['SessionID']; ?></td>
                            <td class="p-4 font-bold text-gray-800"><?php echo htmlspecialchars($s['Title']); ?></td>
                            <td class="p-4">
                                <?php if($s['IsActive']): ?>
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold border border-green-200">Active Current</span>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-semibold">Inactive (Past/Future)</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-right flex justify-end gap-2">
                                <?php if(!$s['IsActive']): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="session_id" value="<?php echo $s['SessionID']; ?>">
                                    <button type="submit" name="set_active" class="text-white bg-blue-600 hover:bg-blue-700 px-3 py-1.5 text-sm font-semibold rounded shadow-sm transition">Set as Active</button>
                                </form>
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this session? Timetables linked to this session will lose their association.');">
                                    <input type="hidden" name="session_id" value="<?php echo $s['SessionID']; ?>">
                                    <button type="submit" name="delete_session" class="text-white bg-red-600 hover:bg-red-700 px-3 py-1.5 text-sm font-semibold rounded shadow-sm transition">Delete</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($sessions)): ?>
                        <tr><td colspan="4" class="p-4 text-center text-gray-500">No sessions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 flex">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-lg text-gray-800">Add New Academic Session</h3>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Session Title *</label>
                <input type="text" name="title" placeholder="e.g. 2026-2027" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" required>
            </div>
            <div class="mb-6">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" name="is_active" class="w-5 h-5 accent-maroon">
                    <span class="text-sm font-bold text-gray-700">Set as completely Active/Current session</span>
                </label>
                <p class="text-xs text-gray-500 mt-1 ml-7">Warning: Checking this will make all other sessions inactive.</p>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 border rounded font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" name="add_session" class="px-4 py-2 bg-maroon shadow hover:bg-red-800 text-white rounded font-bold transition-all">Add Session</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
