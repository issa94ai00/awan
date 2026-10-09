/**
 * Turns a rendered price-offer table into a paginated PDF.
 *
 * html2canvas only ever captures whatever is already painted on screen, so a
 * lazy-loaded or still-fetching <img> shows up as a blank box in the output.
 * `waitForImages` is the fix: it blocks until every <img> under a container
 * has either loaded or failed (with a timeout so one broken URL can't hang
 * the whole export), and reports progress as it goes.
 *
 * `renderTableToPdf` then captures the table once and slices that single
 * canvas into pages, choosing every cut at a product-group boundary (never
 * through the middle of a row) and re-drawing the table header on each page.
 */

import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
import { downloadBlob } from '@/utils/download';

const IMAGE_TIMEOUT_MS = 8000;
const PAGE_MARGIN_PT = 24;

function waitForOneImage(img, timeoutMs) {
    if (img.complete && img.naturalWidth > 0) return Promise.resolve();
    return new Promise((resolve) => {
        let settled = false;
        const finish = () => {
            if (settled) return;
            settled = true;
            img.removeEventListener('load', finish);
            img.removeEventListener('error', finish);
            clearTimeout(timer);
            resolve();
        };
        img.addEventListener('load', finish);
        img.addEventListener('error', finish);
        const timer = setTimeout(finish, timeoutMs);
    });
}

/** Resolves once every <img> under `container` has loaded, failed, or timed out. */
export async function waitForImages(container, onProgress) {
    const imgs = Array.from(container.querySelectorAll('img'));
    const total = imgs.length;
    let loaded = 0;
    onProgress?.(0, total);
    if (!total) return;
    await Promise.all(imgs.map((img) => waitForOneImage(img, IMAGE_TIMEOUT_MS).then(() => {
        loaded += 1;
        onProgress?.(loaded, total);
    })));
}

function loadImage(src) {
    return new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null);
        img.src = src;
    });
}

/**
 * @param {HTMLTableElement} table - the rendered <table> to capture.
 * @param {{items: any[], rowCount?: number}[]} groups - same array used to
 *   render `table`, so row counts line up with the DOM and page breaks land
 *   between groups. `rowCount` overrides `items.length` for a group that also
 *   renders a classification heading row.
 * @param {string} filename
 * @param {string} [coverSrc] - optional full-bleed cover page, drawn "contain"-fit.
 * @param {HTMLElement} [headerEl] - optional header banner element captured on page 1.
 * @param {boolean} [showPageNumbers] - whether to print page numbers and footer.
 * @param {string} [footerBrand] - optional footer brand text.
 * @param {(loaded: number, total: number) => void} [onPageProgress]
 */
export async function renderTableToPdf({
    table,
    groups,
    filename,
    coverSrc,
    headerEl,
    showPageNumbers = true,
    footerBrand,
    orientation = 'portrait',
    onPageProgress,
}) {
    const scale = 2;
    const canvas = await html2canvas(table, {
        scale,
        useCORS: true,
        backgroundColor: '#ffffff',
        logging: false,
    });

    const tableRect = table.getBoundingClientRect();
    const theadEl = table.querySelector('thead');
    const theadHeight = theadEl ? theadEl.getBoundingClientRect().height : 0;

    // Every group's rows are contiguous <tr>s in the same order as `groups`,
    // so we can slice the DOM row list without inspecting classes. A group that
    // opens a classification section also owns the heading row above it, which
    // is why the count comes from `rowCount` rather than the item count — get
    // this wrong and every page break after the first section lands short.
    const rowEls = Array.from(table.querySelectorAll('tbody tr'));
    let rowIdx = 0;
    const chunkRects = groups.map((g) => {
        const rowsInGroup = g.rowCount ?? g.items.length;
        const groupRows = rowEls.slice(rowIdx, rowIdx + rowsInGroup);
        rowIdx += rowsInGroup;
        const first = groupRows[0];
        const last = groupRows[groupRows.length - 1] || first;
        if (!first) return null;
        return {
            top: first.getBoundingClientRect().top - tableRect.top,
            bottom: (last || first).getBoundingClientRect().bottom - tableRect.top,
        };
    }).filter((c) => c && c.bottom > c.top);

    const isLandscape = orientation === 'landscape';
    const pdf = new jsPDF({ orientation: isLandscape ? 'l' : 'p', unit: 'pt', format: 'a4' });
    const pageWidthPt = pdf.internal.pageSize.getWidth();
    const pageHeightPt = pdf.internal.pageSize.getHeight();
    const contentWidthPt = pageWidthPt - PAGE_MARGIN_PT * 2;
    const FOOTER_HEIGHT_PT = showPageNumbers ? 22 : 0;
    const contentHeightPt = pageHeightPt - PAGE_MARGIN_PT * 2 - FOOTER_HEIGHT_PT;
    const ptPerCssPx = contentWidthPt / (tableRect.width || 960);

    // Optional header banner element capture for the first content page
    let headerCanvas = null;
    let headerHeightPt = 0;
    if (headerEl) {
        try {
            headerCanvas = await html2canvas(headerEl, {
                scale,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
            });
            const headerRect = headerEl.getBoundingClientRect();
            const widthForScale = headerRect.width || tableRect.width || 960;
            headerHeightPt = (headerCanvas.height / scale) * (contentWidthPt / widthForScale);
        } catch (e) {
            console.warn('Could not capture header banner for PDF:', e);
            headerCanvas = null;
            headerHeightPt = 0;
        }
    }

    const maxBodyHeightCssOther = (contentHeightPt / ptPerCssPx) - theadHeight;
    const maxBodyHeightCssPage1 = headerCanvas
        ? Math.max(120, ((contentHeightPt - headerHeightPt - 10) / ptPerCssPx) - theadHeight)
        : maxBodyHeightCssOther;

    // Greedily pack whole groups onto each page; a single group taller than
    // one page is left to overflow rather than being split mid-row.
    const pages = [];
    let pageTop = null;
    let pageBottom = null;
    let isFirst = true;

    for (const chunk of chunkRects) {
        const maxBodyHeight = (isFirst && headerCanvas) ? maxBodyHeightCssPage1 : maxBodyHeightCssOther;
        if (pageTop === null) {
            pageTop = chunk.top;
            pageBottom = chunk.bottom;
            continue;
        }
        if (chunk.bottom - pageTop > maxBodyHeight) {
            pages.push({ top: pageTop, bottom: pageBottom, hasHeader: isFirst && !!headerCanvas });
            isFirst = false;
            pageTop = chunk.top;
            pageBottom = chunk.bottom;
        } else {
            pageBottom = chunk.bottom;
        }
    }
    if (pageTop !== null) {
        pages.push({ top: pageTop, bottom: pageBottom, hasHeader: isFirst && !!headerCanvas });
    }
    if (!pages.length) {
        pages.push({ top: 0, bottom: 0, hasHeader: !!headerCanvas });
    }

    let pageCount = 0;
    const totalPages = pages.length + (coverSrc ? 1 : 0);

    // Full-bleed Cover Page if provided
    if (coverSrc) {
        try {
            const coverImg = await loadImage(coverSrc);
            if (coverImg) {
                const coverCanvas = document.createElement('canvas');
                coverCanvas.width = coverImg.naturalWidth;
                coverCanvas.height = coverImg.naturalHeight;
                coverCanvas.getContext('2d').drawImage(coverImg, 0, 0);
                const coverData = coverCanvas.toDataURL('image/jpeg', 0.92);

                // Cover page fulfillment: fulfill the entire page with no white margins
                const imgRatio = coverImg.naturalWidth / coverImg.naturalHeight;
                const pageRatio = pageWidthPt / pageHeightPt;
                let drawW, drawH, offX = 0, offY = 0;

                // If portrait or aspect ratios are within 5% of each other, fulfill exact page boundaries
                if (Math.abs(imgRatio - pageRatio) < 0.05) {
                    drawW = pageWidthPt;
                    drawH = pageHeightPt;
                    offX = 0;
                    offY = 0;
                } else if (imgRatio > pageRatio) {
                    drawH = pageHeightPt;
                    drawW = pageHeightPt * imgRatio;
                    offX = (pageWidthPt - drawW) / 2;
                    offY = 0;
                } else {
                    drawW = pageWidthPt;
                    drawH = pageWidthPt / imgRatio;
                    offX = 0;
                    offY = (pageHeightPt - drawH) / 2;
                }
                pdf.addImage(coverData, 'JPEG', offX, offY, drawW, drawH);
                pageCount += 1;
                onPageProgress?.(pageCount, totalPages);
            }
        } catch (e) {
            console.warn('Could not render cover image for PDF:', e);
        }
    }

    const theadHeightScaled = Math.round(theadHeight * scale);
    const totalTablePages = pages.length;

    for (let pIdx = 0; pIdx < pages.length; pIdx++) {
        const { top, bottom, hasHeader } = pages[pIdx];
        if (pageCount > 0) pdf.addPage();
        pageCount += 1;

        let curY = PAGE_MARGIN_PT;

        // Draw header on the first table page
        if (hasHeader && headerCanvas) {
            const headerData = headerCanvas.toDataURL('image/jpeg', 0.95);
            pdf.addImage(headerData, 'JPEG', PAGE_MARGIN_PT, curY, contentWidthPt, headerHeightPt);
            curY += headerHeightPt + 8;
        }

        const bodyHeightScaled = Math.max(0, Math.round((bottom - top) * scale));
        const pageCanvas = document.createElement('canvas');
        pageCanvas.width = canvas.width;
        pageCanvas.height = theadHeightScaled + bodyHeightScaled;

        const ctx = pageCanvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, pageCanvas.width, pageCanvas.height);
        if (theadHeightScaled > 0) {
            ctx.drawImage(canvas, 0, 0, canvas.width, theadHeightScaled, 0, 0, canvas.width, theadHeightScaled);
        }
        if (bodyHeightScaled > 0) {
            ctx.drawImage(
                canvas, 0, Math.round(top * scale), canvas.width, bodyHeightScaled,
                0, theadHeightScaled, canvas.width, bodyHeightScaled,
            );
        }

        const imgData = pageCanvas.toDataURL('image/jpeg', 0.92);
        const imgHeightPt = (pageCanvas.height / scale) * ptPerCssPx;
        pdf.addImage(imgData, 'JPEG', PAGE_MARGIN_PT, curY, contentWidthPt, imgHeightPt);

        // Footer with divider and page numbers
        if (showPageNumbers) {
            const footerY = pageHeightPt - 16;
            pdf.setDrawColor(226, 232, 240); // #e2e8f0
            pdf.setLineWidth(0.75);
            pdf.line(PAGE_MARGIN_PT, footerY - 8, pageWidthPt - PAGE_MARGIN_PT, footerY - 8);

            pdf.setFontSize(8.5);
            pdf.setTextColor(100, 116, 139); // #64748b

            const pageStr = `${pIdx + 1} / ${totalTablePages}`;
            pdf.text(pageStr, pageWidthPt - PAGE_MARGIN_PT, footerY, { align: 'right' });

            const brandStr = footerBrand || 'AWAAN AL-TAKADOM - Sanitary Ware & Building Materials';
            pdf.text(brandStr, PAGE_MARGIN_PT, footerY, { align: 'left' });
        }

        onPageProgress?.(pageCount, totalPages);
        // Yield a frame so the progress overlay actually repaints between pages.
        await new Promise((r) => setTimeout(r, 0));
    }

    downloadBlob(pdf.output('blob'), filename);
}
