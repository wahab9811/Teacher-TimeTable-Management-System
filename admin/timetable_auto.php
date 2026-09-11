<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/timetable_rules.php';

$message = $error = '';
$previewData = null;
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$periods = []; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_timetable'])) {
    $sessID = $_POST['session_id'];
    $pID = $_POST['program_id'];
    $dID = $_POST['department_id'];
    $sID = $_POST['semester_id'];
    $shID = $_POST['shift_id'];
    $secID = $_POST['section_id'] !== '' ? $_POST['section_id'] : null;

    try {
        $stmtDel = $pdo->prepare("DELETE FROM timetable WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SessionID=? AND SectionID <=> ?");
        $stmtDel->execute([$pID, $dID, $sID, $shID, $sessID, $secID]);
        $message = "Timetable successfully cleared for the selected configuration.";
    } catch(Exception $e) {
        $error = "Error clearing timetable: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_timetable'])) {
    $sessID = $_POST['session_id'];
    $pID = $_POST['program_id'];
    $dID = $_POST['department_id'];
    $sID = $_POST['semester_id'];
    $shID = $_POST['shift_id'];
    $secID = $_POST['section_id'] !== '' ? $_POST['section_id'] : null;
    
    // 1. Fetch courses for this specific class
    if ($secID) {
        $stmtC = $pdo->prepare("SELECT * FROM courses WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND (SectionID=? OR SectionID IS NULL)");
        $stmtC->execute([$pID, $dID, $sID, $shID, $secID]);
    } else {
        $stmtC = $pdo->prepare("SELECT * FROM courses WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SectionID IS NULL");
        $stmtC->execute([$pID, $dID, $sID, $shID]);
    }
    $courses = $stmtC->fetchAll();
    
    // Fetch slots dynamically
    $slotQuery = $pdo->prepare("SELECT SlotID, PeriodNumber FROM time_slots WHERE ShiftID = ? ORDER BY PeriodNumber");
    $slotQuery->execute([$shID]);
    $slotMap = []; // Maps PeriodNumber -> SlotID
    while($r = $slotQuery->fetch()) {
        $slotMap[$r['PeriodNumber']] = $r['SlotID'];
        $periods[] = $r['PeriodNumber'];
    }
    
    if(empty($periods)) {
        $error = "Warning: The selected Shift has no time slots defined! Please go to Shifts & Timings to configure the shift.";
    } elseif(empty($courses)) {
        $error = "Warning: No courses are assigned to this Program/Department/Semester/Section combination. Please assign courses first.";
    } else {
        // Prepare courses: separate Labs and normal lectures
        $labs = [];
        $lectures = [];
        
        foreach($courses as $c) {
            if ($c['RoomType'] == 'Lab') {
                // Lab needs placement in Lab rooms (Consecutive if > 1 credit hour)
                $labs[] = $c;
            } else {
                $lectures[] = $c; // Keep as whole courses for Time Striping
            }
        }
        
        $totalRequiredSlots = 0;
        foreach($courses as $c) $totalRequiredSlots += $c['CreditHours'];
        $availableSlots = count($days) * count($periods);
        
        if($totalRequiredSlots > $availableSlots) {
            $error = "Conflict: Total credit hours ($totalRequiredSlots) exceed available weekly periods ($availableSlots). Generation failed.";
        } else {
            // Check for cascading deletions of pending requests or active substitutes
            if (!isset($_POST['confirm_delete'])) {
                $stmtCheck = $pdo->prepare("SELECT TimetableID FROM timetable WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SessionID=? AND SectionID <=> ?");
                $stmtCheck->execute([$pID, $dID, $sID, $shID, $sessID, $secID]);
                $ttIds = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($ttIds)) {
                    $inQuery = implode(',', array_fill(0, count($ttIds), '?'));
                    $chkReq = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE (TimetableID IN ($inQuery) OR SwapTimetableID IN ($inQuery)) AND Status IN ('pending_teacher','pending_admin')");
                    $params = array_merge($ttIds, $ttIds);
                    $chkReq->execute($params);
                    $pendingReqsCount = $chkReq->fetchColumn();

                    $chkSub = $pdo->prepare("SELECT COUNT(*) FROM substitute_assignments WHERE TimetableID IN ($inQuery) AND Status = 'active'");
                    $chkSub->execute($ttIds);
                    $activeSubsCount = $chkSub->fetchColumn();

                    if ($pendingReqsCount > 0 || $activeSubsCount > 0) {
                        $error = "
                            <div class='flex flex-col gap-2'>
                                <h3 class='font-bold text-lg text-red-800 flex items-center gap-2'><svg class='w-6 h-6' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/></svg> Warning: Cascading Deletion</h3>
                                <p>Regenerating the timetable will completely wipe the existing schedule for this class. The following linked records will also be <strong>permanently deleted (discarded)</strong>:</p>
                                <ul class='list-disc ml-8 text-red-900 font-bold'>";
                        if ($pendingReqsCount > 0) $error .= "<li>$pendingReqsCount pending swap/free-change request(s)</li>";
                        if ($activeSubsCount > 0) $error .= "<li>$activeSubsCount active substitute assignment(s)</li>";
                        $error .= "</ul>
                                <p class='mt-2 font-bold'>Are you absolutely sure you want to proceed?</p>
                                <form method='POST' class='flex gap-4 mt-3'>
                                    <input type='hidden' name='session_id' value='".htmlspecialchars($sessID)."'>
                                    <input type='hidden' name='program_id' value='".htmlspecialchars($pID)."'>
                                    <input type='hidden' name='department_id' value='".htmlspecialchars($dID)."'>
                                    <input type='hidden' name='semester_id' value='".htmlspecialchars($sID)."'>
                                    <input type='hidden' name='shift_id' value='".htmlspecialchars($shID)."'>
                                    <input type='hidden' name='section_id' value='".htmlspecialchars($secID ?? '')."'>
                                    
                                    <button type='submit' name='generate_timetable' class='bg-red-700 hover:bg-red-800 text-white px-5 py-2 rounded shadow-sm font-bold transition-colors border border-transparent'>Yes, Wipe and Regenerate</button>
                                    <input type='hidden' name='confirm_delete' value='1'>
                                    <a href='timetable_auto.php' class='bg-white border text-gray-800 hover:bg-gray-100 px-5 py-2 rounded shadow-sm font-bold transition-colors inline-block'>Cancel</a>
                                </form>
                            </div>
                        ";
                    }
                }
            }

            if (empty($error)) {
                try {
                    $pdo->beginTransaction();
                    
                    $stmtCheck = $pdo->prepare("SELECT TimetableID FROM timetable WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SessionID=? AND SectionID <=> ?");
                    $stmtCheck->execute([$pID, $dID, $sID, $shID, $sessID, $secID]);
                    $ttIds = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);
                    
                    $teachersToNotify = [];
                    if (!empty($ttIds)) {
                        $inQuery = implode(',', array_fill(0, count($ttIds), '?'));
                        $qReqs = $pdo->prepare("SELECT RequestedBy FROM requests WHERE (TimetableID IN ($inQuery) OR SwapTimetableID IN ($inQuery)) AND Status IN ('pending_teacher','pending_admin')");
                        $params = array_merge($ttIds, $ttIds);
                        $qReqs->execute($params);
                        foreach($qReqs->fetchAll(PDO::FETCH_COLUMN) as $t) $teachersToNotify[] = $t;
                        
                        $qSubs = $pdo->prepare("SELECT SubstituteTeacherID FROM substitute_assignments WHERE TimetableID IN ($inQuery) AND Status = 'active'");
                        $qSubs->execute($ttIds);
                        foreach($qSubs->fetchAll(PDO::FETCH_COLUMN) as $t) $teachersToNotify[] = $t;
                    }
                    $teachersToNotify = array_unique($teachersToNotify);
                    
                    $stmtDel = $pdo->prepare("DELETE FROM timetable WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SessionID=? AND SectionID <=> ?");
                    $stmtDel->execute([$pID, $dID, $sID, $shID, $sessID, $secID]);
                
                $generated = [];
                $unassigned = [];
                
                foreach($days as $day) {
                    foreach($periods as $per) {
                        $generated[$day][$per] = null; 
                    }
                }
                
                // Randomize to avoid repeated exact patterns
                shuffle($labs);
                shuffle($lectures);
            
            // PHASE 1: Place Labs (Consecutive)
            foreach($labs as $lab) {
                $ch = $lab['CreditHours'];
                $assigned = false;
                
                // Horizontal Packing (Period-first) for Labs
                for ($startIdx = 0; $startIdx <= count($periods) - $ch; $startIdx++) {
                    foreach($days as $day) {
                        $consecutiveFound = true;
                        for($k=0; $k<$ch; $k++) {
                            if ($generated[$day][$periods[$startIdx + $k]] !== null) {
                                $consecutiveFound = false; break;
                            }
                        }
                        
                        if ($consecutiveFound) {
                            // Verify constraints for each slot in the block
                            $allValid = true;
                            for($k=0; $k<$ch; $k++) {
                                $per = $periods[$startIdx + $k];
                                $testData = [
                                    'ProgramID' => $pID, 'DepartmentID' => $dID, 'SemesterID' => $sID,
                                    'ShiftID' => $shID, 'SessionID' => $sessID, 'SectionID' => $secID,
                                    'Day' => $day, 'SlotID' => $slotMap[$per], 'CourseID' => $lab['CourseID'],
                                    'TeacherID' => $lab['TeacherID'], 'RoomID' => null, 'IsFree' => 0
                                ];
                                if (validateTimetableSlot($pdo, $testData) !== true) {
                                    $allValid = false; break;
                                }
                            }
                            
                            if ($allValid) {
                                // Find available room for the entire lab block
                                $slotsToCheck = [];
                                for($k=0; $k<$ch; $k++) {
                                    $slotsToCheck[] = $slotMap[$periods[$startIdx + $k]];
                                }
                                $slotMarks = implode(',', array_fill(0, count($slotsToCheck), '?'));
                                $qRoom = $pdo->prepare("SELECT RoomID FROM rooms WHERE Type = 'Lab' AND IsActive = 1 AND RoomID NOT IN (
                                    SELECT RoomID FROM timetable WHERE Day = ? AND SlotID IN ($slotMarks) AND RoomID IS NOT NULL
                                ) LIMIT 1");
                                $qParams = array_merge([$day], $slotsToCheck);
                                $qRoom->execute($qParams);
                                $selectedRoom = $qRoom->fetchColumn() ?: null;

                                // Assign
                                for($k=0; $k<$ch; $k++) {
                                    $per = $periods[$startIdx + $k];
                                    $generated[$day][$per] = cloneData($lab);
                                    
                                    $stmtTemp = $pdo->prepare("INSERT INTO timetable (ProgramID, DepartmentID, SemesterID, ShiftID, SessionID, SectionID, Day, SlotID, CourseID, TeacherID, RoomID, IsFree) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)");
                                    $stmtTemp->execute([$pID, $dID, $sID, $shID, $sessID, $secID, $day, $slotMap[$per], $lab['CourseID'], $lab['TeacherID'], $selectedRoom]);
                                    $tid = $pdo->lastInsertId();
                                    $generated[$day][$per]['TimetableID'] = $tid;
                                }
                                $assigned = true;
                                break 2;
                            }
                        }
                    }
                }
                if(!$assigned) $unassigned[] = $lab['Name'] . ' (Lab block)';
            }
            
            // Find Home Room for lectures
            $homeRoomID = null;
            if (isset($_POST['home_room_id']) && $_POST['home_room_id'] !== '') {
                $homeRoomID = $_POST['home_room_id'];
            } else {
                throw new Exception("Please select a Dedicated Home Room.");
            }

            // PHASE 2: Place standard lectures (Strict Subject Time-Striping)
            // We want a subject to have a consistent Period across its days (e.g. Always P3).
            foreach($lectures as $lec) {
                $ch = $lec['CreditHours'];
                $assignedCount = 0;
                
                // Strategy A: Find a single period that has enough free days to hold ALL credit hours of this course.
                $bestPeriod = null;
                $bestDays = [];
                
                foreach($periods as $per) {
                    $validDaysForPer = [];
                    foreach($days as $day) {
                        if($generated[$day][$per] === null) {
                            $testData = [
                                'ProgramID' => $pID, 'DepartmentID' => $dID, 'SemesterID' => $sID,
                                'ShiftID' => $shID, 'SessionID' => $sessID, 'SectionID' => $secID,
                                'Day' => $day, 'SlotID' => $slotMap[$per], 'CourseID' => $lec['CourseID'],
                                'TeacherID' => $lec['TeacherID'], 'RoomID' => $homeRoomID, 'IsFree' => 0
                            ];
                            
                            // Prevent double booking on same day
                            $courseAlreadyOnDay = false;
                            foreach($periods as $verifyPer) {
                                if(isset($generated[$day][$verifyPer]) && $generated[$day][$verifyPer] !== null && $generated[$day][$verifyPer]['CourseID'] == $lec['CourseID']) {
                                    $courseAlreadyOnDay = true; break;
                                }
                            }
                            
                            if(!$courseAlreadyOnDay && validateTimetableSlot($pdo, $testData) === true) {
                                $validDaysForPer[] = $day;
                            }
                        }
                    }
                    
                    if (count($validDaysForPer) >= $ch) {
                        $bestPeriod = $per;
                        $bestDays = array_slice($validDaysForPer, 0, $ch);
                        break;
                    }
                }
                
                if ($bestPeriod !== null) {
                    // Success: Place all CH cleanly into this single period
                    $selectedRoom = $homeRoomID;
                    foreach($bestDays as $day) {
                        $generated[$day][$bestPeriod] = cloneData($lec);
                        $stmtTemp = $pdo->prepare("INSERT INTO timetable (ProgramID, DepartmentID, SemesterID, ShiftID, SessionID, SectionID, Day, SlotID, CourseID, TeacherID, RoomID, IsFree) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)");
                        $stmtTemp->execute([$pID, $dID, $sID, $shID, $sessID, $secID, $day, $slotMap[$bestPeriod], $lec['CourseID'], $lec['TeacherID'], $selectedRoom]);
                        $generated[$day][$bestPeriod]['TimetableID'] = $pdo->lastInsertId();
                        $assignedCount++;
                    }
                } else {
                    // Strategy B: Fallback (Fragmented placement) if no single period can hold the full course
                    foreach($periods as $per) {
                        if ($assignedCount >= $ch) break;
                        
                        foreach($days as $day) {
                            if ($assignedCount >= $ch) break;
                            
                            $courseAlreadyOnDay = false;
                            foreach($periods as $verifyPer) {
                                if(isset($generated[$day][$verifyPer]) && $generated[$day][$verifyPer] !== null && $generated[$day][$verifyPer]['CourseID'] == $lec['CourseID']) {
                                    $courseAlreadyOnDay = true; break;
                                }
                            }
                            if($courseAlreadyOnDay) continue; 
                        
                            if($generated[$day][$per] === null) {
                                $testData = [
                                    'ProgramID' => $pID, 'DepartmentID' => $dID, 'SemesterID' => $sID,
                                    'ShiftID' => $shID, 'SessionID' => $sessID, 'SectionID' => $secID,
                                    'Day' => $day, 'SlotID' => $slotMap[$per], 'CourseID' => $lec['CourseID'],
                                    'TeacherID' => $lec['TeacherID'], 'RoomID' => $homeRoomID, 'IsFree' => 0
                                ];
                                
                                if(validateTimetableSlot($pdo, $testData) === true) {
                                    $selectedRoom = $homeRoomID;
                                    $generated[$day][$per] = cloneData($lec);
                                    $stmtTemp = $pdo->prepare("INSERT INTO timetable (ProgramID, DepartmentID, SemesterID, ShiftID, SessionID, SectionID, Day, SlotID, CourseID, TeacherID, RoomID, IsFree) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)");
                                    $stmtTemp->execute([$pID, $dID, $sID, $shID, $sessID, $secID, $day, $slotMap[$per], $lec['CourseID'], $lec['TeacherID'], $selectedRoom]);
                                    $generated[$day][$per]['TimetableID'] = $pdo->lastInsertId();
                                    $assignedCount++;
                                }
                            }
                        }
                    }
                }
                
                if($assignedCount < $ch) {
                    $unassigned[] = $lec['Name'] . " (Missing " . ($ch - $assignedCount) . " CH)";
                }
            }
            
            if (count($unassigned) > 0) {
                $error = "Auto Generator Engine Failed! Conflict prevents flawless placement of: " . implode(', ', $unassigned) . ". The generated partial data has been rolled back.";
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } else {
                // PHASE 3: Fill Free Periods
                foreach($days as $day) {
                    foreach($periods as $per) {
                        if($generated[$day][$per] === null) {
                            $pdo->prepare("INSERT INTO timetable (ProgramID, DepartmentID, SemesterID, ShiftID, SessionID, SectionID, Day, SlotID, IsFree) VALUES (?,?,?,?,?,?,?,?,1)")
                                ->execute([$pID, $dID, $sID, $shID, $sessID, $secID, $day, $slotMap[$per]]);
                            $generated[$day][$per] = ['CourseName' => 'Free (Auto)', 'IsFree' => 1];
                        }
                    }
                }
                $pdo->commit();
                
                if (!empty($teachersToNotify)) {
                    $nMsg = "A class schedule was regenerated by the admin, which cancelled your linked pending request or active substitute assignment.";
                    foreach($teachersToNotify as $ttn) {
                        $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$ttn, $nMsg]);
                    }
                }
                
                $message = "Timetable successfully mapped and saved! Review the footprint below. Go to Manual Timetable for adjustments.";
                $previewData = $generated;
            }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Database Error: " . $e->getMessage();
            }
            }
        }
    }
}

function cloneData($course) {
    return [
        'CourseID' => $course['CourseID'],
        'CourseName' => $course['Name'],
        'TeacherID' => $course['TeacherID'],
        'IsFree' => 0,
        'IsLab' => ($course['RoomType'] == 'Lab')
    ];
}

$programs = $pdo->query("SELECT * FROM programs WHERE IsActive = 1")->fetchAll();
$depts = $pdo->query("SELECT * FROM departments WHERE IsActive = 1")->fetchAll();
$sems = $pdo->query("SELECT * FROM semesters WHERE IsActive = 1")->fetchAll();
$sessionsList = $pdo->query("SELECT * FROM academic_sessions ORDER BY IsActive DESC, SessionID DESC")->fetchAll();
$shiftsList = $pdo->query("SELECT * FROM shifts")->fetchAll();
$classroomsList = $pdo->query("SELECT RoomID, Name FROM rooms WHERE Type = 'Classroom' AND IsActive = 1 ORDER BY Name")->fetchAll();
$teachers = $pdo->query("SELECT UserID, Name FROM users WHERE Role='teacher'")->fetchAll(PDO::FETCH_KEY_PAIR);

?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex flex-col md:flex-row gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="flex-1 min-w-0">
        <h2 class="text-2xl font-bold text-maroon mb-4">Auto Timetable Generator</h2>
        
        <div class="bg-white p-6 shadow-sm border border-gray-200 rounded-xl mb-6 relative overflow-hidden">

            
            <?php if($message): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6 font-medium shadow-sm">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 font-medium shadow-sm">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="auto_tt_form" class="space-y-6 flex flex-col relative z-10">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Current Session</label>
                        <select name="session_id" class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-[#a60b26] outline-none" required>
                            <?php foreach($sessionsList as $sl): echo "<option value='{$sl['SessionID']}' ".($sl['IsActive'] ? 'selected':'').">{$sl['Title']}".($sl['IsActive'] ? ' (Active)':'')."</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Program</label>
                        <select name="program_id" id="pid" class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-[#a60b26] outline-none" required>
                            <option value="">-Select-</option>
                            <?php foreach($programs as $p): echo "<option value='{$p['ProgramID']}'>{$p['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Dept / Group</label>
                        <select name="department_id" id="did" class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-[#a60b26] outline-none" required>
                            <option value="">-Select-</option>
                            <?php foreach($depts as $d): echo "<option value='{$d['DepartmentID']}' data-prog='{$d['ProgramID']}'>{$d['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Semester / Year</label>
                        <select name="semester_id" id="sid" class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-[#a60b26] outline-none" required>
                            <option value="">-Select-</option>
                            <?php foreach($sems as $s): echo "<option value='{$s['SemesterID']}' data-prog='{$s['ProgramID']}'>{$s['Label']}</option>"; endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 border-t border-gray-100 mt-4 pt-4 items-end">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Shift Configuration</label>
                        <select name="shift_id" id="shid" class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-[#a60b26] outline-none" required>
                            <option value="">-Select-</option>
                            <?php foreach($shiftsList as $sh): echo "<option value='{$sh['ShiftID']}'>{$sh['Name']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Section</label>
                        <select name="section_id" id="secid" class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-[#a60b26] outline-none">
                            <option value="">General Class (No Sec)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Home Room</label>
                        <select name="home_room_id" class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-[#a60b26] outline-none" required>
                            <option value="">-Select-</option>
                            <?php foreach($classroomsList as $rm): echo "<option value='{$rm['RoomID']}'>".htmlspecialchars($rm['Name'])."</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2 flex justify-end gap-3 z-10">
                        <button type="submit" name="clear_timetable" class="bg-white border-2 border-red-200 hover:bg-red-50 text-red-600 px-6 py-3 rounded-lg font-bold shadow-sm transition-all text-sm h-[42px] leading-none flex items-center" onclick="return confirm('Are you sure you want to clear/delete the timetable footprint for this specific configuration? Note: Pending requests and active substitutes linked to these periods will also be discarded.')">
                            Clear Timetable
                        </button>         
                        <button type="submit" name="generate_timetable" class="bg-[#a60b26] hover:bg-red-800 hover:shadow-lg text-white px-8 py-3 rounded-lg font-bold shadow-md transition-all h-[42px] leading-none flex items-center">
                            Auto Generate
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if($previewData): ?>
        <div class="bg-white px-6 py-4 shadow-sm border border-gray-200 rounded-xl">
            <h3 class="text-xl font-bold mb-4 text-[#a60b26] flex items-center">
                Output Draft footprint
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse border border-gray-200 shadow-sm rounded">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="border border-gray-200 p-3 font-black text-center w-24">Day</th>
                            <?php foreach($periods as $per): ?><th class="border border-gray-200 p-2 font-bold text-center bg-gray-50/50">P-<?php echo $per; ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($days as $day): ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="border border-gray-200 p-3 font-bold text-gray-800 bg-gray-50 text-center uppercase tracking-wide text-xs">
                                <?php echo substr($day, 0, 3); ?><br><span class="text-[10px] text-gray-400 font-normal"><?php echo $day === 'Friday' ? '(Short)' : ''; ?></span>
                            </td>
                            <?php foreach($periods as $per): 
                                $pd = $previewData[$day][$per] ?? ['IsFree' => 1];
                                $isFree = $pd['IsFree'];
                                $isLab = $pd['IsLab'] ?? false;
                            ?>
                            <td class="border border-gray-200 p-2 text-center <?php echo $isFree ? 'bg-gray-100/30' : ($isLab ? 'bg-amber-50/50' : 'bg-white'); ?>">
                                <?php if($isFree): ?>
                                    <span class="text-gray-300 italic text-xs">Free</span>
                                <?php else: ?>
                                    <div class="font-bold text-gray-800 text-sm mb-1 leading-tight"><?php echo htmlspecialchars($pd['CourseName']); ?></div>
                                    <div class="text-[10px] text-blue-600 font-semibold bg-blue-50 py-0.5 rounded px-2 w-max mx-auto"><?php echo htmlspecialchars($teachers[$pd['TeacherID']] ?? 'Unassigned'); ?></div>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-xs text-gray-500 bg-amber-50 p-3 rounded border border-amber-100"><strong class="text-amber-700">Note:</strong> Auto-generator has automatically assigned available physical Rooms based on course type (Lab/Classroom). You can review or adjust them in the <b>Manual</b> section.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Filter scripts just like manual
const depts = <?php echo json_encode($depts); ?>;
const sems = <?php echo json_encode($sems); ?>;

document.getElementById('pid').addEventListener('change', function() {
    let pid = this.value;
    
    let deptSel = document.getElementById('did');
    let semSel = document.getElementById('sid');
    let currentDept = deptSel.value;
    let currentSem = semSel.value;
    
    deptSel.innerHTML = '<option value="">-Select-</option>';
    depts.forEach(d => {
        if(d.ProgramID == pid) {
            let opt = new Option(d.Name, d.DepartmentID);
            if(d.DepartmentID == currentDept) opt.selected = true;
            deptSel.add(opt);
        }
    });

    semSel.innerHTML = '<option value="">-Select-</option>';
    sems.forEach(s => {
        if(s.ProgramID == pid) {
            let opt = new Option(s.Label, s.SemesterID);
            if(s.SemesterID == currentSem) opt.selected = true;
            semSel.add(opt);
        }
    });
    
    loadSectionsForTT();
});

function loadSectionsForTT() {
    let pid = document.getElementById('pid').value;
    let did = document.getElementById('did').value;
    let sid = document.getElementById('sid').value;
    let sh = document.getElementById('shid').value;
    let secSelect = document.getElementById('secid');
    
    let currentVal = secSelect.value;
    secSelect.innerHTML = '<option value="">General Class (No Sec)</option>';
    
    if(pid && did && sid && sh) {
        fetch(`../api/public.php?action=get_sections&program_id=${pid}&dept_id=${did}&semester_id=${sid}&shift_id=${sh}`)
        .then(r => r.json())
        .then(data => {
            data.forEach(sec => {
                let opt = document.createElement('option');
                opt.value = sec.SectionID; opt.textContent = 'Section ' + sec.Name;
                secSelect.appendChild(opt);
            });
            if([...secSelect.options].some(o => o.value === currentVal)) {
                secSelect.value = currentVal;
            }
        });
    }
}
document.getElementById('did').addEventListener('change', loadSectionsForTT);
document.getElementById('sid').addEventListener('change', loadSectionsForTT);
if(document.getElementById('shid')) document.getElementById('shid').addEventListener('change', loadSectionsForTT);
</script>
