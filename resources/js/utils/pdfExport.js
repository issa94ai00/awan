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
 * @param {{items: any[]}[]} groups - same array used to render `table`, so
 *   row counts line up with the DOM and page breaks land between groups.
 * @param {string} filename
 * @param {string} [coverSrc] - optional full-bleed cover page, drawn "contain"-fit.
 * @param {(loaded: number, total: number) => void} [onPageProgress]
 */
export async function renderTableToPdf({ table, groups, filename, coverSrc, onPageProgress }) {
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
    // so we can slice the DOM row list without inspecting classes.
    const rowEls = Array.from(table.querySelectorAll('tbody tr'));
    let rowIdx = 0;
    const chunkRects = groups.map((g) => {
        const groupRows = rowEls.slice(rowIdx, rowIdx + g.items.length);
        rowIdx += g.items.length;
        const first = groupRows[0];
        const last = groupRows[groupRows.length - 1] || first;
        return {
            top: first.getBoundingClientRect().top - tableRect.top,
            bottom: last.getBoundingClientRect().bottom - tableRect.top,
        };
    }).filter((c) => c && c.bottom > c.top);

    const pdf = new jsPDF({ orientation: 'p', unit: 'pt', format: 'a4' });
    const pageWidthPt = pdf.internal.pageSize.getWidth();
    const pageHeightPt = pdf.internal.pageSize.getHeight();
    const contentWidthPt = pageWidthPt - PAGE_MARGIN_PT * 2;
    const contentHeightPt = pageHeightPt - PAGE_MARGIN_PT * 2;
    const ptPerCssPx = contentWidthPt / tableRect.width;
    const maxBodyHeightCss = (contentHeightPt / ptPerCssPx) - theadHeight;

    // Greedily pack whole groups onto each page; a single group taller than
    // one page is left to overflow rather than being split mid-row.
    const pages = [];
    let pageTop = null;
    let pageBottom = null;
    for (const chunk of chunkRects) {
        if (pageTop === null) {
            pageTop = chunk.top;
            pageBottom = chunk.bottom;
            continue;
        }
        if (chunk.bottom - pageTop > maxBodyHeightCss) {
            pages.push({ top: pageTop, bottom: pageBottom });
            pageTop = chunk.top;
            pageBottom = chunk.bottom;
        } else {
            pageBottom = chunk.bottom;
        }
    }
    if (pageTop !== null) pages.push({ top: pageTop, bottom: pageBottom });
    if (!pages.length) pages.push({ top: 0, bottom: 0 });

    let pageCount = 0;
    const totalPages = pages.length + (coverSrc ? 1 : 0);

    if (coverSrc) {
        const coverImg = await loadImage(coverSrc);
        if (coverImg) {
            const coverCanvas = document.createElement('canvas');
            coverCanvas.width = coverImg.naturalWidth;
            coverCanvas.height = coverImg.naturalHeight;
            coverCanvas.getContext('2d').drawImage(coverImg, 0, 0);
            const coverData = coverCanvas.toDataURL('image/jpeg', 0.92);

            const imgRatio = coverImg.naturalWidth / coverImg.naturalHeight;
            const pageRatio = pageWidthPt / pageHeightPt;
            let drawW, drawH, offX = 0, offY = 0;
            if (imgRatio > pageRatio) {
                drawW = pageWidthPt;
                drawH = pageWidthPt / imgRatio;
                offY = (pageHeightPt - drawH) / 2;
            } else {
                drawH = pageHeightPt;
                drawW = pageHeightPt * imgRatio;
                offX = (pageWidthPt - drawW) / 2;
            }
            pdf.addImage(coverData, 'JPEG', offX, offY, drawW, drawH);
            pageCount += 1;
            onPageProgress?.(pageCount, totalPages);
        }
    }

    const theadHeightScaled = Math.round(theadHeight * scale);
    for (const { top, bottom } of pages) {
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
        if (pageCount > 0) pdf.addPage();
        pdf.addImage(imgData, 'JPEG', PAGE_MARGIN_PT, PAGE_MARGIN_PT, contentWidthPt, imgHeightPt);
        pageCount += 1;
        onPageProgress?.(pageCount, totalPages);
        // Yield a frame so the progress overlay actually repaints between pages.
        await new Promise((r) => setTimeout(r, 0));
    }

    downloadBlob(pdf.output('blob'), filename);
}
