<template>
    <div class="hr-page hr-leaves">
        <div class="page-header">
            <div class="page-title">
                <h1>الإجازات</h1>
                <p>إدارة طلبات الإجازة ومراقبة الموافقات في واجهة واضحة وسهلة الاستخدام.</p>
            </div>
            <el-input v-model="searchQuery" placeholder="ابحث باسم الموظف أو الحالة" clearable class="search-input" />
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>الطلبات المعلقة</p>
                    <h3>{{ pendingCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>الموافق عليها</p>
                    <h3>{{ approvedCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>المرفوضة</p>
                    <h3>{{ rejectedCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-card">
            <template #header>
                <div class="card-header">
                    <span>طلبات الإجازات</span>
                    <el-button type="primary" size="small" @click="createLeaveRequest">طلب إجازة جديد</el-button>
                </div>
            </template>

            <el-table v-loading="leaveStore.loading" :data="filteredRequests" style="width:100%" stripe>
                <el-table-column prop="employeeName" label="الموظف" />
                <el-table-column prop="leave_type" label="نوع الإجازة" width="140" />
                <el-table-column prop="period" label="الفترة" />
                <el-table-column label="الحالة" width="140">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.statusLabel)">{{ row.statusLabel }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="reason" label="السبب" />
                <el-table-column label="الإجراءات" width="160">
                    <template #default="{ row }">
                        <el-button type="text" size="small" @click="editLeaveRequest(row)">تعديل</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="leaveStore.error" class="error-state">
                {{ leaveStore.error }}
            </div>
            <div v-else-if="!filteredRequests.length" class="empty-state">
                لا توجد طلبات إجازة تطابق البحث.
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
    if (['approved', 'موافق عليها'].includes(value)) return 'موافق عليها';
    if (['pending', 'معلقة'].includes(value)) return 'معلقة';
    if (['rejected', 'مرفوضة', 'رفض'].includes(value)) return 'مرفوضة';
    return status || 'غير محدد';
};

const leaveRequests = computed(() => leaveStore.requests.map((request) => ({
    ...request,
    employeeName: request.employee?.name || 'غير محدد',
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
    if (status === 'موافق عليها') return 'success';
    if (status === 'معلقة') return 'warning';
    if (status === 'مرفوضة') return 'danger';
    return 'info';
};

const pendingCount = computed(() => leaveRequests.value.filter((request) => request.statusLabel === 'معلقة').length);
const approvedCount = computed(() => leaveRequests.value.filter((request) => request.statusLabel === 'موافق عليها').length);
const rejectedCount = computed(() => leaveRequests.value.filter((request) => request.statusLabel === 'مرفوضة').length);

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
