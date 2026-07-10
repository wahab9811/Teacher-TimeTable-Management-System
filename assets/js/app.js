let html2pdfReady = false;
let html2pdfPromise = null;

function loadHtml2Pdf() {
    if (html2pdfPromise) return html2pdfPromise;
    
    html2pdfPromise = new Promise((resolve, reject) => {
        if (typeof html2pdf !== 'undefined') {
            html2pdfReady = true;
            resolve();
            return;
        }
        
        let appScript = document.querySelector('script[src$="assets/js/app.js"]');
        let baseUrl = appScript ? appScript.src.replace('/assets/js/app.js', '') : '';
        let script = document.createElement('script');
        script.src = baseUrl + "/assets/vendor/html2pdf.bundle.min.js";
        
        script.onload = () => {
            html2pdfReady = true;
            resolve();
        };
        script.onerror = () => {
            reject(new Error("Local html2pdf library failed to load."));
        };
        document.head.appendChild(script);
    });
    
    return html2pdfPromise;
}

// Pre-load if page has a PDF button
document.addEventListener('DOMContentLoaded', () => {
    if(document.querySelector('.download-pdf-btn')) {
        loadHtml2Pdf().catch(e => console.warn(e));
    }
});

async function downloadPDF(elementId, filename, isLandscape = false) {
    try {
        await loadHtml2Pdf();
    } catch (e) {
        alert(e.message);
        return;
    }

    if (typeof html2pdf === 'undefined') {
        alert("The PDF export library is currently unavailable. Please check if the local dependency is correctly loaded.");
        return;
    }

    const mainContainer = document.getElementById(elementId);
    if (!mainContainer) return;
    
    // Check if table is actually populated
    const table = mainContainer.querySelector('#tt_table');
    if (!table || table.innerHTML.trim() === '') {
        alert("Please generate the timetable before downloading the PDF.");
        return;
    }

    // Step 1: CLONE the printable area to avoid mutating live DOM which causes blank reflow errors
    const printableArea = document.getElementById('printable_area');
    if (!printableArea) return;

    const clone = printableArea.cloneNode(true);
    
    // Step 2: Extract Header and Prepend to Clone
    const pdfHeader = document.getElementById('pdf-header-template');
    if (pdfHeader) {
        const headerClone = pdfHeader.cloneNode(true);
        headerClone.style.display = 'block';
        headerClone.classList.remove('hidden');
        clone.insertBefore(headerClone, clone.firstChild);
    }
    
    // Step 3: Hard-code styles on the clone to guarantee it renders perfectly in html2canvas
    clone.style.backgroundColor = '#ffffff';
    clone.style.padding = '20px';
    clone.style.width = '1200px'; // Rigid width prevents tailwind collapsing
    clone.style.position = 'absolute';
    clone.style.top = '-9999px'; // Render off-screen
    clone.style.left = '-9999px';
    clone.style.zIndex = '-1';
    
    // Fix overflow wraps in clone
    let tableWraps = clone.querySelectorAll('.overflow-x-auto');
    tableWraps.forEach(wrap => {
        wrap.classList.remove('overflow-x-auto');
        wrap.style.overflow = 'visible';
    });

    // Make sure table uses full width inside our rigid clone
    const clonedTable = clone.querySelector('table');
    if(clonedTable) {
        clonedTable.style.width = '100%';
        clonedTable.style.backgroundColor = '#ffffff';
    }

    // Hide any buttons inside clone if they existed
    let btnWrap = clone.querySelector('#pdf_btn_wrapper');
    if (btnWrap) btnWrap.style.display = 'none';

    // Step 4: Inject clone into live body so html2canvas can read computed styles properly
    document.body.appendChild(clone);

    // Configure html2pdf with robust fallback parameters
    var opt = {
      margin:       [0.3, 0.3, 0.3, 0.3],
      filename:     filename,
      image:        { type: 'jpeg', quality: 1.0 },
      html2canvas:  { 
          scale: 2, 
          useCORS: true, 
          backgroundColor: '#ffffff',
          windowWidth: 1200 // Lock viewport width to clone width
      },
      jsPDF:        { unit: 'in', format: 'a4', orientation: isLandscape ? 'landscape' : 'portrait' }
    };

    // Execute capture on the stable clone
    try {
        await html2pdf().set(opt).from(clone).save();
    } catch(err) {
        console.error("PDF Export Error:", err);
        alert("An error occurred while generating the PDF. Check console.");
    } finally {
        // Step 5: Safely remove the clone
        document.body.removeChild(clone);
    }
}
