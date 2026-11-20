<template>
  <section v-if="data && data.score !== null" class="shc" dir="rtl" aria-label="صحة النظام">
    <router-link to="/admin/system-health" class="shc-score" :title="'الدرجة ' + data.score + ' من 100'">
      <svg viewBox="0 0 120 120" role="img" :aria-label="`الدرجة ${data.score} من 100`">
        <circle cx="60" cy="60" r="52" fill="none" stroke="#e2e8f0" stroke-width="12" />
        <circle cx="60" cy="60" r="52" fill="none" :stroke="color(data.score)" stroke-width="12" stroke-linecap="round"
                :stroke-dasharray="`${(data.score / 100) * 326.7} 326.7`" transform="rotate(-90 60 60)" />
        <text x="60" y="68" text-anchor="middle" font-size="32" font-weight="800" fill="#0f172a">{{ data.score }}</text>
      </svg>
    </router-link>

    <div class="shc-body">
      <div class="shc-head">
        <b>صحة النظام</b>
        <span v-if="delta !== null && delta !== 0" :class="delta > 0 ? 'up' : 'down'">{{ delta > 0 ? '▲' : '▼' }} {{ Math.abs(delta) }}</span>
        <span class="muted">{{ when }}</span>
        <router-link to="/admin/system-health" class="shc-link">التفاصيل ←</router-link>
      </div>

      <div class="shc-chips">
        <span class="chip c-critical">{{ data.totals.critical || 0 }} حرج</span>
        <span class="chip c-warning">{{ data.totals.warning || 0 }} تحذير</span>
        <span class="chip c-info">{{ data.totals.info || 0 }} ملاحظة</span>
        <span v-for="s in data.sections" :key="s.key" class="chip c-sec">{{ s.label }} <b :style="{ color: color(s.score) }">{{ s.score ?? '—' }}</b></span>
      </div>

      <ul v-if="top.length" class="shc-top">
        <li v-for="i in top" :key="i.id">
          <span class="dot" :class="`c-${i.severity}`"></span>
          <span class="t">{{ i.title }}</span>
          <span class="n">{{ i.count }}</span>
        </li>
      </ul>
      <p v-else class="ok">لا توجد مشاكل مفتوحة.</p>
    </div>

    <svg v-if="points" viewBox="0 0 160 60" class="shc-spark" preserveAspectRatio="none" aria-hidden="true">
      <polyline :points="points" fill="none" :stroke="color(data.score)" stroke-width="2.5" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
    </svg>
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import diagnostics from '@/services/systemDiagnostics';

const data = ref(null);
const color = (s) => (s === null || s === undefined ? '#94a3b8' : s >= 85 ? '#16a34a' : s >= 65 ? '#d97706' : '#dc2626');

const history = computed(() => (data.value?.history || []).filter((h) => h.score !== null).slice(-20));
const delta = computed(() => {
  const h = data.value?.history || [];
  return h.length > 1 && h[h.length - 2].score !== null ? data.value.score - h[h.length - 2].score : null;
});
const points = computed(() => {
  const h = history.value;
  if (h.length < 2) return '';
  const min = Math.min(...h.map((x) => x.score), 60);
  return h.map((x, n) => `${(n / (h.length - 1)) * 160},${54 - ((x.score - min) / (100 - min)) * 48}`).join(' ');
});
const top = computed(() => (data.value?.issues || []).filter((i) => i.severity !== 'info').slice(0, 3));
const when = computed(() => {
  if (!data.value?.generated_at) return '';
  const m = Math.max(0, Math.round((Date.now() - new Date(data.value.generated_at).getTime()) / 60000));
  return m < 60 ? `منذ ${m} د` : m < 1440 ? `منذ ${Math.round(m / 60)} س` : `منذ ${Math.round(m / 1440)} يوم`;
});

// Admin-only endpoint; a failure just leaves the card out rather than cluttering the dashboard.
onMounted(async () => {
  try {
    data.value = (await diagnostics.get()).data;
  } catch (e) {
    data.value = null;
  }
});
</script>

<style scoped>
.shc { display: flex; align-items: center; gap: 18px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; margin-bottom: 16px; flex-wrap: wrap; }
.shc-score { width: 76px; height: 76px; flex-shrink: 0; }
.shc-body { flex: 1; min-width: 240px; }
.shc-head { display: flex; align-items: center; gap: 10px; font-size: .95rem; }
.shc-head .muted { color: #94a3b8; font-size: .78rem; }
.shc-head .up { color: #16a34a; font-size: .8rem; font-weight: 700; }
.shc-head .down { color: #dc2626; font-size: .8rem; font-weight: 700; }
.shc-link { margin-inline-start: auto; color: #4f46e5; font-size: .82rem; text-decoration: none; }
.shc-link:hover { text-decoration: underline; }
.shc-chips { display: flex; gap: 6px; flex-wrap: wrap; margin: 8px 0; }
.chip { font-size: .74rem; padding: 2px 9px; border-radius: 999px; background: #f1f5f9; color: #475569; }
.chip.c-critical { background: #fee2e2; color: #b91c1c; font-weight: 700; }
.chip.c-warning { background: #fef3c7; color: #b45309; font-weight: 700; }
.chip.c-info { background: #e0f2fe; color: #0369a1; }
.shc-top { list-style: none; margin: 0; padding: 0; display: grid; gap: 3px; font-size: .84rem; color: #334155; }
.shc-top li { display: flex; align-items: center; gap: 8px; }
.shc-top .t { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.shc-top .n { font-variant-numeric: tabular-nums; color: #64748b; font-weight: 700; }
.dot { width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; flex-shrink: 0; }
.dot.c-critical { background: #dc2626; } .dot.c-warning { background: #d97706; } .dot.c-info { background: #0ea5e9; }
.ok { color: #16a34a; font-size: .85rem; margin: 0; }
.shc-spark { width: 160px; height: 60px; flex-shrink: 0; }
@media (max-width: 640px) { .shc-spark { display: none; } }
</style>
