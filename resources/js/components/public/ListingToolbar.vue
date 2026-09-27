<template>
    <!-- Search, sort and stock for a storefront listing. The page owns the
         state (in its URL); this only draws it and reports changes. -->
    <div class="listing-toolbar">
        <div class="toolbar-search">
            <i class="fas fa-search"></i>
            <input
                :value="search"
                type="search"
                :placeholder="placeholder"
                :aria-label="t('search')"
                enterkeyhint="search"
                @input="emit('update:search', $event.target.value)"
            >
            <button v-if="search" type="button" class="search-clear" :aria-label="t('clear')" @click="emit('update:search', '')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <label class="toolbar-select">
            <span>{{ t('sort_by') }}</span>
            <select :value="sort" @change="emit('update:sort', $event.target.value)">
                <option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
        </label>
        <label class="toolbar-toggle">
            <input type="checkbox" :checked="stock" @change="emit('update:stock', $event.target.checked)">
            <span>{{ t('catp_in_stock_only') }}</span>
        </label>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';

defineProps({
    search: { type: String, default: '' },
    sort: { type: String, required: true },
    stock: { type: Boolean, default: false },
    /** [{ value, label }] */
    sortOptions: { type: Array, required: true },
    placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:search', 'update:sort', 'update:stock']);
const { t } = useI18n();
</script>

<style scoped>
.listing-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-top: 1.25rem;
    padding: 12px;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.55);
    border: 1px solid rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
}

[data-theme="dark"] .listing-toolbar {
    background: rgba(30, 41, 59, 0.35);
    border-color: rgba(255, 255, 255, 0.06);
}

.toolbar-search {
    position: relative;
    flex: 1 1 260px;
    display: flex;
    align-items: center;
}

.toolbar-search > i {
    position: absolute;
    inset-inline-start: 14px;
    color: #94a3b8;
    pointer-events: none;
}

.toolbar-search input {
    width: 100%;
    height: 44px;
    padding-inline: 40px 38px;
    border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    background: #fff;
    font: inherit;
    color: #1e293b;
}

.toolbar-search input:focus,
.toolbar-select select:focus {
    outline: 2px solid color-mix(in srgb, var(--mobile-primary) 45%, transparent);
    outline-offset: 1px;
}

/* The browser's own clear button would sit beside ours. */
.toolbar-search input::-webkit-search-cancel-button {
    display: none;
}

.search-clear {
    position: absolute;
    inset-inline-end: 8px;
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
}

.search-clear:hover {
    background: rgba(0, 0, 0, 0.05);
    color: #475569;
}

.toolbar-select {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    color: #64748b;
}

.toolbar-select select {
    height: 44px;
    padding-inline: 12px 32px;
    border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    background-color: #fff;
    font: inherit;
    color: #1e293b;
    cursor: pointer;
}

.toolbar-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 12px;
    border-radius: 12px;
    font-size: 0.88rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    user-select: none;
}

.toolbar-toggle input {
    width: 18px;
    height: 18px;
    accent-color: var(--mobile-primary);
}

[data-theme="dark"] .toolbar-search input,
[data-theme="dark"] .toolbar-select select {
    background-color: #0f172a;
    border-color: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

[data-theme="dark"] .toolbar-toggle {
    color: #e2e8f0;
}

@media (max-width: 640px) {
    .listing-toolbar {
        padding: 10px;
    }

    .toolbar-select {
        flex: 1 1 auto;
    }

    .toolbar-select span {
        display: none;
    }

    .toolbar-select select {
        width: 100%;
    }
}
</style>
