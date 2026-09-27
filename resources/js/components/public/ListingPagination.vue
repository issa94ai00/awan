<template>
    <!-- A window of pages around the current one rather than a button for
         every page — a category can run to over a hundred and eighty. Each is
         a real link, so pages open in a new tab and crawlers can follow them.
         On a phone the numbers give way to "Page 3 of 183". -->
    <nav v-if="lastPage > 1" class="listing-pagination" :aria-label="t('catp_pagination')">
        <router-link v-if="current > 1" :to="linkFor(current - 1)" class="page-btn page-step" rel="prev">
            <i :class="prevIcon"></i>
            <span class="step-label">{{ t('previous') }}</span>
        </router-link>
        <span v-else class="page-btn page-step disabled" aria-hidden="true">
            <i :class="prevIcon"></i>
            <span class="step-label">{{ t('previous') }}</span>
        </span>

        <ol class="page-list">
            <li v-for="item in items" :key="item.key">
                <span v-if="item.gap" class="page-gap" aria-hidden="true">…</span>
                <span v-else-if="item.page === current" class="page-btn active" aria-current="page">{{ formatCount(item.page) }}</span>
                <router-link v-else :to="linkFor(item.page)" class="page-btn">{{ formatCount(item.page) }}</router-link>
            </li>
        </ol>
        <span class="page-of">{{ t('catp_page_of', { page: formatCount(current), pages: formatCount(lastPage) }) }}</span>

        <router-link v-if="current < lastPage" :to="linkFor(current + 1)" class="page-btn page-step" rel="next">
            <span class="step-label">{{ t('next') }}</span>
            <i :class="nextIcon"></i>
        </router-link>
        <span v-else class="page-btn page-step disabled" aria-hidden="true">
            <span class="step-label">{{ t('next') }}</span>
            <i :class="nextIcon"></i>
        </span>

        <label class="per-page">
            <select :value="per" :aria-label="t('catp_per_page_label')" @change="emit('update:per', Number($event.target.value))">
                <option v-for="size in perOptions" :key="size" :value="size">{{ t('catp_per_page', { count: size }) }}</option>
            </select>
        </label>
    </nav>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { PER_PAGE_OPTIONS } from '@/Composables/useListingQuery';

const props = defineProps({
    /** { current_page, last_page } as the API returns it. */
    pagination: { type: Object, required: true },
    /** page → route location */
    linkFor: { type: Function, required: true },
    per: { type: Number, required: true },
    perOptions: { type: Array, default: () => PER_PAGE_OPTIONS },
});

const emit = defineEmits(['update:per']);
const { t, locale } = useI18n();

const current = computed(() => props.pagination.current_page || 1);
const lastPage = computed(() => props.pagination.last_page || 1);

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
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 40px;
    padding: 12px 16px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(10px);
}

[data-theme="dark"] .listing-pagination {
    background: rgba(30, 41, 59, 0.3);
    border-color: rgba(255, 255, 255, 0.05);
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
    min-width: 42px;
    height: 42px;
    padding: 0 10px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 600;
    color: #475569;
    text-decoration: none;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(0, 0, 0, 0.05);
    font-variant-numeric: tabular-nums;
    transition: all 0.2s ease;
}

[data-theme="dark"] .page-btn {
    background: rgba(30, 41, 59, 0.5);
    color: #f1f5f9;
    border-color: rgba(255, 255, 255, 0.05);
}

a.page-btn:hover {
    background: var(--mobile-primary);
    border-color: var(--mobile-primary);
    color: #fff !important;
}

a.page-btn:focus-visible {
    outline: 2px solid var(--mobile-primary);
    outline-offset: 2px;
}

.page-btn.active {
    background: var(--mobile-primary);
    border-color: var(--mobile-primary);
    color: #fff !important;
}

[data-theme="dark"] .page-btn.active {
    color: #0f172a !important;
}

.page-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.page-gap {
    padding: 0 4px;
    color: #94a3b8;
}

/* Shown only where the numbers are not. */
.page-of {
    display: none;
    font-size: 0.9rem;
    font-weight: 600;
    color: #475569;
}

[data-theme="dark"] .page-of {
    color: #cbd5e1;
}

.per-page {
    margin-inline-start: 8px;
}

.per-page select {
    height: 42px;
    padding-inline: 12px 32px;
    border-radius: 10px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    background-color: #fff;
    font: inherit;
    color: #1e293b;
    cursor: pointer;
}

[data-theme="dark"] .per-page select {
    background-color: #0f172a;
    border-color: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

@media (max-width: 640px) {
    .page-list {
        display: none;
    }

    .page-of {
        display: inline;
        flex: 1;
        text-align: center;
    }

    .per-page {
        flex-basis: 100%;
        display: flex;
        justify-content: center;
        margin: 4px 0 0;
    }
}
</style>
