<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';
$teacherId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT s.*, 
           u1.Name as Orig, u2.Name as Sub, 
           t.Day, ts.PeriodNumber, 
           c.Name as CN, sec.Name as SectionName 
    FROM substitute_assignments s 
    JOIN users u1 ON s.OriginalTeacherID=u1.UserID 
    JOIN users u2 ON s.SubstituteTeacherID=u2.UserID 
    JOIN timetable t ON s.TimetableID=t.TimetableID 
    JOIN time_slots ts ON t.SlotID=ts.SlotID 
    LEFT JOIN courses c ON t.CourseID=c.CourseID 
    LEFT JOIN sections sec ON t.SectionID=sec.SectionID 
    WHERE s.OriginalTeacherID=? OR s.SubstituteTeacherID=? 
    ORDER BY s.CreatedAt DESC
");
$stmt->execute([$teacherId, $teacherId]);
$subs = $stmt->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/teacher_sidebar.php'; ?>
    
    <div class="flex-1">
        <h2 class="text-2xl font-bold text-maroon mb-6 border-b pb-2">Substitute History</h2>
        
        <div class="bg-white p-6 shadow-md rounded border border-gray-100">
            <p class="text-gray-600 mb-6 bg-blue-50 p-3 rounded text-sm border border-blue-100">
                <i class="fas fa-info-circle text-blue-500 mr-2"></i> This record shows classes you took as a substitute for others, as well as classes where someone else substituted for you during your leave.
            </p>

            <?php if(empty($subs)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-user-friends text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500 font-medium">No substitute history found.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach($subs as $s): 
                        $isMySub = ($s['OriginalTeacherID'] == $teacherId); 
                        $isActive = ($s['Status'] == 'active');
                    ?>
                    <div class="border rounded-lg p-5 flex flex-col md:flex-row justify-between items-center bg-gray-50 shadow-sm hover:shadow-md transition-shadow <?php echo $isMySub ? 'border-l-4 border-l-blue-500' : 'border-l-4 border-l-green-500'; ?>">
                        
                        <div class="flex-1 z-10 w-full mb-4 md:mb-0">
                            <div class="flex items-center mb-2">
                                <?php if($isMySub): ?>
                                    <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded font-bold uppercase tracking-wide mr-2">My Leave</span>
                                    <h4 class="font-bold text-gray-800"><b><?php echo htmlspecialchars($s['Sub']); ?></b> substituted for me</h4>
                                <?php else: ?>
                                    <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded font-bold uppercase tracking-wide mr-2">Assigned Duty</span>
                                    <h4 class="font-bold text-gray-800">I substituted for <b><?php echo htmlspecialchars($s['Orig']); ?></b></h4>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-sm text-gray-600 mt-2 space-y-1">
                                <p><i class="fas fa-calendar-alt w-5 text-gray-400"></i> <b>Dates:</b> <?php echo date('d M Y', strtotime($s['FromDate'])) . ' - ' . date('d M Y', strtotime($s['ToDate'])); ?></p>
                                <p><i class="fas fa-clock w-5 text-gray-400"></i> <b>Period:</b> <?php echo $s['Day'] . ' P' . $s['PeriodNumber']; ?></p>
                                <p><i class="fas fa-book w-5 text-gray-400"></i> <b>Course:</b> <?php echo htmlspecialchars($s['CN']); ?> 
                                    <span class="bg-white border rounded px-1.5 py-0.5 ml-2 font-medium">Sec: <?php echo htmlspecialchars($s['SectionName'] ?? 'N/A'); ?></span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex flex-col items-end w-full md:w-32">
                            <span class="px-3 py-1 rounded-full text-xs font-bold shadow-sm 
                                <?php echo $isActive ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'; ?>">
                                <?php echo $isActive ? 'Active Duty' : 'Completed'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
