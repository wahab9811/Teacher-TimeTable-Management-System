<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action == 'get_class_courses') {
    $p = $_GET['p'] ?? 0;
    $d = $_GET['d'] ?? 0;
    $s = $_GET['s'] ?? 0;
    $sh = $_GET['sh'] ?? 0;
    $sec = isset($_GET['sec']) && $_GET['sec'] !== '' ? $_GET['sec'] : null;
    
    if ($sec) {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND (SectionID=? OR SectionID IS NULL)");
        $stmt->execute([$p, $d, $s, $sh, $sec]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE ProgramID=? AND DepartmentID=? AND SemesterID=? AND ShiftID=? AND SectionID IS NULL");
        $stmt->execute([$p, $d, $s, $sh]);
    }
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action == 'get_existing_timetable') {
    $sess = $_GET['sess'] ?? 0;
    $p = $_GET['p'] ?? 0;
    $d = $_GET['d'] ?? 0;
    $s = $_GET['s'] ?? 0;
    $sh = $_GET['sh'] ?? 0;
    $sec = isset($_GET['sec']) && $_GET['sec'] !== '' ? $_GET['sec'] : null;
    $day = $_GET['day'] ?? '';

    if ($sec) {
        $stmt = $pdo->prepare("
            SELECT t.*, ts.PeriodNumber 
            FROM timetable t
            JOIN time_slots ts ON t.SlotID = ts.SlotID
            WHERE t.ProgramID=? AND t.DepartmentID=? AND t.SemesterID=? AND t.ShiftID=? AND t.SessionID=? AND t.SectionID=? AND t.Day=?
        ");
        $stmt->execute([$p, $d, $s, $sh, $sess, $sec, $day]);
    } else {
        $stmt = $pdo->prepare("
            SELECT t.*, ts.PeriodNumber 
            FROM timetable t
            JOIN time_slots ts ON t.SlotID = ts.SlotID
            WHERE t.ProgramID=? AND t.DepartmentID=? AND t.SemesterID=? AND t.ShiftID=? AND t.SessionID=? AND t.SectionID IS NULL AND t.Day=?
        ");
        $stmt->execute([$p, $d, $s, $sh, $sess, $day]);
    }
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
?>
