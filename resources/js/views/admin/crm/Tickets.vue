<template>
    <div class="crm-page crm-tickets">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('tickets') }}</h1>
                <p>{{ $t('manage_support_requests_with_easy') }}</p>
            </div>
            <div class="page-actions">
                <el-input v-model="searchQuery" :placeholder="$t('search_by_ticket_subject_or')" clearable class="search-input" />
                <el-button type="primary" @click="createTicket">{{ $t('new_ticket') }}</el-button>
            </div>
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('total_tickets') }}</p>
                    <h3>{{ totalTickets }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('open') }}</p>
                    <h3>{{ openCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('on_hold') }}</p>
                    <h3>{{ pendingCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('ticket_list') }}</span>
                </div>
            </template>

            <el-table v-loading="ticketsStore.loading" :data="filteredTickets" style="width:100%" stripe>
                <el-table-column prop="subject" :label="$t('the_topic')" width="240" />
                <el-table-column prop="customerName" :label="$t('client')" width="200" />
                <el-table-column prop="priorityLabel" :label="$t('priority')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="priorityTagType(row.priority)">{{ row.priorityLabel }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="statusLabel" :label="$t('status')" width="140">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">{{ row.statusLabel }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="updated_at" :label="$t('latest_update')" width="180" />
                <el-table-column :label="$t('procedures')" width="220">
                    <template #default="{ row }">
                        <el-button type="text" size="small" @click="editTicket(row)">{{ $t('edit') }}</el-button>
                        <el-button type="danger" size="small" @click="deleteTicket(row)">{{ $t('delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="ticketsStore.error" class="error-state">{{ ticketsStore.error }}</div>
            <div v-else-if="!filteredTickets.length" class="empty-state">{{ $t('there_are_no_tickets_matching') }}</div>
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
    customerName: ticket.customer?.name || window.t('undefined'),
    statusLabel: {
        open: window.t('open'),
        pending: window.t('on_hold'),
        closed: window.t('closed'),
        resolved: window.t('it_has_been_solved')}[ticket.status] || ticket.status || window.t('undefined'),
    priorityLabel: {
        high: window.t('high'),
        medium: window.t('medium'),
        low: window.t('low')}[ticket.priority] || ticket.priority || window.t('undefined')})));

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
            window.t('are_you_sure_you_want'),
            window.t('confirm_deletion'),
            {
                confirmButtonText: window.t('yes'),
                cancelButtonText: window.t('no'),
                type: 'warning'
            }
        );

        await ticketsStore.deleteTicket(ticket.id);
        ElMessage.success(window.t('the_ticket_has_been_successfully_deleted'));
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error(window.t('failed_to_delete_ticket'));
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
