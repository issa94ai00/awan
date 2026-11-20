<template>
  <div class="diag-page p-4 md:p-6 bg-slate-50 min-h-screen" dir="rtl">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">صحة النظام</h1>
        <p class="text-sm text-slate-500 mt-1">
          فحوص للقراءة فقط على البيانات الفعلية: سلامة البيانات، تدفق العمل، والمنطق المالي والكلفة.
          <span v-if="snapshot.generated_at">آخر فحص: <b class="text-slate-700">{{ formatWhen(snapshot.generated_at) }}</b> ({{ ago }})</span>
          <span v-else>لم يُجرَ أي فحص بعد.</span>
        </p>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <el-button :icon="Download" :disabled="!issues.length" :loading="exporting" @click="exportCsv">تصدير CSV</el-button>
        <a href="/erd.html" target="_blank" rel="noopener"><el-button>التوثيق الكامل (ERD)</el-button></a>
        <el-button type="primary" :icon="Refresh" :loading="running" @click="runNow">
          {{ running ? 'جارٍ الفحص…' : 'فحص الآن' }}
        </el-button>
      </div>
    </div>

    <el-alert v-if="error" :title="error" type="error" show-icon class="mb-4" :closable="false" />

    <div v-if="loading" class="bg-white rounded-xl border border-slate-200 p-10 text-center text-slate-400">جارٍ التحميل…</div>

    <div v-else-if="snapshot.score === null" class="bg-white rounded-xl border border-dashed border-slate-300 p-12 text-center">
      <p class="text-slate-600 mb-4">لا توجد نتائج محفوظة. شغّل أول فحص لتظهر اللوحة.</p>
      <el-button type="primary" :loading="running" @click="runNow">بدء الفحص</el-button>
    </div>

    <template v-else>
      <!-- Score + trend + severity -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5 flex items-center gap-5">
          <svg viewBox="0 0 120 120" class="w-32 h-32 shrink-0" role="img" :aria-label="`الدرجة الإجمالية ${snapshot.score} من 100`">
            <circle cx="60" cy="60" r="52" fill="none" stroke="#e2e8f0" stroke-width="12" />
            <circle cx="60" cy="60" r="52" fill="none" :stroke="scoreColor(snapshot.score)" stroke-width="12" stroke-linecap="round"
                    :stroke-dasharray="`${(snapshot.score / 100) * 326.7} 326.7`" transform="rotate(-90 60 60)" />
            <text x="60" y="66" text-anchor="middle" font-size="30" font-weight="800" fill="#0f172a">{{ snapshot.score }}</text>
          </svg>
          <div>
            <p class="font-bold text-lg" :style="{ color: scoreColor(snapshot.score) }">{{ verdict }}</p>
            <p v-if="delta !== null" class="text-sm mt-1" :class="delta >= 0 ? 'text-emerald-600' : 'text-red-600'">
              {{ delta > 0 ? '▲' : delta < 0 ? '▼' : '■' }} {{ Math.abs(delta) }} عن الفحص السابق
            </p>
            <p class="text-xs text-slate-400 mt-2">مدة الفحص {{ (snapshot.duration_ms / 1000).toFixed(1) }} ث</p>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 lg:col-span-2">
          <div class="flex items-center justify-between mb-2">
            <h2 class="font-bold text-slate-800">اتجاه الدرجة</h2>
            <span class="text-xs text-slate-400">آخر {{ trend.length }} فحص</span>
          </div>
          <svg v-if="trend.length > 1" viewBox="0 0 400 90" class="w-full h-24" preserveAspectRatio="none" role="img" aria-label="اتجاه الدرجة عبر الزمن">
            <line x1="0" x2="400" y1="9" y2="9" stroke="#e2e8f0" stroke-dasharray="3 3" /><line x1="0" x2="400" y1="81" y2="81" stroke="#e2e8f0" stroke-dasharray="3 3" />
            <polyline :points="trendPoints" fill="none" :stroke="scoreColor(snapshot.score)" stroke-width="2.5" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
          </svg>
          <p v-else class="text-sm text-slate-400 py-6 text-center">يلزم فحصان على الأقل لرسم الاتجاه.</p>
          <div class="grid grid-cols-3 gap-3 mt-3">
            <button v-for="s in sevCards" :key="s.key" type="button"
                    class="rounded-lg border p-3 text-right transition"
                    :class="sevFilter === s.key ? s.active : 'border-slate-200 hover:border-slate-300 bg-white'"
                    @click="sevFilter = sevFilter === s.key ? 'all' : s.key">
              <span class="block text-2xl font-extrabold" :class="s.text">{{ snapshot.totals[s.key] || 0 }}</span>
              <span class="text-xs text-slate-500">{{ s.label }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Sections -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <button v-for="s in snapshot.sections" :key="s.key" type="button"
                class="bg-white rounded-xl border p-4 text-right transition"
                :class="sectionFilter === s.key ? 'border-indigo-400 ring-2 ring-indigo-100' : 'border-slate-200 hover:border-slate-300'"
                @click="sectionFilter = sectionFilter === s.key ? 'all' : s.key">
          <div class="flex items-center justify-between">
            <span class="font-bold text-slate-800">{{ s.label }}</span>
            <span class="text-xl font-extrabold" :style="{ color: scoreColor(s.score) }">{{ s.failed ? '—' : s.score }}</span>
          </div>
          <div class="h-1.5 rounded bg-slate-100 mt-2 overflow-hidden"><div class="h-full rounded" :style="{ width: (s.score || 0) + '%', background: scoreColor(s.score) }"></div></div>
          <p class="text-xs text-slate-500 mt-2">
            <span v-if="s.failed" class="text-red-600">تعذّر تشغيل الفحص</span>
            <template v-else>{{ s.total - s.counts.ok }} ملاحظة من {{ s.total }} فحصاً</template>
          </p>
        </button>
      </div>

      <!-- Issues -->
      <div class="bg-white rounded-xl border border-slate-200">
        <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row gap-3 md:items-center">
          <h2 class="font-bold text-slate-800 md:ml-auto">أهم المشاكل <span class="text-slate-400 font-normal">({{ filtered.length }})</span></h2>
          <el-input v-model="search" :prefix-icon="Search" clearable placeholder="بحث في العنوان أو التفاصيل" class="md:!w-72" />
          <el-radio-group v-model="sevFilter" size="small">
            <el-radio-button value="all">الكل</el-radio-button>
            <el-radio-button value="critical">حرج</el-radio-button>
            <el-radio-button value="warning">تحذير</el-radio-button>
            <el-radio-button value="info">ملاحظة</el-radio-button>
          </el-radio-group>
        </div>

        <div v-if="!filtered.length" class="p-10 text-center text-slate-400">
          {{ issues.length ? 'لا نتائج مطابقة للتصفية.' : 'لا توجد مشاكل — كل الفحوص سليمة.' }}
        </div>

        <ul v-else class="divide-y divide-slate-100">
          <li v-for="i in filtered" :key="i.id">
            <button type="button" class="w-full flex items-center gap-3 p-4 text-right hover:bg-slate-50" :aria-expanded="open === i.id" @click="open = open === i.id ? null : i.id">
              <span class="sev-pill" :class="`sev-${i.severity}`">{{ sevLabel[i.severity] }}</span>
              <span class="flex-1 min-w-0">
                <span class="block font-semibold text-slate-800 truncate">{{ i.title }}</span>
                <span class="block text-xs text-slate-500">{{ sectionLabel(i.section) }} · {{ i.category_label }}</span>
              </span>
              <span v-if="isNew(i)" class="text-xs px-2 py-0.5 rounded bg-indigo-50 text-indigo-700">جديد</span>
              <span class="text-sm font-bold text-slate-600 tabular-nums">{{ i.count }}</span>
              <span class="text-slate-400">{{ open === i.id ? '▴' : '▾' }}</span>
            </button>
            <div v-if="open === i.id" class="px-4 pb-4 pr-6 space-y-3 text-sm">
              <p v-if="i.error" class="text-red-600">خطأ أثناء الفحص: {{ i.error }}</p>
              <p><b class="text-slate-700">لماذا يهم: </b><span class="text-slate-600">{{ i.why }}</span></p>
              <p><b class="text-slate-700">الإصلاح المقترح: </b><span class="text-slate-600">{{ i.fix }}</span></p>
              <div v-if="i.items.length" class="rounded-lg border border-slate-200 overflow-hidden">
                <div class="max-h-72 overflow-auto">
                  <table class="w-full text-xs">
                    <tbody>
                      <tr v-for="(it, n) in i.items" :key="n" class="border-b border-slate-100 last:border-0">
                        <td class="p-2 w-40 font-mono text-slate-500" dir="ltr">{{ it.table }}<template v-if="it.column">.{{ it.column }}</template></td>
                        <td class="p-2 text-slate-700">{{ it.detail }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p v-if="i.count > i.items.length" class="p-2 text-xs text-slate-400 bg-slate-50">يُعرض {{ i.items.length }} من {{ i.count }} — الباقي في التصدير/ERD.</p>
              </div>
              <a :href="erdLink(i)" target="_blank" rel="noopener" class="text-indigo-600 text-xs hover:underline">فتح في توثيق ERD ↗</a>
            </div>
          </li>
        </ul>
      </div>

      <p v-if="resolved.length" class="mt-3 text-sm text-emerald-700">
        ✓ حُلّت منذ الفحص السابق: {{ resolved.length }} {{ resolved.length === 1 ? 'مشكلة' : 'مشاكل' }}.
      </p>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Download, Search } from '@element-plus/icons-vue';
import diagnostics from '@/services/systemDiagnostics';

const snapshot = reactive({ score: null, generated_at: null, duration_ms: 0, totals: {}, sections: [], issues: [], history: [] });
const loading = ref(true);
const running = ref(false);
const exporting = ref(false);
const error = ref('');
const search = ref('');
const sevFilter = ref('all');
const sectionFilter = ref('all');
const open = ref(null);
const now = ref(Date.now());
let tick;

const sevLabel = { critical: 'حرج', error: 'تعذّر', warning: 'تحذير', info: 'ملاحظة' };
const sevCards = [
  { key: 'critical', label: 'حرج', text: 'text-red-600', active: 'border-red-300 bg-red-50' },
  { key: 'warning', label: 'تحذير', text: 'text-amber-600', active: 'border-amber-300 bg-amber-50' },
  { key: 'info', label: 'ملاحظة', text: 'text-sky-600', active: 'border-sky-300 bg-sky-50' },
];
const ERD_TAB = { state: 'diag', workflow: 'flow', logic: 'logic' };

const issues = computed(() => snapshot.issues || []);
const scoreColor = (s) => (s === null || s === undefined ? '#94a3b8' : s >= 85 ? '#16a34a' : s >= 65 ? '#d97706' : '#dc2626');
const verdict = computed(() => (snapshot.score >= 85 ? 'النظام بحالة جيدة' : snapshot.score >= 65 ? 'يحتاج مراجعة' : 'حالة حرجة'));
const sectionLabel = (k) => snapshot.sections.find((s) => s.key === k)?.label || k;
const erdLink = (i) => `/erd.html#${ERD_TAB[i.section] || 'diag'}`;

const trend = computed(() => (snapshot.history || []).filter((h) => h.score !== null).slice(-30));
const trendPoints = computed(() => {
  const t = trend.value;
  const min = Math.min(...t.map((h) => h.score), 60), max = 100;
  return t.map((h, n) => `${(n / (t.length - 1)) * 400},${81 - ((h.score - min) / (max - min)) * 72}`).join(' ');
});
const previous = computed(() => (snapshot.history || []).slice(-2, -1)[0] || null);
const delta = computed(() => (previous.value && previous.value.score !== null ? snapshot.score - previous.value.score : null));
const isNew = (i) => previous.value && !previous.value.issue_ids.includes(i.id);
const resolved = computed(() => (previous.value ? previous.value.issue_ids.filter((id) => !issues.value.some((i) => i.id === id)) : []));

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  return issues.value.filter((i) =>
    (sevFilter.value === 'all' || i.severity === sevFilter.value) &&
    (sectionFilter.value === 'all' || i.section === sectionFilter.value) &&
    (!q || i.title.toLowerCase().includes(q) || i.items.some((it) => (it.detail || '').toLowerCase().includes(q))));
});

const formatWhen = (iso) => new Date(iso).toLocaleString('ar-SY-u-nu-latn', { dateStyle: 'medium', timeStyle: 'short' });
const ago = computed(() => {
  const m = Math.max(0, Math.round((now.value - new Date(snapshot.generated_at).getTime()) / 60000));
  return m < 1 ? 'الآن' : m < 60 ? `منذ ${m} دقيقة` : m < 1440 ? `منذ ${Math.round(m / 60)} ساعة` : `منذ ${Math.round(m / 1440)} يوم`;
});

const apply = (d) => Object.assign(snapshot, { history: [], ...d });

async function load() {
  try {
    apply((await diagnostics.get()).data || {});
  } catch (e) {
    error.value = 'تعذّر تحميل نتائج الفحص.';
  } finally {
    loading.value = false;
  }
}

async function runNow() {
  running.value = true;
  error.value = '';
  try {
    apply((await diagnostics.run()).data || {});
    ElMessage.success('اكتمل الفحص');
  } catch (e) {
    error.value = 'فشل تشغيل الفحص، حاول مجدداً.';
  } finally {
    running.value = false;
  }
}

async function exportCsv() {
  exporting.value = true;
  try {
    const res = await diagnostics.exportCsv();
    const url = URL.createObjectURL(res instanceof Blob ? res : new Blob([res]));
    const a = document.createElement('a');
    a.href = url;
    a.download = `system-diagnostics-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (e) {
    ElMessage.error('فشل التصدير');
  } finally {
    exporting.value = false;
  }
}

onMounted(() => { load(); tick = setInterval(() => { now.value = Date.now(); }, 60000); });
onBeforeUnmount(() => clearInterval(tick));
</script>

<style scoped>
.sev-pill { font-size: .72rem; font-weight: 700; padding: 2px 10px; border-radius: 999px; white-space: nowrap; }
.sev-critical { background: #fee2e2; color: #b91c1c; }
.sev-error { background: #f1f5f9; color: #475569; }
.sev-warning { background: #fef3c7; color: #b45309; }
.sev-info { background: #e0f2fe; color: #0369a1; }
</style>
