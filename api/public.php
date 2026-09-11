<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ... existing code ...
if ($action == 'get_teacher_slots') {
    $t_id = $_GET['t_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, c.Name as CourseName FROM timetable t JOIN time_slots ts ON t.SlotID=ts.SlotID JOIN courses c ON t.CourseID=c.CourseID JOIN academic_sessions sess ON t.SessionID=sess.SessionID WHERE t.TeacherID=? AND sess.IsActive=1 ORDER BY t.Day, ts.PeriodNumber");
    $stmt->execute([$t_id]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action == 'get_sections') {
    $progId = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;
    $deptId = isset($_GET['dept_id']) ? intval($_GET['dept_id']) : 0;
    $semId = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;
    $shiftId = isset($_GET['shift_id']) ? intval($_GET['shift_id']) : 0;

    if($progId && $deptId && $semId) {
        if ($shiftId) {
            $stmt = $pdo->prepare("SELECT s.SectionID, s.Name, sh.Name as ShiftName, s.ShiftID FROM sections s JOIN shifts sh ON s.ShiftID=sh.ShiftID WHERE s.ProgramID=? AND s.DepartmentID=? AND s.SemesterID=? AND s.ShiftID=? ORDER BY s.Name, sh.Name");
            $stmt->execute([$progId, $deptId, $semId, $shiftId]);
        } else {
            $stmt = $pdo->prepare("SELECT s.SectionID, s.Name, sh.Name as ShiftName, s.ShiftID FROM sections s JOIN shifts sh ON s.ShiftID=sh.ShiftID WHERE s.ProgramID=? AND s.DepartmentID=? AND s.SemesterID=? ORDER BY s.Name, sh.Name");
            $stmt->execute([$progId, $deptId, $semId]);
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo json_encode([]);
    }
    exit;
}

if ($action == 'get_free_slots_for_section') {
    $secId = isset($_GET['sec_id']) ? intval($_GET['sec_id']) : 0;
    if ($secId) {
        $stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, 
                               IF(t.Day='Friday' AND ts.FridayStartTime IS NOT NULL, ts.FridayStartTime, ts.StartTime) as StartTime,
                               IF(t.Day='Friday' AND ts.FridayEndTime IS NOT NULL, ts.FridayEndTime, ts.EndTime) as EndTime
                               FROM timetable t JOIN time_slots ts ON t.SlotID=ts.SlotID JOIN academic_sessions sess ON t.SessionID=sess.SessionID WHERE t.SectionID=? AND t.IsFree=1 AND sess.IsActive=1 ORDER BY FIELD(t.Day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), ts.PeriodNumber");
        $stmt->execute([$secId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo json_encode([]);
    }
    exit;
}

if ($action == 'get_swap_targets_for_section') {
    $secId = isset($_GET['sec_id']) ? intval($_GET['sec_id']) : 0;
    $ttId = isset($_GET['tt_id']) ? intval($_GET['tt_id']) : 0;
    
    if ($secId && $ttId) {
        $stmtTT = $pdo->prepare("SELECT TeacherID FROM timetable WHERE TimetableID=?");
        $stmtTT->execute([$ttId]);
        $teacherId = $stmtTT->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT t.*, ts.PeriodNumber, c.Name as CourseName, u.Name as TeacherName,
                               IF(t.Day='Friday' AND ts.FridayStartTime IS NOT NULL, ts.FridayStartTime, ts.StartTime) as StartTime,
                               IF(t.Day='Friday' AND ts.FridayEndTime IS NOT NULL, ts.FridayEndTime, ts.EndTime) as EndTime
                               FROM timetable t 
                               JOIN time_slots ts ON t.SlotID=ts.SlotID 
                               JOIN courses c ON t.CourseID=c.CourseID
                               JOIN users u ON t.TeacherID=u.UserID
                               JOIN academic_sessions sess ON t.SessionID=sess.SessionID 
                               WHERE t.SectionID=? AND t.TeacherID != ? AND t.IsFree=0 AND sess.IsActive=1 
                               ORDER BY FIELD(t.Day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), ts.PeriodNumber");
        $stmt->execute([$secId, $teacherId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo json_encode([]);
    }
    exit;
}

// REST OF THE EXISTING CODE from before:
if ($action == 'get_filters') {
    $stmt = $pdo->query("SELECT * FROM programs WHERE IsActive = 1");
    $programs = $stmt->fetchAll();
    $stmt = $pdo->query("SELECT * FROM departments WHERE IsActive = 1");
    $departments = $stmt->fetchAll();
    $stmt = $pdo->query("SELECT * FROM semesters WHERE IsActive = 1");
    $semesters = $stmt->fetchAll();
    $data = ['programs' => $programs, 'departments' => $departments, 'semesters' => $semesters];
    echo json_encode($data);
    exit;
}

if ($action == 'get_timetable') {
    $program_id = $_GET['program_id'] ?? 0;
    $dept_id = $_GET['dept_id'] ?? 0;
    $sem_id = $_GET['sem_id'] ?? 0;
    $shift_id = $_GET['shift_id'] ?? 0; // 1 morning, 2 evening
    $view = $_GET['view'] ?? 'day'; // day or week
    $day = $_GET['day'] ?? 'Today'; // Option for Day-wise
    
    // Holiday check
    $targetDate = date('Y-m-d');
    if ($day == 'Tomorrow') $targetDate = date('Y-m-d', strtotime('+1 day'));
    elseif ($day == 'Yesterday') $targetDate = date('Y-m-d', strtotime('-1 day'));
    
    // Active session check
    $sessionCount = $pdo->query("SELECT COUNT(*) FROM academic_sessions WHERE IsActive = 1")->fetchColumn();
    if ($sessionCount == 0) {
        echo json_encode(['error' => 'no_active_session', 'message' => "No active academic session is set. Admin must activate a session."]);
        exit;
    }
    
    if (in_array($day, ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'])) {
        $dayName = $day;
        // E.g., strtotime('Monday this week')
        $targetDate = date('Y-m-d', strtotime($day . ' this week'));
    } else {
        $dayName = date('l', strtotime($targetDate));
    }

    if ($view === 'day') {
        $stmt = $pdo->prepare("SELECT * FROM college_calendar WHERE Category = 'holiday' AND Date = ?");
        $stmt->execute([$targetDate]);
        $holiday = $stmt->fetch();

        if ($holiday) {
            echo json_encode(['error' => 'holiday', 'message' => "Holiday: {$holiday['Title']} - {$holiday['Description']}"]);
            exit;
        }
    }

    // Fetch timetable
    $query = "SELECT t.*, c.Name as CourseName, c.RoomType, u.Name as TeacherName, r.Name as RoomName, ts.PeriodNumber, sec.Name as SectionName,
                     IF(t.Day='Friday' AND ts.FridayStartTime IS NOT NULL, ts.FridayStartTime, ts.StartTime) as StartTime,
                     IF(t.Day='Friday' AND ts.FridayEndTime IS NOT NULL, ts.FridayEndTime, ts.EndTime) as EndTime
              FROM timetable t
              JOIN time_slots ts ON t.SlotID = ts.SlotID
              JOIN academic_sessions sess ON t.SessionID = sess.SessionID
              LEFT JOIN courses c ON t.CourseID = c.CourseID
              LEFT JOIN users u ON t.TeacherID = u.UserID
              LEFT JOIN rooms r ON t.RoomID = r.RoomID
              LEFT JOIN sections sec ON t.SectionID = sec.SectionID
              WHERE t.ProgramID = ? AND t.DepartmentID = ? AND t.SemesterID = ? AND t.ShiftID = ? AND sess.IsActive = 1 ";
              
    $params = [$program_id, $dept_id, $sem_id, $shift_id];
    
    if (isset($_GET['sec_id']) && $_GET['sec_id'] !== '') {
        $query .= " AND t.SectionID = ? ";
        $params[] = $_GET['sec_id'];
    }
    
    if ($view == 'day') {
        $query .= " AND t.Day = ? ";
        $params[] = $dayName;
    }
    
    $query .= " ORDER BY t.Day, ts.PeriodNumber";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $schedule = $stmt->fetchAll();
    
    if ($schedule) {
        foreach($schedule as &$slot) {
            if ($view === 'day') {
                $slotDate = $targetDate;
            } else {
                $slotDate = date('Y-m-d', strtotime($slot['Day'] . ' this week'));
            }
            $stmtSub = $pdo->prepare("SELECT u.Name as SubName FROM substitute_assignments sa JOIN users u ON sa.SubstituteTeacherID = u.UserID WHERE sa.TimetableID = ? AND sa.Status = 'active' AND ? BETWEEN sa.FromDate AND sa.ToDate");
            $stmtSub->execute([$slot['TimetableID'], $slotDate]);
            $sub = $stmtSub->fetch();
            if ($sub) {
                $slot['TeacherName'] = $sub['SubName'] . " (Sub)";
            }
        }
    }
    
    $emptyFlag = empty($schedule) ? true : false;
    echo json_encode(['success' => true, 'data' => $schedule, 'day' => $dayName, 'view' => $view, 'empty' => $emptyFlag]);
    exit;
}
?>
