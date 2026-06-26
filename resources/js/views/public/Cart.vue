<template>
    <div class="cart-page-view">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ t('shopping_cart') || 'سلة المشتريات' }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <span class="sep">›</span>
                    <span>{{ t('shopping_cart') || 'سلة المشتريات' }}</span>
                </div>
            </div>
        </section>

        <!-- Cart Content -->
        <section class="cart-section fade-up">
            <div class="container">
                <div v-if="cartStore.loading && !cart" style="text-align: center; padding: 4rem 0;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2.5rem; color: var(--mobile-primary);"></i>
                    <p style="margin-top:15px; color:#666;">{{ t('loading_cart') || 'جاري تحميل السلة...' }}</p>
                </div>

                <div v-else-if="cart && cart.items && cart.items.length" class="cart-grid">
                    <!-- Cart Items -->
                    <div class="cart-items">
                        <div class="cart-items-header">
                            <h2>{{ t('items') || 'المنتجات' }} ({{ cartStore.totalItems }})</h2>
                            <button class="btn-clear-cart" @click="handleClearCart" :disabled="cartStore.loading">
                                <i class="fas fa-trash-alt"></i>
                                {{ t('clear_cart') || 'تفريغ السلة' }}
                            </button>
                        </div>

                        <div class="cart-items-list">
                            <div v-for="item in cart.items" :key="item.id" class="cart-item">
                                <div class="cart-item-image">
                                    <img :src="getImageUrl(item.product.image_main)" :alt="item.product.name_ar" @error="handleImageError">
                                </div>
                                <div class="cart-item-details">
                                    <h3 class="cart-item-name">{{ $p(item.product, 'name') }}</h3>
                                    <p class="cart-item-category">{{ $p(item.product.category, 'name') || t('category') || 'تصنيف' }}</p>
                                    <div class="cart-item-price">
                                        <template v-if="item.product.sale_price && parseFloat(item.product.sale_price) < parseFloat(item.product.price)">
                                            <span class="original-price">${{ parseFloat(item.product.price).toFixed(2) }}</span>
                                            <span class="sale-price">${{ parseFloat(item.product.sale_price).toFixed(2) }}</span>
                                        </template>
                                        <template v-else>
                                            <span class="price">${{ parseFloat(item.price).toFixed(2) }}</span>
                                        </template>
                                    </div>
                                </div>
                                <div class="cart-item-actions">
                                    <div class="quantity-control">
                                        <button class="qty-btn qty-minus" @click="handleUpdateQty(item, item.quantity - 1)" :disabled="cartStore.loading">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="qty-input" :value="item.quantity" min="1" readonly>
                                        <button class="qty-btn qty-plus" @click="handleUpdateQty(item, item.quantity + 1)" :disabled="cartStore.loading">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    <div class="cart-item-subtotal">
                                        <span class="subtotal-label">{{ t('subtotal') || 'المجموع:' }}</span>
                                        <span class="subtotal-value">${{ parseFloat(item.subtotal).toFixed(2) }}</span>
                                    </div>
                                    <button class="btn-remove-item" @click="handleRemoveItem(item.id)" :disabled="cartStore.loading">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Summary -->
                    <div class="cart-summary">
                        <div class="summary-card">
                            <h2>{{ t('order_summary') || 'ملخص الطلب' }}</h2>
                            
                            <div class="summary-row">
                                <span>{{ t('items_count') || 'عدد المنتجات' }}</span>
                                <span>{{ cartStore.totalItems }}</span>
                            </div>
                            
                            <div class="summary-row">
                                <span>{{ t('subtotal') || 'المجموع الفرعي' }}</span>
                                <span>${{ cartStore.totalAmount.toFixed(2) }}</span>
                            </div>
                            
                            <div class="summary-row">
                                <span>{{ t('shipping') || 'الشحن' }}</span>
                                <span>{{ t('determined_later') || 'يتم تحديده لاحقاً' }}</span>
                            </div>
                            
                            <div class="summary-divider"></div>
                            
                            <div class="summary-row summary-total">
                                <span>{{ t('total') || 'الإجمالي' }}</span>
                                <span class="total-value">${{ cartStore.totalAmount.toFixed(2) }}</span>
                            </div>

                            <div class="cart-actions">
                                <button class="btn-checkout purchase-request-btn" @click="openPurchaseModal" :disabled="cartStore.loading">
                                    <i class="fas fa-file-invoice"></i>
                                    {{ t('send_purchase_request') || 'إرسال طلب شراء' }}
                                </button>
                                <a :href="'https://wa.me/' + (settings.contact_whatsapp || '963900000000') + '?text=' + encodeURIComponent(whatsappMessageText)" 
                                   class="btn-checkout whatsapp-checkout" 
                                   target="_blank">
                                    <i class="fab fa-whatsapp"></i>
                                    {{ t('whatsapp_checkout') || 'إتمام الطلب عبر واتساب' }}
                                </a>
                                <router-link to="/categories" class="btn-continue-shopping">
                                    <i class="fas fa-shopping-bag"></i>
                                    {{ t('continue_shopping') || 'متابعة التسوق' }}
                                </router-link>
                            </div>
                        </div>

                        <!-- Trust Badges -->
                        <div class="trust-badges">
                            <div class="trust-badge">
                                <i class="fas fa-shield-alt"></i>
                                <span>{{ t('quality_guarantee') || 'ضمان الجودة' }}</span>
                            </div>
                            <div class="trust-badge">
                                <i class="fas fa-truck"></i>
                                <span>{{ t('fast_delivery') || 'توصيل سريع' }}</span>
                            </div>
                            <div class="trust-badge">
                                <i class="fas fa-headset"></i>
                                <span>{{ t('technical_support') || 'دعم فني' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty Cart -->
                <div v-else class="empty-cart">
                    <div class="empty-cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h2>{{ t('cart_empty') || 'سلة المشتريات فارغة' }}</h2>
                    <p>{{ t('cart_empty_desc') || 'لم تقم بإضافة أي منتجات إلى السلة بعد' }}</p>
                    <router-link to="/categories" class="btn-start-shopping">
                        <i class="fas fa-shopping-bag"></i>
                        {{ t('start_shopping_now') || 'ابدأ التسوق الآن' }}
                    </router-link>
                </div>
            </div>
        </section>

        <!-- Purchase Request Modal -->
        <div v-if="showPurchaseModal" class="purchase-modal-overlay" @click.self="closePurchaseModal">
            <div class="purchase-modal">
                <div class="purchase-modal-header">
                    <h2>{{ t('send_purchase_request') || 'إرسال طلب شراء' }}</h2>
                    <button @click="closePurchaseModal" class="purchase-modal-close">&times;</button>
                </div>
                <div class="purchase-modal-body">
                    
                    <div v-if="purchaseState.successMsg" style="background: #d4edda; color: #155724; padding: 20px; border-radius: 12px; text-align: center; margin-bottom: 20px;">
                        <i class="fas fa-check-circle" style="font-size: 2.5rem; margin-bottom: 10px;"></i>
                        <h3 style="margin-bottom: 8px;">تم إرسال طلب الشراء بنجاح</h3>
                        <p>{{ purchaseState.successMsg }}</p>
                        <p style="color: #666; margin-top: 10px;">سنقوم بالتواصل معك في أقرب وقت ممكن</p>
                    </div>
                    
                    <div v-if="purchaseState.errorMsg" style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 12px; text-align: center; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>{{ purchaseState.errorMsg }}</p>
                    </div>

                    <form v-if="!purchaseState.successMsg" @submit.prevent="submitPurchaseRequest">
                        <div class="form-row two-cols">
                            <div class="form-group">
                                <label for="prName">{{ t('full_name') || 'الاسم الكامل' }} <span style="color:#dc3545;">*</span></label>
                                <input type="text" id="prName" v-model="purchaseState.name" required>
                            </div>
                            <div class="form-group">
                                <label for="prPhone">{{ t('phone_label') || 'رقم الهاتف' }} <span style="color:#dc3545;">*</span></label>
                                <input type="tel" id="prPhone" v-model="purchaseState.phone" required>
                            </div>
                        </div>
                        <div class="form-row two-cols">
                            <div class="form-group">
                                <label for="prEmail">{{ t('email_label') || 'البريد الإلكتروني' }}</label>
                                <input type="email" id="prEmail" v-model="purchaseState.email">
                            </div>
                            <div class="form-group">
                                <label for="prAddress">{{ t('address_label') || 'العنوان' }}</label>
                                <input type="text" id="prAddress" v-model="purchaseState.address">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>{{ t('items') || 'المنتجات المطلوبة' }}</label>
                            <div class="pr-items-list-box">
                                <div v-for="item in cart.items" :key="item.id" class="pr-item-row">
                                    <span class="pr-item-name">{{ $p(item.product, 'name') }}</span>
                                    <span class="pr-item-qty">{{ t('quantity') || 'الكمية' }}: {{ item.quantity }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:15px;">
                            <label for="prNotes">{{ t('additional_notes') || 'ملاحظات إضافية' }}</label>
                            <textarea id="prNotes" v-model="purchaseState.notes" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn-checkout" style="margin-top:20px; width:100%; background: var(--mobile-primary);" :disabled="purchaseState.submitting">
                            <i class="fas" :class="purchaseState.submitting ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                            {{ t('send_order') || 'إرسال الطلب' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notification Toast -->
        <div v-if="toast.show" class="cart-notification success show" style="top: 100px;">
            <i class="fas fa-check-circle"></i>
            <span>{{ toast.message }}</span>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useCartStore } from '@/stores/cart';
import { useSettingsStore } from '@/stores/settings';
import { useCustomerAuthStore } from '@/stores/customerAuth';
import { getImageUrl } from '@/utils/imageUrl';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

// Stores
const cartStore = useCartStore();
const settingsStore = useSettingsStore();
const customerAuth = useCustomerAuthStore();
const { t } = useI18n();

// State
const showPurchaseModal = ref(false);
const toast = reactive({ show: false, message: '' });

const purchaseState = reactive({
    name: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
    submitting: false,
    successMsg: '',
    errorMsg: ''
});

// Computed
const cart = computed(() => cartStore.cart);
const settings = computed(() => settingsStore.data);

const whatsappMessageText = computed(() => {
    if (!cart.value || !cart.value.items) return '';
    // $p is generally used in templates, we can just use the fallback here
    const itemsStr = cart.value.items.map(i => `${i.product.name_ar} (Qty: ${i.quantity})`).join('\n');
    return `Hello, I'd like to place an order:\n${itemsStr}\nTotal: $${cartStore.totalAmount.toFixed(2)}`;
});

// Helpers

const handleImageError = (e) => {
    e.target.src = '/assets/images/placeholder.jpg';
};

const handleUpdateQty = async (item, newQty) => {
    if (newQty < 1) return;
    try {
        await cartStore.updateQuantity(item.id, newQty);
        showToast('تم تحديث الكمية');
    } catch (e) {
        showToast('فشل تحديث الكمية');
    }
};

const handleRemoveItem = async (itemId) => {
    try {
        await cartStore.removeItem(itemId);
        showToast('تم حذف المنتج من السلة');
    } catch (e) {
        showToast('فشل حذف المنتج');
    }
};

const handleClearCart = async () => {
    if (confirm('هل أنت متأكد من رغبتك في تفريغ سلة المشتريات بالكامل؟')) {
        try {
            await cartStore.clearCart();
            showToast('تم تفريغ السلة');
        } catch (e) {
            showToast('فشل تفريغ السلة');
        }
    }
};

const showToast = (msg) => {
    toast.message = msg;
    toast.show = true;
    setTimeout(() => {
        toast.show = false;
    }, 3000);
};

// Purchase Modal
const openPurchaseModal = () => {
    // Prefill with customer profile if available
    const user = customerAuth.customer;
    purchaseState.name = user?.name || '';
    purchaseState.phone = user?.phone || '';
    purchaseState.email = user?.email || '';
    purchaseState.address = user?.address || '';
    purchaseState.notes = '';
    purchaseState.successMsg = '';
    purchaseState.errorMsg = '';
    showPurchaseModal.value = true;
};

const closePurchaseModal = () => {
    showPurchaseModal.value = false;
};

const submitPurchaseRequest = async () => {
    purchaseState.submitting = true;
    purchaseState.successMsg = '';
    purchaseState.errorMsg = '';
    try {
        const payload = {
            name: purchaseState.name,
            phone: purchaseState.phone,
            email: purchaseState.email || null,
            address: purchaseState.address || null,
            notes: purchaseState.notes || null,
            items: cart.value.items.map(item => ({
                product_name: item.product.name_ar,
                quantity: item.quantity,
                notes: null
            }))
        };
        const res = await axios.post('/api/v1/purchase-requests', payload);
        if (res.data?.success) {
            purchaseState.successMsg = `رقم الطلب: ${res.data.data.order_number}`;
            
            // If they are not logged in but successfully ordered, we save their info in localStorage so it persists
            if (!customerAuth.isAuthenticated) {
                localStorage.setItem('customer_info', JSON.stringify(res.data.data.customer));
            }
            
            // Clear cart from Pinia store
            await cartStore.clearCart();
            
            setTimeout(() => {
                showPurchaseModal.value = false;
                purchaseState.successMsg = '';
            }, 5000);
        } else {
            purchaseState.errorMsg = res.data?.message || 'فشل إرسال الطلب.';
        }
    } catch (e) {
        purchaseState.errorMsg = e.response?.data?.message || 'حدث خطأ في الاتصال بالخادم.';
    } finally {
        purchaseState.submitting = false;
    }
};

onMounted(() => {
    cartStore.fetchCart();
});
</script>

<style scoped>
.cart-page-view {
    min-height: 80vh;
    padding-bottom: 3rem;
}

.cart-section {
    padding: 40px 0 60px;
    min-height: 60vh;
}

.cart-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 30px;
}

/* Cart Items */
.cart-items {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    border-radius: 24px !important;
    padding: 30px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
    transition: all 0.4s ease;
}

[data-theme="dark"] .cart-items {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
}

.cart-items-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px dashed rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .cart-items-header {
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.cart-items-header h2 {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--mobile-primary);
}

[data-theme="dark"] .cart-items-header h2 {
    color: var(--mobile-primary);
}

.btn-clear-cart {
    background: #fee2e2;
    color: #dc2626;
    border: none;
    padding: 10px 20px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 700;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-clear-cart:hover:not(:disabled) {
    background: #fecaca;
    transform: translateY(-2px);
}

[data-theme="dark"] .btn-clear-cart {
    background: rgba(220, 38, 38, 0.15);
    color: #f28b82;
    border: 1px solid rgba(242, 139, 130, 0.2);
}

[data-theme="dark"] .btn-clear-cart:hover:not(:disabled) {
    background: rgba(220, 38, 38, 0.25);
}

.cart-items-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.cart-item {
    display: grid;
    grid-template-columns: 120px 1fr auto;
    gap: 20px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.5);
    border: 1px solid rgba(0, 0, 0, 0.05);
    border-radius: 16px;
    transition: all 0.3s ease;
}

[data-theme="dark"] .cart-item {
    background: rgba(15, 23, 42, 0.2);
    border-color: rgba(255, 255, 255, 0.05);
}

.cart-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.03);
    border-color: var(--mobile-primary);
}

[data-theme="dark"] .cart-item:hover {
    border-color: var(--mobile-primary);
}

.cart-item-image {
    width: 120px;
    height: 120px;
    border-radius: 12px;
    overflow: hidden;
    background: white;
    border: 1px solid rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    justify-content: center;
}

[data-theme="dark"] .cart-item-image {
    background: #1e293b;
    border-color: rgba(255, 255, 255, 0.05);
}

.cart-item-image img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.cart-item-details h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 6px 0;
}

[data-theme="dark"] .cart-item-details h3 {
    color: #f1f5f9;
}

.cart-item-subtitle {
    font-size: 0.88rem;
    color: #64748b;
    margin: 0 0 8px 0;
}

[data-theme="dark"] .cart-item-subtitle {
    color: #94a3b8;
}

.cart-item-category {
    font-size: 0.85rem;
    color: var(--mobile-primary);
    font-weight: 600;
    margin: 0 0 10px 0;
}

[data-theme="dark"] .cart-item-category {
    color: var(--mobile-primary);
}

.cart-item-price {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cart-item-price .price {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--mobile-primary);
}

[data-theme="dark"] .cart-item-price .price {
    color: var(--mobile-primary);
}

.cart-item-price .original-price {
    font-size: 0.9rem;
    color: #94a3b8;
    text-decoration: line-through;
}

.cart-item-price .sale-price {
    font-size: 1.2rem;
    font-weight: 800;
    color: #ef4444;
}

.cart-item-actions {
    display: flex;
    flex-direction: column;
    gap: 15px;
    align-items: flex-end;
    justify-content: space-between;
}

.quantity-control {
    display: flex;
    align-items: center;
    gap: 5px;
}

.qty-btn {
    width: 36px;
    height: 36px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    background: rgba(255, 255, 255, 0.5);
    color: #334155;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

[data-theme="dark"] .qty-btn {
    border-color: rgba(255, 255, 255, 0.08);
    background: rgba(15, 23, 42, 0.3);
    color: #cbd5e1;
}

.qty-btn:hover:not(:disabled) {
    background: var(--mobile-primary);
    color: white;
    border-color: var(--mobile-primary);
}

[data-theme="dark"] .qty-btn:hover:not(:disabled) {
    background: var(--mobile-primary);
    color: #0f172a;
    border-color: var(--mobile-primary);
}

.qty-input {
    width: 50px;
    height: 36px;
    text-align: center;
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 700;
    background: white;
}

[data-theme="dark"] .qty-input {
    border-color: rgba(255, 255, 255, 0.08);
    background: rgba(15, 23, 42, 0.5);
    color: #f1f5f9;
}

.cart-item-subtotal {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 3px;
}

.subtotal-label {
    font-size: 0.8rem;
    color: #64748b;
}

.subtotal-value {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--mobile-primary);
}

[data-theme="dark"] .subtotal-value {
    color: var(--mobile-primary);
}

.btn-remove-item {
    width: 32px;
    height: 32px;
    border: none;
    background: #fee2e2;
    color: #dc2626;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-remove-item:hover:not(:disabled) {
    background: #dc2626;
    color: white;
    transform: rotate(90deg);
}

[data-theme="dark"] .btn-remove-item {
    background: rgba(220, 38, 38, 0.15);
    color: #f28b82;
}

[data-theme="dark"] .btn-remove-item:hover:not(:disabled) {
    background: #dc2626;
    color: white;
}

/* Cart Summary */
.cart-summary {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.summary-card {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    border-radius: 24px !important;
    padding: 30px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
}

[data-theme="dark"] .summary-card {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
}

.summary-card h2 {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--mobile-primary);
    margin: 0 0 20px 0;
    padding-bottom: 15px;
    border-bottom: 1px dashed rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .summary-card h2 {
    color: var(--mobile-primary);
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    color: #475569;
    font-weight: 600;
}

[data-theme="dark"] .summary-row {
    color: #cbd5e1;
}

.summary-row.summary-total {
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    margin-top: 10px;
}

[data-theme="dark"] .summary-row.summary-total {
    color: #f1f5f9;
}

.total-value {
    color: var(--mobile-primary);
    font-size: 1.45rem;
}

[data-theme="dark"] .total-value {
    color: var(--mobile-primary);
}

.summary-divider {
    height: 1px;
    border-top: 1px dashed rgba(0, 0, 0, 0.08);
    margin: 15px 0;
}

[data-theme="dark"] .summary-divider {
    border-top-color: rgba(255, 255, 255, 0.08);
}

.cart-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 25px;
}

.btn-checkout {
    padding: 15px 25px;
    border-radius: 12px;
    font-size: 1.05rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border: none;
}

.purchase-request-btn {
    background: linear-gradient(135deg, var(--mobile-primary) 0%, color-mix(in srgb, var(--mobile-primary) 80%, white) 100%);
    color: white;
}

.purchase-request-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--mobile-primary) 35%, transparent);
}

.whatsapp-checkout {
    background: #25d366;
    color: white;
}

.whatsapp-checkout:hover {
    background: #20ba5a;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(37, 211, 102, 0.35);
}

.btn-continue-shopping {
    background: transparent;
    color: var(--mobile-primary);
    border: 2px solid var(--mobile-primary);
    padding: 12px 25px;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-continue-shopping:hover {
    background: var(--mobile-primary);
    color: white;
}

[data-theme="dark"] .btn-continue-shopping {
    color: var(--mobile-primary);
    border-color: var(--mobile-primary);
}

[data-theme="dark"] .btn-continue-shopping:hover {
    background: var(--mobile-primary);
    color: #0f172a;
}

/* Trust Badges */
.trust-badges {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.trust-badge {
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
    padding: 20px 15px;
    border-radius: 16px;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.4);
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    transition: all 0.3s ease;
}

[data-theme="dark"] .trust-badge {
    background: rgba(30, 41, 59, 0.25);
    border-color: rgba(255, 255, 255, 0.05);
}

.trust-badge:hover {
    transform: translateY(-4px);
    border-color: var(--mobile-primary);
}

[data-theme="dark"] .trust-badge:hover {
    border-color: var(--mobile-primary);
}

.trust-badge i {
    font-size: 1.8rem;
    color: var(--mobile-primary);
    margin-bottom: 8px;
}

[data-theme="dark"] .trust-badge i {
    color: var(--mobile-primary);
}

.trust-badge span {
    font-size: 0.85rem;
    color: #475569;
    font-weight: 700;
}

[data-theme="dark"] .trust-badge span {
    color: #cbd5e1;
}

/* Empty Cart */
.empty-cart {
    text-align: center;
    padding: 80px 20px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.5);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03);
}

[data-theme="dark"] .empty-cart {
    background: rgba(30, 41, 59, 0.45);
    border-color: rgba(255, 255, 255, 0.08);
}

.empty-cart-icon {
    font-size: 5rem;
    color: #cbd5e1;
    margin-bottom: 30px;
}

[data-theme="dark"] .empty-cart-icon {
    color: #475569;
}

.empty-cart h2 {
    font-size: 1.8rem;
    font-weight: 800;
    color: #1e293b;
    margin: 0 0 15px 0;
}

[data-theme="dark"] .empty-cart h2 {
    color: #f1f5f9;
}

.empty-cart p {
    font-size: 1.05rem;
    color: #64748b;
    margin: 0 0 30px 0;
}

[data-theme="dark"] .empty-cart p {
    color: #94a3b8;
}

.btn-start-shopping {
    background: linear-gradient(135deg, var(--mobile-primary) 0%, color-mix(in srgb, var(--mobile-primary) 80%, white) 100%);
    color: white;
    border: none;
    padding: 15px 35px;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.btn-start-shopping:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--mobile-primary) 35%, transparent);
}

[data-theme="dark"] .btn-start-shopping {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

[data-theme="dark"] .btn-start-shopping:hover {
    background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

/* Purchase Request Modal */
.purchase-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(8px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.purchase-modal {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 24px;
    max-width: 550px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 60px rgba(0,0,0,0.15);
}

[data-theme="dark"] .purchase-modal {
    background: rgba(30, 41, 59, 0.98);
    border-color: rgba(255, 255, 255, 0.08);
}

.purchase-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px dashed rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .purchase-modal-header {
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.purchase-modal-header h2 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--mobile-primary);
}

[data-theme="dark"] .purchase-modal-header h2 {
    color: var(--mobile-primary);
}

.purchase-modal-close {
    background: none;
    border: none;
    font-size: 1.8rem;
    color: #94a3b8;
    cursor: pointer;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.purchase-modal-close:hover {
    background: #fee2e2;
    color: #dc3545;
}

[data-theme="dark"] .purchase-modal-close:hover {
    background: rgba(220, 38, 38, 0.15);
    color: #f28b82;
}

.purchase-modal-body {
    padding: 25px;
}

.purchase-modal-body .form-row {
    display: flex;
    gap: 15px;
}

.purchase-modal-body .form-row.two-cols .form-group {
    flex: 1;
}

.purchase-modal-body .form-group {
    margin-bottom: 20px;
}

.purchase-modal-body label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
    color: #334155;
    font-size: 0.9rem;
}

[data-theme="dark"] .purchase-modal-body label {
    color: #cbd5e1;
}

.purchase-modal-body input,
.purchase-modal-body textarea {
    width: 100%;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.5);
    border: 2px solid rgba(0, 0, 0, 0.08);
    border-radius: 12px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    box-sizing: border-box;
    font-family: inherit;
}

[data-theme="dark"] .purchase-modal-body input,
[data-theme="dark"] .purchase-modal-body textarea {
    background: rgba(15, 23, 42, 0.5);
    border-color: rgba(255, 255, 255, 0.08);
    color: #f1f5f9;
}

.purchase-modal-body input:focus,
.purchase-modal-body textarea:focus {
    outline: none;
    background: white;
    border-color: var(--mobile-primary);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--mobile-primary) 12%, transparent);
}

[data-theme="dark"] .purchase-modal-body input:focus,
[data-theme="dark"] .purchase-modal-body textarea:focus {
    background: rgba(15, 23, 42, 0.7);
    border-color: var(--mobile-primary);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--mobile-primary) 18%, transparent);
}

.pr-items-list-box {
    background: rgba(0, 0, 0, 0.02);
    padding: 15px;
    border-radius: 12px;
    margin-top: 5px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .pr-items-list-box {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.05);
}

.pr-item-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px dashed rgba(0, 0, 0, 0.08);
    font-size: 0.9rem;
}

[data-theme="dark"] .pr-item-row {
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.pr-item-row:last-child {
    border-bottom: none;
}

.pr-item-name {
    font-weight: 700;
    color: #1e293b;
}

[data-theme="dark"] .pr-item-name {
    color: #cbd5e1;
}

.pr-item-qty {
    color: #64748b;
    font-weight: 600;
}

[data-theme="dark"] .pr-item-qty {
    color: #cbd5e1;
}

/* Responsive Design */
@media (max-width: 992px) {
    .cart-grid {
        grid-template-columns: 1fr;
    }
    
    .cart-summary {
        order: -1;
    }
    
    .trust-badges {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .cart-item {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .cart-item-image {
        width: 100%;
        height: 200px;
    }
    
    .cart-item-actions {
        align-items: flex-start;
    }
    
    .trust-badges {
        grid-template-columns: 1fr;
    }
    
    .purchase-modal-body .form-row {
        flex-direction: column;
        gap: 0;
    }
}
</style>



