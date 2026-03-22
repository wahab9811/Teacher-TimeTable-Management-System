<?php
// Calculate pending requests for the badge
if (isset($pdo)) {
    $pendingRequests = $pdo->query("SELECT (SELECT COUNT(*) FROM requests WHERE Status = 'pending_admin') + (SELECT COUNT(*) FROM leave_requests WHERE Status = 'pending')")->fetchColumn();
    $pendingReports = $pdo->query("SELECT COUNT(*) FROM student_reports WHERE Status = 'pending'")->fetchColumn();
    $pendingInquiries = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE Status = 'pending'")->fetchColumn();
} else { 
    $pendingRequests = 0; 
    $pendingReports = 0;
    $pendingInquiries = 0;
}
$current_page = basename($_SERVER['PHP_SELF']);

$menuGroups = [
  'Academic Setup' => ['programs.php'=>'Programs','departments.php'=>'Departments','semesters.php'=>'Semesters','sections.php'=>'Sections','courses.php'=>'Courses','rooms.php'=>'Rooms','shifts.php'=>'Shifts & Time Slots'],
  'People'     => ['teachers.php'=>'Teachers','designations.php'=>'Manage Designations','workload.php'=>'Teacher Workload'],
  'Timetable'  => ['timetable_manual.php'=>'Manual Timetable','timetable_auto.php'=>'Auto Timetable','timetable_viewer.php'=>'Manage Timetables','timetable_master_view.php'=>'View Master Timetable'],
  'Operations' => ['import_data.php'=>'Bulk Import Data', 'requests.php'=>'Requests','substitutes.php'=>'Substitutes','reports.php'=>'Student Reports','inquiries.php'=>'General Inquiries'],
  'Content'    => ['announcements.php'=>'Announcements','notices.php'=>'Notice Board','calendar_manage.php'=>'Calendar Edit','downloads.php'=>'Manage Downloads'],
];
?>
<aside class="w-64 bg-white p-4 shadow-md rounded h-full flex-shrink-0">
  <h3 class="text-lg font-bold text-maroon mb-4">Menu</h3>
  <ul class="space-y-1">
    <li><a href="dashboard.php" class="block p-2 rounded <?= $current_page=='dashboard.php' ? 'bg-gray-100 text-maroon font-semibold':'hover:bg-gray-100' ?>">Dashboard</a></li>
    <li><a href="sessions.php" class="block p-2 rounded <?= $current_page=='sessions.php' ? 'bg-gray-100 text-maroon font-semibold':'hover:bg-gray-100' ?>">Manage Sessions</a></li>
    <?php foreach($menuGroups as $groupName => $items):
        $isOpen = array_key_exists($current_page, $items); ?>
      <li>
        <details <?= $isOpen ? 'open' : '' ?> class="group mb-1">
          <summary class="p-2 rounded cursor-pointer font-semibold text-gray-700 hover:bg-gray-100 select-none list-none [&::-webkit-details-marker]:hidden flex justify-between items-center transition-colors">
            <span><?= $groupName ?></span>
            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-transform duration-200 group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          </summary>
          <ul class="ml-2 mt-1 space-y-1 border-l pl-2">
            <?php foreach($items as $page => $label): ?>
              <li><a href="<?= $page ?>" class="block p-2 rounded text-sm <?= $current_page==$page ? 'bg-gray-100 text-maroon font-semibold':'hover:bg-gray-100' ?>"><?= $label ?><?php if($page==='requests.php'): ?> (<span class="text-red-500 font-bold"><?= $pendingRequests ?></span>)<?php elseif($page==='reports.php'): ?> (<span class="text-red-500 font-bold"><?= $pendingReports ?></span>)<?php elseif($page==='inquiries.php'): ?> (<span class="text-red-500 font-bold"><?= $pendingInquiries ?></span>)<?php endif; ?></a></li>
            <?php endforeach; ?>
          </ul>
        </details>
      </li>
    <?php endforeach; ?>
  </ul>
</aside>
