<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'resolve') {
        $stmt = $pdo->prepare("UPDATE student_reports SET Status = 'resolved' WHERE ReportID = ?");
        $stmt->execute([$_POST['report_id']]);
        $message = "Report marked as resolved.";
    } elseif ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM student_reports WHERE ReportID = ?");
        $stmt->execute([$_POST['report_id']]);
        $message = "Report deleted.";
    }
}

$statusFilter = $_GET['status'] ?? 'pending';
if ($statusFilter === 'all') {
    $reports = $pdo->query("SELECT * FROM student_reports ORDER BY CreatedAt DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT * FROM student_reports WHERE Status = ? ORDER BY CreatedAt DESC");
    $stmt->execute([$statusFilter]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<?php include '../includes/header.php'; ?>

<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4">
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">Student Reports</h2>
        
        <?php if($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 font-bold shadow-sm">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="mb-4">
            <a href="reports.php?status=pending" class="px-4 py-2 <?= $statusFilter=='pending' ? 'bg-[#a60b26] text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> rounded font-bold mr-2 transition-colors">Pending</a>
            <a href="reports.php?status=resolved" class="px-4 py-2 <?= $statusFilter=='resolved' ? 'bg-[#a60b26] text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> rounded font-bold mr-2 transition-colors">Resolved</a>
            <a href="reports.php?status=all" class="px-4 py-2 <?= $statusFilter=='all' ? 'bg-[#a60b26] text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> rounded font-bold transition-colors">All Reports</a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto p-4 md:p-6 mb-8">
            <table class="w-full border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-left">
                        <th class="p-3 text-sm font-bold text-gray-700">Date/Time</th>
                        <th class="p-3 text-sm font-bold text-gray-700">Student Name</th>
                        <th class="p-3 text-sm font-bold text-gray-700">Class Info</th>
                        <th class="p-3 text-sm font-bold text-gray-700">Issue Type</th>
                        <th class="p-3 text-sm font-bold text-gray-700">Message</th>
                        <th class="p-3 text-sm font-bold text-gray-700">Status</th>
                        <th class="p-3 text-sm font-bold text-gray-700 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($reports) > 0): ?>
                        <?php foreach($reports as $r): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="p-3 text-sm text-gray-600"><?= date('d M Y, h:i A', strtotime($r['CreatedAt'])) ?></td>
                                <td class="p-3 text-sm font-bold text-maroon"><?= htmlspecialchars($r['StudentName']) ?></td>
                                <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($r['ProgramDept']) ?></td>
                                <td class="p-3 text-sm text-gray-700 font-semibold"><?= htmlspecialchars($r['IssueType']) ?></td>
                                <td class="p-3 text-sm text-gray-700 max-w-md break-words whitespace-normal leading-relaxed"><?= htmlspecialchars($r['Message']) ?></td>
                                <td class="p-3 text-sm">
                                    <?php if ($r['Status'] === 'pending'): ?>
                                        <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-[11px] font-bold uppercase tracking-wider">Pending</span>
                                    <?php else: ?>
                                        <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-[11px] font-bold uppercase tracking-wider">Resolved</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-sm text-right">
                                    <form method="POST" class="flex flex-col sm:flex-row gap-2 justify-end items-center">
                                        <input type="hidden" name="report_id" value="<?= $r['ReportID'] ?>">
                                        <?php if ($r['Status'] === 'pending'): ?>
                                            <button type="submit" name="action" value="resolve" class="px-3 py-1 bg-green-50 text-green-700 rounded hover:bg-green-100 font-bold text-xs transition-colors">Resolve</button>
                                        <?php endif; ?>
                                        <button type="submit" name="action" value="delete" class="px-3 py-1 bg-red-50 text-red-600 rounded hover:bg-red-100 font-bold text-xs transition-colors" onclick="return confirm('Delete this report permanently?');">Del</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="p-8 text-center text-gray-500 font-semibold bg-gray-50 rounded">No reports found in this section.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
