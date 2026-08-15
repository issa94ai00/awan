<template>
    <header class="admin-page-header">
        <div class="page-title">
            <span v-if="badge" class="title-badge">{{ badge }}</span>
            <h1>
                <i v-if="icon" :class="icon" aria-hidden="true"></i>
                {{ title }}
            </h1>
            <p v-if="subtitle">{{ subtitle }}</p>
        </div>

        <div v-if="$slots.actions" class="toolbar-actions">
            <slot name="actions" />
        </div>
    </header>
</template>

<script setup>
/**
 * The header every admin page opens with.
 *
 * Each page used to carry its own copy of this markup and its own `.page-header`
 * rules, so the same heading came out at four different sizes, the action
 * buttons sat in a different order on every screen, and on a narrow window the
 * title and the buttons fought over one row. Owning it here is what makes the
 * section look like one product.
 *
 * Actions go in the `actions` slot, most important last (RTL reads the row from
 * the right, so the primary action lands nearest the reader's thumb).
 */
defineProps({
    /** Short section label above the title, e.g. "CRM", "WMS". */
    badge: { type: String, default: '' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    /** Icon classes for the title, e.g. "fas fa-file-signature". */
    icon: { type: String, default: '' },
});
</script>

<style scoped>
.admin-page-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-end;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.page-title {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
}

.title-badge {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.12), rgba(118, 75, 162, 0.12));
    color: #4f46e5;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.page-title h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: #1f2d3d;
    line-height: 1.3;
}

.page-title p {
    margin: 0;
    color: #64748b;
    font-size: 0.9rem;
}

.toolbar-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* Below the tablet break the buttons take the full row rather than wrapping
   one at a time under a half-empty title. */
@media (max-width: 768px) {
    .admin-page-header {
        align-items: stretch;
    }

    .toolbar-actions {
        width: 100%;
    }

    .toolbar-actions > * {
        flex: 1 1 auto;
    }

    .page-title h1 {
        font-size: 1.35rem;
    }
}
</style>
