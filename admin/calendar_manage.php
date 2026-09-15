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
        
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $pdo->prepare("INSERT INTO college_calendar (Title, Date, EndDate, EventTime, Category, ImagePath, Description) VALUES (?,?,?,?,?,?,?)")
            ->execute([$_POST['title'], $_POST['date'], $endDate, $_POST['time'], $_POST['category'], $imagePath, $_POST['desc']]);
        
        $message="Added to calendar.";
        if($_POST['category'] === 'holiday') {
            $pdo->prepare("INSERT INTO notifications (ScopeType, Message) VALUES ('global', ?)")->execute(["New Holiday: {$_POST['title']} on {$_POST['date']}"]);
        }
    } elseif(isset($_POST['edit'])) {
        $id = $_POST['id'];
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        // Image handling for Edit
        $uploadedPaths = [];
        $hasNewImages = false;
        
        if(isset($_FILES['image']) && !empty($_FILES['image']['name'][0])) {
            $hasNewImages = true;
            $fileCount = count($_FILES['image']['name']);
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
        
        if($hasNewImages) {
            // Delete old images
            $stmt = $pdo->prepare("SELECT ImagePath FROM college_calendar WHERE CalendarID=?");
            $stmt->execute([$id]);
            $oldPath = $stmt->fetchColumn();
            if($oldPath) {
                $paths = explode(',', $oldPath);
                foreach($paths as $p) {
                    if(file_exists(__DIR__ . '/../' . ltrim($p, '/'))) {
                        unlink(__DIR__ . '/../' . ltrim($p, '/'));
                    }
                }
            }
            $imagePath = !empty($uploadedPaths) ? implode(',', $uploadedPaths) : null;
            $pdo->prepare("UPDATE college_calendar SET Title=?, Date=?, EndDate=?, EventTime=?, Category=?, ImagePath=?, Description=? WHERE CalendarID=?")
                ->execute([$_POST['title'], $_POST['date'], $endDate, $_POST['time'], $_POST['category'], $imagePath, $_POST['desc'], $id]);
        } else {
            $pdo->prepare("UPDATE college_calendar SET Title=?, Date=?, EndDate=?, EventTime=?, Category=?, Description=? WHERE CalendarID=?")
                ->execute([$_POST['title'], $_POST['date'], $endDate, $_POST['time'], $_POST['category'], $_POST['desc'], $id]);
        }
        $message = "Event updated.";
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
                                <div class="font-bold text-gray-900 whitespace-nowrap">
                                    <?php echo date('d M Y', strtotime($e['Date'])); ?>
                                    <?php if($e['EndDate'] && $e['EndDate'] !== $e['Date']): ?>
                                        <br><span class="text-xs text-gray-500 font-normal">to</span> <?php echo date('d M Y', strtotime($e['EndDate'])); ?>
                                    <?php endif; ?>
                                </div>
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
                                    <button type="button" onclick='openEditModal(<?php echo json_encode([
                                        "id" => $e["CalendarID"],
                                        "title" => $e["Title"],
                                        "date" => $e["Date"],
                                        "end_date" => $e["EndDate"],
                                        "time" => $e["EventTime"],
                                        "category" => $e["Category"],
                                        "desc" => $e["Description"]
                                    ]); ?>)' class="text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 p-1.5 rounded outline-none transition mr-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                    <form method="POST" class="inline m-0" onsubmit="return confirm('Delete this event?');">
                                        <input type="hidden" name="id" value="<?php echo $e['CalendarID']; ?>">
                                        <button type="submit" name="delete" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-1.5 rounded outline-none transition">
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
<div id="addModal" class="fixed inset-0 bg-black/50 hidden items-start justify-center z-50 flex overflow-y-auto pt-10 pb-10">
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
                    <label class="block text-sm font-bold text-gray-700 mb-1">Start Date *</label>
                    <input type="date" name="date" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" required>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">End Date (Optional)</label>
                    <input type="date" name="end_date" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" title="Leave empty for single day event">
                </div>
                <div class="col-span-2">
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
                <input type="file" name="image[]" id="add_file_input" multiple accept="image/*" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" onchange="handleFileSelect(this, 'add_file_preview')">
                <div id="add_file_preview" class="mt-2 space-y-1"></div>
            </div>
            <div class="mb-6">
                <div class="flex justify-between items-end mb-1">
                    <label class="block text-sm font-bold text-gray-700">Detail (Optional)</label>
                    <span id="addDescCount" class="text-[11px] font-medium text-gray-400">0/1000</span>
                </div>
                <textarea name="desc" id="add_desc" rows="3" maxlength="1000" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" oninput="document.getElementById('addDescCount').innerText = this.value.length + '/1000'"></textarea>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 border rounded font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" name="add" class="px-4 py-2 bg-maroon shadow hover:bg-red-800 text-white rounded font-bold transition-all">Add Event</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 hidden items-start justify-center z-50 flex overflow-y-auto pt-10 pb-10">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden m-4">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-lg text-gray-800">Edit Event</h3>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6">
            <input type="hidden" name="id" id="edit_id">
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Title *</label>
                <input type="text" name="title" id="edit_title" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" required>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Start Date *</label>
                    <input type="date" name="date" id="edit_date" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" required>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">End Date (Optional)</label>
                    <input type="date" name="end_date" id="edit_end_date" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" title="Leave empty for single day event">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Category *</label>
                    <select name="category" id="edit_category" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" required>
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
                <input type="text" name="time" id="edit_time" placeholder="e.g. All Day or 09:00 AM" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-maroon focus:ring-1 focus:ring-maroon">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Replace Poster / Image (Optional)</label>
                <input type="file" name="image[]" id="edit_file_input" multiple accept="image/*" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" onchange="handleFileSelect(this, 'edit_file_preview')">
                <div id="edit_file_preview" class="mt-2 space-y-1"></div>
                <div class="text-[11px] text-gray-500 mt-1 font-medium bg-blue-50 text-blue-700 p-2 border border-blue-100 rounded">Note: Uploading new images will permanently replace the existing ones. Leave empty to keep existing images.</div>
            </div>
            <div class="mb-6">
                <div class="flex justify-between items-end mb-1">
                    <label class="block text-sm font-bold text-gray-700">Detail (Optional)</label>
                    <span id="editDescCount" class="text-[11px] font-medium text-gray-400">0/1000</span>
                </div>
                <textarea name="desc" id="edit_desc" rows="3" maxlength="1000" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" oninput="document.getElementById('editDescCount').innerText = this.value.length + '/1000'"></textarea>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-4 py-2 border rounded font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" name="edit" class="px-4 py-2 bg-blue-600 shadow hover:bg-blue-700 text-white rounded font-bold transition-all">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(event) {
        document.getElementById('edit_id').value = event.id;
        document.getElementById('edit_title').value = event.title;
        document.getElementById('edit_date').value = event.date;
        document.getElementById('edit_end_date').value = event.end_date || '';
        document.getElementById('edit_time').value = event.time;
        document.getElementById('edit_category').value = event.category;
        document.getElementById('edit_desc').value = event.desc || '';
        document.getElementById('edit_file_input').value = '';
        document.getElementById('edit_file_preview').innerHTML = '';
        document.getElementById('editDescCount').innerText = (event.desc || '').length + '/1000';
        document.getElementById('editModal').classList.remove('hidden');
    }

    function handleFileSelect(inputElement, previewContainerId) {
        const previewContainer = document.getElementById(previewContainerId);
        previewContainer.innerHTML = '';
        const files = Array.from(inputElement.files);
        
        if(files.length > 5) {
            alert('You can only upload a maximum of 5 images');
            inputElement.value = '';
            return;
        }
        
        files.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between bg-gray-50 border border-gray-200 rounded px-3 py-1.5 text-sm';
            item.innerHTML = `
                <span class="truncate max-w-[250px] text-gray-700 text-xs font-bold font-mono">${file.name}</span>
                <button type="button" class="text-red-500 hover:text-red-700 hover:bg-red-50 px-2 py-0.5 rounded transition" onclick="removeFile('${inputElement.id}', ${index}, '${previewContainerId}')">Remove</button>
            `;
            previewContainer.appendChild(item);
        });
    }

    function removeFile(inputId, indexToRemove, previewContainerId) {
        const input = document.getElementById(inputId);
        const dt = new DataTransfer();
        
        Array.from(input.files).forEach((file, index) => {
            if(index !== indexToRemove) {
                dt.items.add(file);
            }
        });
        
        input.files = dt.files;
        handleFileSelect(input, previewContainerId);
    }
</script>


