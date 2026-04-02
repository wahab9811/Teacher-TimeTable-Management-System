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
$stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, ts.StartTime, ts.EndTime, c.Name as CourseName, r.Name as RoomName,
                       p.Name as ProgName, d.Name as DeptName, s.Label as SemName
                       FROM timetable t 
                       JOIN time_slots ts ON t.SlotID=ts.SlotID
                       LEFT JOIN courses c ON t.CourseID=c.CourseID
                       LEFT JOIN rooms r ON t.RoomID=r.RoomID
                       LEFT JOIN programs p ON t.ProgramID=p.ProgramID
                       LEFT JOIN departments d ON t.DepartmentID=d.DepartmentID
                       LEFT JOIN semesters s ON t.SemesterID=s.SemesterID
                       WHERE t.TeacherID=? 
                       ORDER BY t.Day, ts.PeriodNumber");
$stmt->execute([$teacherId]);
$tt = $stmt->fetchAll();

$dayClasses = array_filter($tt, function($item) use ($dayFilter) { return $item['Day'] === $dayFilter; });
?>
<?php include '../includes/header.php'; ?>
<div class="max-w-7xl mx-auto flex gap-6 mt-4">
    <!-- Sidebar -->
    <aside class="w-64 bg-white p-4 shadow-md rounded h-full">
        <h3 class="text-lg font-bold text-maroon mb-4">Teacher Menu</h3>
        <ul class="space-y-2">
            <li><a href="dashboard.php" class="block p-2 hover:bg-gray-100 rounded">Dashboard</a></li>
            <li><a href="timetable.php" class="block p-2 bg-gray-100 rounded text-maroon font-semibold">My Timetable</a></li>
        </ul>
    </aside>
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
                            <?php if($c['IsFree']): ?>
                                <tr class="free-period-row"><td class="border p-2 text-center"><?php echo $c['PeriodNumber']; ?></td><td class="border p-2"><?php echo $c['StartTime'] . ' - ' . $c['EndTime']; ?></td><td colspan="3" class="border p-2 text-center">Free</td></tr>
                            <?php else: ?>
                                <tr><td class="border p-2 text-center"><?php echo $c['PeriodNumber']; ?></td><td class="border p-2"><?php echo $c['StartTime'] . ' - ' . $c['EndTime']; ?></td><td class="border p-2"><?php echo $c['CourseName']; ?></td><td class="border p-2"><?php echo $c['ProgName'].' - '.$c['DeptName'].' - '.$c['SemName']; ?></td><td class="border p-2"><?php echo $c['RoomName']; ?></td></tr>
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
                                    <td class="border p-2 text-center text-sm <?php echo (!$slot || $slot['IsFree']) ? 'bg-gray-50' : ''; ?>">
                                        <?php if($slot && empty($slot['IsFree'])): ?>
                                            <div class="font-bold text-maroon"><?php echo htmlspecialchars($slot['CourseName'] ?? ''); ?></div>
                                            <div class="text-[11px] text-gray-600 my-1"><?php echo htmlspecialchars(($slot['ProgName'] ?? '').' - '.($slot['DeptName'] ?? '')); ?></div>
                                            <div class="text-[12px] italic font-semibold text-gray-500"><?php echo htmlspecialchars($slot['RoomName'] ?? ''); ?></div>
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
