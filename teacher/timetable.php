<?php
// teacher/timetable.php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';
$teacherId = $_SESSION['user_id'];

$view = $_GET['view'] ?? 'day';
$targetDay = $_GET['day'] ?? 'Today';

$dayFilter = $targetDay;
if ($targetDay === 'Today') $dayFilter = date('l');
if ($targetDay === 'Yesterday') $dayFilter = date('l', strtotime('-1 day'));

// Fetch teaching periods info
$stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, 
                       IF(t.Day='Friday' AND ts.FridayStartTime IS NOT NULL, ts.FridayStartTime, ts.StartTime) as StartTime,
                       IF(t.Day='Friday' AND ts.FridayEndTime IS NOT NULL, ts.FridayEndTime, ts.EndTime) as EndTime,
                       c.Name as CourseName, r.Name as RoomName,
                       p.Name as ProgName, d.Name as DeptName, s.Label as SemName
                       FROM timetable t 
                       JOIN time_slots ts ON t.SlotID=ts.SlotID
                       LEFT JOIN courses c ON t.CourseID=c.CourseID
                       LEFT JOIN rooms r ON t.RoomID=r.RoomID
                       LEFT JOIN programs p ON t.ProgramID=p.ProgramID
                       LEFT JOIN departments d ON t.DepartmentID=d.DepartmentID
                       LEFT JOIN semesters s ON t.SemesterID=s.SemesterID
                       WHERE t.TeacherID=? AND t.Status = 'published'
                       ORDER BY t.Day, ts.PeriodNumber");
$stmt->execute([$teacherId]);
$tt = $stmt->fetchAll();

// Fetch active substitute assignments for this teacher
$stmtSub = $pdo->prepare("SELECT t.*, ts.PeriodNumber, 
                       IF(t.Day='Friday' AND ts.FridayStartTime IS NOT NULL, ts.FridayStartTime, ts.StartTime) as StartTime,
                       IF(t.Day='Friday' AND ts.FridayEndTime IS NOT NULL, ts.FridayEndTime, ts.EndTime) as EndTime,
                       c.Name as CourseName, r.Name as RoomName,
                       p.Name as ProgName, d.Name as DeptName, s.Label as SemName,
                       1 as IsSubstitute
                       FROM substitute_assignments sa
                       JOIN timetable t ON sa.TimetableID = t.TimetableID
                       JOIN time_slots ts ON t.SlotID=ts.SlotID
                       LEFT JOIN courses c ON t.CourseID=c.CourseID
                       LEFT JOIN rooms r ON t.RoomID=r.RoomID
                       LEFT JOIN programs p ON t.ProgramID=p.ProgramID
                       LEFT JOIN departments d ON t.DepartmentID=d.DepartmentID
                       LEFT JOIN semesters s ON t.SemesterID=s.SemesterID
                       WHERE sa.SubstituteTeacherID=? 
                       AND sa.Status='active'
                       AND CURDATE() BETWEEN sa.FromDate AND sa.ToDate");
$stmtSub->execute([$teacherId]);
$subTT = $stmtSub->fetchAll();

$tt = array_merge($tt, $subTT);

$dayClasses = array_filter($tt, function($item) use ($dayFilter) { return $item['Day'] === $dayFilter; });

// Unread Notifications
$stmtNotif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE TeacherID = ? AND IsRead = 0");
$stmtNotif->execute([$teacherId]);
$unreadNotif = $stmtNotif->fetchColumn();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4">
    <!-- Sidebar -->
    <?php include '../includes/teacher_sidebar.php'; ?>
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-4">My Timetable</h2>
        <div class="bg-white p-6 shadow-md rounded mb-4">
            <form method="GET" class="flex gap-4">
                <select name="view" class="border rounded p-2" onchange="this.form.submit()">
                    <option value="day" <?php if($view=='day') echo 'selected'; ?>>Day-wise</option>
                    <option value="week" <?php if($view=='week') echo 'selected'; ?>>Week-wise</option>
                </select>
                <?php if($view === 'day'): ?>
                <select name="day" class="border rounded p-2" onchange="this.form.submit()">
                    <option value="Today" <?php if($targetDay=='Today') echo 'selected'; ?>>Today</option>
                    <option value="Monday" <?php if($targetDay=='Monday') echo 'selected'; ?>>Monday</option>
                    <option value="Tuesday" <?php if($targetDay=='Tuesday') echo 'selected'; ?>>Tuesday</option>
                    <option value="Wednesday" <?php if($targetDay=='Wednesday') echo 'selected'; ?>>Wednesday</option>
                    <option value="Thursday" <?php if($targetDay=='Thursday') echo 'selected'; ?>>Thursday</option>
                    <option value="Friday" <?php if($targetDay=='Friday') echo 'selected'; ?>>Friday</option>
                    <option value="Saturday" <?php if($targetDay=='Saturday') echo 'selected'; ?>>Saturday</option>
                </select>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="bg-white p-6 shadow-md rounded">
            <?php if($view === 'day'): ?>
                <table class="w-full text-left border">
                    <thead class="bg-gray-100"><tr><th class="border p-2">Period</th><th class="border p-2">Time</th><th class="border p-2">Course</th><th class="border p-2">Details</th><th class="border p-2">Room</th></tr></thead>
                    <tbody>
                        <?php foreach($dayClasses as $c): ?>
                            <?php if(empty($c['IsFree'])): ?>
                                <tr><td class="border p-2 text-center"><?php echo $c['PeriodNumber']; ?></td><td class="border p-2"><?php echo $c['StartTime'] . ' - ' . $c['EndTime']; ?></td><td class="border p-2"><?php echo $c['CourseName']; ?><?php if(!empty($c['IsSubstitute'])) echo ' <span class="text-orange-500 text-xs font-bold">(Substitute)</span>'; ?></td><td class="border p-2"><?php echo $c['ProgName'].' - '.$c['DeptName'].' - '.$c['SemName']; ?></td><td class="border p-2"><?php echo $c['RoomName']; ?></td></tr>
                            <?php else: ?>
                                <tr class="free-period-row"><td class="border p-2 text-center"><?php echo $c['PeriodNumber']; ?></td><td class="border p-2"><?php echo $c['StartTime'] . ' - ' . $c['EndTime']; ?></td><td colspan="3" class="border p-2 text-center">Free</td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if(empty($dayClasses)) echo "<tr><td colspan='5' class='p-2 text-center text-gray-500'>No classes scheduled.</td></tr>"; ?>
                    </tbody>
                </table>
            <?php elseif($view === 'week'): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="border p-2 text-center w-16">Period</th>
                                <th class="border p-2 text-center">Time</th>
                                <?php 
                                $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; 
                                foreach($days as $d) echo "<th class='border p-2 text-center min-w-[120px]'>$d</th>"; 
                                ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for($i=1; $i<=6; $i++): ?>
                            <tr>
                                <td class="border p-2 text-center font-bold bg-gray-50"><?php echo $i; ?></td>
                                <?php 
                                    $timeStr = "--";
                                    $anySlot = array_filter($tt, function($item) use ($i) { 
                                        return $item['PeriodNumber'] == $i && !empty($item['StartTime']); 
                                    });
                                    if (!empty($anySlot)) {
                                        $firstSlot = array_values($anySlot)[0];
                                        $timeStr = substr($firstSlot['StartTime'], 0, 5) . ' - ' . substr($firstSlot['EndTime'], 0, 5);
                                    }
                                ?>
                                <td class="border p-2 text-center text-[13px] bg-gray-50 whitespace-nowrap text-gray-600 font-semibold"><?php echo $timeStr; ?></td>
                                <?php foreach($days as $d): 
                                    $slot = array_filter($tt, function($item) use ($d, $i) { return $item['Day'] === $d && $item['PeriodNumber'] == $i; });
                                    $slot = array_shift($slot);
                                ?>
                                    <td class="border p-2 text-center text-sm <?php echo (!$slot || !empty($slot['IsFree'])) ? 'bg-gray-50' : ''; ?>">
                                        <?php if($slot && empty($slot['IsFree'])): ?>
                                            <?php if($d === 'Friday' && !empty($slot['StartTime'])): ?>
                                                <div class="text-[10px] text-maroon flex justify-center font-bold mb-1 border-b border-gray-200 pb-1"><?php echo substr($slot['StartTime'],0,5).' - '.substr($slot['EndTime'],0,5); ?></div>
                                            <?php endif; ?>
                                            <div class="font-bold text-maroon"><?php echo htmlspecialchars($slot['CourseName'] ?? ''); ?></div>
                                            <div class="text-[11px] text-gray-600 my-1"><?php echo htmlspecialchars(($slot['ProgName'] ?? '').' - '.($slot['DeptName'] ?? '')); ?></div>
                                            <div class="text-[12px] italic font-semibold text-gray-500">
                                                <?php echo htmlspecialchars($slot['RoomName'] ?? ''); ?>
                                                <?php if(!empty($slot['IsSubstitute'])) echo '<br><span class="text-orange-500">(Substitute)</span>'; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-300">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
