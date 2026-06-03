<template>
    <div class="crm-page crm-tickets">
        <div class="page-header">
            <div class="page-title">
                <h1>التذاكر</h1>
                <p>إدارة طلبات الدعم مع تتبع الحالة والأولوية بسهولة.</p>
            </div>
            <div class="page-actions">
                <el-input v-model="searchQuery" placeholder="ابحث بموضوع التذكرة أو اسم العميل" clearable class="search-input" />
                <el-button type="primary" @click="createTicket">تذكرة جديدة</el-button>
            </div>
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>إجمالي التذاكر</p>
                    <h3>{{ totalTickets }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>مفتوحة</p>
                    <h3>{{ openCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>قيد الانتظار</p>
                    <h3>{{ pendingCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>قائمة التذاكر</span>
                </div>
            </template>

            <el-table v-loading="ticketsStore.loading" :data="filteredTickets" style="width:100%" stripe>
                <el-table-column prop="subject" label="الموضوع" width="240" />
                <el-table-column prop="customerName" label="العميل" width="200" />
                <el-table-column prop="priorityLabel" label="الأولوية" width="120">
                    <template #default="{ row }">
                        <el-tag :type="priorityTagType(row.priority)">{{ row.priorityLabel }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="statusLabel" label="الحالة" width="140">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">{{ row.statusLabel }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="updated_at" label="آخر تحديث" width="180" />
                <el-table-column label="الإجراءات" width="220">
                    <template #default="{ row }">
                        <el-button type="text" size="small" @click="editTicket(row)">تعديل</el-button>
                        <el-button type="danger" size="small" @click="deleteTicket(row)">حذف</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="ticketsStore.error" class="error-state">{{ ticketsStore.error }}</div>
            <div v-else-if="!filteredTickets.length" class="empty-state">لا توجد تذاكر تطابق البحث.</div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useTicketsStore } from '@/stores/tickets';

const router = useRouter();
const ticketsStore = useTicketsStore();
const searchQuery = ref('');

const tickets = computed(() => ticketsStore.tickets.map((ticket) => ({
    ...ticket,
    customerName: ticket.customer?.name || 'غير محدد',
    statusLabel: {
        open: 'مفتوحة',
        pending: 'قيد الانتظار',
        closed: 'مغلقة',
        resolved: 'تم حلها'
    }[ticket.status] || ticket.status || 'غير محدد',
    priorityLabel: {
        high: 'عالية',
        medium: 'متوسطة',
        low: 'منخفضة'
    }[ticket.priority] || ticket.priority || 'غير محدد'
})));

const filteredTickets = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return tickets.value;
    return tickets.value.filter((ticket) => {
        return [ticket.subject, ticket.customerName, ticket.statusLabel, ticket.priorityLabel]
            .some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const totalTickets = computed(() => ticketsStore.pagination.total || ticketsStore.tickets.length);
const openCount = computed(() => tickets.value.filter((ticket) => ticket.status === 'open').length);
const pendingCount = computed(() => tickets.value.filter((ticket) => ticket.status === 'pending').length);

const statusTagType = (status) => {
    if (status === 'open') return 'success';
    if (status === 'pending') return 'warning';
    if (status === 'closed') return 'danger';
    if (status === 'resolved') return 'info';
    return 'info';
};

const priorityTagType = (priority) => {
    if (priority === 'high') return 'danger';
    if (priority === 'medium') return 'warning';
    if (priority === 'low') return 'success';
    return 'info';
};

const createTicket = () => {
    router.push({ name: 'admin.crm.tickets.create' });
};

const editTicket = (ticket) => {
    if (!ticket?.id) return;
    router.push({ name: 'admin.crm.tickets.edit', params: { id: ticket.id } });
};

const deleteTicket = async (ticket) => {
    if (!ticket?.id) return;

    try {
        await ElMessageBox.confirm(
            `هل أنت متأكد من حذف التذكرة "${ticket.subject}"؟`,
            'تأكيد الحذف',
            {
                confirmButtonText: 'نعم',
                cancelButtonText: 'لا',
                type: 'warning'
            }
        );

        await ticketsStore.deleteTicket(ticket.id);
        ElMessage.success('تم حذف التذكرة بنجاح');
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error('فشل في حذف التذكرة');
        }
    }
};

const loadTickets = async () => {
    await ticketsStore.fetchTickets().catch(() => {});
};

onMounted(loadTickets);
</script>

<style scoped>
.crm-page { padding: 0 }
.page-header { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1.5rem }
.page-actions { display:flex; flex-wrap:wrap; align-items:center; gap:1rem }
.page-title h1 { margin:0; font-size:1.8rem; font-weight:700; color:#1f2d3d }
.search-input { min-width:260px }
.overview-cards { margin-bottom:1.5rem }
.summary-card { min-height:110px; display:flex; flex-direction:column; justify-content:center; gap:0.4rem; border-radius:1rem }
.summary-card p { margin:0; color:#6b7c98; font-size:0.95rem }
.table-panel { border-radius:1rem }
.card-header { display:flex; justify-content:space-between; align-items:center }
.error-state, .empty-state { padding:1.25rem; text-align:center; color:#6b7c98 }
</style>
