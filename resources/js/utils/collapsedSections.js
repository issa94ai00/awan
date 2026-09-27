import { reactive, computed } from 'vue';

/**
 * Which report sections are folded away, remembered per browser. A table
 * someone never reads stays out of their way on the next visit; one they fold
 * on the invoices tab is folded on the orders tab too, since it is the same
 * table there.
 */
const STORAGE_KEY = 'admin_report_collapsed_sections';

function load() {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        return saved && typeof saved === 'object' ? saved : {};
    } catch {
        return {};
    }
}

const collapsed = reactive(load());

// The sections on screen right now, so "collapse all" folds what is there.
const mounted = reactive(new Map());

function persist() {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(collapsed));
    } catch {
        // Private mode or storage full: the choice just lasts the visit.
    }
}

export function isCollapsed(id) {
    return !!collapsed[id];
}

export function setCollapsed(id, value) {
    if (value) collapsed[id] = true;
    else delete collapsed[id];
    persist();
}

export function registerSection(id) {
    mounted.set(id, (mounted.get(id) || 0) + 1);
}

export function unregisterSection(id) {
    const count = (mounted.get(id) || 0) - 1;
    if (count > 0) mounted.set(id, count);
    else mounted.delete(id);
}

/** True while every section on screen is folded. */
export const allCollapsed = computed(() => mounted.size > 0 && [...mounted.keys()].every((id) => collapsed[id]));

export function setAllCollapsed(value) {
    for (const id of mounted.keys()) {
        if (value) collapsed[id] = true;
        else delete collapsed[id];
    }
    persist();
}
