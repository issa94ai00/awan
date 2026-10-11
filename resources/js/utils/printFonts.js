/**
 * Arabic typefaces offered for printed documents.
 *
 * Each font is loaded from Google Fonts with every weight the print sheet
 * uses, so headings never fall back to a synthesized bold, and the print
 * iframe and the PDF capture wait for the glyphs before rendering.
 */
export const PRINT_FONTS = [
    { key: 'Cairo', label: 'Cairo', hint: 'عصري واضح', weights: '400;500;600;700;800;900' },
    { key: 'Tajawal', label: 'Tajawal', hint: 'خفيف أنيق', weights: '400;500;700;800;900' },
    { key: 'Almarai', label: 'Almarai', hint: 'مقروء جداً', weights: '400;700;800' },
    { key: 'IBM Plex Sans Arabic', label: 'IBM Plex', hint: 'رسمي مؤسسي', weights: '400;500;600;700' },
    { key: 'Noto Naskh Arabic', label: 'Noto Naskh', hint: 'نسخ تقليدي', weights: '400;500;600;700' },
    { key: 'Readex Pro', label: 'Readex Pro', hint: 'أرقام واضحة', weights: '400;500;600;700' },
];

export const DEFAULT_PRINT_FONT = 'Cairo';

const MONO_FAMILY = 'family=JetBrains+Mono:wght@400;500;700;800';

export const resolvePrintFont = (key) => PRINT_FONTS.find((f) => f.key === key) || PRINT_FONTS[0];

/** The CSS font-family stack for a print font, with Arabic-capable fallbacks. */
export const printFontStack = (key) => `'${resolvePrintFont(key).key}', 'Cairo', 'Noto Sans Arabic', 'Segoe UI', Tahoma, sans-serif`;

export const printFontCssUrl = (key) => {
    const font = resolvePrintFont(key);
    const family = `family=${font.key.replace(/ /g, '+')}:wght@${font.weights}`;
    return `https://fonts.googleapis.com/css2?${family}&${MONO_FAMILY}&display=swap`;
};

/** Add the font's stylesheet to a document once; returns the <link>. */
export const ensurePrintFont = (key, doc = document) => {
    const href = printFontCssUrl(key);
    let link = Array.from(doc.querySelectorAll('link[data-print-font]')).find((l) => l.getAttribute('href') === href);
    if (!link) {
        link = doc.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.setAttribute('data-print-font', resolvePrintFont(key).key);
        doc.head.appendChild(link);
    }
    return link;
};

/**
 * Resolve once the font's regular and bold Arabic glyphs are ready in `doc`,
 * or after `timeout` ms so a blocked network never stops printing.
 */
export const waitForPrintFont = async (key, doc = document, timeout = 4000) => {
    if (!doc?.fonts) return;
    const font = resolvePrintFont(key);
    const link = ensurePrintFont(key, doc);
    const sample = 'فاتورة ١٢٣ 123';
    const weights = font.weights.split(';').filter((w) => ['400', '700', '800'].includes(w));
    // fonts.load() only sees @font-face rules that are already parsed.
    const sheetReady = link.sheet
        ? Promise.resolve()
        : new Promise((resolve) => {
            link.addEventListener('load', resolve, { once: true });
            link.addEventListener('error', resolve, { once: true });
        });
    const loads = sheetReady.then(() => Promise.all([
        ...weights.map((w) => doc.fonts.load(`${w} 16px '${font.key}'`, sample).catch(() => null)),
        doc.fonts.load(`700 16px 'JetBrains Mono'`, 'INV-0123').catch(() => null),
    ])).then(() => doc.fonts.ready);
    await Promise.race([loads, new Promise((resolve) => setTimeout(resolve, timeout))]);
};
