<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$sessionsList = $pdo->query("SELECT * FROM academic_sessions ORDER BY IsActive DESC, SessionID DESC")->fetchAll();
$shiftsList = $pdo->query("SELECT * FROM shifts")->fetchAll();
$programsList = $pdo->query("SELECT * FROM programs WHERE IsActive = 1")->fetchAll();

$sessID = $_POST['session_id'] ?? ($_GET['session_id'] ?? ($sessionsList[0]['SessionID'] ?? null));
$shID = $_POST['shift_id'] ?? ($_GET['shift_id'] ?? ($shiftsList[0]['ShiftID'] ?? null));
$pID = $_POST['program_id'] ?? ($_GET['program_id'] ?? null);

$masterData = [];
$periods = [];
$error = '';

if ($sessID && $shID) {
    // Determine periods for this shift
    $slotQuery = $pdo->prepare("SELECT PeriodNumber FROM time_slots WHERE ShiftID = ? ORDER BY PeriodNumber");
    $slotQuery->execute([$shID]);
    while($r = $slotQuery->fetch()) {
        $periods[] = $r['PeriodNumber'];
    }
    
    if (empty($periods)) {
        $error = "This shift has no time slots defined.";
    } else {
        // Fetch ALL timetable data for this session and shift (filtered by Program if selected)
        $qStr = "
            SELECT t.*, 
                   p.Name as ProgName, d.Name as DeptName, s.Label as SemName, sec.Name as SecName,
                   c.Name as CourseName, c.RoomType,
                   u.Name as TeacherName, r.Name as RoomName
            FROM timetable t
            JOIN programs p ON t.ProgramID = p.ProgramID
            JOIN departments d ON t.DepartmentID = d.DepartmentID
            JOIN semesters s ON t.SemesterID = s.SemesterID
            LEFT JOIN sections sec ON t.SectionID = sec.SectionID
            LEFT JOIN courses c ON t.CourseID = c.CourseID
            LEFT JOIN users u ON t.TeacherID = u.UserID
            LEFT JOIN rooms r ON t.RoomID = r.RoomID
            WHERE t.SessionID = ? AND t.ShiftID = ?
        ";
        $params = [$sessID, $shID];
        
        if ($pID) {
            $qStr .= " AND t.ProgramID = ?";
            $params[] = $pID;
        }
        
        $qStr .= " ORDER BY p.ProgramID, d.DepartmentID, s.SemesterID, sec.SectionID, t.Day";
        
        $stmt = $pdo->prepare($qStr);
        $stmt->execute($params);
        $allRecords = $stmt->fetchAll();
        
        $daysArray = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $dayIndex = ['Monday'=>1, 'Tuesday'=>2, 'Wednesday'=>3, 'Thursday'=>4, 'Friday'=>5, 'Saturday'=>6];
        
        // Group by Class Hierarchy (Program > Dept > Sem > Sec)
        $classGroups = [];
        foreach($allRecords as $r) {
            $classLabel = $r['ProgName'] . '<br>' . $r['DeptName'] . ' - ' . $r['SemName'];
            if ($r['SecName'] && strtolower($r['SecName']) !== 'none') {
                $classLabel .= ' (Sec: ' . $r['SecName'] . ')';
            }
            
            $period = $r['PeriodNumber'] ?? null;
            if(!$period) {
                // Determine period manually if PeriodNumber not joined
                $pt = $pdo->prepare("SELECT PeriodNumber FROM time_slots WHERE SlotID=?");
                $pt->execute([$r['SlotID']]);
                $period = $pt->fetchColumn();
            }
            
            if (!isset($classGroups[$classLabel])) {
                foreach($periods as $p) {
                    $classGroups[$classLabel][$p] = [];
                    foreach($daysArray as $d) {
                        $classGroups[$classLabel][$p][$d] = ['IsFree' => 1];
                    }
                }
            }
            
            if ($r['IsFree'] == 0 && $period && isset($classGroups[$classLabel][$period])) {
                $classGroups[$classLabel][$period][$r['Day']] = [
                    'IsFree' => 0,
                    'Teacher' => $r['TeacherName'],
                    'Course' => $r['CourseName'],
                    'CourseCode' => $r['CourseCode'] ?? '',
                    'Room' => $r['RoomName']
                ];
            }
        }
        
        // Compress days into syntax Teacher | Course | Room [1-3]
        foreach($classGroups as $cLabel => $cPeriods) {
            $masterData[$cLabel] = [];
            
            foreach($periods as $p) {
                $dayEntries = $cPeriods[$p];
                
                // Group identical entries
                $merged = [];
                foreach($daysArray as $d) {
                    $entry = $dayEntries[$d];
                    if ($entry['IsFree'] == 1) continue;
                    
                    $hash = $entry['Teacher'] . '|' . $entry['Course'] . '|' . $entry['Room'];
                    if (!isset($merged[$hash])) {
                        $merged[$hash] = ['entry' => $entry, 'days' => []];
                    }
                    $merged[$hash]['days'][] = $dayIndex[$d];
                }
                
                $cellBlocks = [];
                foreach($merged as $hash => $data) {
                    $e = $data['entry'];
                    $dNums = $data['days'];
                    sort($dNums);
                    
                    // Format day output e.g [1-3] or [1,3,5]
                    $dStr = '';
                    if (count($dNums) > 1) {
                        $isConsecutive = true;
                        for($i=0; $i<count($dNums)-1; $i++) {
                            if($dNums[$i+1] - $dNums[$i] !== 1) { $isConsecutive = false; break; }
                        }
                        if ($isConsecutive) {
                            $dStr = '[' . $dNums[0] . '-' . end($dNums) . ']';
                        } else {
                            $dStr = '[' . implode(',', $dNums) . ']';
                        }
                    } else {
                        $dStr = '[' . $dNums[0] . ']';
                    }
                    
                    $courseStr = $e['Course'];
                    if($e['CourseCode']) $courseStr .= ' ' . $e['CourseCode'];
                    
                    $blockStr = "<b>{$e['Teacher']}</b> | {$courseStr} | {$e['Room']} <b>{$dStr}</b>";
                    $cellBlocks[] = $blockStr;
                }
                
                $masterData[$cLabel][$p] = implode('<br><br>', $cellBlocks);
            }
        }
    }
}
?>
<?php include '../includes/header.php'; ?>
<style>
@media print {
    body * { visibility: hidden; }
    #printableArea, #printableArea * { visibility: visible; }
    #printableArea { position: absolute; left: 0; top: 0; width: 100%; border:none; box-shadow:none; padding:0; margin:0;}
    .sidebar, .navbar, .hide-on-print { display: none !important; }
    .print-table { width: 100%; border-collapse: collapse; font-size: 10px; }
    .print-table th, .print-table td { border: 1px solid #000; padding: 4px; text-align: left; }
    .print-header { text-align: center; font-weight: bold; margin-bottom: 10px; }
}
</style>
<div class="w-full px-2 md:px-8 mx-auto flex flex-col md:flex-row gap-6 mt-4 pb-12 hide-on-print">
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="flex-1 min-w-0">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-maroon">Master Timetable</h2>
        </div>
        
        <div class="bg-white p-4 shadow-sm border border-gray-200 rounded-xl mb-6 hide-on-print">
            <form method="GET" class="grid grid-cols-2 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">Session</label>
                    <select name="session_id" class="w-full border border-gray-300 rounded p-2 focus:ring-1 focus:ring-[#a60b26]" required>
                        <?php foreach($sessionsList as $sl): echo "<option value='{$sl['SessionID']}' ".($sl['SessionID'] == $sessID ? 'selected':'').">{$sl['Title']}</option>"; endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">Shift</label>
                    <select name="shift_id" class="w-full border border-gray-300 rounded p-2 focus:ring-1 focus:ring-[#a60b26]" required>
                        <?php foreach($shiftsList as $sh): echo "<option value='{$sh['ShiftID']}' ".($sh['ShiftID'] == $shID ? 'selected':'').">{$sh['Name']}</option>"; endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">Program</label>
                    <select name="program_id" class="w-full border border-gray-300 rounded p-2 focus:ring-1 focus:ring-[#a60b26]">
                        <option value="">All Programs</option>
                        <?php foreach($programsList as $p): echo "<option value='{$p['ProgramID']}' ".($p['ProgramID'] == $pID ? 'selected':'').">{$p['Name']}</option>"; endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="bg-[#a60b26] text-white px-5 py-2 rounded font-bold hover:bg-[#8a0a20] w-full">View</button>
                </div>
            </form>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-50 text-red-700 p-4 rounded border border-red-200 hide-on-print"><?php echo $error; ?></div>
        <?php elseif (!empty($masterData)): ?>
            <div id="printableArea" class="bg-white p-6 shadow-sm border border-gray-200 rounded-xl overflow-x-auto">
                <div class="print-header hidden md:block text-center mb-4">
                    <h1 class="text-xl font-black uppercase text-gray-800">Govt. Graduate College, Civil Lines</h1>
                    <h2 class="text-md font-bold text-gray-600">Master Timetable</h2>
                </div>
                <table class="w-full border-collapse border border-gray-300 print-table text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 p-2 text-center w-32 font-bold text-gray-800">Class / Dept</th>
                            <?php foreach($periods as $p): ?>
                                <th class="border border-gray-300 p-2 text-center font-bold text-gray-800 w-48">Period <?php echo $p; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($masterData as $cLabel => $pData): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 p-2 font-bold text-gray-800 text-xs bg-gray-50 text-center uppercase"><?php echo $cLabel; ?></td>
                            <?php foreach($periods as $p): ?>
                                <td class="border border-gray-300 p-2 text-xs leading-relaxed text-gray-700 align-top">
                                    <?php echo empty($pData[$p]) ? '<span class="text-gray-300 italic">Free</span>' : $pData[$p]; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="bg-gray-50 text-gray-600 p-6 rounded text-center border border-gray-200 hide-on-print">
                No timetable data found for this selection.
            </div>
        <?php endif; ?>
    </div>
</div>
