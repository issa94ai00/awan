/**
 * Helpers for turning a printable A4 sheet into a multi-page PDF with
 * html2canvas + jsPDF.
 */

export const A4_WIDTH_MM = 210;
export const A4_HEIGHT_MM = 297;

/** html2canvas options that drop the on-screen paper frame from the capture. */
export const sheetCaptureOptions = (bodySelector) => ({
    scale: 2,
    useCORS: true,
    backgroundColor: '#ffffff',
    logging: false,
    onclone: (clonedDoc) => {
        const body = clonedDoc.querySelector(bodySelector);
        if (body) {
            body.style.borderColor = 'transparent';
            body.style.borderRadius = '0';
            body.style.boxShadow = 'none';
        }
    },
});

/**
 * Page slices for the PDF, in CSS px of the sheet. Each cut lands on the
 * bottom edge of an element matched by `breakSelector` (table rows,
 * blocks), so no row is split across two pages.
 */
export const pdfPageSlices = (bodyEl, breakSelector, firstCapacity, nextCapacity) => {
    const top = bodyEl.getBoundingClientRect().top;
    const total = bodyEl.offsetHeight;
    const cuts = Array.from(bodyEl.querySelectorAll(breakSelector))
        .map((el) => el.getBoundingClientRect().bottom - top)
        .sort((a, b) => a - b);

    const slices = [];
    let start = 0;
    while (total - start > (slices.length ? nextCapacity : firstCapacity)) {
        const capacity = slices.length ? nextCapacity : firstCapacity;
        const limit = start + capacity;
        // Only accept a cut that still fills most of the page.
        const fit = cuts.filter((c) => c > start + capacity * 0.5 && c <= limit);
        const end = fit.length ? fit[fit.length - 1] : limit;
        slices.push([start, end]);
        start = end;
    }
    slices.push([start, total]);
    return slices;
};

/**
 * Capture `bodyEl` and add it to `pdf` as A4 pages, cutting on
 * `breakSelector` boundaries and leaving `marginPx` above continuation pages.
 */
export const addSheetPages = async (pdf, html2canvas, bodyEl, { breakSelector, captureOptions, marginPx = 18 }) => {
    const pagePx = (bodyEl.offsetWidth * A4_HEIGHT_MM) / A4_WIDTH_MM;
    const slices = pdfPageSlices(bodyEl, breakSelector, pagePx - marginPx, pagePx - marginPx * 2);
    const bodyCanvas = await html2canvas(bodyEl, captureOptions);
    const ratio = bodyCanvas.width / bodyEl.offsetWidth;
    const mmPerPx = A4_WIDTH_MM / bodyEl.offsetWidth;

    slices.forEach(([start, end], index) => {
        const sliceCanvas = document.createElement('canvas');
        sliceCanvas.width = bodyCanvas.width;
        sliceCanvas.height = Math.max(1, Math.round((end - start) * ratio));
        const ctx = sliceCanvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, sliceCanvas.width, sliceCanvas.height);
        ctx.drawImage(bodyCanvas, 0, -Math.round(start * ratio));

        if (index > 0) pdf.addPage();
        const offsetMm = index > 0 ? marginPx * mmPerPx : 0;
        pdf.addImage(sliceCanvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, offsetMm, A4_WIDTH_MM, (end - start) * mmPerPx);
    });
};
