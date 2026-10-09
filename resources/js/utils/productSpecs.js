// A product's specifications, as the storefront's table shows them and the
// admin price list repeats them.

/** A lone "Label: value" line counts as specs only with a label this short. */
const SINGLE_LINE_LABEL_MAX = 40;

/**
 * Descriptions written as "Label: value • Label: value" (or one pair per line)
 * read as a specs table. Anything else is prose and gives no rows. A single
 * pair counts too, as long as its label is short enough to be a label rather
 * than a sentence that happens to contain a colon.
 */
export function parseDescriptionSpecs(text) {
    const parts = String(text || '').trim().split(/\s*[•\n]\s*/).map((s) => s.trim()).filter(Boolean);
    if (!parts.length) return [];
    if (parts.length === 1 && parts[0].indexOf(':') > SINGLE_LINE_LABEL_MAX) return [];
    const rows = [];
    for (const part of parts) {
        const idx = part.indexOf(':');
        if (idx <= 0 || idx === part.length - 1) return [];
        rows.push({ label: part.slice(0, idx).trim(), value: part.slice(idx + 1).trim() });
    }
    return rows;
}

/**
 * The price list's reading of a description: a supplier sheet such as Ingco's
 * mixes "Label: value" lines with bare ones ("بطارية ليثيوم"), which the strict
 * reading above treats as prose and drops whole. Here each line stays a row —
 * a pair where it has a short label, a label-less line where it does not.
 */
export function parseDescriptionLines(text) {
    const strict = parseDescriptionSpecs(text);
    if (strict.length) return strict;
    return String(text || '').split(/\s*[•\n]\s*/).map((s) => s.trim()).filter(Boolean).map((line) => {
        const idx = line.indexOf(':');
        return idx > 0 && idx <= SINGLE_LINE_LABEL_MAX && idx < line.length - 1
            ? { label: line.slice(0, idx).trim(), value: line.slice(idx + 1).trim() }
            : { label: '', value: line };
    });
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

/**
 * The other way: rows back to the description text the storefront parses.
 * Rows without a value are dropped; an empty list is no description at all.
 * A label-less row is written as a bare line, and then the list is one line
 * per row, the way the supplier sheets lay it out; an all-pairs list keeps
 * the "Label: value • …" form it always had.
 */
export function serializeDescriptionSpecs(rows) {
    const clean = (Array.isArray(rows) ? rows : [])
        .map((r) => ({ label: String(r?.label ?? '').trim(), value: String(r?.value ?? '').trim() }))
        .filter((r) => r.value);
    const text = clean
        .map((r) => (r.label ? `${r.label}: ${r.value}` : r.value))
        .join(clean.every((r) => r.label) ? ' • ' : '\n');
    return text || null;
}
