<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$dir_path = str_replace('\\', '/', __DIR__);
$base_url = str_replace($doc_root, '', $dir_path);
$base_url = str_replace('/includes', '', $base_url);

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
    <!-- Tailwind CSS (via CDN for development/design specs) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Bootstrap CSS (for modals/components if needed) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">
</head>
<body class="bg-[#fcfdff] min-h-screen flex flex-col font-sans relative">

    <!-- Top Header -->
    <?php if(basename($_SERVER['PHP_SELF']) !== 'login.php'): ?>
    <header class="w-[98%] mx-auto mt-4 bg-white py-4 px-6 md:px-8 rounded-[24px] shadow-[0_2px_15px_rgba(0,0,0,0.02)] border border-gray-100/60 flex flex-col md:flex-row justify-between items-center gap-5 relative z-50">
        <a href="<?php echo $base_url; ?>/index.php" class="flex items-center gap-3">
            <img src="<?php echo $base_url; ?>/assets/img/logo.png" alt="GCB Logo" class="h-[46px] w-[46px] object-contain" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\' fill=\'none\' stroke=\'%23a60b26\' stroke-width=\'3\'><circle cx=\'50\' cy=\'50\' r=\'40\'/><path d=\'M50 20v60M30 40h40M30 60h40\'/></svg>';">
            <div class="flex flex-col leading-snug mt-0.5">
                <span class="text-gray-900 font-bold text-[15px]">Govt. Graduate College,</span>
                <span class="text-gray-900 font-bold text-[15px]">Civil Lines, Sheikhupura</span>
            </div>
        </a>
        <div class="flex flex-wrap justify-center items-center gap-6 md:gap-8">
            <?php if(isset($_SESSION['user_id'])): ?>
                <span class="text-[14px] text-gray-800 font-medium">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
                <?php if($_SESSION['role'] === 'admin'): ?>
                    <a href="<?php echo $base_url; ?>/admin/dashboard.php" class="text-gray-600 font-semibold text-[14px] hover:text-[#a60b26] transition-colors">Admin Dashboard</a>
                <?php else: ?>
                    <a href="<?php echo $base_url; ?>/teacher/dashboard.php" class="text-gray-600 font-semibold text-[14px] hover:text-[#a60b26] transition-colors">My Dashboard</a>
                <?php endif; ?>
                <a href="<?php echo $base_url; ?>/api/auth.php?action=logout" class="bg-[#8a0a20] text-white px-5 py-2.5 rounded-xl text-[14px] font-bold hover:bg-[#6c0819] transition-all duration-300 shadow-sm">Logout</a>
            <?php else: ?>
                <!-- Home -->
                <a href="<?php echo $base_url; ?>/index.php" class="group relative flex items-center justify-center text-[14.5px] font-bold <?= basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == '' ? 'text-gray-900' : 'text-gray-500 hover:text-gray-900' ?> transition-colors pb-1.5">
                    <div class="flex items-center gap-2">
                        <svg class="w-[18px] h-[18px] <?= basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == '' ? 'text-[#a60b26]' : 'text-gray-400 group-hover:text-gray-600' ?> transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        <span>Home</span>
                    </div>
                    <?php if(basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == ''): ?>
                        <div class="absolute bottom-0 w-[100%] h-[2.5px] bg-[#a60b26] rounded-full left-1/2 -translate-x-1/2"></div>
                    <?php endif; ?>
                </a>
                
                <!-- Calendar -->
                <a href="<?php echo $base_url; ?>/calendar.php" class="group relative flex items-center justify-center text-[14.5px] font-bold <?= basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'text-gray-900' : 'text-gray-500 hover:text-gray-900' ?> transition-colors pb-1.5">
                    <div class="flex items-center gap-2">
                        <svg class="w-[18px] h-[18px] <?= basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'text-[#a60b26]' : 'text-gray-400 group-hover:text-gray-600' ?> transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span>Calendar</span>
                    </div>
                    <?php if(basename($_SERVER['PHP_SELF']) == 'calendar.php'): ?>
                        <div class="absolute bottom-0 w-[100%] h-[2.5px] bg-[#a60b26] rounded-full left-1/2 -translate-x-1/2"></div>
                    <?php endif; ?>
                </a>

                <!-- About -->
                <a href="<?php echo $base_url; ?>/about.php" class="group relative flex items-center justify-center text-[14.5px] font-bold <?= basename($_SERVER['PHP_SELF']) == 'about.php' ? 'text-gray-900' : 'text-gray-500 hover:text-gray-900' ?> transition-colors pb-1.5">
                    <div class="flex items-center gap-2">
                        <svg class="w-[18px] h-[18px] <?= basename($_SERVER['PHP_SELF']) == 'about.php' ? 'text-[#a60b26]' : 'text-gray-400 group-hover:text-gray-600' ?> transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>About</span>
                    </div>
                    <?php if(basename($_SERVER['PHP_SELF']) == 'about.php'): ?>
                        <div class="absolute bottom-0 w-[100%] h-[2.5px] bg-[#a60b26] rounded-full left-1/2 -translate-x-1/2"></div>
                    <?php endif; ?>
                </a>

                <!-- Contact -->
                <a href="<?php echo $base_url; ?>/contact.php" class="group relative flex items-center justify-center text-[14.5px] font-bold <?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'text-gray-900' : 'text-gray-500 hover:text-gray-900' ?> transition-colors pb-1.5">
                    <div class="flex items-center gap-2">
                        <svg class="w-[18px] h-[18px] <?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'text-[#a60b26]' : 'text-gray-400 group-hover:text-gray-600' ?> transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        <span>Contact</span>
                    </div>
                    <?php if(basename($_SERVER['PHP_SELF']) == 'contact.php'): ?>
                        <div class="absolute bottom-0 w-[100%] h-[2.5px] bg-[#a60b26] rounded-full left-1/2 -translate-x-1/2"></div>
                    <?php endif; ?>
                </a>

                <!-- Staff Login Button -->
                <a href="<?php echo $base_url; ?>/login.php" class="ml-2 bg-[#8a0a20] text-white font-bold text-[14.5px] px-6 py-[9.5px] rounded-xl transition-all duration-300 hover:bg-[#6c0819] hover:shadow-md flex items-center gap-2">
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    Staff Login
                </a>
            <?php endif; ?>
        </div>
    </header>
    <?php endif; ?>

    <!-- Main Content Wrapper -->
    <main class="flex-grow p-6">
