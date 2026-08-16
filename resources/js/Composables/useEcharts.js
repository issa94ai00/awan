import { onUnmounted, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import * as echarts from 'echarts';

/**
 * ECharts instances that survive the things that actually break them.
 *
 * Every analytics screen wrote its own copy of init/dispose/resize, and each
 * copy missed the same three cases:
 *
 *   - **Language.** Series names, axis titles and legends are baked into the
 *     option object when the chart is drawn, so a language switch left every
 *     chart labelled in the old one until a reload.
 *   - **Width.** Charts follow the window, but the sidebar collapsing is not a
 *     window resize, and a chart drawn inside a tab that was still hidden keeps
 *     the zero width it was initialised at.
 *   - **Re-mounted containers.** A cached instance bound to a node that has
 *     been replaced paints into nothing, and the card just shows an empty box.
 *
 * Register charts with `register(key, elRef, optionFactory)`. The factory is
 * re-run — not the cached option object re-applied — whenever the language
 * changes, which is what makes the labels follow.
 */
export function useEcharts() {
    const { locale } = useI18n();

    const instances = {};
    const factories = {};

    let resizeObserver = null;
    let resizeFrame = null;

    const draw = (key) => {
        const entry = factories[key];
        if (!entry) return;

        const element = entry.elRef?.value;
        if (!element) return;

        // A hidden container measures zero; drawing into it produces a chart
        // that stays invisible even after the pane opens.
        if (!element.offsetWidth && !element.offsetHeight) return;

        const existing = instances[key];
        if (existing && existing.getDom() !== element) {
            existing.dispose();
            delete instances[key];
        }

        if (!instances[key]) {
            instances[key] = echarts.init(element);
        }

        instances[key].setOption(entry.optionFactory(), true);
        instances[key].resize();
    };

    /**
     * Declares a chart. Call `render(key)` (or `renderAll()`) once the data is in.
     *
     * @param {string} key
     * @param {import('vue').Ref<HTMLElement|null>} elRef
     * @param {() => object} optionFactory Re-invoked on every draw, so it must
     *   read its labels and data fresh rather than closing over a snapshot.
     */
    const register = (key, elRef, optionFactory) => {
        factories[key] = { elRef, optionFactory };
    };

    const render = async (key) => {
        await nextTick();
        draw(key);
    };

    const renderAll = async () => {
        await nextTick();
        Object.keys(factories).forEach(draw);
    };

    const resizeAll = () => {
        Object.values(instances).forEach((chart) => chart && chart.resize());
    };

    /** Coalesced to one call per frame so dragging a window edge stays cheap. */
    const scheduleResize = () => {
        if (resizeFrame) cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(() => {
            resizeFrame = null;
            resizeAll();
        });
    };

    /**
     * Watches `element` for width changes — which covers the sidebar collapsing,
     * a tab opening and the window resizing in one mechanism.
     */
    const observe = (element) => {
        if (!element) return;

        if (typeof ResizeObserver === 'undefined') {
            window.addEventListener('resize', scheduleResize);
            return;
        }

        resizeObserver = new ResizeObserver(scheduleResize);
        resizeObserver.observe(element);
    };

    watch(locale, () => {
        renderAll();
    });

    const teardown = () => {
        if (resizeObserver) {
            resizeObserver.disconnect();
            resizeObserver = null;
        } else {
            window.removeEventListener('resize', scheduleResize);
        }

        if (resizeFrame) cancelAnimationFrame(resizeFrame);

        Object.values(instances).forEach((chart) => chart && chart.dispose());
    };

    onUnmounted(teardown);

    return { register, render, renderAll, resizeAll, observe, teardown };
}

/**
 * Chart colours, named by what they mean rather than by hue.
 *
 * Keeps one palette across the module: the screens each picked their own hex
 * values, so "completed" was three different greens depending on the page.
 */
export const CHART_COLORS = {
    primary: '#667eea',
    accent: '#8c6dfd',
    success: '#67c23a',
    warning: '#e6a23c',
    danger: '#f56c6c',
    info: '#409eff',
    neutral: '#909399',
    series: ['#667eea', '#67c23a', '#e6a23c', '#f56c6c', '#8c6dfd', '#409eff', '#13c2c2', '#909399'],
};

/** A donut, the shape most of these breakdowns want. */
export const donutOption = (items, { formatter = null } = {}) => ({
    tooltip: { trigger: 'item', valueFormatter: formatter || undefined },
    legend: { bottom: 0, textStyle: { fontSize: 12 } },
    series: [{
        type: 'pie',
        radius: ['55%', '78%'],
        avoidLabelOverlap: true,
        itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 2 },
        label: { show: false },
        data: items.map((item, index) => ({
            name: item.name,
            value: item.value,
            itemStyle: { color: item.color || CHART_COLORS.series[index % CHART_COLORS.series.length] },
        })),
    }],
});

/** The empty-state option: an explicit message rather than a blank rectangle. */
export const emptyChartOption = (message) => ({
    title: {
        text: message,
        left: 'center',
        top: 'center',
        textStyle: { color: '#a8b1c2', fontSize: 13, fontWeight: 'normal' },
    },
});
