<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: /login.php"); exit; }
require_once __DIR__ . '/../config/db.php';

$message = '';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['update'])) {
        $st = $_POST['st']; $et = $_POST['et'];
        $fst = $_POST['fst']; $fet = $_POST['fet'];
        
        if (strtotime($et) <= strtotime($st)) {
            $message = "Error: Regular End Time must be strictly after Start Time.";
        } elseif (strtotime($fet) <= strtotime($fst)) {
            $message = "Error: Friday End Time must be strictly after Start Time.";
        } else {
            $pdo->prepare("UPDATE time_slots SET StartTime=?, EndTime=?, FridayStartTime=?, FridayEndTime=? WHERE SlotID=?")
                ->execute([$st, $et, $fst, $fet, $_POST['id']]);
            $message = "Time slot and Friday timings updated successfully.";
        }
    } elseif(isset($_POST['add_slot'])) {
        $shiftId = $_POST['shift_id'];
        $maxPer = $pdo->prepare("SELECT MAX(PeriodNumber) FROM time_slots WHERE ShiftID = ?");
        $maxPer->execute([$shiftId]);
        $nextPeriod = ($maxPer->fetchColumn() ?: 0) + 1;
        
        $pdo->prepare("INSERT INTO time_slots (ShiftID, PeriodNumber, StartTime, EndTime, FridayStartTime, FridayEndTime) VALUES (?, ?, '00:00', '00:00', '00:00', '00:00')")->execute([$shiftId, $nextPeriod]);
        $message = "New blank period added. Please set the timings and click Save.";
    } elseif(isset($_POST['delete_slot'])) {
        $slotId = $_POST['id'];
        $pdo->prepare("DELETE FROM time_slots WHERE SlotID = ?")->execute([$slotId]);
        $message = "Time slot securely removed.";
    }
}

$slots = $pdo->query("
    SELECT ts.*, s.Name as ShiftName 
    FROM time_slots ts 
    JOIN shifts s ON ts.ShiftID = s.ShiftID 
    ORDER BY ts.ShiftID, ts.PeriodNumber
")->fetchAll();

$groupedSlots = [];
foreach($slots as $s) {
    if(!isset($groupedSlots[$s['ShiftName']])) $groupedSlots[$s['ShiftName']] = [];
    $groupedSlots[$s['ShiftName']][] = $s;
}
?>
<?php include '../includes/header.php'; ?>
<div class="w-full px-2 md:px-8 mx-auto flex gap-6 mt-4 pb-12">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="flex-1 bg-white p-6 shadow-md rounded-xl border border-gray-100">
        
        <div class="flex justify-between items-center mb-6 border-b pb-4">
            <div>
                <h2 class="text-2xl font-bold text-[#a60b26]">Class Timing & Shifts</h2>
                <p class="text-gray-500 text-sm mt-1">Configure both the regular bell timings and shortened Friday timings.</p>
            </div>
        </div>

        <?php if($message): ?>
            <div class="bg-green-100 text-green-800 p-3 rounded-lg mb-6 font-semibold text-sm border border-green-200">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="space-y-8">
            <?php foreach($groupedSlots as $shiftName => $shiftSlots): ?>
            <div class="bg-gray-50 rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                <div class="bg-gradient-to-r from-gray-200 to-gray-100 p-4 border-b border-gray-300">
                    <h3 class="font-black text-gray-800 uppercase tracking-widest text-sm flex items-center gap-2">
                        <?php echo htmlspecialchars($shiftName); ?> Shift Timings
                    </h3>
                </div>
                
                <div class="p-4 flex flex-col gap-3 overflow-x-auto">
                    <div class="min-w-[800px]">
                        <div class="grid grid-cols-12 gap-2 text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 border-b pb-2">
                            <div class="col-span-1 text-center">Period</div>
                            <div class="col-span-10 grid grid-cols-2 gap-6">
                                <div class="text-center">Regular (Mon-Thu, Sat)</div>
                                <div class="text-center">Friday (Jummah Short Timings)</div>
                            </div>
                            <div class="col-span-1 text-center">Save</div>
                        </div>
                        
                        <?php foreach($shiftSlots as $s): ?>
                        <form method="POST" class="grid grid-cols-12 gap-2 items-center bg-white border border-gray-200 p-2 rounded-lg hover:border-gray-400 focus-within:border-[#a60b26] focus-within:ring-1 focus-within:ring-[#a60b26] transition-all mb-2">
                            <input type="hidden" name="id" value="<?php echo $s['SlotID']; ?>">
                            
                            <div class="col-span-1 text-center font-bold text-gray-700 text-sm">
                                <?php echo $s['PeriodNumber']; ?>
                            </div>
                            
                            <div class="col-span-10 grid grid-cols-2 gap-6 border-l pl-4">
                                <!-- Regular -->
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-400 uppercase font-bold w-10">Start:</span>
                                    <input type="time" name="st" value="<?php echo $s['StartTime']; ?>" required class="w-full bg-indigo-50/50 font-semibold text-gray-700 outline-none text-sm cursor-pointer p-1.5 rounded focus:bg-white border border-transparent focus:border-indigo-300">
                                    <span class="text-gray-300">-</span>
                                    <span class="text-xs text-gray-400 uppercase font-bold w-10 text-right">End:</span>
                                    <input type="time" name="et" value="<?php echo $s['EndTime']; ?>" required class="w-full bg-indigo-50/50 font-semibold text-gray-700 outline-none text-sm cursor-pointer p-1.5 rounded focus:bg-white border border-transparent focus:border-indigo-300">
                                </div>
                                
                                <!-- Friday -->
                                <div class="flex items-center gap-2 border-l border-emerald-100 pl-4">
                                    <span class="text-xs text-gray-400 uppercase font-bold w-10">Start:</span>
                                    <input type="time" name="fst" value="<?php echo $s['FridayStartTime'] ?? $s['StartTime']; ?>" required class="w-full bg-emerald-50/50 font-semibold text-gray-700 outline-none text-sm cursor-pointer p-1.5 rounded focus:bg-white border border-transparent focus:border-emerald-300">
                                    <span class="text-gray-300">-</span>
                                    <span class="text-xs text-gray-400 uppercase font-bold w-10 text-right">End:</span>
                                    <input type="time" name="fet" value="<?php echo $s['FridayEndTime'] ?? $s['EndTime']; ?>" required class="w-full bg-emerald-50/50 font-semibold text-gray-700 outline-none text-sm cursor-pointer p-1.5 rounded focus:bg-white border border-transparent focus:border-emerald-300">
                                </div>
                            </div>
                            
                            <div class="col-span-1 text-center mt-2 md:mt-0 flex gap-2 justify-center items-center h-full">
                                <button name="update" type="submit" class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-green-500/30" title="Save Timings">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                </button>
                                <button name="delete_slot" type="submit" onclick="return confirm('Are you sure you want to permanently delete this period?')" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-red-500/30" title="Delete Period">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </form>
                        <?php endforeach; ?>
                        
                        <div class="mt-4 flex justify-end">
                            <form method="POST">
                                <input type="hidden" name="shift_id" value="<?php echo $shiftSlots[0]['ShiftID']; ?>">
                                <button type="submit" name="add_slot" class="bg-[#a60b26] hover:bg-[#8a0a20] text-white px-4 py-2.5 rounded-lg font-bold text-sm shadow-sm transition">
                                    Add New Period
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
    </div>
</div>
