<template>
    <div class="customer-profile-page-view">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ t('my_profile') || 'ملفي الشخصي' }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <span class="sep">›</span>
                    <span>{{ t('my_profile') || 'ملفي الشخصي' }}</span>
                </div>
            </div>
        </section>

        <!-- Main Content Section -->
        <section class="contact-section fade-up">
            <div class="container">
                <div class="section-header">
                    <h2>{{ t('orders_invoices') || 'الطلبات والفواتير' }}</h2>
                    <p>{{ t('enter_phone_orders') || 'أدخل رقم هاتفك لعرض طلباتك وفواتيرك' }}</p>
                </div>

                <!-- Lookup form when info is not loaded -->
                <div v-if="!customerData" class="contact-wrapper" style="justify-content: center;">
                    <div class="contact-form-wrapper" style="max-width: 500px; width: 100%;">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                            <h3 style="margin:0;">{{ t('search_by_phone') || 'البحث برقم الهاتف' }}</h3>
                            <button v-if="!customerAuth.isAuthenticated" class="btn-submit" style="padding:8px 18px; font-size:0.85rem;" @click="triggerHeaderLogin">
                                <i class="fas fa-sign-in-alt"></i> {{ t('login') || 'تسجيل دخول' }}
                            </button>
                        </div>
                        <form @submit.prevent="handleLookup" class="contact-form" style="margin-top:15px;">
                            <div class="form-group">
                                <label for="lookupPhone">{{ t('phone_label') || 'رقم الهاتف' }} <span style="color: #dc3545;">*</span></label>
                                <input type="tel" id="lookupPhone" v-model="phoneInput" required>
                            </div>
                            <button type="submit" class="btn-submit" :disabled="loading">
                                <i class="fas" :class="loading ? 'fa-spinner fa-spin' : 'fa-search'"></i>
                                {{ t('view_my_orders') || 'عرض طلباتي' }}
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Customer Details card when loaded -->
                <div v-else class="customer-details-section">
                    <div class="customer-info-gradient-card">
                        <div class="card-inner-flex">
                            <div class="user-avatar-circle">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="user-info-text">
                                <h3>{{ customerData.customer.name }}</h3>
                                <p class="greeting-text">{{ customerAuth.isAuthenticated ? (t('welcome_back_orders') || 'مرحباً بعودتك! إليك أحدث طلباتك وفواتيرك') : (t('track_orders_here') || 'يمكنك متابعة طلباتك وفواتيرك من هنا') }}</p>
                                <p class="contact-meta">{{ t('phone') || 'هاتف:' }} {{ customerData.customer.phone }}</p>
                                <p class="contact-meta" v-if="customerData.customer.email">{{ t('email') || 'بريد:' }} {{ customerData.customer.email }}</p>
                            </div>
                            <div class="actions-flex">
                                <button class="btn-refresh" @click="refreshLookup" :disabled="loading">
                                    <i class="fas" :class="loading ? 'fa-spinner fa-spin' : 'fa-sync-alt'"></i>
                                    {{ t('refresh') || 'تحديث' }}
                                </button>
                                <button class="btn-logout-cust" @click="handleClearLookup">
                                    <i class="fas fa-sign-out-alt"></i>
                                    {{ t('logout') || 'خروج' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Orders List -->
                    <div class="orders-list-section" style="margin-top: 30px;">
                        <h3 class="section-title-orders">
                            <i class="fas fa-shopping-cart"></i> {{ t('orders_invoices') || 'الطلبات والفواتير' }}
                        </h3>
                        
                        <div v-if="loading" style="text-align: center; padding: 3rem;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--mobile-primary);"></i>
                            <p style="margin-top:10px; color:#666;">{{ t('updating_data') || 'جاري تحديث البيانات...' }}</p>
                        </div>

                        <div v-else-if="customerData.orders && customerData.orders.length > 0" class="orders-grid-list">
                            <div v-for="order in customerData.orders" 
                                 :key="order.id" 
                                 class="order-card"
                                 @click="toggleOrderExpand(order.id)">
                                
                                <div class="order-card-header">
                                    <div>
                                        <strong class="order-num">{{ order.order_number }}</strong>
                                        <br>
                                        <small class="order-date">{{ order.order_date }}</small>
                                    </div>
                                    <span :class="getStatusClass(order.status)">{{ getStatusText(order.status) }}</span>
                                </div>

                                <div class="order-card-summary">
                                    <span class="order-total-price">${{ parseFloat(order.total).toFixed(2) }}</span>
                                    <small class="toggle-detail-text">
                                        <i class="fas" :class="expandedOrders.includes(order.id) ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                        {{ expandedOrders.includes(order.id) ? (t('hide_details') || 'إخفاء التفاصيل') : (t('show_details') || 'عرض التفاصيل') }}
                                    </small>
                                </div>

                                <div v-if="order.notes" class="order-notes-box">
                                    <i class="fas fa-sticky-note"></i> {{ order.notes }}
                                </div>

                                <!-- Expanded Detail Area -->
                                <div v-if="expandedOrders.includes(order.id)" class="order-expanded-details" @click.stop>
                                    <!-- Items Table -->
                                    <div class="items-table-wrapper" v-if="order.items && order.items.length">
                                        <table class="order-items-table">
                                            <thead>
                                                <tr>
                                                    <th>{{ t('product') || 'المنتج' }}</th>
                                                    <th>{{ t('quantity') || 'الكمية' }}</th>
                                                    <th style="text-align: left;">{{ t('price') || 'السعر' }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="item in order.items" :key="item.id">
                                                    <td>{{ item.product_name }}</td>
                                                    <td>{{ item.quantity }}</td>
                                                    <td style="text-align: left;">${{ parseFloat(item.total).toFixed(2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Invoices List -->
                                    <div class="order-invoices-section" v-if="order.invoices && order.invoices.length">
                                        <div class="invoices-header">
                                            <i class="fas fa-file-invoice"></i> {{ t('related_invoices') || 'الفواتير المرتبطة' }}
                                        </div>
                                        <div v-for="inv in order.invoices" :key="inv.id" class="invoice-row-card">
                                            <strong>{{ inv.invoice_number }}</strong>
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <span :class="getStatusClass(inv.status)" style="font-size:0.75rem;">{{ inv.status_label || getStatusText(inv.status) }}</span>
                                                <span class="invoice-total">${{ parseFloat(inv.total).toFixed(2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div v-else class="no-orders-box">
                            <i class="fas fa-inbox"></i>
                            <p>{{ t('no_orders_yet') || 'لا توجد طلبات حتى الآن' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Error area -->
                <div v-if="errorMsg" class="lookup-error-box">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>{{ errorMsg }}</p>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useCustomerAuthStore } from '@/stores/customerAuth';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

// Stores
const customerAuth = useCustomerAuthStore();
const { t } = useI18n();

// State
const phoneInput = ref('');
const loading = ref(false);
const customerData = ref(null);
const errorMsg = ref('');
const expandedOrders = ref([]);

// Computed
const isAuthenticated = computed(() => customerAuth.isAuthenticated);

// Helpers
const getStatusClass = (status) => {
    return 'status-badge status-' + status;
};

const getStatusText = (status) => {
    const map = {
        'pending': t('status_pending') || 'معلق',
        'confirmed': t('status_confirmed') || 'مؤكد',
        'processing': t('status_processing') || 'قيد المعالجة',
        'shipped': t('status_shipped') || 'تم الشحن',
        'delivered': t('status_delivered') || 'تم التسليم',
        'cancelled': t('status_cancelled') || 'ملغي',
        'paid': t('status_paid') || 'مدفوع',
    };
    return map[status] || status;
};

const toggleOrderExpand = (orderId) => {
    if (expandedOrders.value.includes(orderId)) {
        expandedOrders.value = expandedOrders.value.filter(id => id !== orderId);
    } else {
        expandedOrders.value.push(orderId);
    }
};

const triggerHeaderLogin = () => {
    // Dispatch custom event to trigger login modal in PublicLayout
    window.dispatchEvent(new CustomEvent('trigger-customer-login-modal'));
};

const lookupOrders = async (phone) => {
    loading.value = true;
    errorMsg.value = '';
    try {
        const res = await axios.get('/api/v1/purchase-requests/orders?phone=' + encodeURIComponent(phone));
        if (res.data?.success && res.data.data) {
            customerData.value = res.data.data;
        } else {
            errorMsg.value = res.data?.message || 'لم يتم العثور على بيانات لهذا الرقم';
            customerData.value = null;
        }
    } catch (err) {
        console.error(err);
        errorMsg.value = err.response?.data?.message || 'حدث خطأ في الاتصال بالخادم';
        customerData.value = null;
    } finally {
        loading.value = false;
    }
};

const handleLookup = () => {
    const phone = phoneInput.value.trim();
    if (!phone) return;
    lookupOrders(phone);
};

const refreshLookup = () => {
    if (customerData.value?.customer?.phone) {
        lookupOrders(customerData.value.customer.phone);
    }
};

const handleClearLookup = () => {
    customerData.value = null;
    phoneInput.value = '';
    errorMsg.value = '';
};

// Check stored settings / user profiles
const initProfile = () => {
    // If logged in via customer store
    if (customerAuth.isAuthenticated && customerAuth.customer?.phone) {
        phoneInput.value = customerAuth.customer.phone;
        lookupOrders(customerAuth.customer.phone);
    } else {
        // Fallback to locally saved profile info from guest checkout
        try {
            const stored = localStorage.getItem('customer_info');
            if (stored) {
                const info = JSON.parse(stored);
                if (info && info.phone) {
                    phoneInput.value = info.phone;
                    lookupOrders(info.phone);
                }
            }
        } catch (e) {
            console.error('Failed parsing locally stored customer info', e);
        }
    }
};

onMounted(() => {
    initProfile();
});

// Watch authenticated user changes
watch(() => customerAuth.customer, (newVal) => {
    if (newVal && newVal.phone) {
        phoneInput.value = newVal.phone;
        lookupOrders(newVal.phone);
    } else {
        customerData.value = null;
    }
});
</script>

<style scoped>
.customer-profile-page-view {
    min-height: 80vh;
}

.customer-details-section {
    max-width: 800px;
    margin: 0 auto;
}

.customer-info-gradient-card {
    background: linear-gradient(135deg, var(--mobile-primary), #1d4ed8);
    padding: 30px;
    border-radius: 16px;
    color: #fff;
    box-shadow: 0 4px 20px rgba(37, 99, 235, 0.25);
}

.card-inner-flex {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.user-avatar-circle {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.user-info-text {
    flex: 1;
}

.user-info-text h3 {
    margin: 0 0 5px 0;
    font-size: 1.4rem;
    color: #fff;
}

.greeting-text {
    margin: 0 0 8px 0;
    color: #e0f2fe;
    font-size: 0.95rem;
}

.contact-meta {
    margin: 0 0 3px 0;
    color: #f0f9ff;
    font-size: 0.9rem;
}

.actions-flex {
    display: flex;
    gap: 10px;
}

.btn-refresh {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.2);
    color: #fff;
    border: none;
    padding: 10px 22px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-refresh:hover:not(:disabled) {
    background: rgba(255,255,255,0.3);
}

.btn-logout-cust {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ef4444;
    color: #fff;
    border: none;
    padding: 10px 22px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-logout-cust:hover {
    background: #dc2626;
}

.section-title-orders {
    margin-bottom: 20px;
    color: var(--mobile-primary);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1.3rem;
}

.orders-grid-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.order-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-right: 4px solid var(--mobile-primary);
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid #eee;
    border-right-width: 4px;
    border-right-color: var(--mobile-primary);
}

.order-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.order-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.order-num {
    font-size: 1.05rem;
    color: #333;
}

.order-date {
    color: #888;
}

.status-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
}

:deep(.status-pending) { background: #fff3cd; color: #856404; }
:deep(.status-confirmed) { background: #cce5ff; color: #004085; }
:deep(.status-processing) { background: #d6d8d9; color: #383d41; }
:deep(.status-shipped) { background: #d4edda; color: #155724; }
:deep(.status-delivered) { background: #d4edda; color: #155724; border: 2px solid #28a745; }
:deep(.status-cancelled) { background: #f8d7da; color: #721c24; }
:deep(.status-paid) { background: #d4edda; color: #155724; }

.order-card-summary {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #f6f6f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.order-total-price {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--mobile-primary);
}

.toggle-detail-text {
    color: #909399;
    display: flex;
    align-items: center;
    gap: 5px;
}

.order-notes-box {
    margin-top: 8px;
    color: #666;
    font-size: 0.9rem;
    background: #fdfdfd;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #f5f5f5;
}

.order-expanded-details {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px dashed #eee;
}

.items-table-wrapper {
    margin-top: 12px;
    font-size: 0.9rem;
}

.order-items-table {
    width: 100%;
    border-collapse: collapse;
}

.order-items-table th {
    border-bottom: 2px solid var(--mobile-primary);
    text-align: right;
    padding: 8px 4px;
    color: #333;
    font-weight: 600;
}

.order-items-table td {
    padding: 8px 4px;
    border-bottom: 1px solid #f6f6f6;
    color: #555;
}

.order-invoices-section {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--mobile-primary);
    font-size: 0.85rem;
}

.invoices-header {
    font-weight: 700;
    color: var(--mobile-primary);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.invoice-row-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 5px;
    border: 1px solid #f0f0f0;
}

.invoice-total {
    font-weight: 700;
    color: var(--mobile-primary);
}

.no-orders-box {
    text-align: center;
    padding: 40px;
    background: #fcfcfc;
    border-radius: 16px;
    color: #888;
    border: 1px dashed #ddd;
}

.no-orders-box i {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: #ccc;
}

.lookup-error-box {
    text-align: center;
    padding: 20px;
    background: #fdf2f2;
    border: 1px solid #fde2e2;
    border-radius: 12px;
    color: #ef4444;
    margin-top: 20px;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.lookup-error-box i {
    font-size: 1.5rem;
    margin-bottom: 8px;
}
</style>

<style scoped>
.customer-profile-page-view {
    min-height: 80vh;
    padding-bottom: 3rem;
}

.customer-details-section {
    max-width: 800px;
    margin: 0 auto;
    padding: 30px 15px;
}

.customer-info-gradient-card {
    background: linear-gradient(135deg, color-mix(in srgb, var(--mobile-primary) 80%, transparent) 0%, rgba(29, 78, 216, 0.5) 100%) !important;
    backdrop-filter: blur(25px) saturate(180%) !important;
    -webkit-backdrop-filter: blur(25px) saturate(180%) !important;
    padding: 30px;
    border-radius: 24px !important;
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15) !important;
}

[data-theme="dark"] .customer-info-gradient-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.75) 0%, color-mix(in srgb, var(--mobile-primary) 20%, transparent) 100%) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
}

.card-inner-flex {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.user-avatar-circle {
    width: 70px;
    height: 70px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.25);
}

.user-info-text {
    flex: 1;
}

.user-info-text h3 {
    margin: 0 0 5px 0;
    font-size: 1.4rem;
    font-weight: 800;
    color: #fff;
}

.greeting-text {
    margin: 0 0 8px 0;
    color: #f1f5f9;
    font-size: 0.95rem;
    font-weight: 600;
}

.contact-meta {
    margin: 0 0 3px 0;
    color: #cbd5e1;
    font-size: 0.9rem;
}

.actions-flex {
    display: flex;
    gap: 12px;
}

.btn-refresh {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 10px 22px;
    border-radius: 12px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 700;
    transition: all 0.3s ease;
}

.btn-refresh:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-2px);
}

[data-theme="dark"] .btn-refresh {
    background: rgba(99, 102, 241, 0.2);
    color: #e2e8f0;
    border-color: rgba(99, 102, 241, 0.3);
}

[data-theme="dark"] .btn-refresh:hover:not(:disabled) {
    background: rgba(99, 102, 241, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.btn-logout-cust {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ef4444;
    color: #fff;
    border: none;
    padding: 10px 22px;
    border-radius: 12px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 700;
    transition: all 0.3s ease;
}

.btn-logout-cust:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(239, 68, 68, 0.3);
}

[data-theme="dark"] .btn-logout-cust {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
}

[data-theme="dark"] .btn-logout-cust:hover {
    background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
}

[data-theme="dark"] .btn-submit {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3) !important;
    border: none !important;
}

[data-theme="dark"] .btn-submit:hover {
    background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%) !important;
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4) !important;
}

.section-title-orders {
    margin: 30px 0 20px;
    color: var(--mobile-primary);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1.3rem;
    font-weight: 800;
}

[data-theme="dark"] .section-title-orders {
    color: var(--mobile-primary);
}

.orders-grid-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.order-card {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%) !important;
    -webkit-backdrop-filter: blur(20px) saturate(160%) !important;
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    border-right: 4px solid var(--mobile-primary) !important;
    border-radius: 20px !important;
    padding: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

[data-theme="dark"] .order-card {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    border-right: 4px solid var(--mobile-primary) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
}

.order-card:hover {
    transform: translateY(-4px) scale(1.005) !important;
    background: rgba(255, 255, 255, 0.85) !important;
    box-shadow: 0 15px 35px color-mix(in srgb, var(--mobile-primary) 8%, transparent) !important;
}

[data-theme="dark"] .order-card:hover {
    background: rgba(30, 41, 59, 0.6) !important;
    box-shadow: 0 15px 35px color-mix(in srgb, var(--mobile-primary) 10%, transparent) !important;
}

.order-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.order-num {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
}

[data-theme="dark"] .order-num {
    color: #f1f5f9;
}

.order-date {
    color: #64748b;
    font-weight: 600;
}

[data-theme="dark"] .order-date {
    color: #cbd5e1;
}

.status-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
    box-shadow: 0 4px 10px rgba(0,0,0,0.02);
}

:deep(.status-pending) { background: #fef3c7; color: #92400e; }
[data-theme="dark"] :deep(.status-pending) { background: rgba(146, 64, 14, 0.2); color: #fcd34d; border: 1px solid rgba(252, 211, 77, 0.2); }

:deep(.status-confirmed) { background: #dbeafe; color: #1e40af; }
[data-theme="dark"] :deep(.status-confirmed) { background: rgba(30, 64, 175, 0.2); color: #93c5fd; border: 1px solid rgba(147, 197, 253, 0.2); }

:deep(.status-processing) { background: #f1f5f9; color: #475569; }
[data-theme="dark"] :deep(.status-processing) { background: rgba(255,255,255,0.05); color: #cbd5e1; border: 1px solid rgba(255,255,255,0.08); }

:deep(.status-shipped) { background: #e0f2fe; color: #0369a1; }
[data-theme="dark"] :deep(.status-shipped) { background: rgba(3, 105, 161, 0.2); color: #7dd3fc; border: 1px solid rgba(125, 211, 252, 0.2); }

:deep(.status-delivered) { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
[data-theme="dark"] :deep(.status-delivered) { background: rgba(6, 95, 70, 0.2); color: #34d399; border: 1px solid #34d399; }

:deep(.status-cancelled) { background: #fee2e2; color: #991b1b; }
[data-theme="dark"] :deep(.status-cancelled) { background: rgba(153, 27, 27, 0.2); color: #fca5a5; border: 1px solid rgba(252, 165, 165, 0.2); }

:deep(.status-paid) { background: #d1fae5; color: #065f46; }
[data-theme="dark"] :deep(.status-paid) { background: rgba(6, 95, 70, 0.2); color: #34d399; }

.order-card-summary {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed rgba(0, 0, 0, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

[data-theme="dark"] .order-card-summary {
    border-top-color: rgba(255, 255, 255, 0.08);
}

.order-total-price {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--mobile-primary);
}

[data-theme="dark"] .order-total-price {
    color: var(--mobile-primary);
}

.toggle-detail-text {
    color: #64748b;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}

[data-theme="dark"] .toggle-detail-text {
    color: #94a3b8;
}

.order-notes-box {
    margin-top: 8px;
    color: #475569;
    font-size: 0.9rem;
    background: rgba(0, 0, 0, 0.02);
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid rgba(0, 0, 0, 0.04);
}

[data-theme="dark"] .order-notes-box {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.05);
    color: #cbd5e1;
}

.order-expanded-details {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px dashed rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .order-expanded-details {
    border-top-color: rgba(255, 255, 255, 0.08);
}

.items-table-wrapper {
    margin-top: 12px;
    font-size: 0.9rem;
}

.order-items-table {
    width: 100%;
    border-collapse: collapse;
}

.order-items-table th {
    border-bottom: 2px solid var(--mobile-primary);
    text-align: right;
    padding: 8px 4px;
    color: #334155;
    font-weight: 700;
}

[data-theme="dark"] .order-items-table th {
    border-bottom-color: var(--mobile-primary);
    color: #cbd5e1;
}

.order-items-table td {
    padding: 10px 4px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.04);
    color: #475569;
}

[data-theme="dark"] .order-items-table td {
    border-bottom-color: rgba(255, 255, 255, 0.04);
    color: #94a3b8;
}

.order-invoices-section {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px dashed rgba(0, 0, 0, 0.08);
    font-size: 0.85rem;
}

[data-theme="dark"] .order-invoices-section {
    border-top-color: rgba(255, 255, 255, 0.08);
}

.invoices-header {
    font-weight: 700;
    color: var(--mobile-primary);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

[data-theme="dark"] .invoices-header {
    color: var(--mobile-primary);
}

.invoice-row-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: rgba(0, 0, 0, 0.02);
    border-radius: 8px;
    margin-bottom: 5px;
    border: 1px solid rgba(0, 0, 0, 0.04);
}

[data-theme="dark"] .invoice-row-card {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.05);
}

.invoice-total {
    font-weight: 700;
    color: var(--mobile-primary);
}

[data-theme="dark"] .invoice-total {
    color: var(--mobile-primary);
}

.no-orders-box {
    text-align: center;
    padding: 40px;
    background: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    color: #64748b;
    border: 1px dashed rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .no-orders-box {
    background: rgba(30, 41, 59, 0.35);
    border-color: rgba(255, 255, 255, 0.08);
    color: #cbd5e1;
}

.no-orders-box i {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: #cbd5e1;
}

[data-theme="dark"] .no-orders-box i {
    color: #475569;
}

.lookup-error-box {
    text-align: center;
    padding: 20px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 16px;
    color: #ef4444;
    margin-top: 20px;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

[data-theme="dark"] .lookup-error-box {
    background: rgba(239, 68, 68, 0.15);
}

.lookup-error-box i {
    font-size: 1.5rem;
    margin-bottom: 8px;
}
</style>

