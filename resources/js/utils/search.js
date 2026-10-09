/**
 * Word-by-word matching for lists filtered in the browser: "خلاط ذهبي"
 * matches "خلاط مغسلة لون ذهبي" — every word has to appear in one of the
 * fields, in any order and with anything between them.
 *
 * Arabic spellings that people type interchangeably are folded together
 * (أ إ آ → ا, ة → ه, ى → ي, harakat and tatweel dropped).
 */
export const normalizeSearchText = (value) => String(value ?? '')
    .toLocaleLowerCase()
    .replace(/[ً-ْـ]/g, '')
    .replace(/[أإآٱ]/g, 'ا')
    .replace(/ة/g, 'ه')
    .replace(/ى/g, 'ي');

export const searchWords = (query) => normalizeSearchText(query).split(/\s+/).filter(Boolean);

/** True when every word of the query is found in at least one of the fields. */
export const matchesSearch = (fields, query) => {
    const words = searchWords(query);
    if (!words.length) return true;
    const haystack = (Array.isArray(fields) ? fields : [fields]).map(normalizeSearchText);
    return words.every((word) => haystack.some((field) => field.includes(word)));
};
