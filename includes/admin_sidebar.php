<?php
// Calculate pending requests for the badge
if (isset($pdo)) {
    $pendingRequests = $pdo->query("SELECT COUNT(*) FROM requests WHERE Status = 'pending_admin'")->fetchColumn();
} else {
    $pendingRequests = 0;
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-64 bg-white p-4 shadow-md rounded h-full flex-shrink-0">
    <h3 class="text-lg font-bold text-maroon mb-4">Admin Menu</h3>
    <ul class="space-y-2">
        <li><a href="dashboard.php" class="block p-2 rounded <?= $current_page == 'dashboard.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Dashboard</a></li>
        <li><a href="sessions.php" class="block p-2 rounded <?= $current_page == 'sessions.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Manage Sessions</a></li>
        <li><a href="programs.php" class="block p-2 rounded <?= $current_page == 'programs.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Programs</a></li>
        <li><a href="departments.php" class="block p-2 rounded <?= $current_page == 'departments.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Departments</a></li>
        <li><a href="semesters.php" class="block p-2 rounded <?= $current_page == 'semesters.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Semesters</a></li>
        <li><a href="sections.php" class="block p-2 rounded <?= $current_page == 'sections.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Sections</a></li>
        <li><a href="rooms.php" class="block p-2 rounded <?= $current_page == 'rooms.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Rooms</a></li>
        <li><a href="shifts.php" class="block p-2 rounded <?= $current_page == 'shifts.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Shifts & Time Slots</a></li>
        <li><a href="teachers.php" class="block p-2 rounded <?= $current_page == 'teachers.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Teachers</a></li>
        <li><a href="courses.php" class="block p-2 rounded <?= $current_page == 'courses.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Courses</a></li>
        <li><a href="timetable_manual.php" class="block p-2 rounded <?= $current_page == 'timetable_manual.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Manual Timetable</a></li>
        <li><a href="timetable_auto.php" class="block p-2 rounded <?= $current_page == 'timetable_auto.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Auto Timetable</a></li>
        <li><a href="requests.php" class="block p-2 rounded <?= $current_page == 'requests.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Requests (<span class="text-red-500 font-bold"><?= $pendingRequests ?></span>)</a></li>
        <li><a href="substitutes.php" class="block p-2 rounded <?= $current_page == 'substitutes.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Substitutes</a></li>
        <li><a href="notifications.php" class="block p-2 rounded <?= $current_page == 'notifications.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Notifications</a></li>
        <li><a href="calendar_manage.php" class="block p-2 rounded <?= $current_page == 'calendar_manage.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Calendar Edit</a></li>
        <li><a href="notices.php" class="block p-2 rounded <?= $current_page == 'notices.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Notice Board</a></li>
        <li><a href="downloads.php" class="block p-2 rounded <?= $current_page == 'downloads.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Manage Downloads</a></li>
        <li><a href="workload.php" class="block p-2 rounded <?= $current_page == 'workload.php' ? 'bg-gray-100 text-maroon font-semibold' : 'hover:bg-gray-100' ?>">Teacher Workload</a></li>
    </ul>
</aside>
