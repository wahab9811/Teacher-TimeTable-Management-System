<?php
require_once __DIR__ . '/../config/db.php';
// This assumes it's called via cron or task scheduler, or a lightweight include in header.php

// Automation One — Swap Deadline Auto Cancel
$now = date('Y-m-d H:i:s');
$stmtSwap = $pdo->prepare("SELECT * FROM requests WHERE Type = 'swap' AND Status = 'pending_teacher' AND TeacherBStatus = 'pending' AND Deadline < ?");
$stmtSwap->execute([$now]);
$expiredSwaps = $stmtSwap->fetchAll();

foreach($expiredSwaps as $swap) {
    // Update status
    $pdo->prepare("UPDATE requests SET Status = 'cancelled' WHERE RequestID = ?")->execute([$swap['RequestID']]);
    
    // Notify Teacher A
    $msgA = "Swap request automatically cancelled. Teacher B did not respond within 48 hours.";
    $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$swap['RequestedBy'], $msgA]);
    
    // Notify Teacher B
    if($swap['TargetTeacherID']) {
        $msgB = "Swap request cancelled because no response was given within the deadline.";
        $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$swap['TargetTeacherID'], $msgB]);
    }
}

// Automation Two — Substitute Auto Expiry
$today = date('Y-m-d');
$stmtSub = $pdo->prepare("SELECT * FROM substitute_assignments WHERE Status = 'active' AND ToDate < ?");
$stmtSub->execute([$today]);
$expiredSubs = $stmtSub->fetchAll();

foreach($expiredSubs as $sub) {
    $pdo->prepare("UPDATE substitute_assignments SET Status = 'expired' WHERE SubstituteID = ?")->execute([$sub['SubstituteID']]);
    
    $msgOrig = "Your substitute period has ended and your timetable is restored.";
    $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$sub['OriginalTeacherID'], $msgOrig]);
    
    $msgSub = "Your substitution assignment has ended.";
    $pdo->prepare("INSERT INTO notifications (ScopeType, TeacherID, Message) VALUES ('teacher', ?, ?)")->execute([$sub['SubstituteTeacherID'], $msgSub]);
}
?>
