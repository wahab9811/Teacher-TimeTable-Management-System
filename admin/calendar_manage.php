<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$message='';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['add'])) {
        $imagePath = null;
        $uploadedPaths = [];
        if(isset($_FILES['image']) && !empty($_FILES['image']['name'][0])) {
            $fileCount = count($_FILES['image']['name']);
            // Limit to max 5
            $fileCount = $fileCount > 5 ? 5 : $fileCount;
            for($i = 0; $i < $fileCount; $i++) {
                if($_FILES['image']['error'][$i] == 0) {
                    $ext = strtolower(pathinfo($_FILES['image']['name'][$i], PATHINFO_EXTENSION));
                    if(in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $newName = uniqid() . '_' . time() . '_' . $i . '.' . $ext;
                        if(move_uploaded_file($_FILES['image']['tmp_name'][$i], __DIR__ . '/../uploads/calendar/' . $newName)) {
                            $uploadedPaths[] = 'uploads/calendar/' . $newName;
                        }
                    }
                }
            }
        }
        $imagePath = !empty($uploadedPaths) ? implode(',', $uploadedPaths) : null;
        
        $pdo->prepare("INSERT INTO college_calendar (Title, Date, EventTime, Category, ImagePath, Description) VALUES (?,?,?,?,?,?)")
            ->execute([$_POST['title'], $_POST['date'], $_POST['time'], $_POST['category'], $imagePath, $_POST['desc']]);
        
        $message="Added to calendar.";
        if($_POST['category'] === 'holiday') {
            $pdo->prepare("INSERT INTO notifications (ScopeType, Message) VALUES ('global', ?)")->execute(["New Holiday: {$_POST['title']} on {$_POST['date']}"]);
        }
    } elseif(isset($_POST['delete'])) {
        $stmt = $pdo->prepare("SELECT ImagePath FROM college_calendar WHERE CalendarID=?");
        $stmt->execute([$_POST['id']]);
        $path = $stmt->fetchColumn();
        if($path) {
            $paths = explode(',', $path);
            foreach($paths as $p) {
                if(file_exists(__DIR__ . '/../' . $p)) {
                    unlink(__DIR__ . '/../' . $p);
                }
            }
        }
        $pdo->prepare("DELETE FROM college_calendar WHERE CalendarID=?")->execute([$_POST['id']]);
        $message="Event deleted.";
    }
}
$events = $pdo->query("SELECT * FROM college_calendar ORDER BY Date DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="flex-1 min-w-0">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-maroon">Manage Calendar</h2>
            <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-maroon hover:bg-red-800 text-white px-4 py-2 rounded shadow flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Event / Holiday
            </button>
        </div>
        
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4 font-bold border"><?php echo $message; ?></div><?php endif; ?>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-sm border-b">
                        <th class="p-4 font-semibold w-32">Date</th>
                        <th class="p-4 font-semibold w-32">Time</th>
                        <th class="p-4 font-semibold">Event Title</th>
                        <th class="p-4 font-semibold w-40 text-center">Category</th>
                        <th class="p-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach($events as $e): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="p-4">
                                <div class="font-bold text-gray-900"><?php echo date('d M Y', strtotime($e['Date'])); ?></div>
                            </td>
                            <td class="p-4">
                                <div class="text-sm font-medium text-gray-600"><?php echo htmlspecialchars($e['EventTime']); ?></div>
                            </td>
                            <td class="p-4">
                                <div class="font-bold text-gray-800"><?php echo htmlspecialchars($e['Title']); ?></div>
                                <?php if($e['Description']): ?>
                                    <div class="text-[12px] text-gray-500 mt-1 truncate max-w-sm"><?php echo htmlspecialchars($e['Description']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center">
                                <?php 
                                    $catConf = [
                                        'holiday' => 'bg-red-100 text-red-700 border-red-200',
                                        'academic' => 'bg-green-100 text-green-700 border-green-200',
                                        'examination' => 'bg-purple-100 text-purple-700 border-purple-200',
                                        'event' => 'bg-blue-100 text-blue-700 border-blue-200',
                                        'meeting' => 'bg-orange-100 text-orange-700 border-orange-200',
                                        'other' => 'bg-yellow-100 text-yellow-800 border-yellow-200'
                                    ];
                                    $c = $e['Category'] ?? 'event';
                                    $cls = $catConf[$c];
                                ?>
                                <span class="<?php echo $cls; ?> px-3 py-1 rounded-full text-[11px] font-bold border uppercase tracking-wider"><?php echo $c; ?></span>
                            </td>
                            <td class="p-4 text-right">
                                <form method="POST" class="inline m-0" onsubmit="return confirm('Delete this event?');">
                                    <input type="hidden" name="id" value="<?php echo $e['CalendarID']; ?>">
                                    <button type="submit" name="delete" class="text-gray-500 hover:text-red-500 bg-gray-100 p-1.5 rounded outline-none">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($events)): ?>
                        <tr><td colspan="5" class="p-6 text-center text-gray-500 font-medium">No events currently scheduled.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 flex overflow-y-auto">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden m-4">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-lg text-gray-800">Add to Calendar</h3>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Title *</label>
                <input type="text" name="title" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" required>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Date *</label>
                    <input type="date" name="date" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" required>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Category *</label>
                    <select name="category" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" required>
                        <option value="academic">Academic</option>
                        <option value="holiday">Holiday</option>
                        <option value="examination">Examination</option>
                        <option value="event">Event</option>
                        <option value="meeting">Meeting</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Event Time</label>
                <input type="text" name="time" placeholder="e.g. All Day or 09:00 AM" value="All Day" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-maroon focus:ring-1 focus:ring-maroon">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Event Poster / Image (Optional)</label>
                <input type="file" name="image[]" multiple accept="image/*" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" onchange="if(this.files.length > 5) { alert('You can only upload a maximum of 5 images'); this.value = ''; }">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-bold text-gray-700 mb-1">Details / Lengthy Paragraph (Optional)</label>
                <textarea name="desc" rows="5" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-maroon focus:ring-1 focus:ring-maroon"></textarea>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 border rounded font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" name="add" class="px-4 py-2 bg-maroon shadow hover:bg-red-800 text-white rounded font-bold transition-all">Add Event</button>
            </div>
        </form>
    </div>
</div>


