<template>
    <div class="sales-page sales-quotes">
        <AdminPageHeader
            icon="fas fa-file-signature"
            :title="$t('quotes')"
            :subtitle="$t('manage_quotes_quickly_with_instant')"
        >
            <template #actions>
                <el-input
                    v-model="searchQuery"
                    :placeholder="$t('search_by_offer_number_or_customer_name')"
                    clearable
                    class="search-input"
                    :prefix-icon="Search"
                />
                <el-select v-model="statusFilter" clearable class="status-filter" :placeholder="$t('status')">
                    <el-option
                        v-for="status in QUOTE_STATUSES"
                        :key="status"
                        :value="status"
                        :label="statusLabel(status)"
                    />
                </el-select>
                <el-button :icon="Refresh" :loading="store.loading" @click="reload" />
            </template>
        </AdminPageHeader>

        <!-- Pipeline summary. Clicking a card filters the table below. -->
        <AdminStatGrid>
            <el-card
                shadow="hover"
                class="stat-card"
                :class="{ 'is-active': statusFilter === card.status }"
                @click="toggleFilter(card.status)"
            >
                <div class="stat-inner">
                    <div class="stat-icon" :class="card.tone">
                        <i class="fas" :class="card.icon"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ card.value }}</h3>
                        <p>{{ card.label }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list"></i> {{ $t('list_of_quotations') }}</span>
                    <span class="result-count">{{ filteredQuotes.length }} / {{ store.quotes.length }}</span>
                </div>
            </template>

            <el-skeleton v-if="store.loading" :rows="6" animated />

            <el-alert
                v-else-if="store.error"
                type="error"
                show-icon
                :closable="false"
                :title="store.error"
            />

            <template v-else>
                <el-table
                    v-if="filteredQuotes.length"
                    :data="filteredQuotes"
                    style="width:100%"
                    stripe
                    highlight-current-row
                >
                    <el-table-column prop="quote_number" :label="$t('quote_number')" width="150">
                        <template #default="{ row }">
                            <button type="button" class="record-link" @click="openDetail(row)">
                                {{ row.quote_number }}
                            </button>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('client')" min-width="180">
                        <template #default="{ row }">
                            <div class="customer-cell">
                                <i class="fas fa-user-circle"></i>
                                <span>{{ customerName(row) }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('total')" width="160" align="left">
                        <template #default="{ row }">
                            <strong class="amount">{{ formatCurrency(row.total) }}</strong>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('status')" width="170" align="center">
                        <template #default="{ row }">
                            <el-dropdown
                                v-if="transitionsFor(row.status).length > 1"
                                trigger="click"
                                @command="(status) => changeStatus(row, status)"
                            >
                                <el-tag :type="statusTagType(row.status)" effect="light" class="status-tag clickable">
                                    <i class="fas" :class="statusIcon(row.status)"></i>
                                    {{ statusLabel(row.status) }}
                                    <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                                </el-tag>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item
                                            v-for="status in transitionsFor(row.status)"
                                            :key="status"
                                            :command="status"
                                            :disabled="status === normalizeStatus(row.status)"
                                        >
                                            <el-tag :type="statusTagType(status)" size="small">
                                                {{ statusLabel(status) }}
                                            </el-tag>
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                            <el-tag v-else :type="statusTagType(row.status)" effect="light" class="status-tag">
                                <i class="fas" :class="statusIcon(row.status)"></i>
                                {{ statusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('valid_until')" width="150" align="center">
                        <template #default="{ row }">
                            <span :class="{ 'is-expired': isExpired(row) }">
                                {{ formatDate(row.valid_until) }}
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('actions')" width="190" align="center" fixed="right">
                        <template #default="{ row }">
                            <el-button-group>
                                <el-tooltip :content="$t('view_details')" placement="top">
                                    <el-button size="small" plain @click="openDetail(row)">
                                        <i class="fas fa-eye"></i>
                                    </el-button>
                                </el-tooltip>

                                <!-- The API only converts accepted quotes, so the
                                     button states why it is unavailable instead of
                                     letting the request fail. -->
                                <el-tooltip :content="convertHint(row)" placement="top">
                                    <span>
                                        <el-button
                                            size="small"
                                            type="success"
                                            plain
                                            :disabled="!canConvert(row)"
                                            @click="convertToOrder(row)"
                                        >
                                            <i class="fas fa-right-left"></i>
                                        </el-button>
                                    </span>
                                </el-tooltip>

                                <el-tooltip :content="$t('delete')" placement="top">
                                    <el-button size="small" type="danger" plain @click="removeQuote(row)">
                                        <i class="fas fa-trash"></i>
                                    </el-button>
                                </el-tooltip>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty
                    v-else
                    :description="store.quotes.length
                        ? ($t('there_are_no_offers_matching_your_search'))
                        : ($t('no_quotes_yet'))"
                />

                <div v-if="store.pagination.total > store.pagination.per_page" class="pagination-bar">
                    <el-pagination
                        layout="prev, pager, next, total"
                        :total="store.pagination.total"
                        :page-size="store.pagination.per_page"
                        :current-page="store.pagination.current_page"
                        @current-change="changePage"
                    />
                </div>
            </template>
        </el-card>

        <!-- Detail drawer -->
        <el-drawer v-model="detailVisible" :title="selected?.quote_number || ''" size="520px" direction="rtl">
            <div v-if="selected" class="detail-body">
                <div class="detail-status">
                    <el-tag :type="statusTagType(selected.status)" effect="dark" size="large">
                        <i class="fas" :class="statusIcon(selected.status)"></i>
                        {{ statusLabel(selected.status) }}
                    </el-tag>
                </div>

                <el-descriptions :column="1" border class="detail-descriptions">
                    <el-descriptions-item :label="$t('client')">
                        {{ customerName(selected) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="$t('valid_until')">
                        {{ formatDate(selected.valid_until) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="$t('notes')">
                        {{ selected.notes || '—' }}
                    </el-descriptions-item>
                </el-descriptions>

                <h4 class="detail-heading">{{ $t('items') }}</h4>
                <el-table :data="selected.items || []" size="small" border>
                    <el-table-column :label="$t('product')" min-width="150">
                        <template #default="{ row }">
                            {{ row.description || row.product?.name_ar || row.product?.name || '—' }}
                        </template>
                    </el-table-column>
                    <el-table-column prop="quantity" :label="$t('quantity')" width="80" align="center" />
                    <el-table-column :label="$t('unit_price')" width="120" align="left">
                        <template #default="{ row }">{{ formatCurrency(row.unit_price) }}</template>
                    </el-table-column>
                </el-table>

                <div class="totals-box">
                    <div class="totals-row">
                        <span>{{ $t('subtotal') }}</span>
                        <span>{{ formatCurrency(selected.subtotal) }}</span>
                    </div>
                    <div class="totals-row">
                        <span>{{ $t('tax') }}</span>
                        <span>{{ formatCurrency(selected.tax) }}</span>
                    </div>
                    <div class="totals-row">
                        <span>{{ $t('discount') }}</span>
                        <span>-{{ formatCurrency(selected.discount) }}</span>
                    </div>
                    <div class="totals-row grand">
                        <span>{{ $t('total') }}</span>
                        <span>{{ formatCurrency(selected.total) }}</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <el-button @click="detailVisible = false">{{ $t('close') }}</el-button>
                <el-button
                    type="success"
                    :disabled="!canConvert(selected)"
                    :loading="store.saving"
                    @click="convertToOrder(selected)"
                >
                    <i class="fas fa-right-left"></i>
                    {{ $t('convert_to_sales_order') }}
                </el-button>
            </template>
        </el-drawer>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Refresh, ArrowDown } from '@element-plus/icons-vue';
import { useQuotesStore } from '@/stores/quotes';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const { t } = useI18n();
import {
    QUOTE_STATUSES,
    QUOTE_TRANSITIONS,
    availableTransitions,
    normalizeStatus,
    statusTagType,
    statusIcon,
    statusLabel,
    formatCurrency,
    formatDate,
    customerName,
    apiErrorMessage,
} from '@/utils/sales';

const store = useQuotesStore();
const router = useRouter();

const searchQuery = ref('');
const statusFilter = ref('');
const detailVisible = ref(false);
const selected = ref(null);

const countBy = (predicate) => store.quotes.filter(predicate).length;

const summaryCards = computed(() => [
    {
        key: 'all',
        status: '',
        label: window.t?.('total_offers') || t('total_offers'),
        value: store.quotes.length,
        icon: 'fa-file-signature',
        tone: 'blue',
    },
    {
        key: 'sent',
        status: 'sent',
        label: statusLabel('sent'),
        value: countBy((q) => normalizeStatus(q.status) === 'sent'),
        icon: 'fa-paper-plane',
        tone: 'orange',
    },
    {
        key: 'accepted',
        status: 'accepted',
        label: statusLabel('accepted'),
        value: countBy((q) => normalizeStatus(q.status) === 'accepted'),
        icon: 'fa-circle-check',
        tone: 'green',
    },
    {
        key: 'rejected',
        status: 'rejected',
        label: statusLabel('rejected'),
        value: countBy((q) => normalizeStatus(q.status) === 'rejected'),
        icon: 'fa-circle-xmark',
        tone: 'red',
    },
]);

const filteredQuotes = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const status = statusFilter.value;

    return store.quotes.filter((quote) => {
        if (status && normalizeStatus(quote.status) !== status) return false;
        if (!query) return true;
        return [quote.quote_number, customerName(quote), quote.notes]
            .some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const transitionsFor = (status) => availableTransitions(status, QUOTE_TRANSITIONS);

const isExpired = (quote) => {
    if (!quote.valid_until) return false;
    if (normalizeStatus(quote.status) === 'accepted') return false;
    return new Date(quote.valid_until) < new Date();
};

const canConvert = (quote) => !!quote && normalizeStatus(quote.status) === 'accepted';

const convertHint = (quote) =>
    canConvert(quote)
        ? (window.t?.('convert_to_sales_order') || t('convert_to_sales_order'))
        : t('quote_must_be_accepted_first');

const toggleFilter = (status) => {
    statusFilter.value = statusFilter.value === status ? '' : status;
};

const reload = () => store.fetchQuotes({ page: store.pagination.current_page }).catch(() => {});

const changePage = (page) => store.fetchQuotes({ page }).catch(() => {});

const openDetail = async (quote) => {
    selected.value = quote;
    detailVisible.value = true;
    try {
        // The list payload may omit items; fetch the full record for the drawer.
        selected.value = await store.fetchQuote(quote.id);
    } catch (error) {
        // Keep showing the list row rather than emptying the drawer.
    }
};

const changeStatus = async (quote, status) => {
    if (normalizeStatus(quote.status) === status) return;
    try {
        await ElMessageBox.confirm(
            `تغيير حالة العرض ${quote.quote_number} إلى "${statusLabel(status)}"؟`,
            t('confirm'),
            { type: 'warning', confirmButtonText: t('confirm'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    try {
        await store.updateQuoteStatus(quote, status);
        ElMessage.success(t('quote_status_updated'));
        if (selected.value?.id === quote.id) selected.value = { ...selected.value, status };
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_update_quote_status')));
    }
};

const convertToOrder = async (quote) => {
    if (!canConvert(quote)) return;
    try {
        await ElMessageBox.confirm(
            `تحويل عرض السعر ${quote.quote_number} إلى طلب بيع؟`,
            t('convert_to_sales_order'),
            { type: 'info', confirmButtonText: t('convert'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    try {
        const order = await store.convertToSalesOrder(quote.id);
        detailVisible.value = false;
        ElMessage.success({
            message: `تم إنشاء طلب البيع ${order?.order_number || ''}`,
            duration: 4000,
        });
        // Take the user to the order that was just created — the conversion is
        // only useful if they can act on the result.
        router.push('/admin/sales/sales-orders');
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_convert_quote')));
    }
};

const removeQuote = async (quote) => {
    try {
        await ElMessageBox.confirm(
            `حذف عرض السعر ${quote.quote_number}؟ لا يمكن التراجع عن هذا الإجراء.`,
            t('confirm_deletion'),
            { type: 'warning', confirmButtonText: t('delete'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    try {
        await store.deleteQuote(quote.id);
        ElMessage.success(t('quote_deleted'));
        if (selected.value?.id === quote.id) detailVisible.value = false;
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_delete_quote')));
    }
};

onMounted(() => {
    store.fetchQuotes().catch(() => {});
});
</script>

<!-- Layout comes from resources/css/sales-shared.css (namespaced under .sales-page). -->
