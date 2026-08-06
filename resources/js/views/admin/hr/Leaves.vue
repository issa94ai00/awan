<template>
    <div class="hr-page hr-leaves">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('vacations') }}</h1>
                <p>{{ $t('manage_leave_requests_and_monitor') }}</p>
            </div>
            <el-input v-model="searchQuery" :placeholder="$t('search_by_employee_name_or_status')" clearable class="search-input" />
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('pending_orders') }}</p>
                    <h3>{{ pendingCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('approved') }}</p>
                    <h3>{{ approvedCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('rejected') }}</p>
                    <h3>{{ rejectedCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-card">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('leave_requests') }}</span>
                    <el-button type="primary" size="small" @click="createLeaveRequest">{{ $t('new_leave_request') }}</el-button>
                </div>
            </template>

            <el-table v-loading="leaveStore.loading" :data="filteredRequests" style="width:100%" stripe>
                <el-table-column prop="employeeName" :label="$t('employee')" />
                <el-table-column prop="leave_type" :label="$t('leave_type')" width="140" />
                <el-table-column prop="period" :label="$t('period')" />
                <el-table-column :label="$t('status')" width="140">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.statusLabel)">{{ row.statusLabel }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="reason" :label="$t('the_reason')" />
                <el-table-column :label="$t('procedures')" width="160">
                    <template #default="{ row }">
                        <el-button type="text" size="small" @click="editLeaveRequest(row)">{{ $t('edit') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="leaveStore.error" class="error-state">
                {{ leaveStore.error }}
            </div>
            <div v-else-if="!filteredRequests.length" class="empty-state">
                {{ $t('there_are_no_vacation_requests') }}
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useLeaveRequestsStore } from '@/stores/leaveRequests';

const router = useRouter();
const leaveStore = useLeaveRequestsStore();
const searchQuery = ref('');

const statusLabel = (status) => {
    const value = String(status || '').toLowerCase();
    if (['approved', window.t('agreed')].includes(value)) return window.t('agreed');
    if (['pending', window.t('suspended')].includes(value)) return window.t('suspended');
    if (['rejected', window.t('rejected'), window.t('to_reject')].includes(value)) return window.t('rejected');
    return status || window.t('undefined');
};

const leaveRequests = computed(() => leaveStore.requests.map((request) => ({
    ...request,
    employeeName: request.employee?.name || window.t('undefined'),
    period: `${request.start_date || ''} - ${request.end_date || ''}`,
    statusLabel: statusLabel(request.status)
})));

const filteredRequests = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return leaveRequests.value;

    return leaveRequests.value.filter((request) => {
        return [request.employeeName, request.leave_type, request.statusLabel, request.reason].some((field) =>
            String(field || '').toLowerCase().includes(query)
        );
    });
});

const statusTagType = (status) => {
    if (status === window.t('agreed')) return 'success';
    if (status === window.t('suspended')) return 'warning';
    if (status === window.t('rejected')) return 'danger';
    return 'info';
};

const pendingCount = computed(() => leaveRequests.value.filter((request) => request.statusLabel === window.t('suspended')).length);
const approvedCount = computed(() => leaveRequests.value.filter((request) => request.statusLabel === window.t('agreed')).length);
const rejectedCount = computed(() => leaveRequests.value.filter((request) => request.statusLabel === window.t('rejected')).length);

const loadRequests = async () => {
    await leaveStore.fetchLeaveRequests().catch(() => {});
};

const createLeaveRequest = () => {
    router.push({ name: 'admin.hr.leaves.create' });
};

const editLeaveRequest = (request) => {
    if (!request?.id) return;
    router.push({ name: 'admin.hr.leaves.edit', params: { id: request.id } });
};

onMounted(loadRequests);
</script>

<style scoped>
.hr-page {
    padding: 0;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.page-title h1 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #1f2d3d;
}

.page-title p {
    margin: 0.35rem 0 0;
    color: #5f6d85;
}

.search-input {
    min-width: 260px;
}

.overview-cards {
    margin-bottom: 1.5rem;
}

.summary-card {
    min-height: 110px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 1rem;
}

.summary-card p {
    margin: 0;
    color: #6b7c98;
    font-size: 0.95rem;
}

.summary-card h3 {
    margin: 0;
    font-size: 2rem;
    color: #253358;
}

.table-card {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.empty-state {
    padding: 1.25rem;
    text-align: center;
    color: #6b7c98;
}
</style>

<style scoped>
.hr-page {
    padding: 0;
}

.page-header {
    margin-bottom: 1.5rem;
}

.page-title h1 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #1f2d3d;
}

.page-title p {
    margin: 0.35rem 0 0;
    color: #5f6d85;
}

.overview-cards {
    margin-bottom: 1.5rem;
}

.summary-card {
    min-height: 110px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 1rem;
}

.summary-card p {
    margin: 0;
    color: #6b7c98;
    font-size: 0.95rem;
}

.summary-card h3 {
    margin: 0;
    font-size: 2rem;
    color: #253358;
}

.content-card {
    border-radius: 1rem;
}

.empty-state {
    padding: 1.5rem;
    color: #58657e;
}
</style>
