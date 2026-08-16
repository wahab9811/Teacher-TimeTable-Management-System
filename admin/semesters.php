<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['add'])) {
        $pdo->prepare("INSERT INTO semesters (ProgramID, Label) VALUES (?, ?)")->execute([$_POST['program_id'], $_POST['label']]);
    } elseif(isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM semesters WHERE SemesterID = ?")->execute([$_POST['id']]);
    }
}
$semesters = $pdo->query("SELECT s.*, p.Name as PName FROM semesters s JOIN programs p ON s.ProgramID = p.ProgramID")->fetchAll();
$programs = $pdo->query("SELECT * FROM programs")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <h2 class="text-2xl font-bold text-maroon mb-4">Manage Semesters/Years</h2>
        <form method="POST" class="flex gap-4 mb-6">
            <select name="program_id" class="border p-2 rounded" required>
                <option value="">Program...</option>
                <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
            </select>
            <input type="text" name="label" placeholder="Semester 1 / 1st Year" required class="border p-2 rounded flex-1">
            <button name="add" class="btn-maroon px-6 rounded font-bold">Add</button>
        </form>
        <table class="w-full text-left"><thead class="bg-gray-100"><tr><th class="p-2">Program</th><th class="p-2">Label</th><th class="p-2">Action</th></tr></thead>
        <tbody>
            <?php foreach($semesters as $s): ?>
            <tr class="border-b"><td class="p-2"><?php echo $s['PName']; ?></td><td class="p-2"><?php echo $s['Label']; ?></td>
            <td class="p-2"><form method="POST" onsubmit="return confirm('Delete?');"><input type="hidden" name="id" value="<?php echo $s['SemesterID']; ?>"><button name="delete" class="text-red-500">Delete</button></form></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
</div>

