    </main>
    <?php if(basename($_SERVER['PHP_SELF']) !== 'login.php'): ?>
    <footer class="w-[98%] max-w-none mx-auto bg-white pt-8 pb-4 mt-8 mb-6 border border-gray-100 relative overflow-hidden z-10 rounded-[24px] shadow-[0_-4px_20px_rgba(0,0,0,0.03)]">
        <div class="w-full px-6 md:px-8 mx-auto relative">
            <!-- Faded background logo on the right -->
            <div class="absolute -right-6 top-1/2 -translate-y-1/2 opacity-[0.04] pointer-events-none hidden md:block">
                <img src="<?php echo $base_url; ?>/assets/img/logo.png" alt="Logo Watermark" class="w-[300px] h-auto">
            </div>

            <!-- Main Footer Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 md:gap-x-8 relative z-10">
            
            <!-- Column 1: Info -->
            <div class="md:col-span-1 md:pr-4 md:border-r border-gray-100/80">
                <div class="flex items-center gap-3 mb-5">
                    <img src="<?php echo $base_url; ?>/assets/img/logo.png" alt="GCB Logo" class="w-[45px] h-[45px] object-contain">
                    <div class="text-[14.5px] font-bold text-gray-900 leading-tight">
                        Govt. Graduate College,<br>Civil Lines, Sheikhupura
                    </div>
                </div>
                
                <div class="flex items-start gap-3 text-gray-500 text-[13.5px] font-medium mb-5">
                    <svg class="w-4 h-4 mt-0.5 shrink-0 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    <span>Civil Lines, Sheikhupura,<br>Punjab, Pakistan</span>
                </div>
                
                <div class="flex items-center gap-3 mb-3 md:mb-0">
                    <a href="https://www.facebook.com/GCbSkp/" target="_blank" class="w-[32px] h-[32px] rounded-full border-[1.5px] border-[#a60b26] text-[#a60b26] flex items-center justify-center hover:bg-[#a60b26] hover:text-white transition-colors">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                    </a>
                    <a href="https://www.instagram.com/GCbSkp/" target="_blank" class="w-[32px] h-[32px] rounded-full border-[1.5px] border-[#a60b26] text-[#a60b26] flex items-center justify-center hover:bg-[#a60b26] hover:text-white transition-colors">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                    <a href="https://x.com/GCbSkp" target="_blank" class="w-[32px] h-[32px] rounded-full border-[1.5px] border-[#a60b26] text-[#a60b26] flex items-center justify-center hover:bg-[#a60b26] hover:text-white transition-colors">
                        <svg class="w-3.5 h-3.5" transform="scale(0.85)" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
                    </a>
                    <a href="https://www.whatsapp.com/channel/0029VaQ4by5D38CX9hCX7d1B" target="_blank" class="w-[32px] h-[32px] rounded-full border-[1.5px] border-[#a60b26] text-[#a60b26] flex items-center justify-center hover:bg-[#a60b26] hover:text-white transition-colors">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.012 2c-5.506 0-9.989 4.478-9.99 9.984a9.964 9.964 0 001.333 4.993L2 22l5.233-1.337a9.957 9.957 0 004.779 1.216h.004c5.502 0 9.985-4.48 9.985-9.984C21.996 6.478 17.514 2 12.012 2zm5.543 14.331c-.244.693-1.42 1.328-1.999 1.414-.492.073-1.127.112-3.327-.8-2.613-1.085-4.281-3.743-4.41-3.916-.13-.173-1.053-1.402-1.053-2.673 0-1.272.663-1.898.898-2.152.235-.254.512-.317.683-.317.172 0 .346.002.492.008.163.007.382-.061.597.458.225.541.687 1.675.748 1.799.061.124.103.271.018.441-.086.17-.13.275-.259.421-.128.147-.272.32-.387.433-.129.127-.263.268-.112.53.151.261.671 1.11 1.439 1.794.992.883 1.83 1.155 2.088 1.282.258.127.411.106.565-.052.153-.158.665-.77.844-1.036.178-.266.357-.221.594-.132.237.089 1.5.707 1.758.835.258.129.431.193.493.301.062.109.062.63-.182 1.323z"/></svg>
                    </a>
                </div>
            </div>

            <!-- Column 2: Quick Links -->
            <div class="md:col-span-1 md:border-r border-gray-100/80 md:px-4">
                <h3 class="text-[14.5px] font-bold text-gray-900 mb-4 md:mb-5">Quick Links</h3>
                <ul class="space-y-3 md:space-y-4">
                    <li><a href="<?php echo $base_url; ?>/index.php" class="flex items-center gap-2 text-gray-500 text-[13.5px] font-medium hover:text-[#a60b26] transition-colors"><svg class="w-3.5 h-3.5 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg> Home</a></li>
                    <li><a href="<?php echo $base_url; ?>/calendar.php" class="flex items-center gap-2 text-gray-500 text-[13.5px] font-medium hover:text-[#a60b26] transition-colors"><svg class="w-3.5 h-3.5 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg> Calendar</a></li>
                    <li><a href="<?php echo $base_url; ?>/about.php" class="flex items-center gap-2 text-gray-500 text-[13.5px] font-medium hover:text-[#a60b26] transition-colors"><svg class="w-3.5 h-3.5 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg> About</a></li>
                    <li><a href="<?php echo $base_url; ?>/contact.php" class="flex items-center gap-2 text-gray-500 text-[13.5px] font-medium hover:text-[#a60b26] transition-colors"><svg class="w-3.5 h-3.5 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg> Contact</a></li>
                </ul>
            </div>
            
            <!-- Column 3: Important Links -->
            <div class="md:col-span-1 md:border-r border-gray-100/80 md:px-4">
                <h3 class="text-[14.5px] font-bold text-gray-900 mb-4 md:mb-5">Important Links</h3>
                <ul class="space-y-3 md:space-y-4">
                    <li><a href="<?php echo $base_url; ?>/calendar.php" class="flex items-center gap-2 text-gray-500 text-[13.5px] font-medium hover:text-[#a60b26] transition-colors"><svg class="w-3.5 h-3.5 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg> Academic Calendar</a></li>
                    <li><a href="https://gcbskp.edu.pk/" target="_blank" class="flex items-center gap-2 text-gray-500 text-[13.5px] font-medium hover:text-[#a60b26] transition-colors"><svg class="w-3.5 h-3.5 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg> College Website</a></li>
                    <li><a href="<?php echo $base_url; ?>/notices.php" class="flex items-center gap-2 text-gray-500 text-[13.5px] font-medium hover:text-[#a60b26] transition-colors"><svg class="w-3.5 h-3.5 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg> Notice Board</a></li>
                    <li><a href="<?php echo $base_url; ?>/downloads.php" class="flex items-center gap-2 text-gray-500 text-[13.5px] font-medium hover:text-[#a60b26] transition-colors"><svg class="w-3.5 h-3.5 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg> Downloads</a></li>
                </ul>
            </div>
            
            <!-- Column 4: Contact Us -->
            <div class="md:col-span-1 md:pl-4">
                <h3 class="text-[14.5px] font-bold text-gray-900 mb-4 md:mb-5">Contact Us</h3>
                <ul class="space-y-4 text-[13.5px] text-gray-500 font-medium">
                    <li class="flex items-center gap-3">
                        <svg class="w-[18px] h-[18px] shrink-0 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        <span>+92 56 1234567</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <svg class="w-[18px] h-[18px] shrink-0 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <span>info@gcbsheikhupura.edu.pk</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <svg class="w-[18px] h-[18px] shrink-0 text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Mon - Fri 8:00 AM - 4:00 PM</span>
                    </li>
                </ul>
            </div>
            
        </div>

        <!-- Divider Line -->
        <hr class="border-gray-100 mt-4 md:mt-2 mb-3 relative z-10 w-full">

        <!-- Bottom Copyright & Principal -->
        <div class="flex flex-col items-center justify-center gap-1.5 text-[13.5px] text-gray-500 relative z-10 w-full text-center pb-1">
            <div class="flex items-center justify-center gap-2 font-medium bg-white px-2">
                <svg class="w-[16px] h-[16px] text-[#a60b26]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Copyright &copy; 2026 GCB SKP &mdash; All Rights Reserved.
            </div>
            <div>
                <strong class="text-gray-700">Principal:</strong> Dr. Abdur Rahim Ashraf
            </div>
        </div>
    </div>
    </footer>
    <?php endif; ?>
    <!-- Bootstrap JS (for Dropdowns, Modals) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $base_url; ?>/assets/js/app.js"></script>
</body>
</html>
