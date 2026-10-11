<template>
    <div class="hr-page hr-leaves">
        <AdminPageHeader
            :title="$t('vacations')"
            :subtitle="$t('manage_leave_requests_and_monitor')"
        >
            <template #actions>
                <el-button type="primary" :icon="Plus" @click="createLeaveRequest">
                    {{ $t('new_leave_request') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Stat Cards -->
        <AdminStatGrid class="mb-4">
            <el-card shadow="hover" class="stat-card" :class="{ 'highlight-pending': pendingCount > 0 }">
                <span class="stat-title">{{ $t('pending_leaves') }}</span>
                <h3 class="stat-value text-warning">{{ pendingCount }}</h3>
                <span class="stat-sub">{{ $t('pending_orders') }}</span>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('approved') }}</span>
                <h3 class="stat-value text-success">{{ approvedCount }}</h3>
                <span class="stat-sub">{{ $t('agreed') }}</span>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('rejected') }}</span>
                <h3 class="stat-value text-danger">{{ rejectedCount }}</h3>
                <span class="stat-sub">{{ $t('rejected') }}</span>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('total_marches') || $t('total') }}</span>
                <h3 class="stat-value text-primary">{{ leaveStore.requests.length }}</h3>
                <span class="stat-sub">{{ $t('vacations') }}</span>
            </el-card>
        </AdminStatGrid>

        <!-- Filters Bar -->
        <AdminFilterBar>
            <el-select
                v-model="selectedStatus"
                :placeholder="$t('status')"
                clearable
                style="min-width: 140px;"
            >
                <el-option :label="$t('suspended')" value="pending" />
                <el-option :label="$t('agreed')" value="approved" />
                <el-option :label="$t('rejected')" value="rejected" />
            </el-select>

            <el-select
                v-model="selectedEmployeeId"
                :placeholder="$t('select_employee')"
                clearable
                filterable
                style="min-width: 180px;"
            >
                <el-option
                    v-for="emp in employeesStore.employees"
                    :key="emp.id"
                    :label="emp.name"
                    :value="emp.id"
                />
            </el-select>

            <el-input
                v-model="searchQuery"
                :placeholder="$t('search_by_employee_name_or_status')"
                :prefix-icon="Search"
                clearable
                style="max-width: 260px;"
            />

            <el-button type="primary" :icon="Refresh" @click="loadRequests">
                {{ $t('update') }}
            </el-button>
        </AdminFilterBar>

        <!-- Leave Requests Table -->
        <el-card shadow="hover" class="table-card">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('leave_requests') }}</span>
                    <span class="record-count">{{ filteredRequests.length }} {{ $t('records') || 'طلب' }}</span>
                </div>
            </template>

            <div v-if="leaveStore.error" class="error-state">
                {{ leaveStore.error }}
            </div>

            <el-table
                v-loading="leaveStore.loading"
                :data="filteredRequests"
                style="width: 100%"
                stripe
                highlight-current-row
            >
                <el-table-column :label="$t('employee')" min-width="160">
                    <template #default="{ row }">
                        <div class="emp-row-info">
                            <span class="emp-name">{{ row.employeeName }}</span>
                            <small class="emp-dept text-muted">{{ row.employee?.department || '' }}</small>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="leave_type" :label="$t('leave_type')" width="140">
                    <template #default="{ row }">
                        <el-tag effect="plain" size="small">{{ row.leave_type }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('period')" width="200">
                    <template #default="{ row }">
                        <div class="period-display">
                            <span>{{ formatDate(row.start_date) }}</span>
                            <span class="text-muted">&rarr;</span>
                            <span>{{ formatDate(row.end_date) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('duration_days')" width="110" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.daysCount" size="small" type="info" round>
                            {{ row.daysCount }} {{ $t('duration_days') }}
                        </el-tag>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('status')" width="130">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">
                            {{ formatStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column prop="reason" :label="$t('the_reason')" min-width="160" show-overflow-tooltip />

                <el-table-column :label="$t('procedures')" width="200" fixed="right" align="center">
                    <template #default="{ row }">
                        <el-button-group>
                            <!-- 1-Click Approve / Reject for Pending requests -->
                            <el-tooltip v-if="normalizeStatus(row.status) === 'pending'" :content="$t('quick_approve')" placement="top">
                                <el-button
                                    size="small"
                                    type="success"
                                    :icon="Check"
                                    :loading="processingId === row.id"
                                    @click="quickHandle(row, 'approved')"
                                />
                            </el-tooltip>

                            <el-tooltip v-if="normalizeStatus(row.status) === 'pending'" :content="$t('quick_reject')" placement="top">
                                <el-button
                                    size="small"
                                    type="danger"
                                    plain
                                    :icon="Close"
                                    :loading="processingId === row.id"
                                    @click="quickHandle(row, 'rejected')"
                                />
                            </el-tooltip>

                            <el-tooltip :content="$t('edit')" placement="top">
                                <el-button size="small" :icon="Edit" @click="editLeaveRequest(row)" />
                            </el-tooltip>

                            <el-tooltip :content="$t('delete')" placement="top">
                                <el-button size="small" type="danger" :icon="Delete" @click="deleteLeaveRequest(row)" />
                            </el-tooltip>
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="!leaveStore.loading && !filteredRequests.length" class="empty-state">
                {{ $t('there_are_no_vacation_requests') }}
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useLeaveRequestsStore } from '@/stores/leaveRequests';
import { useEmployeesStore } from '@/stores/employees';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import { matchesSearch } from '@/utils/search';
import {
    Plus,
    Search,
    Refresh,
    Edit,
    Delete,
    Check,
    Close
} from '@element-plus/icons-vue';

const { t } = useI18n();
const router = useRouter();
const leaveStore = useLeaveRequestsStore();
const employeesStore = useEmployeesStore();

const searchQuery = ref('');
const selectedStatus = ref('');
const selectedEmployeeId = ref('');
const processingId = ref(null);

const normalizeStatus = (status) => String(status || '').toLowerCase();

const formatStatusLabel = (status) => {
    const val = normalizeStatus(status);
    if (['approved', 'موافق', 'معتمد'].includes(val)) return t('agreed');
    if (['pending', 'معلق', 'معلّق'].includes(val)) return t('suspended');
    if (['rejected', 'مرفوض'].includes(val)) return t('rejected');
    return status || t('undefined');
};

const statusTagType = (status) => {
    const val = normalizeStatus(status);
    if (['approved', 'موافق', 'معتمد'].includes(val)) return 'success';
    if (['pending', 'معلق', 'معلّق'].includes(val)) return 'warning';
    if (['rejected', 'مرفوض'].includes(val)) return 'danger';
    return 'info';
};

const formatDate = (val) => {
    if (!val) return '—';
    return String(val).slice(0, 10);
};

const calcDays = (start, end) => {
    if (!start || !end) return null;
    const d1 = new Date(start);
    const d2 = new Date(end);
    const diff = Math.round((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
    return diff > 0 ? diff : 1;
};

const leaveRequests = computed(() => leaveStore.requests.map((request) => ({
    ...request,
    employeeName: request.employee?.name || t('undefined'),
    daysCount: calcDays(request.start_date, request.end_date)
})));

const filteredRequests = computed(() => {
    return leaveRequests.value.filter((request) => {
        const matchesQuery = !searchQuery.value.trim() || matchesSearch([
            request.employeeName,
            request.leave_type,
            request.reason,
            request.notes
        ], searchQuery.value);

        const matchesStatus = !selectedStatus.value || normalizeStatus(request.status) === normalizeStatus(selectedStatus.value);
        const matchesEmployee = !selectedEmployeeId.value || request.employee_id === selectedEmployeeId.value;

        return matchesQuery && matchesStatus && matchesEmployee;
    });
});

const pendingCount = computed(() => leaveStore.requests.filter((r) => normalizeStatus(r.status) === 'pending').length);
const approvedCount = computed(() => leaveStore.requests.filter((r) => normalizeStatus(r.status) === 'approved').length);
const rejectedCount = computed(() => leaveStore.requests.filter((r) => normalizeStatus(r.status) === 'rejected').length);

const loadRequests = async () => {
    const params = {};
    if (selectedStatus.value) params.status = selectedStatus.value;
    if (selectedEmployeeId.value) params.employee_id = selectedEmployeeId.value;
    await leaveStore.fetchLeaveRequests(params).catch(() => {});
};

const createLeaveRequest = () => {
    router.push({ name: 'admin.hr.leaves.create' });
};

const editLeaveRequest = (request) => {
    if (!request?.id) return;
    router.push({ name: 'admin.hr.leaves.edit', params: { id: request.id } });
};

const quickHandle = async (row, status) => {
    processingId.value = row.id;
    try {
        await leaveStore.updateLeaveRequest(row.id, { status });
        ElMessage.success(status === 'approved' ? t('leave_approved_success') : t('leave_rejected_success'));
        await loadRequests();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('action_failed') || 'فشل تنفيذ الإجراء');
    } finally {
        processingId.value = null;
    }
};

const deleteLeaveRequest = async (request) => {
    try {
        await ElMessageBox.confirm(
            t('confirm_delete_record') || 'هل أنت متأكد من حذف هذا الطلب؟',
            t('confirm_deletion') || 'تأكيد الحذف',
            {
                confirmButtonText: t('yes') || 'نعم',
                cancelButtonText: t('no') || 'إلغاء',
                type: 'warning'
            }
        );

        await leaveStore.deleteLeaveRequest(request.id);
        ElMessage.success(t('deleted_successfully') || 'تم الحذف بنجاح');
    } catch (err) {
        if (err !== 'cancel') {
            ElMessage.error(t('failed_to_delete') || 'فشل حذف الطلب');
        }
    }
};

onMounted(async () => {
    await Promise.all([
        employeesStore.fetchEmployees().catch(() => {}),
        loadRequests()
    ]);
});
</script>

<style scoped>
.hr-page {
    padding: 0;
}

.mb-4 {
    margin-bottom: 1.25rem;
}

.stat-card {
    border-radius: 1rem;
    border: 1px solid #eef2f6;
    padding: 0.25rem 0;
    transition: all 0.2s ease;
}

.highlight-pending {
    border-color: #fef08a;
    background: #fefce8;
}

.stat-title {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}

.stat-value {
    margin: 0.35rem 0 0.15rem;
    font-size: 1.7rem;
    font-weight: 700;
}

.stat-sub {
    font-size: 0.78rem;
    color: #94a3b8;
}

.table-card {
    border-radius: 1rem;
    border: 1px solid #eef2f6;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.record-count {
    color: #6b7c98;
    font-size: 0.9rem;
}

.emp-row-info {
    display: flex;
    flex-direction: column;
}

.emp-name {
    font-weight: 600;
    color: #1e293b;
}

.emp-dept {
    font-size: 0.75rem;
}

.period-display {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    color: #334155;
}

.text-success { color: #16a34a; }
.text-warning { color: #d97706; }
.text-danger { color: #dc2626; }
.text-primary { color: #2563eb; }
.text-muted { color: #64748b; }

.error-state,
.empty-state {
    padding: 2.5rem 1rem;
    text-align: center;
    color: #64748b;
}
</style>
