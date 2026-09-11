<?php
$side_teacherId = $_SESSION['user_id'] ?? 0;
// Fetch unread notifications count for sidebar if $pdo is available
$unreadNotifSidebar = 0;
$unreadInboxSidebar = 0;
if (isset($pdo)) {
    // Unread generic notifications
    $stmtSidebarNotif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE TeacherID = ? AND IsRead = 0");
    $stmtSidebarNotif->execute([$side_teacherId]);
    $unreadNotifSidebar = $stmtSidebarNotif->fetchColumn();
    
    // Pending requests in Action Inbox
    $stmtInboxCount = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE TargetTeacherID = ? AND Status = 'pending_teacher' AND TeacherBStatus='pending'");
    $stmtInboxCount->execute([$side_teacherId]);
    $unreadInboxSidebar = $stmtInboxCount->fetchColumn();
}
// Get the current base name
$current_page_side = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-64 bg-white p-4 shadow-md rounded shrink-0">
    <h3 class="text-lg font-bold text-maroon mb-4">Menu</h3>
    <ul class="space-y-2">
        <li><a href="dashboard.php" class="block p-2 rounded <?= $current_page_side == 'dashboard.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Dashboard</a></li>
        <li><a href="timetable.php" class="block p-2 rounded <?= $current_page_side == 'timetable.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">My Timetable</a></li>
        <li><a href="request_free_change.php" class="block p-2 rounded <?= $current_page_side == 'request_free_change.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">F.P Change Request</a></li>
        <li><a href="request_swap.php" class="block p-2 rounded <?= $current_page_side == 'request_swap.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Swap Request</a></li>
        <li><a href="request_proxy.php" class="block p-2 rounded <?= $current_page_side == 'request_proxy.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Proxy Request</a></li>
        <li><a href="inbox.php" class="block p-2 rounded <?= $current_page_side == 'inbox.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">My Inbox <?php if($unreadInboxSidebar > 0) echo '(<span class="text-maroon font-bold">' . $unreadInboxSidebar . '</span>)'; ?></a></li>
        <li><a href="request_leave.php" class="block p-2 rounded <?= $current_page_side == 'request_leave.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Leave Request</a></li>
        <li><a href="history_requests.php" class="block p-2 rounded <?= $current_page_side == 'history_requests.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Request History</a></li>
        <li><a href="history_substitute.php" class="block p-2 rounded <?= $current_page_side == 'history_substitute.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Substitute History</a></li>
        <li><a href="notifications.php" class="block p-2 rounded <?= $current_page_side == 'notifications.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Notifications (<span class="text-red-500 font-bold"><?= $unreadNotifSidebar ?></span>)</a></li>
        <li><a href="profile.php" class="block p-2 rounded <?= $current_page_side == 'profile.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Profile</a></li>
    </ul>
</aside>
