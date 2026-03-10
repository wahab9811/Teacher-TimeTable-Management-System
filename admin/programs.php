<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['add'])) {
        $pdo->prepare("INSERT INTO programs (Name, PeriodUnit) VALUES (?, ?)")->execute([$_POST['name'], $_POST['unit']]);
    } elseif(isset($_POST['delete'])) {
        // Only if no deps/courses linked
        $pdo->prepare("DELETE FROM programs WHERE ProgramID = ?")->execute([$_POST['id']]);
    }
}
$programs = $pdo->query("SELECT * FROM programs")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <h2 class="text-2xl font-bold text-maroon mb-4">Manage Programs</h2>
        <form method="POST" class="flex gap-4 mb-6">
            <input type="text" name="name" placeholder="Program Name" required class="border p-2 rounded flex-1">
            <select name="unit" class="border p-2 rounded" required>
                <option value="year">Year</option><option value="semester">Semester</option>
            </select>
            <button name="add" class="btn-maroon px-6 rounded font-bold">Add Program</button>
        </form>
        <table class="w-full text-left"><thead class="bg-gray-100"><tr><th class="p-2">ID</th><th class="p-2">Name</th><th class="p-2">Unit</th><th class="p-2">Action</th></tr></thead>
        <tbody>
            <?php foreach($programs as $p): ?>
            <tr class="border-b"><td class="p-2"><?php echo $p['ProgramID']; ?></td><td class="p-2"><?php echo $p['Name']; ?></td><td class="p-2"><?php echo $p['PeriodUnit']; ?></td>
            <td class="p-2"><form method="POST" onsubmit="return confirm('Delete?');"><input type="hidden" name="id" value="<?php echo $p['ProgramID']; ?>"><button name="delete" class="text-red-500">Delete</button></form></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
