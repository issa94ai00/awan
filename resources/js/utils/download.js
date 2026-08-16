/**
 * Handing the browser a file the app built in memory.
 *
 * The same dozen lines — make a blob URL, build an anchor, click it, tidy up —
 * were written out in eight admin screens, and most copies never called
 * `revokeObjectURL`, so every export leaked its blob for the life of the tab.
 */

/**
 * Saves `blob` under `filename`.
 *
 * The anchor has to be in the document for Firefox to honour the click, and the
 * object URL is released on the next frame: revoking it synchronously can cancel
 * the download before the browser has read it.
 */
export function downloadBlob(blob, filename) {
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = filename;
    link.style.display = 'none';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    requestAnimationFrame(() => window.URL.revokeObjectURL(url));
}

/**
 * The filename the server asked for, read off `Content-Disposition`.
 *
 * Returns null when the header is absent or unreadable — a cross-origin
 * response hides it — so the caller falls back to a name of its own rather than
 * saving something called "undefined.csv".
 */
export function filenameFromResponse(response, fallback = null) {
    const disposition = response?.headers?.['content-disposition']
        || response?.headers?.get?.('content-disposition');

    if (!disposition) return fallback;

    // RFC 5987 form first (`filename*=UTF-8''…`), which is the one that carries
    // non-ASCII names correctly; plain `filename=` is the fallback.
    const encoded = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
    if (encoded) {
        try {
            return decodeURIComponent(encoded[1].trim().replace(/^"|"$/g, ''));
        } catch {
            // A malformed header is not worth failing the download over.
        }
    }

    const plain = /filename="?([^";]+)"?/i.exec(disposition);
    return plain ? plain[1].trim() : fallback;
}
