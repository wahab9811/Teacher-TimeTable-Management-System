<?php include 'includes/header.php'; ?>
<div class="max-w-4xl mx-auto bg-white p-8 rounded shadow-md mt-4">
    <h2 class="text-3xl font-bold text-maroon mb-6 text-center">Contact Us</h2>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <div>
            <h4 class="text-xl font-bold text-gray-800 mb-4">Contact Information</h4>
            <p class="mb-2"><strong>Address:</strong><br> Govt. Graduate College, Civil Lines,<br> Sheikhupura, Punjab, Pakistan</p>
            <p class="mb-2"><strong>Email:</strong><br> <a href="mailto:info@gcbskp.edu.pk" class="text-blue-600 hover:underline">info@gcbskp.edu.pk</a></p>
            <p class="mb-2"><strong>Phone:</strong><br> +92-56-3783030</p>
            <p class="mb-2 mt-6"><strong>Principal:</strong><br> Dr. Abdur Rahim Ashraf</p>
        </div>
        <div>
            <h4 class="text-xl font-bold text-gray-800 mb-4">Location Map</h4>
            <!-- Embedded Google Map pointing roughly to Sheikhupura -->
            <a href="https://maps.app.goo.gl/tQgjGcCH2ZqfMNHFA" target="_blank" class="block relative group overflow-hidden rounded border border-gray-200">
                <div id="map-container" class="w-full h-[250px] bg-gray-100 flex items-center justify-center text-gray-500 text-sm relative pointer-events-none">
                    <span>Map requires internet connection to load.</span>
                </div>
                <div class="absolute inset-0 flex items-center justify-center bg-black/0 group-hover:bg-black/10 transition-colors z-10 cursor-pointer">
                    <span class="bg-white/95 backdrop-blur text-[#a60b26] font-bold px-4 py-2 rounded shadow-sm opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        Open in Google Maps
                    </span>
                </div>
            </a>
            <script>
                const iframeContent = '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d13589.655811776517!2d73.9782!3d31.7142!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3918c2b7405e3fbb%3A0xc346610dd4be6228!2sSheikhupura%2C%20Punjab%2C%20Pakistan!5e0!3m2!1sen!2s!4v1699999999999!5m2!1sen!2s" width="100%" height="250" style="border:0; pointer-events: none;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
                
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
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
