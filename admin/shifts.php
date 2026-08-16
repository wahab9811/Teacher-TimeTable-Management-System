<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['update'])) {
        $pdo->prepare("UPDATE time_slots SET StartTime=?, EndTime=? WHERE SlotID=?")->execute([$_POST['st'], $_POST['et'], $_POST['id']]);
    }
}
$slots = $pdo->query("SELECT ts.*, s.Name as ShiftName FROM time_slots ts JOIN shifts s ON ts.ShiftID=s.ShiftID ORDER BY ts.ShiftID, ts.PeriodNumber")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded">
        <h2 class="text-2xl font-bold text-maroon mb-4">Manage Time Slots</h2>
        <table class="w-full text-left"><thead class="bg-gray-100"><tr><th class="p-2">Shift</th><th class="p-2">Period</th><th class="p-2">Start Time</th><th class="p-2">End Time</th><th class="p-2">Action</th></tr></thead>
        <tbody>
            <?php foreach($slots as $s): ?>
            <tr class="border-b"><td class="p-2"><?php echo $s['ShiftName']; ?></td><td class="p-2"><?php echo $s['PeriodNumber']; ?></td>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $s['SlotID']; ?>">
                <td class="p-2"><input type="time" name="st" value="<?php echo $s['StartTime']; ?>" class="border p-1 rounded"></td>
                <td class="p-2"><input type="time" name="et" value="<?php echo $s['EndTime']; ?>" class="border p-1 rounded"></td>
                <td class="p-2"><button name="update" class="bg-blue-500 text-white px-2 py-1 rounded">Save</button></td>
            </form></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
</div>

