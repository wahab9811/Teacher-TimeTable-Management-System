// Add html2pdf via dynamically if on pages that require it
if(document.querySelector('.download-pdf-btn')) {
    let appScript = document.querySelector('script[src$="assets/js/app.js"]');
    let baseUrl = appScript ? appScript.src.replace('/assets/js/app.js', '') : '';
    let script = document.createElement('script');
    script.src = baseUrl + "/assets/vendor/html2pdf.bundle.min.js";
    script.onerror = function() {
        console.error("Local html2pdf library failed to load.");
    };
    document.head.appendChild(script);
}

function downloadPDF(elementId, filename, isLandscape = false) {
    if (typeof html2pdf === 'undefined') {
        alert("The PDF export library is currently unavailable. Please check if the local dependency is correctly loaded.");
        return;
    }
    const element = document.getElementById(elementId);
    if(!element) return;
    
    // Configure html2pdf
    var opt = {
      margin:       0.5,
      filename:     filename,
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2 },
      jsPDF:        { unit: 'in', format: 'a4', orientation: isLandscape ? 'landscape' : 'portrait' }
    };
    
    // Add header manually before render
    let pdfHeader = document.getElementById('pdf-header-template');
    if(pdfHeader) pdfHeader.style.display = 'block';
    
    // Fix for html2canvas overflow and margin issues causing blank PDFs
    let tableWrap = element.querySelector('.overflow-x-auto');
    if(tableWrap) tableWrap.classList.remove('overflow-x-auto');
    let btnWrap = document.getElementById('pdf_btn_wrapper');
    if(btnWrap) btnWrap.style.display = 'none';
    
    // Remove auto margins temporarily to prevent rendering offsets (Blank PDF issue)
    let hadMxAuto = element.classList.contains('mx-auto');
    if(hadMxAuto) element.classList.remove('mx-auto');

    html2pdf().set(opt).from(element).save().then(() => {
        if(pdfHeader) pdfHeader.style.display = 'none';
        if(tableWrap) tableWrap.classList.add('overflow-x-auto');
        if(btnWrap) btnWrap.style.display = 'flex';
        if(hadMxAuto) element.classList.add('mx-auto');
    });
}
