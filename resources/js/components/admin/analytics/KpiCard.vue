<template>
    <el-card
        shadow="hover"
        class="kpi-card"
        :class="{ 'kpi-card--clickable': !!to }"
        @click="to && $router.push(to)"
    >
        <div class="kpi-card__body">
            <div class="kpi-card__icon" :style="{ background: color }">
                <el-icon :size="24" color="#fff"><component :is="icon" /></el-icon>
            </div>

            <div class="kpi-card__text">
                <p class="kpi-card__label">{{ label }}</p>

                <el-skeleton v-if="loading" animated :rows="1" class="kpi-card__skeleton" />
                <strong v-else class="kpi-card__value">{{ value }}</strong>

                <!--
                    The delta is rendered only when the backend actually computed
                    one. These cards used to carry `+12.5%` written into the
                    template — identical on every install, every day, whatever
                    the data said — and a number nobody computed gets read as a
                    fact and acted on.
                -->
                <span v-if="!loading && hasDelta" class="kpi-card__delta" :class="`is-${comparison.direction}`">
                    <el-icon :size="12"><component :is="deltaIcon" /></el-icon>
                    <span>{{ deltaText }}</span>
                    <span class="kpi-card__delta-caption">{{ $t('analytics.vs_previous_period') }}</span>
                </span>

                <!-- Says why there is no comparison, rather than showing 0%. -->
                <span v-else-if="!loading && comparison" class="kpi-card__delta is-unknown">
                    {{ $t('analytics.no_previous_period') }}
                </span>

                <span v-else-if="!loading && caption" class="kpi-card__caption">{{ caption }}</span>
            </div>
        </div>
    </el-card>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { CaretTop, CaretBottom, Minus } from '@element-plus/icons-vue';

/**
 * One headline figure, and honestly how it moved.
 *
 * `comparison` is the shape `PeriodComparison::compare()` returns on the server:
 * `{ current, previous, change, change_percent, direction }`. `change_percent`
 * is null when the previous period was zero — growth from nothing is not a
 * percentage — so the card shows the direction without inventing a ratio.
 */
const props = defineProps({
    label: { type: String, required: true },
    /** Pre-formatted for display: money through useCurrency, counts localised. */
    value: { type: [String, Number], default: '—' },
    icon: { type: [Object, Function, String], default: null },
    color: { type: String, default: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' },
    /** Router target; the card becomes clickable when given. */
    to: { type: String, default: '' },
    comparison: { type: Object, default: null },
    /** Secondary line for cards that have no comparison to show. */
    caption: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    /**
     * Whether a rise is good. False for costs and expenses, where growth should
     * read red — colouring every increase green is how a rising expense card
     * ends up looking like good news.
     */
    riseIsGood: { type: Boolean, default: true },
});

const { locale } = useI18n();

const hasDelta = computed(() => (
    props.comparison && props.comparison.change_percent !== null && props.comparison.change_percent !== undefined
));

const deltaIcon = computed(() => {
    if (props.comparison?.direction === 'up') return CaretTop;
    if (props.comparison?.direction === 'down') return CaretBottom;
    return Minus;
});

const deltaText = computed(() => {
    const percent = Number(props.comparison?.change_percent ?? 0);
    const formatted = new Intl.NumberFormat(locale.value === 'en' ? 'en-US' : 'ar-SY', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    }).format(Math.abs(percent));

    const sign = percent > 0 ? '+' : (percent < 0 ? '−' : '');
    return `${sign}${formatted}%`;
});
</script>

<style scoped>
.kpi-card {
    border: none;
    border-radius: 14px;
    height: 100%;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kpi-card--clickable {
    cursor: pointer;
}

.kpi-card--clickable:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 28px rgba(102, 126, 234, 0.18) !important;
}

.kpi-card__body {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.kpi-card__icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
}

.kpi-card__text {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.kpi-card__label {
    margin: 0;
    color: #6b7280;
    font-size: 0.85rem;
}

.kpi-card__value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a202c;
    line-height: 1.25;
    word-break: break-word;
}

.kpi-card__skeleton {
    width: 70%;
    padding-top: 0.35rem;
}

.kpi-card__delta {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.78rem;
    font-weight: 600;
    flex-wrap: wrap;
}

/* Direction is coloured by whether it is good news for *this* metric. */
.kpi-card__delta.is-up { color: v-bind('props.riseIsGood ? "#16a34a" : "#dc2626"'); }
.kpi-card__delta.is-down { color: v-bind('props.riseIsGood ? "#dc2626" : "#16a34a"'); }
.kpi-card__delta.is-flat { color: #64748b; }
.kpi-card__delta.is-unknown { color: #94a3b8; font-weight: 500; }

.kpi-card__delta-caption {
    color: #94a3b8;
    font-weight: 400;
}

.kpi-card__caption {
    font-size: 0.78rem;
    color: #94a3b8;
}

@media (max-width: 576px) {
    .kpi-card__value {
        font-size: 1.25rem;
    }
}
</style>
