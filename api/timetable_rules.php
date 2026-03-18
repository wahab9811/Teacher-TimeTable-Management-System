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

// Rule One & Three
function checkTeacherDailyLimit($pdo, $data, $ignoreID) {
    // Student classes don't have limit, but teachers do. Max 5 teaching periods per shift per day.
    if(empty($data['TeacherID'])) return true;
    
    $sql = "SELECT COUNT(*) FROM timetable WHERE TeacherID = ? AND ShiftID = ? AND Day = ? AND IsFree = 0";
    $params = [$data['TeacherID'], $data['ShiftID'], $data['Day']];
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
    $sql = "SELECT COUNT(*) FROM timetable WHERE TeacherID = ? AND Day = ? AND SlotID = ?";
    $params = [$data['TeacherID'], $data['Day'], $data['SlotID']];
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
    
    $sql = "SELECT COUNT(*) FROM timetable WHERE RoomID = ? AND Day = ? AND SlotID = ?";
    $params = [$data['RoomID'], $data['Day'], $data['SlotID']];
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
    $sql = "SELECT COUNT(*) FROM timetable WHERE ProgramID = ? AND DepartmentID = ? AND SemesterID = ? AND ShiftID = ? AND Day = ? AND SlotID = ?";
    $params = [$data['ProgramID'], $data['DepartmentID'], $data['SemesterID'], $data['ShiftID'], $data['Day'], $data['SlotID']];
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
    
    $sql = "SELECT COUNT(*) FROM timetable WHERE CourseID = ? AND Day = ?";
    $params = [$data['CourseID'], $data['Day']];
    if($ignoreID) { $sql .= " AND TimetableID != ?"; $params[] = $ignoreID; }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn() > 0) {
         return "Rule Eight Failed: A course cannot appear more than once on the same day.";
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
    
    $sql = "SELECT COUNT(*) FROM timetable WHERE TeacherID = ? AND IsFree = 0";
    $params = [$data['TeacherID']];
    if($ignoreID) { $sql .= " AND TimetableID != ?"; $params[] = $ignoreID; }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->fetchColumn();
    
    if ($count >= $maxLimit) {
         return "Rule Nine/Ten Failed: Teacher weekly limit ($maxLimit) exceeded.";
    }
    return true;
}

// Rule Eleven
function checkSubstituteEligibility($pdo, $oldTimetableID, $subTeacherID, $fromDate, $toDate) {
    // 1. Fetch exact timetable attributes for oldTimetableID
    // 2. Map date range to Days
    // 3. Loop through days and use checkTeacherDoubleBooking simulation and load Limit simulation
    // Implemented within Substitute Management Route
    return true; 
}
?>
