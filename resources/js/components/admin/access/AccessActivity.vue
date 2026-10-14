<template>
    <div v-loading="loading && !entries.length" class="access-activity">
        <ol v-if="entries.length" class="timeline">
            <li v-for="entry in entries" :key="entry.id" class="entry" :class="entry.action">
                <span class="dot"><el-icon><component :is="iconFor(entry.action)" /></el-icon></span>
                <div class="entry-body">
                    <p class="entry-text">{{ entry.description }}</p>
                    <div class="entry-meta">
                        <span class="actor">{{ entry.actor?.name || $t('ac_history_system') }}</span>
                        <span>·</span>
                        <time :datetime="entry.created_at" :title="absolute(entry.created_at)">{{ relative(entry.created_at) }}</time>
                        <span v-if="entry.added" class="delta added">+{{ entry.added }}</span>
                        <span v-if="entry.removed" class="delta removed">−{{ entry.removed }}</span>
                    </div>
                </div>
            </li>
        </ol>
        <el-empty v-else-if="!loading" :description="failed ? $t('ac_history_failed') : $t('ac_history_empty')" :image-size="80" />
        <div v-if="page < lastPage" class="more">
            <el-button text type="primary" :loading="loading" @click="load(page + 1)">{{ $t('ac_history_more') }}</el-button>
        </div>
    </div>
</template>

<script setup>
// The access-control history for one role, one account, or everything —
// read from the audit log by /admin/access/activity.
import { onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { CirclePlus, Delete, EditPen } from '@element-plus/icons-vue';
import accessApi from '@/api/access';

const props = defineProps({
    params: { type: Object, default: () => ({}) },
    // Bump to reload from the first page after a change made elsewhere.
    reloadKey: { type: Number, default: 0 },
});

const { locale } = useI18n();
const entries = ref([]);
const page = ref(0);
const lastPage = ref(1);
const loading = ref(false);
const failed = ref(false);

const iconFor = (action) => ({ create: CirclePlus, delete: Delete }[action] || EditPen);

const absolute = (iso) => (iso ? new Date(iso).toLocaleString(locale.value) : '');

function relative(iso) {
    if (!iso) return '';
    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const rtf = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' });
    const steps = [[60, 'second'], [60, 'minute'], [24, 'hour'], [7, 'day']];
    let value = seconds;
    for (const [size, unit] of steps) {
        if (Math.abs(value) < size) return rtf.format(value, unit);
        value = Math.round(value / size);
    }
    return new Date(iso).toLocaleDateString(locale.value);
}

async function load(next = 1) {
    loading.value = true;
    failed.value = false;
    try {
        const { data } = await accessApi.getActivity({ ...props.params, page: next });
        entries.value = next === 1 ? data.data.entries : [...entries.value, ...data.data.entries];
        page.value = data.data.pagination.current_page;
        lastPage.value = data.data.pagination.last_page;
    } catch {
        failed.value = true;
        if (next === 1) entries.value = [];
    } finally {
        loading.value = false;
    }
}

watch(() => [props.reloadKey, JSON.stringify(props.params)], () => load(1));
onMounted(() => load(1));
</script>

<style scoped>
.access-activity {
    min-height: 120px;
}

.timeline {
    list-style: none;
    margin: 0;
    padding: 0;
    position: relative;
}

.entry {
    display: flex;
    gap: 12px;
    position: relative;
    padding-bottom: 16px;
}

.entry:not(:last-child)::before {
    content: '';
    position: absolute;
    inset-inline-start: 15px;
    top: 32px;
    bottom: 0;
    width: 2px;
    background: #e2e8f0;
}

.dot {
    flex: 0 0 32px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #eef2ff;
    color: #4f46e5;
}

.entry.create .dot {
    background: #ecfdf5;
    color: #059669;
}

.entry.delete .dot {
    background: #fef2f2;
    color: #dc2626;
}

.entry-body {
    min-width: 0;
    flex: 1;
    padding-top: 4px;
}

.entry-text {
    margin: 0;
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    overflow-wrap: anywhere;
}

.entry-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
}

.actor {
    font-weight: 600;
    color: #475569;
}

.delta {
    padding: 0 6px;
    border-radius: 999px;
    font-weight: 700;
}

.delta.added {
    color: #059669;
    background: #ecfdf5;
}

.delta.removed {
    color: #dc2626;
    background: #fef2f2;
}

.more {
    text-align: center;
}
</style>
