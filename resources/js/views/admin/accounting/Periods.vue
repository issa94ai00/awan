<template>
    <div class="accounting-page accounting-periods">
        <AdminPageHeader
            icon="fas fa-lock text-primary"
            :title="$t('accounting_periods')"
            :subtitle="$t('accounting_periods_subtitle')"
        >
            <template #actions>
                <el-button :icon="Refresh" :loading="store.loading" @click="reload" />
                <el-button type="primary" :icon="Plus" @click="openCreateDialog">
                    {{ $t('add_period') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Anyone entering documents today needs to know before the API
             refuses them, not after. -->
        <el-alert
            v-if="store.todayIsClosed"
            type="warning"
            show-icon
            :closable="false"
            class="mb-4"
            :title="$t('today_is_inside_a_closed_period')"
        />

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-calendar-check text-muted"></i> {{ $t('accounting_periods') }}</span>
                </div>
            </template>

            <el-skeleton v-if="store.loading" :rows="4" animated />
            <el-alert v-else-if="store.error" type="error" show-icon :closable="false" :title="store.error" />

            <template v-else>
                <el-table v-if="store.periods.length" :data="store.periods" style="width:100%" stripe>
                    <el-table-column prop="name" :label="$t('name')" min-width="140" />

                    <el-table-column :label="$t('period')" width="230" align="center">
                        <template #default="{ row }">
                            <span class="mono">{{ row.start_date }} → {{ row.end_date }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('journal_entries')" width="120" align="center">
                        <template #default="{ row }">{{ row.entry_count }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('status')" width="120" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'closed' ? 'danger' : 'success'" effect="light">
                                {{ row.status === 'closed' ? $t('closed_period') : $t('open_period') }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('last_action')" min-width="200">
                        <template #default="{ row }">
                            <span v-if="row.status === 'closed' && row.closed_at" class="muted">
                                {{ $t('closed_on') }} {{ row.closed_at }}
                                <template v-if="row.closed_by">— {{ row.closed_by }}</template>
                            </span>
                            <span v-else-if="row.reopened_at" class="muted">
                                {{ $t('reopened_on') }} {{ row.reopened_at }}
                                <template v-if="row.reopened_by">— {{ row.reopened_by }}</template>
                            </span>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('actions')" width="200" align="center">
                        <template #default="{ row }">
                            <el-button
                                v-if="row.status === 'open'"
                                size="small"
                                type="danger"
                                plain
                                :loading="store.saving"
                                @click="confirmClose(row)"
                            >
                                <i class="fas fa-lock"></i> {{ $t('close_period') }}
                            </el-button>
                            <el-button
                                v-else
                                size="small"
                                type="warning"
                                plain
                                :loading="store.saving"
                                @click="confirmReopen(row)"
                            >
                                <i class="fas fa-lock-open"></i> {{ $t('reopen_period') }}
                            </el-button>
                            <el-button
                                v-if="row.status === 'open'"
                                size="small"
                                plain
                                @click="confirmRemove(row)"
                            >
                                <i class="fas fa-trash"></i>
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty v-else :description="$t('no_accounting_periods_yet')" />
            </template>
        </el-card>

        <el-dialog v-model="dialogVisible" :title="$t('add_period')" width="460px" destroy-on-close>
            <el-form :model="form" label-position="top">
                <el-form-item :label="$t('name')" required>
                    <el-input v-model="form.name" :placeholder="$t('period_name_example')" />
                </el-form-item>
                <el-form-item :label="$t('period')" required>
                    <el-date-picker
                        v-model="form.range"
                        type="daterange"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        :start-placeholder="$t('period_from')"
                        :end-placeholder="$t('to')"
                        style="width:100%"
                    />
                </el-form-item>
                <el-form-item :label="$t('notes')">
                    <el-input v-model="form.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="dialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="store.saving" :disabled="!canSubmit" @click="submit">
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh } from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { useAccountingPeriodsStore } from '@/stores/accountingPeriods';

const { t } = useI18n();
const store = useAccountingPeriodsStore();

const dialogVisible = ref(false);
const form = reactive({ name: '', range: [], notes: '' });

const canSubmit = computed(() => form.name.trim() && form.range?.length === 2);

const reload = () => store.fetchPeriods().catch(() => {});

const openCreateDialog = () => {
    form.name = '';
    form.range = [];
    form.notes = '';
    dialogVisible.value = true;
};

const submit = async () => {
    try {
        await store.createPeriod({
            name: form.name.trim(),
            start_date: form.range[0],
            end_date: form.range[1],
            notes: form.notes || null,
        });
        dialogVisible.value = false;
        ElMessage.success(t('period_created'));
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_save_period'));
    }
};

const confirmClose = async (period) => {
    try {
        await ElMessageBox.confirm(
            t('confirm_close_period'),
            `${t('close_period')} — ${period.name}`,
            { type: 'warning', confirmButtonText: t('confirm'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    try {
        await store.setClosed(period.id, true);
        ElMessage.success(t('period_closed'));
    } catch (error) {
        // The server refuses to close over an unbalanced entry, and says which
        // one — surface that rather than a generic failure.
        ElMessage.error(error.response?.data?.message || t('failed_to_close_period'));
    }
};

const confirmReopen = async (period) => {
    try {
        await ElMessageBox.confirm(
            t('confirm_reopen_period'),
            `${t('reopen_period')} — ${period.name}`,
            { type: 'warning', confirmButtonText: t('confirm'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    try {
        await store.setClosed(period.id, false);
        ElMessage.success(t('period_reopened'));
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_save_period'));
    }
};

const confirmRemove = async (period) => {
    try {
        await ElMessageBox.confirm(t('confirm_delete_period'), t('confirm_deletion'), {
            type: 'warning',
            confirmButtonText: t('delete'),
            cancelButtonText: t('cancel'),
        });
    } catch {
        return;
    }

    try {
        await store.removePeriod(period.id);
        ElMessage.success(t('period_deleted'));
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_save_period'));
    }
};

onMounted(reload);
</script>

<style scoped>
.accounting-periods {
    font-family: 'Cairo', sans-serif;
}

.table-panel {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
}

.mono {
    font-family: monospace;
    font-weight: 600;
}

.muted {
    color: var(--text-muted);
    font-size: 0.85rem;
}
</style>
