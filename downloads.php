<?php
require_once __DIR__ . '/config/db.php';

// Fetch active documents
$downloads = $pdo->query("SELECT * FROM downloads WHERE IsActive = 1 ORDER BY CreatedAt DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include 'includes/header.php'; ?>

<!-- Decorative Dots Pattern Background -->
<div class="absolute inset-0 pointer-events-none flex justify-between z-0 overflow-hidden" style="opacity: 0.4;">
    <div class="w-[200px] h-[300px] mt-32 -ml-10" style="background-image: radial-gradient(#a60b26 1.5px, transparent 1.5px); background-size: 20px 20px;"></div>
    <div class="w-[200px] h-[300px] mt-[400px] -mr-10" style="background-image: radial-gradient(#a60b26 1.5px, transparent 1.5px); background-size: 20px 20px;"></div>
</div>

<!-- Decorative Soft Shape -->
<div class="fixed top-20 right-0 w-[500px] h-[500px] bg-red-50 rounded-full blur-[100px] pointer-events-none opacity-50 z-0 transform translate-x-1/3"></div>

<div class="max-w-[1024px] w-[88%] mx-auto mt-10 mb-16 relative z-10">

    <div class="text-center mb-12">
        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight leading-tight mb-3">Official <span class="text-maroon">Downloads</span></h2>
        <p class="text-gray-500 text-[15px] md:text-base">Download important forms, sheets, and documents</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($downloads as $d): 
            $ext = strtolower(pathinfo($d['FilePath'], PATHINFO_EXTENSION));
        ?>
            <div class="bg-white p-6 rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.04)] border border-gray-100 hover:shadow-[0_8px_30px_rgba(0,0,0,0.08)] transition-all duration-300 flex flex-col items-center text-center group">
                <div class="w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center mb-5 group-hover:bg-red-50 transition-colors">
                    <?php if($ext == 'pdf'): ?>
                        <svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" /></svg>
                    <?php else: ?>
                        <svg class="w-8 h-8 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" /></svg>
                    <?php endif; ?>
                </div>
                
                <h3 class="font-bold text-gray-900 text-[17px] leading-snug mb-2 flex-grow flex items-center justify-center">
                    <?php echo htmlspecialchars($d['Title']); ?>
                </h3>
                <p class="text-[12px] text-gray-400 font-medium mb-6">Added: <?php echo date('M d, Y', strtotime($d['CreatedAt'])); ?></p>
                
                <a href="<?php echo htmlspecialchars($d['FilePath']); ?>" target="_blank" class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl border-2 border-maroon text-maroon font-bold text-[14px] hover:bg-maroon hover:text-white transition-all">
                    <svg class="w-4 h-4 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Download File
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if(empty($downloads)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center max-w-2xl mx-auto">
            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-5">
                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">No Documents Available</h3>
            <p class="text-gray-500 text-[15px]">There are currently no files available for download. Please check back later.</p>
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
