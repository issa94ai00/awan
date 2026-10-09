<template>
    <!-- Two rows: the pages themselves, then where you are, a jump box and the
         page size. A window of pages around the current one rather than a
         button for every page — the full catalogue runs to over four hundred.
         Each is a real link, so pages open in a new tab and crawlers can follow
         them. On a phone the numbers give way to "Page 3 of 183" and the jump
         box, which is how you get anywhere on a list that long. -->
    <div v-if="visible" class="listing-pagination">
        <nav v-if="lastPage > 1" class="page-nav" :aria-label="t('catp_pagination')">
            <router-link
                v-if="current > 1"
                :to="linkFor(current - 1)"
                class="page-btn page-step"
                rel="prev"
                :aria-label="t('previous')"
            >
                <i :class="prevIcon" aria-hidden="true"></i>
                <span class="step-label">{{ t('previous') }}</span>
            </router-link>
            <span v-else class="page-btn page-step disabled" aria-hidden="true">
                <i :class="prevIcon"></i>
                <span class="step-label">{{ t('previous') }}</span>
            </span>

            <ol class="page-list">
                <li v-for="item in items" :key="item.key">
                    <span v-if="item.gap" class="page-gap" aria-hidden="true">…</span>
                    <span
                        v-else-if="item.page === current"
                        class="page-btn active"
                        aria-current="page"
                        :aria-label="t('pg_page_n', { page: formatCount(item.page) })"
                    >{{ formatCount(item.page) }}</span>
                    <router-link
                        v-else
                        :to="linkFor(item.page)"
                        class="page-btn"
                        :aria-label="t('pg_page_n', { page: formatCount(item.page) })"
                    >{{ formatCount(item.page) }}</router-link>
                </li>
            </ol>
            <span class="page-of">
                <strong>{{ formatCount(current) }}</strong>
                <span class="page-of-sep">/</span>
                {{ formatCount(lastPage) }}
            </span>

            <router-link
                v-if="current < lastPage"
                :to="linkFor(current + 1)"
                class="page-btn page-step page-step--next"
                rel="next"
                :aria-label="t('next')"
            >
                <span class="step-label">{{ t('next') }}</span>
                <i :class="nextIcon" aria-hidden="true"></i>
            </router-link>
            <span v-else class="page-btn page-step page-step--next disabled" aria-hidden="true">
                <span class="step-label">{{ t('next') }}</span>
                <i :class="nextIcon"></i>
            </span>
        </nav>

        <div class="page-footer">
            <p v-if="total" class="page-range">
                {{ t('catp_range', { from: formatCount(rangeFrom), to: formatCount(rangeTo), total: formatCount(total) }) }}
            </p>

            <div class="page-controls">
                <form v-if="lastPage > JUMP_FROM" class="page-jump" @submit.prevent="jump">
                    <label :for="jumpId">{{ t('pg_go_to') }}</label>
                    <input
                        :id="jumpId"
                        v-model="target"
                        type="number"
                        inputmode="numeric"
                        min="1"
                        :max="lastPage"
                        :placeholder="String(current)"
                        enterkeyhint="go"
                    >
                    <button type="submit" :disabled="!validTarget">{{ t('pg_go') }}</button>
                </form>

                <!-- Shown even when everything fits one page, so a size picked
                     earlier can be put back. -->
                <label class="per-page">
                    <span>{{ t('pg_show') }}</span>
                    <select :value="per" :aria-label="t('catp_per_page_label')" @change="emit('update:per', Number($event.target.value))">
                        <option v-for="size in perOptions" :key="size" :value="size">{{ t('catp_per_page', { count: formatCount(size) }) }}</option>
                    </select>
                </label>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch, useId } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { PER_PAGE_OPTIONS } from '@/Composables/useListingQuery';

const props = defineProps({
    /** { current_page, last_page, per_page, total } as the API returns it. */
    pagination: { type: Object, required: true },
    /** page → route location */
    linkFor: { type: Function, required: true },
    per: { type: Number, required: true },
    perOptions: { type: Array, default: () => PER_PAGE_OPTIONS },
});

const emit = defineEmits(['update:per']);
const { t, locale } = useI18n();
const router = useRouter();

/** Below this many pages the numbers already reach every page. */
const JUMP_FROM = 7;

const current = computed(() => props.pagination.current_page || 1);
const lastPage = computed(() => props.pagination.last_page || 1);
const total = computed(() => Number(props.pagination.total || 0));
const pageSize = computed(() => props.pagination.per_page || props.per);

const rangeFrom = computed(() => (current.value - 1) * pageSize.value + 1);
const rangeTo = computed(() => Math.min(current.value * pageSize.value, total.value));

// Nothing to page through and nothing a page size would change.
const visible = computed(() => lastPage.value > 1 || total.value > Math.min(...props.perOptions));

/** First, last, and two either side of the current page; gaps in between. */
const items = computed(() => {
    const pages = new Set([1, lastPage.value]);
    for (let p = current.value - 2; p <= current.value + 2; p++) {
        if (p >= 1 && p <= lastPage.value) pages.add(p);
    }
    const sorted = [...pages].sort((a, b) => a - b);
    const out = [];
    sorted.forEach((page, index) => {
        if (index && page - sorted[index - 1] > 1) out.push({ key: `gap-${page}`, gap: true });
        out.push({ key: page, page });
    });
    return out;
});

/* Jump to a page */
const jumpId = useId();
const target = ref('');
const targetPage = computed(() => Number.parseInt(target.value, 10));
const validTarget = computed(() => Number.isFinite(targetPage.value) && targetPage.value >= 1);

const jump = () => {
    if (!validTarget.value) return;
    // Past the end goes to the end, rather than to an empty page.
    const page = Math.min(targetPage.value, lastPage.value);
    target.value = '';
    if (page !== current.value) router.push(props.linkFor(page));
};

watch(current, () => { target.value = ''; });

// "Previous" points back along the reading direction: right in Arabic, left
// in English.
const isRtl = computed(() => locale.value === 'ar');
const prevIcon = computed(() => (isRtl.value ? 'fas fa-chevron-right' : 'fas fa-chevron-left'));
const nextIcon = computed(() => (isRtl.value ? 'fas fa-chevron-left' : 'fas fa-chevron-right'));

const formatCount = (value) => Number(value || 0).toLocaleString(isRtl.value ? 'ar-SY' : 'en-US');
</script>

<style scoped>
.listing-pagination {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-top: 40px;
    padding: 16px 18px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(15, 23, 42, 0.06);
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.05);
}

[data-theme="dark"] .listing-pagination {
    background: rgba(30, 41, 59, 0.55);
    border-color: rgba(255, 255, 255, 0.06);
    box-shadow: none;
}

/* ── Pages ── */
.page-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.page-list {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.page-btn {
    min-width: 44px;
    height: 44px;
    padding: 0 12px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 700;
    color: #334155;
    text-decoration: none;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    font-variant-numeric: tabular-nums;
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

[data-theme="dark"] .page-btn {
    background: rgba(15, 23, 42, 0.6);
    color: #e2e8f0;
    border-color: rgba(255, 255, 255, 0.08);
}

a.page-btn:hover {
    border-color: var(--mobile-primary);
    color: var(--mobile-primary);
    background: color-mix(in srgb, var(--mobile-primary) 8%, #fff);
}

[data-theme="dark"] a.page-btn:hover {
    color: #fff;
    background: color-mix(in srgb, var(--mobile-primary) 30%, transparent);
}

a.page-btn:focus-visible,
.page-jump input:focus-visible,
.page-jump button:focus-visible,
.per-page select:focus-visible {
    outline: 2px solid var(--mobile-primary);
    outline-offset: 2px;
}

/* White on the brand colour in both themes: the dark theme used to set the
   number in near-black on it. */
.page-btn.active,
[data-theme="dark"] .page-btn.active {
    background: var(--mobile-primary);
    border-color: var(--mobile-primary);
    color: #fff;
    box-shadow: 0 6px 16px color-mix(in srgb, var(--mobile-primary) 30%, transparent);
}

.page-step {
    padding: 0 16px;
}

/* Previous at the start, Next at the end, the numbers centred between. */
.page-step:first-child {
    margin-inline-end: auto;
}

.page-step--next {
    margin-inline-start: auto;
}

.page-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.page-gap {
    min-width: 20px;
    text-align: center;
    color: #94a3b8;
    font-weight: 700;
}

/* Shown only where the numbers are not. */
.page-of {
    display: none;
    align-items: baseline;
    gap: 6px;
    font-size: 1rem;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}

.page-of strong {
    font-size: 1.15rem;
    color: #0f172a;
}

[data-theme="dark"] .page-of strong {
    color: #f1f5f9;
}

.page-of-sep {
    color: #cbd5e1;
}

/* ── Footer: where you are, jump, page size ── */
.page-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px 16px;
    padding-top: 14px;
    border-top: 1px solid rgba(15, 23, 42, 0.06);
}

.listing-pagination > .page-footer:first-child {
    padding-top: 0;
    border-top: none;
}

[data-theme="dark"] .page-footer {
    border-color: rgba(255, 255, 255, 0.06);
}

.page-range {
    margin: 0;
    font-size: 0.88rem;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}

.page-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 16px;
}

.page-jump,
.per-page {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
    color: #475569;
    white-space: nowrap;
}

[data-theme="dark"] .page-jump,
[data-theme="dark"] .per-page {
    color: #cbd5e1;
}

.page-jump input,
.per-page select {
    height: 40px;
    border-radius: 10px;
    border: 1px solid rgba(15, 23, 42, 0.12);
    background-color: #fff;
    font: inherit;
    color: #1e293b;
}

[data-theme="dark"] .page-jump input,
[data-theme="dark"] .per-page select {
    background-color: #0f172a;
    border-color: rgba(255, 255, 255, 0.12);
    color: #e2e8f0;
}

.page-jump input {
    width: 76px;
    padding: 0 10px;
    text-align: center;
    font-variant-numeric: tabular-nums;
    -moz-appearance: textfield;
}

.page-jump input::-webkit-outer-spin-button,
.page-jump input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.page-jump button {
    height: 40px;
    padding: 0 16px;
    border: none;
    border-radius: 10px;
    background: var(--mobile-primary);
    color: #fff;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}

.page-jump button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.per-page select {
    padding-inline: 12px 32px;
    cursor: pointer;
}

@media (prefers-reduced-motion: reduce) {
    .page-btn {
        transition: none;
    }
}

/* ── Tablet: the step labels go, the numbers stay ── */
@media (max-width: 900px) {
    .step-label {
        display: none;
    }

    .page-step {
        padding: 0;
    }
}

/* ── Phone: Prev · 3 / 183 · Next, and the jump box does the rest ── */
@media (max-width: 640px) {
    .listing-pagination {
        padding: 14px;
    }

    .page-list {
        display: none;
    }

    .page-of {
        display: inline-flex;
    }

    .page-footer {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }

    .page-controls {
        justify-content: space-between;
    }

    .page-jump input {
        width: 64px;
    }
}

@media (max-width: 380px) {
    .page-controls {
        flex-direction: column;
        align-items: stretch;
    }

    .page-jump,
    .per-page {
        justify-content: center;
    }
}
</style>
