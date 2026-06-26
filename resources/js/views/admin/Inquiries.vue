<template>
    <div class="inquiries-page">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('inquiries') }}</h1>
                <p>{{ $t('the_inquiry_management_panel_gives') }}</p>
            </div>

            <div class="page-actions">
                <el-input
                    v-model="searchQuery"
                    @input="onSearchInput"
                    :placeholder="$t('search_by_name_subject_mail_or_message')"
                    clearable
                    class="search-input"
                />
                <el-select
                    v-model="selectedStatus"
                    :placeholder="$t('inquiry_status')"
                    class="search-input"
                    clearable
                    @change="onFilterChange"
                >
                    <el-option
                        v-for="status in statusOptions"
                        :key="status.value"
                        :label="status.label"
                        :value="status.value"
                    />
                </el-select>
                <el-select
                    v-model="selectedPriority"
                    :placeholder="$t('priority')"
                    class="search-input"
                    clearable
                    @change="onFilterChange"
                >
                    <el-option
                        v-for="priority in priorityOptions"
                        :key="priority.value"
                        :label="priority.label"
                        :value="priority.value"
                    />
                </el-select>
            </div>
        </div>

        <el-row :gutter="20" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('total_inquiries') }}</p>
                    <h3>{{ pagination.total || inquiriesStore.items.length }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('new') }}</p>
                    <h3>{{ statusSummary.new }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('readable') }}</p>
                    <h3>{{ statusSummary.read }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('answered') }}</p>
                    <h3>{{ statusSummary.replied }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <div>
                        <h2>{{ $t('list_of_inquiries') }}</h2>
                        <p class="header-note">{{ $t('quick_view_of_current_orders') }}</p>
                    </div>
                    <div class="header-meta">
                        {{ pagination.from || 0 }} - {{ pagination.to || inquiriesStore.items.length }} {{ $t('from') }} {{ pagination.total || inquiriesStore.items.length }} {{ $t('inquiry') }}
                    </div>
                </div>
            </template>

            <el-table
                :data="inquiriesStore.items"
                v-loading="inquiriesStore.loading"
                style="width: 100%"
                stripe
                border
                size="small"
                :row-class-name="getRowClass"
            >
                <el-table-column prop="id" label="#" width="70" />

                <el-table-column :label="$t('sender')" min-width="220">
                    <template #default="{ row }">
                        <div class="sender-cell">
                            <strong>{{ row.name || $t('unknown') }}</strong>
                            <div class="sender-meta">
                                <span>{{ row.email || $t('without_mail') }}</span>
                                <span v-if="row.phone">• {{ row.phone }}</span>
                            </div>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="subject_label" :label="$t('the_topic')" min-width="180" />

                <el-table-column prop="priority_label" :label="$t('priority')" width="120">
                    <template #default="{ row }">
                        <el-tag type="warning" effect="plain">{{ row.priority_label || $t('not_specified') }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('appointment')" min-width="140">
                    <template #default="{ row }">
                        {{ row.assigned_to?.name || $t('not_assigned') }}
                    </template>
                </el-table-column>

                <el-table-column :label="$t('status')" width="140">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">{{ row.status_label || row.status_text || translateStatus(row.status) }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column prop="created_at_human" :label="$t('since')" width="130" />
                <el-table-column prop="created_at_formatted" :label="$t('the_date')" width="160" />

                <el-table-column :label="$t('procedures')" width="170">
                    <template #default="{ row }">
                        <div class="action-buttons">
                            <el-button type="primary" size="mini" @click="viewInquiry(row.id)">{{ $t('an_offer') }}</el-button>
                            <el-button type="danger" size="mini" @click="deleteInquiry(row.id)">{{ $t('delete') }}</el-button>
                        </div>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="!inquiriesStore.loading && !inquiriesStore.items.length" class="empty-state">
                {{ $t('there_are_no_inquiries_currently') }}
            </div>
        </el-card>

        <div class="pagination-wrapper" v-if="pagination.last_page > 1">
            <el-pagination
                background
                layout="prev, pager, next, jumper, ->, total"
                :page-size="pagination.per_page"
                :current-page="currentPage"
                :total="pagination.total"
                @current-change="handlePageChange"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useInquiriesStore } from '@/stores/inquiries';

const router = useRouter();
const inquiriesStore = useInquiriesStore();
const searchQuery = ref('');
const selectedStatus = ref('');
const selectedPriority = ref('');
const currentPage = ref(1);
const searchTimeout = ref(null);

const statusOptions = [
    { value: '', label: window.t('all_cases')},
    { value: 'new', label: window.t('new')},
    { value: 'read', label: window.t('readable')},
    { value: 'replied', label: window.t('answered')}
];

const priorityOptions = [
    { value: '', label: window.t('all_priorities')},
    { value: 'low', label: window.t('low')},
    { value: 'medium', label: window.t('middle')},
    { value: 'high', label: window.t('high')},
    { value: 'urgent', label: window.t('urgent')}
];

const pagination = computed(() => inquiriesStore.pagination || {});

const statusSummary = computed(() => {
    const counts = inquiriesStore.statusCounts || {};
    return {
        new: counts.new || 0,
        read: counts.read || 0,
        replied: counts.replied || 0,
    };
});

const loadInquiries = async () => {
    await inquiriesStore.fetch({
        per_page: 15,
        page: currentPage.value,
        search: searchQuery.value.trim() || undefined,
        status: selectedStatus.value || undefined,
        priority: selectedPriority.value || undefined,
    }).catch(() => {
        // silent fail: keep current data
    });
};

const onSearchInput = () => {
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
    }
    searchTimeout.value = setTimeout(() => {
        currentPage.value = 1;
        loadInquiries();
    }, 450);
};

const onFilterChange = () => {
    currentPage.value = 1;
    loadInquiries();
};

const handlePageChange = (page) => {
    currentPage.value = page;
    loadInquiries();
};

const viewInquiry = (id) => {
    router.push({ name: 'admin.inquiries.show', params: { id } });
};

const deleteInquiry = async (id) => {
    const confirmed = window.confirm(window.t('are_you_sure_you_want'));
    if (!confirmed) {
        return;
    }

    try {
        await inquiriesStore.remove(id);
        ElMessage.success(window.t('the_query_has_been_successfully_deleted'));
        loadInquiries();
    } catch (error) {
        ElMessage.error(window.t('failed_to_delete_query'));
    }
};

const getRowClass = (row) => {
    return row.status === 'new' ? 'table-row-new' : '';
};

const previewMessage = (message) => {
    if (!message) return window.t('no_message');
    return message.length > 80 ? message.substring(0, 80) + '...' : message;
};

const translateStatus = (status) => {
    return {
        new: window.t('new'),
        read: window.t('readable'),
        replied: window.t('answered')}[status] || status || window.t('not_specified');
};

const statusTagType = (status) => {
    if (status === 'new') return 'danger';
    if (status === 'read') return 'warning';
    if (status === 'replied') return 'success';
    return 'info';
};

onMounted(loadInquiries);
</script>

<style scoped>
.inquiries-page {
    padding: 0;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
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

.page-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem;
}

.search-input {
    min-width: 260px;
    max-width: 320px;
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

.table-panel {
    border-radius: 1rem;
}

.table-panel :deep(.el-table__header th) {
    background: #fafbff;
    color: #344154;
    border-bottom: 1px solid #e5e9f2;
    font-weight: 700;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.header-note {
    margin: 0.35rem 0 0;
    color: #6b7c98;
    font-size: 0.95rem;
}

.header-meta {
    color: #6b7c98;
    font-size: 0.95rem;
}

.sender-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.sender-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    color: #6b7c98;
    font-size: 0.95rem;
}

.table-row-new {
    background: #fff7f0 !important;
}

.action-buttons {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.el-table .el-button {
    height: 32px;
}

.empty-state {
    padding: 1.25rem;
    text-align: center;
    color: #6b7c98;
}

.pagination-wrapper {
    margin-top: 1rem;
    display: flex;
    justify-content: flex-end;
}
</style>
