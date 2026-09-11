<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';

$message = $error = '';
$uploadDir = __DIR__ . '/../uploads/downloads/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_download'])) {
        $title = trim($_POST['title']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if($title && isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $allowed = ['pdf', 'doc', 'docx'];
            $filename = $_FILES['file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if(in_array($ext, $allowed)) {
                $newName = uniqid() . '_' . preg_replace('/[^A-Za-z0-9\-]/', '', $title) . '.' . $ext;
                if(move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $newName)) {
                    $filePath = 'uploads/downloads/' . $newName;
                    $stmt = $pdo->prepare("INSERT INTO downloads (Title, FilePath, IsActive) VALUES (?, ?, ?)");
                    $stmt->execute([$title, $filePath, $isActive]);
                    $message = "Document uploaded successfully!";
                } else {
                    $error = "Failed to upload document.";
                }
            } else {
                $error = "Invalid file format. Allowed: PDF, DOC, DOCX.";
            }
        } else {
            $error = "Please provide a title and select a valid file.";
        }
    } elseif (isset($_POST['delete_download'])) {
        $id = $_POST['doc_id'];
        $stmt = $pdo->prepare("SELECT FilePath FROM downloads WHERE DocumentID = ?");
        $stmt->execute([$id]);
        $path = $stmt->fetchColumn();
        
        if($path && file_exists(__DIR__ . '/../' . $path)) {
            unlink(__DIR__ . '/../' . $path);
        }
        
        $pdo->prepare("DELETE FROM downloads WHERE DocumentID = ?")->execute([$id]);
        $message = "Document deleted.";
    }
}

$downloads = $pdo->query("SELECT * FROM downloads ORDER BY CreatedAt DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="flex-1 min-w-0">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-maroon">Manage Official Downloads</h2>
            <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-maroon hover:bg-red-800 text-white px-4 py-2 rounded shadow flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                Upload File
            </button>
        </div>
        
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4 font-bold border"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4 font-bold border"><?php echo $error; ?></div><?php endif; ?>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-sm border-b">
                        <th class="p-4 font-semibold w-16">Icon</th>
                        <th class="p-4 font-semibold">Document Title</th>
                        <th class="p-4 font-semibold text-center">Status</th>
                        <th class="p-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach($downloads as $d): 
                        $ext = strtolower(pathinfo($d['FilePath'], PATHINFO_EXTENSION));
                    ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="p-4 text-center">
                                <?php if($ext == 'pdf'): ?>
                                    <svg class="w-8 h-8 text-red-500 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" /></svg>
                                <?php else: ?>
                                    <svg class="w-8 h-8 text-blue-500 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" /></svg>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <div class="font-bold text-gray-800"><?php echo htmlspecialchars($d['Title']); ?></div>
                                <div class="text-[12px] text-gray-400 mt-1">Uploaded: <?php echo date('d M Y', strtotime($d['CreatedAt'])); ?></div>
                            </td>
                            <td class="p-4 text-center">
                                <?php if($d['IsActive']): ?>
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-[11px] font-bold border border-green-200">Public</span>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-[11px] font-semibold">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-right flex justify-end gap-3 items-center">
                                <a href="../<?php echo $d['FilePath']; ?>" target="_blank" class="text-maroon hover:text-red-700 font-semibold text-sm flex items-center gap-1">
                                    View File
                                </a>
                                <form method="POST" class="inline m-0" onsubmit="return confirm('Are you sure you want to delete this document permanently?');">
                                    <input type="hidden" name="doc_id" value="<?php echo $d['DocumentID']; ?>">
                                    <button type="submit" name="delete_download" class="text-gray-500 hover:text-red-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($downloads)): ?>
                        <tr><td colspan="4" class="p-6 text-center text-gray-500 font-medium">No documents uploaded yet.</td></tr>
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
            <h3 class="font-bold text-lg text-gray-800">Upload New File</h3>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Document Title *</label>
                <input type="text" name="title" placeholder="e.g. Data Sheet 2026, Admission Form" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" required>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-bold text-gray-700 mb-1">Select File (PDF, DOC) *</label>
                <input type="file" name="file" accept=".pdf,.doc,.docx" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" required>
                <p class="text-[12px] text-gray-400 mt-1">Recommended: Upload PDF formats for best compatibility.</p>
            </div>
            <div class="mb-6">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" name="is_active" checked class="w-5 h-5 accent-maroon">
                    <span class="text-sm font-bold text-gray-700">Make Public Immediately</span>
                </label>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 border rounded font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" name="add_download" class="px-4 py-2 bg-maroon shadow hover:bg-red-800 text-white rounded font-bold transition-all">Upload File</button>
            </div>
        </form>
    </div>
</div>


