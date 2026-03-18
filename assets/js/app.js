// Add html2pdf via CDN dynamically if on pages that require it
if(document.querySelector('.download-pdf-btn')) {
    let script = document.createElement('script');
    script.src = "https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js";
    document.head.appendChild(script);
}

function downloadPDF(elementId, filename, isLandscape = false) {
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
    
    // Add header manually before render? Better to have it hidden in UI then reveal
    let pdfHeader = document.getElementById('pdf-header-template');
    if(pdfHeader) pdfHeader.style.display = 'block';

    html2pdf().set(opt).from(element).save().then(() => {
        if(pdfHeader) pdfHeader.style.display = 'none';
    });
}
