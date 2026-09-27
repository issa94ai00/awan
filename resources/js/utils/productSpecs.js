// A product's specifications, as the storefront's table shows them and the
// admin price list repeats them.

/**
 * Descriptions written as "Label: value • Label: value" (or one pair per line)
 * read as a specs table. Anything else is prose and gives no rows.
 */
export function parseDescriptionSpecs(text) {
    const parts = String(text || '').trim().split(/\s*[•\n]\s*/).map((s) => s.trim()).filter(Boolean);
    if (parts.length < 2) return [];
    const rows = [];
    for (const part of parts) {
        const idx = part.indexOf(':');
        if (idx <= 0 || idx === part.length - 1) return [];
        rows.push({ label: part.slice(0, idx).trim(), value: part.slice(idx + 1).trim() });
    }
    return rows;
}

/**
 * A variant's own details over its product's: a line with the same label
 * replaces the product's ("Power" of this size, not of the range), the rest
 * follow after it.
 */
export function mergeSpecs(base, own) {
    const mine = (Array.isArray(own) ? own : []).filter((r) => r && String(r.value || '').trim());
    if (!mine.length) return base;
    const rows = base.map((r) => ({ ...r }));
    for (const spec of mine) {
        const label = String(spec.label || '').trim();
        const at = label ? rows.findIndex((r) => r.label === label) : -1;
        if (at !== -1) rows[at] = { label, value: spec.value };
        else rows.push({ label, value: spec.value });
    }
    return rows;
}
