<template>
    <div class="cart-page">
        <section class="page-header">
            <div class="container">
                <h1>سلة المشتريات</h1>
                <div class="breadcrumb">
                    <router-link to="/">الرئيسية</router-link>
                    <span class="sep">&rsaquo;</span>
                    <span>سلة المشتريات</span>
                </div>
            </div>
        </section>

        <section class="cart-section fade-up">
            <div class="container">
                <div v-if="loading" class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>جاري تحميل السلة...</p>
                </div>

                <div v-else-if="items.length === 0" class="empty-cart">
                    <div class="empty-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h2>سلتك فارغة</h2>
                    <p>لم تقم بإضافة أي منتجات إلى سلة المشتريات بعد</p>
                    <router-link to="/categories" class="btn btn-primary">
                        <i class="fas fa-th-large"></i>
                        تصفح المنتجات
                    </router-link>
                </div>

                <div v-else class="cart-grid">
                    <!-- Cart Items -->
                    <div class="cart-items">
                        <div class="cart-items-header">
                            <h2>الproducts ({{ totalItems }})</h2>
                            <button class="btn-clear-cart" @click="clearCart">
                                <i class="fas fa-trash-alt"></i>
                                تفريغ السلة
                            </button>
                        </div>

                        <TransitionGroup name="cart-item" tag="div" class="cart-items-list">
                            <div v-for="item in items" :key="item.id" class="cart-item">
                                <div class="cart-item-image">
                                    <img :src="item.product?.image_main || '/assets/images/products/default-product.jpg'" :alt="item.product?.name_ar">
                                </div>
                                <div class="cart-item-details">
                                    <h3 class="cart-item-name">{{ item.product?.name_ar }}</h3>
                                    <p v-if="item.product?.name_en" class="cart-item-subtitle">{{ item.product?.name_en }}</p>
                                    <p class="cart-item-category">{{ item.product?.category?.name_ar || 'منتجات' }}</p>
                                    <div class="cart-item-price">
                                        <span v-if="item.product?.sale_price && item.product.sale_price < item.product.price" class="original-price">
                                            {{ formatPrice(item.product.price) }} {{ item.product?.currency || 'SAR' }}
                                        </span>
                                        <span class="sale-price">{{ formatPrice(item.product?.sale_price || item.price) }} {{ item.product?.currency || 'SAR' }}</span>
                                    </div>
                                </div>
                                <div class="cart-item-actions">
                                    <div class="quantity-control">
                                        <button class="qty-btn qty-minus" @click="updateQuantity(item.id, item.quantity - 1)" :disabled="item.quantity <= 1">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="qty-input" :value="item.quantity" min="1" readonly>
                                        <button class="qty-btn qty-plus" @click="updateQuantity(item.id, item.quantity + 1)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    <div class="cart-item-subtotal">
                                        <span class="subtotal-label">المجموع:</span>
                                        <span class="subtotal-value">{{ formatPrice(item.quantity * (item.product?.sale_price || item.price)) }} {{ item.product?.currency || 'SAR' }}</span>
                                    </div>
                                    <button class="btn-remove-item" @click="removeItem(item.id)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>

                    <!-- Cart Summary -->
                    <div class="cart-summary">
                        <div class="summary-card">
                            <h2>ملخص الطلب</h2>

                            <div class="summary-row">
                                <span>عدد المنتجات</span>
                                <span>{{ totalItems }}</span>
                            </div>

                            <div class="summary-row">
                                <span>المجموع الفرعي</span>
                                <span>{{ formatPrice(cartTotal) }} {{ currency }}</span>
                            </div>

                            <div class="summary-row">
                                <span>الشحن</span>
                                <span>يتم تحديده لاحقاً</span>
                            </div>

                            <div class="summary-divider"></div>

                            <div class="summary-row summary-total">
                                <span>الإجمالي</span>
                                <span class="total-value">{{ formatPrice(cartTotal) }} {{ currency }}</span>
                            </div>

                            <a :href="`https://wa.me/${whatsappNumber}?text=${encodeURIComponent(whatsappMessage)}`" 
                               class="btn btn-whatsapp-full" target="_blank">
                                <i class="fab fa-whatsapp"></i>
                                إتمام الطلب عبر واتساب
                            </a>

                            <router-link to="/categories" class="btn btn-continue">
                                <i class="fas fa-arrow-right"></i>
                                متابعة التسوق
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import cartApi from '@/api/cart';

const settingsStore = useSettingsStore();

const loading = ref(true);
const items = ref([]);
const cartTotal = ref(0);
const totalItems = ref(0);
const currency = ref('SAR');

const settings = computed(() => settingsStore.data || {});
const whatsappNumber = computed(() => settings.value.contact_whatsapp || '963900000000');

const whatsappMessage = computed(() => {
    const lines = items.value.map(item => {
        const name = item.product?.name_ar || 'منتج';
        const price = item.product?.sale_price || item.price;
        const qty = item.quantity;
        return `- ${name} (x${qty}) = ${formatPrice(price * qty)} ${currency.value}`;
    });
    return `مرحباً، أريد تأكيد الطلب:\n\n${lines.join('\n')}\n\nالإجمالي: ${formatPrice(cartTotal.value)} ${currency.value}`;
});

function formatPrice(price) {
    if (!price) return '0';
    return Number(price).toLocaleString('ar-SA');
}

async function loadCart() {
    loading.value = true;
    try {
        const res = await cartApi.get();
        const cart = res.data.data?.cart || res.data.cart || res.data;
        items.value = cart.items || [];
        cartTotal.value = cart.total || 0;
        totalItems.value = cart.total_items || 0;
        if (items.value.length > 0 && items.value[0].product?.currency) {
            currency.value = items.value[0].product.currency;
        }
    } catch (e) {
        console.error('Failed to load cart:', e);
        items.value = [];
    } finally {
        loading.value = false;
    }
}

async function updateQuantity(itemId, newQty) {
    if (newQty < 1) return;
    try {
        const res = await cartApi.update(itemId, newQty);
        const data = res.data;
        if (data.success) {
            const item = items.value.find(i => i.id === itemId);
            if (item) item.quantity = newQty;
            cartTotal.value = data.total || cartTotal.value;
            totalItems.value = data.cart_count || totalItems.value;
        }
    } catch (e) {
        console.error('Failed to update:', e);
    }
}

async function removeItem(itemId) {
    try {
        const res = await cartApi.remove(itemId);
        if (res.data.success) {
            items.value = items.value.filter(i => i.id !== itemId);
            cartTotal.value = res.data.total || 0;
            totalItems.value = items.value.reduce((sum, i) => sum + i.quantity, 0);
        }
    } catch (e) {
        console.error('Failed to remove:', e);
    }
}

async function clearCart() {
    if (!confirm('هل أنت متأكد من تفريغ السلة؟')) return;
    try {
        await cartApi.clear();
        items.value = [];
        cartTotal.value = 0;
        totalItems.value = 0;
    } catch (e) {
        console.error('Failed to clear:', e);
    }
}

onMounted(loadCart);
</script>

<style scoped>
.cart-page {
    min-height: 100vh;
    background: #f8f9fa;
}

.page-header {
    padding: 120px 0 2rem;
    background: linear-gradient(135deg, var(--mobile-primary-dark), #5a6b7a);
    color: white;
    text-align: center;
}

.page-header h1 {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
}

.breadcrumb {
    font-size: 0.9rem;
    opacity: 0.85;
}

.breadcrumb a {
    color: white;
    text-decoration: none;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

.breadcrumb .sep {
    margin: 0 0.5rem;
}

.cart-section {
    padding: 2rem 0 4rem;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.loading-state {
    text-align: center;
    padding: 4rem;
    color: #666;
}

.loading-state i {
    font-size: 2rem;
    color: #c9a959;
    margin-bottom: 1rem;
}

.empty-cart {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
}

.empty-icon i {
    font-size: 2.5rem;
    color: #ccc;
}

.empty-cart h2 {
    font-size: 1.5rem;
    color: #333;
    margin-bottom: 0.5rem;
}

.empty-cart p {
    color: #888;
    margin-bottom: 1.5rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.8rem 1.5rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--mobile-primary-dark), var(--mobile-primary));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px color-mix(in srgb, var(--mobile-primary-dark) 30%, transparent);
}

.cart-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 2rem;
    align-items: start;
}

.cart-items-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.cart-items-header h2 {
    font-size: 1.25rem;
    color: #333;
}

.btn-clear-cart {
    background: none;
    border: 1px solid #dc3545;
    color: #dc3545;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.3s;
}

.btn-clear-cart:hover {
    background: #dc3545;
    color: white;
}

.cart-items-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.cart-item {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    background: white;
    border-radius: 16px;
    padding: 1.25rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    transition: all 0.3s;
}

.cart-item:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.cart-item-image {
    width: 90px;
    height: 90px;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
}

.cart-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cart-item-details {
    flex: 1;
    min-width: 0;
}

.cart-item-name {
    font-size: 1rem;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0 0 0.2rem;
}

.cart-item-subtitle {
    font-size: 0.8rem;
    color: #999;
    margin: 0 0 0.3rem;
}

.cart-item-category {
    font-size: 0.75rem;
    color: #888;
    margin: 0 0 0.4rem;
}

.cart-item-price {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.original-price {
    font-size: 0.8rem;
    color: #aaa;
    text-decoration: line-through;
}

.sale-price {
    font-size: 0.95rem;
    font-weight: 700;
    color: #dc3545;
}

.price {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--mobile-primary-dark);
}

.cart-item-actions {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
}

.quantity-control {
    display: flex;
    align-items: center;
    gap: 0;
    border: 2px solid #e8e8e8;
    border-radius: 10px;
    overflow: hidden;
}

.qty-btn {
    width: 36px;
    height: 36px;
    border: none;
    background: #f8f8f8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-size: 0.7rem;
    color: #555;
}

.qty-btn:hover:not(:disabled) {
    background: var(--mobile-primary-dark);
    color: white;
}

.qty-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.qty-input {
    width: 48px;
    height: 36px;
    text-align: center;
    border: none;
    border-left: 1px solid #e8e8e8;
    border-right: 1px solid #e8e8e8;
    font-size: 0.95rem;
    font-weight: 700;
    color: #333;
    outline: none;
    -moz-appearance: textfield;
}

.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.cart-item-subtotal {
    text-align: center;
}

.subtotal-label {
    font-size: 0.7rem;
    color: #999;
    display: block;
}

.subtotal-value {
    font-size: 1rem;
    font-weight: 800;
    color: var(--mobile-primary-dark);
}

.btn-remove-item {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 1px solid #eee;
    background: white;
    color: #dc3545;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    font-size: 0.75rem;
}

.btn-remove-item:hover {
    background: #dc3545;
    color: white;
    border-color: #dc3545;
}

.summary-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    position: sticky;
    top: 100px;
}

.summary-card h2 {
    font-size: 1.2rem;
    color: #333;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f0f0f0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
    color: #666;
}

.summary-divider {
    height: 1px;
    background: #f0f0f0;
    margin: 1rem 0;
}

.summary-total {
    font-size: 1.1rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 1.5rem;
}

.total-value {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--mobile-primary-dark);
}

.btn-whatsapp-full {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, #25d366, #128c7e);
    color: white;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 700;
    font-size: 1rem;
    transition: all 0.3s;
    margin-bottom: 0.75rem;
}

.btn-whatsapp-full:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(37, 211, 102, 0.35);
}

.btn-continue {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.8rem;
    border: 2px solid #e8e8e8;
    border-radius: 14px;
    text-decoration: none;
    color: #666;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s;
}

.btn-continue:hover {
    border-color: var(--mobile-primary-dark);
    color: var(--mobile-primary-dark);
}

/* Transitions */
.cart-item-enter-active,
.cart-item-leave-active {
    transition: all 0.4s ease;
}

.cart-item-enter-from {
    opacity: 0;
    transform: translateX(-30px);
}

.cart-item-leave-to {
    opacity: 0;
    transform: translateX(30px);
}

@media (max-width: 900px) {
    .cart-grid {
        grid-template-columns: 1fr;
    }

    .cart-item {
        flex-wrap: wrap;
    }

    .cart-item-actions {
        flex-direction: row;
        width: 100%;
        justify-content: space-between;
        padding-top: 0.75rem;
        border-top: 1px solid #f0f0f0;
    }
}
</style>
