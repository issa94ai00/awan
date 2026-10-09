/**
 * Utility: the shop's contact details as the storefront shows them — the
 * footer and the contact page read the same settings the same way.
 */

/** "00963 962-889-577" → "963962889577": what tel: (after a +) and wa.me take. */
export function phoneDigits(value) {
    return String(value || '').replace(/\D/g, '').replace(/^00/, '');
}

/** A Syrian number grouped "+963 962 889 577"; anything else as +digits. */
export function formatPhone(value) {
    const digits = phoneDigits(value);
    const match = digits.match(/^(963)(\d{3})(\d{3})(\d{3})$/);
    return match ? `+${match[1]} ${match[2]} ${match[3]} ${match[4]}` : `+${digits}`;
}

export function telHref(value) {
    const digits = phoneDigits(value);
    return digits ? `tel:+${digits}` : '';
}

/** wa.me refuses the number with its 00 prefix, which the old links kept. */
export function whatsappHref(value, text = '') {
    const digits = phoneDigits(value);
    if (!digits) return '';
    return `https://wa.me/${digits}${text ? `?text=${encodeURIComponent(text)}` : ''}`;
}

/**
 * Branches from Settings › Contact (`contact_branches`, a JSON list), head
 * office first, in the viewer's language with Arabic standing in for missing
 * English. Empty when none are saved.
 *
 * @returns {{ name: string, address: string, phone: string, map_url: string, is_main: boolean }[]}
 */
export function contactBranches(settings, isEn) {
    let list = settings?.contact_branches;
    if (typeof list === 'string') {
        try {
            list = list ? JSON.parse(list) : [];
        } catch {
            list = [];
        }
    }
    if (!Array.isArray(list)) return [];

    return list
        .map((branch) => ({
            name: (isEn ? branch.name_en : '') || branch.name_ar || branch.name_en || '',
            address: (isEn ? branch.address_en : '') || branch.address_ar || branch.address_en || '',
            phone: phoneDigits(branch.phone) ? branch.phone : '',
            map_url: /^https?:\/\//i.test(branch.map_url || '') ? branch.map_url : '',
            is_main: Boolean(branch.is_main),
        }))
        .filter((branch) => branch.name || branch.address)
        .sort((a, b) => Number(b.is_main) - Number(a.is_main));
}

/** The old single address text, one branch a line, for when no branches are saved. */
export function addressLines(settings, isEn) {
    const raw = (isEn && settings?.address_en) || settings?.contact_address || settings?.address || '';
    return String(raw)
        .split(/\r?\n/)
        .map((line) => line.replace(/\s*-\s*$/, '').trim())
        .filter(Boolean);
}
