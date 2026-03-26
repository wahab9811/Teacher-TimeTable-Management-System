<?php
require_once __DIR__ . '/config/db.php';

// Fetch active notices safely
$notices = $pdo->query("SELECT * FROM notices WHERE IsActive = 1 ORDER BY CreatedAt DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include 'includes/header.php'; ?>

<!-- Decorative Dots Pattern Background -->
<div class="absolute inset-0 pointer-events-none flex justify-between z-0 overflow-hidden" style="opacity: 0.4;">
    <div class="w-[200px] h-[300px] mt-32 -ml-10" style="background-image: radial-gradient(#a60b26 1.5px, transparent 1.5px); background-size: 20px 20px;"></div>
    <div class="w-[200px] h-[300px] mt-[400px] -mr-10" style="background-image: radial-gradient(#a60b26 1.5px, transparent 1.5px); background-size: 20px 20px;"></div>
</div>

<!-- Decorative Soft Shape -->
<div class="fixed bottom-0 right-0 w-[600px] h-[600px] bg-red-50 rounded-full blur-[100px] pointer-events-none opacity-50 z-0 transform translate-x-1/3 translate-y-1/3"></div>

<div class="max-w-[760px] w-[92%] mx-auto mt-10 mb-16 relative z-10">

    <div class="text-center mb-10">
        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight leading-tight mb-3">Notice <span class="text-maroon">Board</span></h2>
        <p class="text-gray-500 text-[15px] md:text-base">Official announcements and updates from the College</p>
    </div>

    <!-- Feed Container -->
    <div class="flex flex-col space-y-8">
        <?php foreach($notices as $n): ?>
            <div class="bg-white rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.06)] border border-gray-100 overflow-hidden hover:shadow-[0_8px_30px_rgba(0,0,0,0.08)] transition-all duration-300">
                
                <!-- Notice Header -->
                <div class="px-6 py-4 border-b border-gray-50 flex justify-between items-start bg-gray-50/50">
                    <div>
                        <h3 class="text-[18px] font-bold text-gray-900 leading-snug" dir="auto"><?php echo htmlspecialchars($n['Title']); ?></h3>
                        <p class="text-[13px] text-gray-500 font-medium mt-1 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-maroon/70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <?php echo date('d M Y, h:i A', strtotime($n['CreatedAt'])); ?>
                        </p>
                    </div>
                </div>

                <!-- Notice Image (if strictly present) -->
                <?php if($n['ImagePath']): ?>
                <div class="w-full bg-gray-100 flex justify-center border-b border-gray-100">
                    <img src="<?php echo htmlspecialchars($n['ImagePath']); ?>" alt="Notice Flyer" class="max-w-full h-auto max-h-[500px] object-contain cursor-pointer transition-transform hover:scale-[1.01] duration-500" onclick="window.open(this.src, '_blank');">
                </div>
                <?php endif; ?>

                <!-- Notice Content -->
                <?php if($n['Content']): ?>
                <div class="p-6">
                    <div class="text-[15.5px] text-gray-800 whitespace-pre-wrap font-medium" dir="auto" style="line-height: 1.8;"><?php echo htmlspecialchars($n['Content']); ?></div>
                </div>
                <?php endif; ?>
                
            </div>
        <?php endforeach; ?>
        
        <?php if(empty($notices)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">No Announcements</h3>
                <p class="text-gray-500 text-[15px]">There are currently no active notices to display.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
