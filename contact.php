<?php 
require_once __DIR__ . '/config/db.php';

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $messageText = trim($_POST['message'] ?? '');

    if ($name && $email && $subject && $messageText) {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (Name, Email, SubjectCategory, MessageText) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $messageText]);
            $msg = "Thank you for your message! We will get back to you soon.";
            $msg_type = 'success';
        } catch (PDOException $e) {
            $msg = "Error sending message. Please try again later.";
            $msg_type = 'error';
        }
    } else {
        $msg = "Please fill in all required fields.";
        $msg_type = 'error';
    }
}
?>
<?php include 'includes/header.php'; ?>
<div class="max-w-6xl mx-auto mt-4 px-4 sm:px-6 lg:px-8 mb-12">
    
    <?php if ($msg): ?>
        <div class="mb-6 px-4 py-3 rounded relative shadow-sm font-semibold <?php echo $msg_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white p-8 md:p-12 rounded-xl shadow-md border border-gray-100">
        <h2 class="text-3xl font-bold text-[#a60b26] mb-8 text-center tracking-tight">Contact Us & Inquiries</h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            
            <!-- Left Side: Contact Info & Map -->
            <div class="space-y-8">
                <div>
                    <h4 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        College Information
                    </h4>
                    <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 text-gray-700 space-y-4">
                        <p><strong>Address:</strong><br> Govt. Graduate College, Civil Lines,<br> Sheikhupura, Punjab, Pakistan</p>
                        <p><strong>Email:</strong><br> <a href="mailto:info@gcbskp.edu.pk" class="text-blue-600 hover:underline">info@gcbskp.edu.pk</a></p>
                        <p><strong>Phone:</strong><br> +92-56-3783030</p>
                        <p class="pt-2 border-t border-gray-200"><strong>Principal:</strong><br> Dr. Abdur Rahim Ashraf</p>
                    </div>
                </div>

                <div>
                    <h4 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Location Map
                    </h4>
                    <a href="https://maps.app.goo.gl/tQgjGcCH2ZqfMNHFA" target="_blank" class="block relative group overflow-hidden rounded-lg border border-gray-200 shadow-sm">
                        <div id="map-container" class="w-full h-[220px] bg-gray-100 flex items-center justify-center text-gray-500 text-sm relative pointer-events-none">
                            <span>Map requires internet connection to load.</span>
                        </div>
                        <div class="absolute inset-0 flex items-center justify-center bg-black/0 group-hover:bg-black/10 transition-colors z-10 cursor-pointer">
                            <span class="bg-white/95 backdrop-blur text-[#a60b26] font-bold px-4 py-2 rounded shadow-sm opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                Open in Google Maps
                            </span>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Right Side: Contact Form -->
            <div>
                <h4 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Send a Message
                </h4>
                <div class="bg-gray-50/50 p-6 sm:p-8 rounded-lg border border-gray-200">
                    <form method="POST" action="contact.php" class="space-y-5">
                        
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Your Name *</label>
                            <input type="text" name="name" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-[#a60b26] focus:border-transparent outline-none transition-all">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Email Address *</label>
                            <input type="email" name="email" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-[#a60b26] focus:border-transparent outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Subject / Category *</label>
                            <select name="subject" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-[#a60b26] focus:border-transparent outline-none transition-all appearance-none bg-white">
                                <option value="">-- Select a Topic --</option>
                                <option value="General Inquiry">General Inquiry</option>
                                <option value="Website Problem">Website Problem</option>
                                <option value="Suggestion / Feedback">Suggestion / Feedback</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Message *</label>
                            <textarea name="message" required rows="4" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-[#a60b26] focus:border-transparent outline-none transition-all resize-y" placeholder="How can we help you?"></textarea>
                        </div>
                        
                        <div class="pt-2">
                            <button type="submit" name="submit_contact" class="w-full bg-[#a60b26] text-white font-bold py-3 px-4 rounded-lg hover:bg-red-800 transition-colors shadow-sm text-center">
                                Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    const iframeContent = '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d13589.655811776517!2d73.9782!3d31.7142!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3918c2b7405e3fbb%3A0xc346610dd4be6228!2sSheikhupura%2C%20Punjab%2C%20Pakistan!5e0!3m2!1sen!2s!4v1699999999999!5m2!1sen!2s" width="100%" height="220" style="border:0; pointer-events: none; border-radius: 6px;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
    
    if (navigator.onLine) {
        document.getElementById('map-container').innerHTML = iframeContent;
    }
    
    window.addEventListener('online', function() {
        const ct = document.getElementById('map-container');
        if(ct.innerHTML.indexOf('iframe') === -1) {
            ct.innerHTML = iframeContent;
        }
    });
    window.addEventListener('offline', function() {
        document.getElementById('map-container').innerHTML = '<span>Map requires internet connection to load.</span>';
    });
</script>

<?php include 'includes/footer.php'; ?>
