<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $desig = trim($_POST['designation'] ?? '');
        $max = (int)($_POST['max'] ?? 0);
        $hod = (int)($_POST['hod'] ?? 0);
        
        if($desig && $max > 0 && $hod > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO designation_workload (Designation, MaxWeeklyPeriods, HODWeeklyPeriods) VALUES (?, ?, ?)");
                $stmt->execute([$desig, $max, $hod]);
                $message = "Designation added successfully.";
            } catch(PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "This Designation already exists!";
                } else {
                    $error = "Error adding designation: " . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['edit'])) {
        $old_desig = trim($_POST['old_designation'] ?? '');
        $desig = trim($_POST['designation'] ?? '');
        $max = (int)($_POST['max'] ?? 0);
        $hod = (int)($_POST['hod'] ?? 0);
        
        if($desig && $old_desig && $max > 0 && $hod > 0) {
            try {
                // If the designation name changed, we update the primary key. Since it's linked via FK (ON DELETE SET NULL),
                // we should manually update the users table so users don't lose their designation, but wait, MySQL might restrict PK updates if not ON UPDATE CASCADE.
                // Let's just update the workload limits. We won't allow renaming the designation itself to keep it safe.
                $stmt = $pdo->prepare("UPDATE designation_workload SET MaxWeeklyPeriods = ?, HODWeeklyPeriods = ? WHERE Designation = ?");
                $stmt->execute([$max, $hod, $old_desig]);
                $message = "Designation workload updated successfully.";
            } catch(PDOException $e) {
                $error = "Error updating designation: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete'])) {
        $desig = $_POST['designation'];
        try {
            $stmt = $pdo->prepare("DELETE FROM designation_workload WHERE Designation = ?");
            $stmt->execute([$desig]);
            $message = "Designation deleted successfully.";
        } catch(PDOException $e) {
            $error = "Cannot delete designation. It might be in use.";
        }
    }
}

$designations = $pdo->query("SELECT * FROM designation_workload ORDER BY MaxWeeklyPeriods DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="flex-1 min-w-0">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-maroon">Manage Teacher Designations</h2>
            <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-maroon hover:bg-red-800 text-white px-4 py-2 rounded shadow flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Designation
            </button>
        </div>
        
        <?php if($message): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4 font-bold border"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4 font-bold border"><?php echo $error; ?></div><?php endif; ?>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-sm border-b">
                        <th class="p-4 font-semibold">Designation</th>
                        <th class="p-4 font-semibold">Max Weekly Periods</th>
                        <th class="p-4 font-semibold">HOD Weekly Periods</th>
                        <th class="p-4 font-semibold text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach($designations as $d): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <form method="POST" class="contents">
                                <td class="p-4 font-bold text-gray-800">
                                    <input type="hidden" name="old_designation" value="<?php echo htmlspecialchars($d['Designation']); ?>">
                                    <input type="hidden" name="designation" value="<?php echo htmlspecialchars($d['Designation']); ?>">
                                    <?php echo htmlspecialchars($d['Designation']); ?>
                                </td>
                                <td class="p-4">
                                    <input type="number" name="max" value="<?php echo $d['MaxWeeklyPeriods']; ?>" class="border rounded p-1 w-24 text-center font-bold text-gray-700 outline-none focus:ring-1 focus:ring-maroon" required min="1">
                                </td>
                                <td class="p-4">
                                    <input type="number" name="hod" value="<?php echo $d['HODWeeklyPeriods']; ?>" class="border rounded p-1 w-24 text-center font-bold text-gray-700 outline-none focus:ring-1 focus:ring-maroon" required min="1">
                                </td>
                                <td class="p-4 text-center whitespace-nowrap w-32">
                                    <button type="submit" name="edit" title="Save Limits" class="text-blue-600 hover:text-blue-800 hover:underline text-xs font-bold uppercase transition mr-3">Save</button>
                                    <button type="submit" name="delete" title="Delete Designation" onclick="return confirm('Are you sure you want to delete this designation?');" class="text-red-500 hover:text-red-700 hover:underline text-xs font-bold uppercase transition">Del</button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($designations)): ?>
                        <tr><td colspan="4" class="p-4 text-center text-gray-500">No designations found.</td></tr>
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
            <h3 class="font-bold text-lg text-gray-800">Add New Designation</h3>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Designation Title (e.g. Lecturer) *</label>
                <input type="text" name="designation" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" required>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Max Periods</label>
                    <input type="number" name="max" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" required>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">HOD Periods</label>
                    <input type="number" name="hod" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-maroon focus:ring-1 focus:ring-maroon" required>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 border rounded font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" name="add" class="px-4 py-2 bg-maroon shadow hover:bg-red-800 text-white rounded font-bold transition-all">Add Designation</button>
            </div>
        </form>
    </div>
</div>

