<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$dir_path = str_replace('\\', '/', __DIR__);
$base_url = str_replace($doc_root, '', $dir_path);
$base_url = str_replace('/includes', '', $base_url);

require_once __DIR__ . '/../config/db.php';
$stmtGlobal = $pdo->prepare("SELECT Message FROM notifications WHERE ScopeType = 'global' AND ExpiryDate IS NOT NULL AND NOW() <= ExpiryDate ORDER BY CreatedAt DESC");
$stmtGlobal->execute();
$globalNotices = $stmtGlobal->fetchAll(PDO::FETCH_COLUMN);
$activeGlobalNotice = empty($globalNotices) ? '' : implode(" &nbsp;&nbsp;&nbsp;&bull;&nbsp;&nbsp;&nbsp; ", array_map('htmlspecialchars', $globalNotices));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCB SKP Teacher Timetable Management System</title>
    <!-- Tailwind CSS Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { maroon: '#800000' }
                }
            }
        }
    </script>
    <!-- Tailwind CSS (Local for offline support) -->
    <script src="<?php echo $base_url; ?>/assets/vendor/tailwindcss.js"></script>
    <!-- Bootstrap CSS (Local for offline support) -->
    <link href="<?php echo $base_url; ?>/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">
</head>
<body class="bg-[#fcfdff] min-h-screen flex flex-col font-sans relative">

    <?php if(!in_array(basename($_SERVER['PHP_SELF']), ['login.php', 'forgot_password.php', 'reset_password.php'])): ?>
    <!-- Global Announcement Banner -->
    <?php if(!empty($activeGlobalNotice) && (basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == '' || (basename($_SERVER['PHP_SELF']) == 'dashboard.php' && strpos($_SERVER['PHP_SELF'], '/teacher/') !== false))): ?>
    <div id="global-announcement-banner" class="bg-red-600 text-white w-full py-2.5 px-4 z-[100] relative shadow-md flex items-center gap-3 overflow-hidden">
        <svg class="w-5 h-5 flex-shrink-0 hidden md:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
        <?php if(count($globalNotices) > 1): ?>
        <marquee class="flex-1 text-sm font-bold leading-snug cursor-pointer" scrollamount="6" onmouseover="this.stop();" onmouseout="this.start();">
            <?= $activeGlobalNotice ?>
        </marquee>
        <?php else: ?>
        <span class="flex-1 text-sm font-bold leading-snug text-center">
            <?= $activeGlobalNotice ?>
        </span>
        <?php endif; ?>
        <button onclick="document.getElementById('global-announcement-banner').style.display='none'" class="flex-shrink-0 p-1 hover:bg-red-700 rounded transition-colors focus:outline-none ml-2" title="Dismiss">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
    <?php endif; ?>
    <!-- Top Header -->
    <header class="w-full bg-white py-4 px-6 md:px-8 border-b border-gray-200 flex flex-col md:flex-row justify-between items-center gap-5 relative z-50">
        <a href="<?php echo $base_url; ?>/index.php" class="flex items-center gap-3">
            <img src="<?php echo $base_url; ?>/assets/img/logo.png" alt="GCB Logo" class="h-[46px] w-[46px] object-contain" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\' fill=\'none\' stroke=\'%23a60b26\' stroke-width=\'3\'><circle cx=\'50\' cy=\'50\' r=\'40\'/><path d=\'M50 20v60M30 40h40M30 60h40\'/></svg>';">
            <div class="flex flex-col leading-snug mt-0.5">
                <span class="text-gray-900 font-bold text-[15px]">Govt. Graduate College,</span>
                <span class="text-gray-900 font-bold text-[15px]">Civil Lines, Sheikhupura</span>
            </div>
        </a>
        <div class="flex flex-wrap justify-center items-center gap-6 md:gap-8">
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php if($_SESSION['role'] !== 'admin'): ?>
                <?php
                // Teacher Header Notification Logic
                $navTeacherBadgeTotal = 0;
                $navTeacherInbox = 0;
                $navTeacherAlerts = 0;
                if(isset($pdo)) {
                    $navT_id = $_SESSION['user_id'] ?? 0;
                    $navTeacherInbox = $pdo->query("SELECT COUNT(*) FROM requests WHERE TargetTeacherID = $navT_id AND Status = 'pending_teacher' AND TeacherBStatus='pending'")->fetchColumn();
                    $navTeacherAlerts = $pdo->query("SELECT COUNT(*) FROM notifications WHERE TeacherID = $navT_id AND IsRead = 0") ? $pdo->query("SELECT COUNT(*) FROM notifications WHERE TeacherID = $navT_id AND IsRead = 0")->fetchColumn() : 0;
                    $navTeacherBadgeTotal = $navTeacherInbox + $navTeacherAlerts;
                }
                ?>
                <a href="<?php echo $base_url; ?>/teacher/dashboard.php" class="text-[14.5px] font-bold text-[#a60b26] hover:text-[#8a0a20] transition-colors hover:underline underline-offset-4 decoration-2">Go to Dashboard</a>
                
                <div class="relative flex items-center ml-2 mr-2">
                    <button onclick="document.getElementById('teacherNotifDropdown').classList.toggle('hidden')" class="relative text-gray-500 hover:text-[#a60b26] transition-colors focus:outline-none" title="Notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 22a2 2 0 002-2H10a2 2 0 002 2zm6-6V10a6 6 0 10-12 0v6l-2 2v1h16v-1l-2-2z" />
                        </svg>
                        <?php if($navTeacherBadgeTotal > 0): ?>
                            <span class="absolute -top-1.5 -right-1.5 flex h-[18px] w-[18px] items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white border-2 border-white">
                                <?php echo $navTeacherBadgeTotal > 99 ? '99+' : $navTeacherBadgeTotal; ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <!-- Dropdown Menu -->
                    <div id="teacherNotifDropdown" class="hidden absolute right-0 top-[140%] mt-1 w-64 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-[100] transform origin-top-right transition-all">
                        <div class="px-4 py-2 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 rounded-t-xl">
                            <span class="font-bold text-gray-800 text-sm">Notifications</span>
                            <span class="text-xs bg-red-100 text-red-600 font-bold px-2 py-0.5 rounded-full"><?php echo $navTeacherBadgeTotal; ?> New</span>
                        </div>
                        
                        <?php if($navTeacherBadgeTotal == 0): ?>
                            <div class="px-4 py-6 text-center text-sm text-gray-400 font-bold">
                                You're all caught up!
                            </div>
                        <?php else: ?>
                            <div class="py-1">
                                <?php if($navTeacherInbox > 0): ?>
                                <a href="<?php echo $base_url; ?>/teacher/inbox.php" class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 hover:text-[#a60b26] transition-colors border-b border-gray-100 last:border-0">
                                    <?php echo $navTeacherInbox; ?> Pending Action(s) received
                                </a>
                                <?php endif; ?>
                                
                                <?php if($navTeacherAlerts > 0): ?>
                                <a href="<?php echo $base_url; ?>/teacher/notifications.php" class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 hover:text-[#a60b26] transition-colors border-b border-gray-100 last:border-0">
                                    <?php echo $navTeacherAlerts; ?> New System Alert(s)
                                </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Close dropdown when clicking outside -->
                <script>
                    document.addEventListener('click', function(event) {
                        const tDropdown = document.getElementById('teacherNotifDropdown');
                        if (tDropdown && !tDropdown.classList.contains('hidden') && !tDropdown.contains(event.target) && !tDropdown.previousElementSibling.contains(event.target)) {
                            tDropdown.classList.add('hidden');
                        }
                    });
                </script>

                <span class="text-[14px] text-gray-800 font-medium pl-4 border-l border-gray-300">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
                <?php else: ?>
                    <?php
                    $navAdminBadgeTotal = 0;
                    if(isset($pdo)) {
                        $navScheduleReqs = $pdo->query("SELECT COUNT(*) FROM requests WHERE Status = 'pending_admin'")->fetchColumn();
                        $navLeaveReqs = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE Status = 'pending'")->fetchColumn();
                        $navReqs = $navScheduleReqs + $navLeaveReqs;
                        
                        $navReps = $pdo->query("SELECT COUNT(*) FROM student_reports WHERE Status = 'pending'")->fetchColumn();
                        $navNotifs = $pdo->query("SELECT COUNT(*) FROM notifications WHERE ScopeType = 'admin' AND IsRead = 0") ? $pdo->query("SELECT COUNT(*) FROM notifications WHERE ScopeType = 'admin' AND IsRead = 0")->fetchColumn() : 0;
                        $navAdminBadgeTotal = $navReqs + $navReps + $navNotifs;
                    }
                    ?>
                    <div class="relative flex items-center ml-4 mr-2">
                        <button onclick="document.getElementById('adminNotifDropdown').classList.toggle('hidden')" class="relative text-gray-500 hover:text-[#a60b26] transition-colors focus:outline-none" title="Notifications">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 22a2 2 0 002-2H10a2 2 0 002 2zm6-6V10a6 6 0 10-12 0v6l-2 2v1h16v-1l-2-2z" />
                            </svg>
                            <?php if($navAdminBadgeTotal > 0): ?>
                                <span class="absolute -top-1.5 -right-1.5 flex h-[18px] w-[18px] items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white border-2 border-white">
                                    <?php echo $navAdminBadgeTotal > 99 ? '99+' : $navAdminBadgeTotal; ?>
                                </span>
                            <?php endif; ?>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="adminNotifDropdown" class="hidden absolute right-0 top-[140%] mt-1 w-64 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-[100] transform origin-top-right transition-all">
                            <div class="px-4 py-2 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 rounded-t-xl">
                                <span class="font-bold text-gray-800 text-sm">Notifications</span>
                                <span class="text-xs bg-red-100 text-red-600 font-bold px-2 py-0.5 rounded-full"><?php echo $navAdminBadgeTotal; ?> New</span>
                            </div>
                            
                            <?php if($navAdminBadgeTotal == 0): ?>
                                <div class="px-4 py-6 text-center text-sm text-gray-400 font-bold">
                                    You're all caught up!
                                </div>
                            <?php else: ?>
                                <div class="py-1">
                                    <?php if($navScheduleReqs > 0): ?>
                                    <a href="<?php echo $base_url; ?>/admin/requests.php" class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 hover:text-[#a60b26] transition-colors border-b border-gray-100 last:border-0">
                                        <?php echo $navScheduleReqs; ?> Pending Schedule Request(s)
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if($navLeaveReqs > 0): ?>
                                    <a href="<?php echo $base_url; ?>/admin/requests.php?tab=leave" class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 hover:text-[#a60b26] transition-colors border-b border-gray-100 last:border-0">
                                        <?php echo $navLeaveReqs; ?> Pending Leave Request(s)
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if($navReps > 0): ?>
                                    <a href="<?php echo $base_url; ?>/admin/reports.php" class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 hover:text-[#a60b26] transition-colors border-b border-gray-100 last:border-0">
                                        <?php echo $navReps; ?> Unresolved Student Report(s)
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if($navNotifs > 0): ?>
                                    <div class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 transition-colors border-b border-gray-100 last:border-0 cursor-pointer">
                                        <?php echo $navNotifs; ?> New System Alert(s)
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Close dropdown when clicking outside -->
                    <script>
                        document.addEventListener('click', function(event) {
                            const dropdown = document.getElementById('adminNotifDropdown');
                            const button = dropdown.previousElementSibling;
                            if (dropdown && !dropdown.classList.contains('hidden') && !dropdown.contains(event.target) && !button.contains(event.target)) {
                                dropdown.classList.add('hidden');
                            }
                        });
                    </script>
                    <span class="text-[14px] text-gray-800 font-medium pl-4 border-l border-gray-300">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
                <?php endif; ?>
                <a href="<?php echo $base_url; ?>/api/auth.php?action=logout" class="bg-[#8a0a20] text-white px-5 py-2.5 rounded-xl text-[14px] font-bold hover:bg-[#6c0819] transition-all duration-300 shadow-sm">Logout</a>
            <?php else: ?>
                <!-- Home -->
                <a href="<?php echo $base_url; ?>/index.php" class="group relative flex items-center justify-center text-[14.5px] font-bold <?= basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == '' ? 'text-gray-900' : 'text-gray-500 hover:text-gray-900' ?> transition-colors pb-1.5">
                    <div class="flex items-center gap-2">
                        <span>Home</span>
                    </div>
                    <?php if(basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == ''): ?>
                        <div class="absolute bottom-0 w-[100%] h-[2.5px] bg-[#a60b26] rounded-full left-1/2 -translate-x-1/2"></div>
                    <?php endif; ?>
                </a>
                
                <!-- Calendar -->
                <a href="<?php echo $base_url; ?>/calendar.php" class="group relative flex items-center justify-center text-[14.5px] font-bold <?= basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'text-gray-900' : 'text-gray-500 hover:text-gray-900' ?> transition-colors pb-1.5">
                    <div class="flex items-center gap-2">
                        <span>Calendar</span>
                    </div>
                    <?php if(basename($_SERVER['PHP_SELF']) == 'calendar.php'): ?>
                        <div class="absolute bottom-0 w-[100%] h-[2.5px] bg-[#a60b26] rounded-full left-1/2 -translate-x-1/2"></div>
                    <?php endif; ?>
                </a>

                <!-- About -->
                <a href="<?php echo $base_url; ?>/about.php" class="group relative flex items-center justify-center text-[14.5px] font-bold <?= basename($_SERVER['PHP_SELF']) == 'about.php' ? 'text-gray-900' : 'text-gray-500 hover:text-gray-900' ?> transition-colors pb-1.5">
                    <div class="flex items-center gap-2">
                        <span>About</span>
                    </div>
                    <?php if(basename($_SERVER['PHP_SELF']) == 'about.php'): ?>
                        <div class="absolute bottom-0 w-[100%] h-[2.5px] bg-[#a60b26] rounded-full left-1/2 -translate-x-1/2"></div>
                    <?php endif; ?>
                </a>

                <!-- Contact -->
                <a href="<?php echo $base_url; ?>/contact.php" class="group relative flex items-center justify-center text-[14.5px] font-bold <?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'text-gray-900' : 'text-gray-500 hover:text-gray-900' ?> transition-colors pb-1.5">
                    <div class="flex items-center gap-2">
                        <span>Contact</span>
                    </div>
                    <?php if(basename($_SERVER['PHP_SELF']) == 'contact.php'): ?>
                        <div class="absolute bottom-0 w-[100%] h-[2.5px] bg-[#a60b26] rounded-full left-1/2 -translate-x-1/2"></div>
                    <?php endif; ?>
                </a>

                <!-- Staff Login Button -->
                <a href="<?php echo $base_url; ?>/login.php" class="ml-2 bg-[#8a0a20] text-white font-bold text-[14.5px] px-6 py-[9.5px] rounded-xl transition-all duration-300 hover:bg-[#6c0819] hover:shadow-md flex items-center gap-2">
                    Staff Login
                </a>
            <?php endif; ?>
        </div>
    </header>
    <?php endif; ?>

    <!-- Main Content Wrapper -->
    <main class="flex-grow p-6">
