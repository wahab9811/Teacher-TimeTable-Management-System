<?php
require_once __DIR__ . '/../config/db.php';

/* 
 * CONFLICT CHECKER RULES - SECTION 7
 */

function validateTimetableSlot($pdo, $data, $ignoreTimetableID = null) {
    // $data includes: ProgramID, DepartmentID, SemesterID, ShiftID, Day, SlotID, CourseID, TeacherID, RoomID, IsFree
    
    // If IsFree is true, no conflicts can occur for Course/Teacher/Room. 
    // We only need to check Class Double Booking for the same exact slot.
    if (!empty($data['IsFree'])) {
        // Rule 7
        return checkClassDoubleBooking($pdo, $data, $ignoreTimetableID);
    }
    
    // Check all rules
    $res = checkTeacherAvailability($pdo, $data);
    if($res !== true) return $res;
    
    $res = checkClassDoubleBooking($pdo, $data, $ignoreTimetableID);
    if($res !== true) return $res;
    
    $res = checkRoomDoubleBooking($pdo, $data, $ignoreTimetableID);
    if($res !== true) return $res;
    
    $res = checkTeacherDoubleBooking($pdo, $data, $ignoreTimetableID);
    if($res !== true) return $res;
    
    $res = checkTeacherDailyLimit($pdo, $data, $ignoreTimetableID);
    if($res !== true) return $res;
    
    $res = checkRoomTypeMatch($pdo, $data);
    if($res !== true) return $res;
    
    $res = checkCreditHoursDistribution($pdo, $data, $ignoreTimetableID);
    if($res !== true) return $res;
    
    $res = checkWeeklyLoadLimit($pdo, $data, $ignoreTimetableID);
    if($res !== true) return $res;
    
    return true; // Valid
}

// Teacher Day Availability Rule
function checkTeacherAvailability($pdo, $data) {
    if(empty($data['TeacherID']) || empty($data['Day'])) return true;
    
    $stmt = $pdo->prepare("SELECT AvailableDays FROM users WHERE UserID = ?");
    $stmt->execute([$data['TeacherID']]);
    $days = $stmt->fetchColumn();
    
    if (!empty($days)) {
        $allowedDays = array_map('trim', explode(',', $days));
        if (!in_array($data['Day'], $allowedDays)) {
            return "Teacher Availability Rule Failed: Teacher is only available on (" . $days . "), but attempted on " . $data['Day'] . ".";
        }
    }
    return true;
}

// Rule One & Three
function checkTeacherDailyLimit($pdo, $data, $ignoreID) {
    // Student classes don't have limit, but teachers do. Max 5 teaching periods per shift per day.
    if(empty($data['TeacherID'])) return true;
    
    $sess = $data['SessionID'] ?? null;
    $sql = "SELECT COUNT(*) FROM timetable WHERE TeacherID = ? AND ShiftID = ? AND Day = ? AND IsFree = 0 AND SessionID <=> ?";
    $params = [$data['TeacherID'], $data['ShiftID'], $data['Day'], $sess];
    if($ignoreID) { $sql .= " AND TimetableID != ?"; $params[] = $ignoreID; }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->fetchColumn();
    
    if ($count >= 5) {
        return "Rule One Failed: Teacher cannot exceed 5 teaching periods in one shift per day (At least 1 free period missing).";
    }
    return true;
}

// Rule Four
function checkRoomTypeMatch($pdo, $data) {
    if(empty($data['CourseID']) || empty($data['RoomID'])) return true;
    
    $course = $pdo->prepare("SELECT RoomType FROM courses WHERE CourseID = ?");
    $course->execute([$data['CourseID']]);
    $cType = $course->fetchColumn();
    
    $room = $pdo->prepare("SELECT Type FROM rooms WHERE RoomID = ?");
    $room->execute([$data['RoomID']]);
    $rType = $room->fetchColumn();
    
    if ($cType !== $rType) {
        return "Rule Four Failed: Course requires $cType but room is $rType.";
    }
    return true;
}

// Rule Five
function checkTeacherDoubleBooking($pdo, $data, $ignoreID) {
    if(empty($data['TeacherID'])) return true;
    
    // same exact slot time (ShiftID ensures it's the exact same time slots, but strictly it's SlotID)
    $sess = $data['SessionID'] ?? null;
    $sql = "SELECT COUNT(*) FROM timetable WHERE TeacherID = ? AND Day = ? AND SlotID = ? AND SessionID <=> ?";
    $params = [$data['TeacherID'], $data['Day'], $data['SlotID'], $sess];
    if($ignoreID) { $sql .= " AND TimetableID != ?"; $params[] = $ignoreID; }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn() > 0) {
         return "Rule Five Failed: Teacher is already booked at this time slot.";
    }
    return true;
}

// Rule Six
function checkRoomDoubleBooking($pdo, $data, $ignoreID) {
    if(empty($data['RoomID'])) return true;
    
    $sess = $data['SessionID'] ?? null;
    $sql = "SELECT COUNT(*) FROM timetable WHERE RoomID = ? AND Day = ? AND SlotID = ? AND SessionID <=> ?";
    $params = [$data['RoomID'], $data['Day'], $data['SlotID'], $sess];
    if($ignoreID) { $sql .= " AND TimetableID != ?"; $params[] = $ignoreID; }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn() > 0) {
         return "Rule Six Failed: Room is already booked at this time slot.";
    }
    return true;
}

// Rule Seven
function checkClassDoubleBooking($pdo, $data, $ignoreID) {
    if(!array_key_exists('SectionID', $data)) $data['SectionID'] = null; // safety
    
    $sess = $data['SessionID'] ?? null;
    $sql = "SELECT COUNT(*) FROM timetable WHERE ProgramID = ? AND DepartmentID = ? AND SemesterID = ? AND ShiftID = ? AND SectionID <=> ? AND Day = ? AND SlotID = ? AND SessionID <=> ?";
    $params = [$data['ProgramID'], $data['DepartmentID'], $data['SemesterID'], $data['ShiftID'], $data['SectionID'], $data['Day'], $data['SlotID'], $sess];
    if($ignoreID) { $sql .= " AND TimetableID != ?"; $params[] = $ignoreID; }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn() > 0) {
         return "Rule Seven Failed: Class is already booked at this time slot.";
    }
    return true;
}

// Rule Eight
function checkCreditHoursDistribution($pdo, $data, $ignoreID) {
    if(empty($data['CourseID'])) return true;
    
    $course = $pdo->prepare("SELECT RoomType FROM courses WHERE CourseID = ?");
    $course->execute([$data['CourseID']]);
    if ($course->fetchColumn() == 'Lab') return true; // Skip single day limit for labs
    
    $sess = $data['SessionID'] ?? null;
    $sql = "SELECT COUNT(*) FROM timetable WHERE CourseID = ? AND Day = ? AND SessionID <=> ?";
    $params = [$data['CourseID'], $data['Day'], $sess];
    if($ignoreID) { $sql .= " AND TimetableID != ?"; $params[] = $ignoreID; }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn() > 0) {
         return "Rule Eight Failed: A lecture course cannot appear more than once on the same day.";
    }
    return true;
}

// Rule Nine & Ten
function checkWeeklyLoadLimit($pdo, $data, $ignoreID) {
    if(empty($data['TeacherID'])) return true;
    
    $teacherData = $pdo->prepare("SELECT u.IsHOD, d.MaxWeeklyPeriods, d.HODWeeklyPeriods FROM users u LEFT JOIN designation_workload d ON u.Designation = d.Designation WHERE u.UserID = ?");
    $teacherData->execute([$data['TeacherID']]);
    $t = $teacherData->fetch();
    if(!$t) return true;
    
    $maxLimit = $t['IsHOD'] ? $t['HODWeeklyPeriods'] : $t['MaxWeeklyPeriods'];
    
    $sess = $data['SessionID'] ?? null;
    $sql = "SELECT COUNT(*) FROM timetable WHERE TeacherID = ? AND IsFree = 0 AND SessionID <=> ?";
    $params = [$data['TeacherID'], $sess];
    if($ignoreID) { $sql .= " AND TimetableID != ?"; $params[] = $ignoreID; }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->fetchColumn();
    
    if ($count >= $maxLimit) {
         return "Rule Nine/Ten Failed: Teacher weekly limit ($maxLimit) exceeded.";
    }
    return true;
}

function checkSubstituteEligibility($pdo, $oldTimetableID, $subTeacherID, $fromDate, $toDate) {
    // 1. Fetch exact timetable attributes for target slot
    $ttQ = $pdo->prepare("SELECT Day, SlotID FROM timetable WHERE TimetableID = ?");
    $ttQ->execute([$oldTimetableID]);
    $targetSlot = $ttQ->fetch();
    
    if (!$targetSlot) return "Invalid slot selected.";

    // 2. Check normal timetable clash
    $busyQ = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE TeacherID = ? AND Day = ? AND SlotID = ?");
    $busyQ->execute([$subTeacherID, $targetSlot['Day'], $targetSlot['SlotID']]);
    if ($busyQ->fetchColumn() > 0) {
        return "Rule 11 Failed: Substitute teacher already has a regular class assigned at this Day and Time slot.";
    }

    // 3. Check other active substitute assignments clash during these dates
    $subBusyQ = $pdo->prepare("
        SELECT COUNT(*) FROM substitute_assignments sa
        JOIN timetable t ON sa.TimetableID = t.TimetableID
        WHERE sa.SubstituteTeacherID = ? 
        AND t.Day = ? 
        AND t.SlotID = ?
        AND sa.Status = 'active'
        AND (sa.FromDate <= ? AND sa.ToDate >= ?)
    ");
    $subBusyQ->execute([$subTeacherID, $targetSlot['Day'], $targetSlot['SlotID'], $toDate, $fromDate]);
    
    if ($subBusyQ->fetchColumn() > 0) {
        return "Rule 11 Failed: Substitute teacher is already covering another class at this time during the selected dates.";
    }

    return true; 
}
?>
