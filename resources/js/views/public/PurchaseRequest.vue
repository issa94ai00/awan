<template>
    <div class="purchase-request-page-view">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>طلب شراء</h1>
                <div class="breadcrumb">
                    <router-link to="/">الرئيسية</router-link>
                    <span class="sep">›</span>
                    <span>طلب شراء</span>
                </div>
            </div>
        </section>

        <!-- Form Section -->
        <section class="contact-section fade-up">
            <div class="container">
                <div class="section-header">
                    <h2>تقديم طلب شراء</h2>
                    <p>املأ بياناتك وسنقوم بالتواصل معك لتأكيد الطلب</p>
                </div>

                <!-- Success Box -->
                <div v-if="success" id="successMessage">
                    <div style="background: #e6f4ea; color: #137333; padding: 30px; border-radius: 16px; text-align: center; margin-bottom: 30px; border: 1px solid #ceead6;">
                        <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 15px;"></i>
                        <h3 style="margin-bottom: 10px;">تم إرسال طلب الشراء بنجاح</h3>
                        <p style="font-size: 1.1rem; margin-bottom: 5px;" v-html="successDetails"></p>
                        <p style="color: #666; margin-top: 15px;">سنقوم بالتواصل معك في أقرب وقت ممكن</p>
                    </div>
                </div>

                <!-- Error Box -->
                <div v-if="error" id="errorMessage">
                    <div style="background: #fce8e6; color: #c5221f; padding: 30px; border-radius: 16px; text-align: center; margin-bottom: 30px; border: 1px solid #fad2cf;">
                        <i class="fas fa-exclamation-circle" style="font-size: 3rem; margin-bottom: 15px;"></i>
                        <h3 style="margin-bottom: 10px;">حدث خطأ</h3>
                        <p>{{ error }}</p>
                    </div>
                </div>

                <!-- Form Wrapper -->
                <div v-if="!success" class="contact-wrapper" id="formWrapper">
                    <div class="contact-info">
                        <h3>لماذا تطلب شراء منا؟</h3>

                        <div class="inquiry-reasons">
                            <div class="reason-item">
                                <i class="fas fa-check-circle"></i>
                                <div>
                                    <h4>جودة مضمونة</h4>
                                    <p>نقدم منتجات أصلية عالية الجودة</p>
                                </div>
                            </div>

                            <div class="reason-item">
                                <i class="fas fa-truck"></i>
                                <div>
                                    <h4>توصيل سريع</h4>
                                    <p>نوفر خيارات توصيل مرنة وسريعة</p>
                                </div>
                            </div>

                            <div class="reason-item">
                                <i class="fas fa-headset"></i>
                                <div>
                                    <h4>دعم فني متواصل</h4>
                                    <p>فريقنا جاهز للإجابة على استفساراتك</p>
                                </div>
                            </div>

                            <div class="reason-item">
                                <i class="fas fa-percent"></i>
                                <div>
                                    <h4>أسعار تنافسية</h4>
                                    <p>أفضل الأسعار في السوق مع ضمان الجودة</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="contact-form-wrapper">
                        <h3>بيانات الطلب</h3>
                        <form @submit.prevent="submitRequest" class="contact-form">
                            <div class="form-row two-cols">
                                <div class="form-group">
                                    <label for="name">الاسم الكامل <span style="color: #dc3545;">*</span></label>
                                    <input type="text" id="name" v-model="form.name" required placeholder="أدخل اسمك الكامل">
                                </div>

                                <div class="form-group">
                                    <label for="phone">رقم الهاتف <span style="color: #dc3545;">*</span></label>
                                    <input type="tel" id="phone" v-model="form.phone" required placeholder="+963 ...">
                                </div>
                            </div>

                            <div class="form-row two-cols">
                                <div class="form-group">
                                    <label for="email">البريد الإلكتروني</label>
                                    <input type="email" id="email" v-model="form.email" placeholder="example@email.com">
                                </div>

                                <div class="form-group">
                                    <label for="address">العنوان</label>
                                    <input type="text" id="address" v-model="form.address" placeholder="العنوان بالكامل">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>المنتجات المطلوبة</label>
                                <div id="itemsContainer">
                                    <div v-for="(item, index) in form.items" :key="index" class="form-row two-cols item-row" style="margin-bottom: 10px; align-items: center;">
                                        <div class="form-group" style="flex: 2; margin-left: 10px;">
                                            <input type="text" v-model="item.product_name" required placeholder="اسم المنتج" class="item-name">
                                        </div>
                                        <div class="form-group" style="flex: 1; margin-left: 10px;">
                                            <input type="number" v-model.number="item.quantity" required min="1" placeholder="الكمية" class="item-qty">
                                        </div>
                                        <div class="form-group" style="flex: 0 0 auto;">
                                            <button type="button" 
                                                    class="btn-remove-item" 
                                                    style="background: #dc3545; color: white; border: none; width: 40px; height: 40px; border-radius: 10px; cursor: pointer;"
                                                    v-show="form.items.length > 1"
                                                    @click="removeItem(index)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" 
                                        @click="addItem" 
                                        id="addItemBtn" 
                                        style="background: none; border: 2px dashed var(--mobile-primary); color: var(--mobile-primary); padding: 10px 20px; border-radius: 10px; cursor: pointer; font-size: 0.9rem; margin-top: 5px;">
                                    <i class="fas fa-plus"></i> إضافة منتج آخر
                                </button>
                            </div>

                            <div class="form-group">
                                <label for="notes">ملاحظات إضافية</label>
                                <textarea id="notes" v-model="form.notes" rows="4" placeholder="أي تفاصيل إضافية عن الطلب..."></textarea>
                            </div>

                            <button type="submit" class="btn-submit" :disabled="submitting">
                                <span v-if="submitting"><i class="fas fa-spinner fa-spin"></i> جاري الإرسال...</span>
                                <span v-else><i class="fas fa-paper-plane"></i> إرسال طلب الشراء</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { reactive, ref, onMounted, computed } from 'vue';
import { useCustomerAuthStore } from '@/stores/customerAuth';
import axios from 'axios';

const customerAuth = useCustomerAuthStore();

const form = reactive({
    name: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
    items: [
        { product_name: '', quantity: 1 }
    ]
});

const submitting = ref(false);
const success = ref(false);
const successDetails = ref('');
const error = ref(null);

const addItem = () => {
    form.items.push({ product_name: '', quantity: 1 });
};

const removeItem = (idx) => {
    form.items.splice(idx, 1);
};

const submitRequest = async () => {
    // Filter out empty items
    const filteredItems = form.items.filter(item => item.product_name.trim());
    if (!filteredItems.length) {
        error.value = 'يرجى إضافة منتج واحد على الأقل';
        return;
    }
    
    submitting.value = true;
    error.value = null;
    
    const payload = {
        name: form.name,
        phone: form.phone,
        email: form.email,
        address: form.address,
        notes: form.notes,
        items: filteredItems
    };

    try {
        const res = await axios.post('/api/v1/purchase-requests', payload);
        if (res.data?.success) {
            success.value = true;
            // Save client info to local storage
            localStorage.setItem('customer_info', JSON.stringify({
                name: form.name,
                phone: form.phone,
                email: form.email,
                address: form.address
            }));
            
            successDetails.value = `
                رقم الطلب: <strong>${res.data.data.order_number}</strong><br>
                رقم الفاتورة: <strong>${res.data.data.invoice_number}</strong><br>
                العميل: <strong>${res.data.data.customer.name}</strong>
            `;
        } else {
            error.value = res.data?.message || 'حدث خطأ أثناء الإرسال';
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'فشل الاتصال بالخادم، يرجى إعادة المحاولة';
    } finally {
        submitting.value = false;
    }
};

onMounted(() => {
    // Pre-fill profile info from store if authenticated, or from local storage fallback
    if (customerAuth.isAuthenticated) {
        const cust = customerAuth.customer;
        form.name = cust.name || '';
        form.phone = cust.phone || '';
        form.email = cust.email || '';
    } else {
        const savedInfo = localStorage.getItem('customer_info');
        if (savedInfo) {
            try {
                const parsed = JSON.parse(savedInfo);
                form.name = parsed.name || '';
                form.phone = parsed.phone || '';
                form.email = parsed.email || '';
                form.address = parsed.address || '';
            } catch (e) {}
        }
    }
});
</script>

<style scoped>
.purchase-request-page-view {
    padding-bottom: 3rem;
}

.contact-section {
    padding: 3rem 0 5rem;
}

.contact-wrapper {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 40px;
    align-items: start;
    margin-top: 30px;
}

@media (max-width: 992px) {
    .contact-wrapper {
        grid-template-columns: 1fr;
    }
}

.contact-info {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
    border-radius: 24px !important;
    padding: 30px;
    color: #1e293b;
    transition: all 0.4s ease;
}

[data-theme="dark"] .contact-info {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
    color: #f1f5f9;
}

.contact-info h3 {
    font-size: 1.35rem;
    font-weight: 800;
    margin-bottom: 25px;
    color: var(--mobile-primary);
}

[data-theme="dark"] .contact-info h3 {
    color: var(--mobile-primary);
}

.inquiry-reasons {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.reason-item {
    display: flex;
    gap: 16px;
    align-items: flex-start;
}

.reason-item i {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: color-mix(in srgb, var(--mobile-primary) 10%, transparent);
    color: var(--mobile-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

[data-theme="dark"] .reason-item i {
    background: color-mix(in srgb, var(--mobile-primary) 15%, transparent);
    color: var(--mobile-primary);
}

.reason-item:hover i {
    transform: scale(1.1) rotate(5deg);
    background: var(--mobile-primary);
    color: white;
}

[data-theme="dark"] .reason-item:hover i {
    background: var(--mobile-primary);
    color: #0f172a;
}

.reason-item h4 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0 0 4px 0;
    color: #0f172a;
}

[data-theme="dark"] .reason-item h4 {
    color: #f1f5f9;
}

.reason-item p {
    font-size: 0.88rem;
    color: #64748b;
    margin: 0;
}

[data-theme="dark"] .reason-item p {
    color: #94a3b8;
}

.contact-form-wrapper {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
    border-radius: 24px !important;
    padding: 30px;
}

[data-theme="dark"] .contact-form-wrapper {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
}

.contact-form-wrapper h3 {
    font-size: 1.35rem;
    font-weight: 800;
    margin-bottom: 25px;
    color: var(--mobile-primary);
}

[data-theme="dark"] .contact-form-wrapper h3 {
    color: var(--mobile-primary);
}

.contact-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-size: 0.9rem;
    font-weight: 700;
    color: #334155;
}

[data-theme="dark"] .form-group label {
    color: #cbd5e1;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 18px;
    background: rgba(255, 255, 255, 0.5);
    border: 2px solid rgba(0, 0, 0, 0.08);
    border-radius: 14px;
    font-size: 0.95rem;
    color: #1e293b;
    font-family: inherit;
    box-sizing: border-box;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

[data-theme="dark"] .form-group input,
[data-theme="dark"] .form-group select,
[data-theme="dark"] .form-group textarea {
    background: rgba(15, 23, 42, 0.35);
    border-color: rgba(255, 255, 255, 0.08) !important;
    color: #f1f5f9;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    background: white;
    border-color: var(--mobile-primary);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--mobile-primary) 12%, transparent);
}

[data-theme="dark"] .form-group input:focus,
[data-theme="dark"] .form-group select:focus,
[data-theme="dark"] .form-group textarea:focus {
    background: rgba(15, 23, 42, 0.55);
    border-color: var(--mobile-primary);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--mobile-primary) 18%, transparent);
}

.form-row.two-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 640px) {
    .form-row.two-cols {
        grid-template-columns: 1fr;
    }
}

.item-row {
    background: rgba(0, 0, 0, 0.02);
    padding: 15px;
    border-radius: 16px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .item-row {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.05);
}

.btn-remove-item {
    background: #dc3545;
    color: white;
    border: none;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn-remove-item:hover {
    background: #bd2130 !important;
    transform: scale(1.05);
}

#addItemBtn {
    background: none;
    border: 2px dashed var(--mobile-primary);
    color: var(--mobile-primary);
    padding: 12px 24px;
    border-radius: 12px;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 700;
    margin-top: 5px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

#addItemBtn:hover {
    background: color-mix(in srgb, var(--mobile-primary) 8%, transparent);
    transform: translateY(-2px);
}

[data-theme="dark"] #addItemBtn {
    border-color: var(--mobile-primary);
    color: var(--mobile-primary);
}

[data-theme="dark"] #addItemBtn:hover {
    background: color-mix(in srgb, var(--mobile-primary) 10%, transparent);
}

.btn-submit {
    padding: 14px 28px;
    border-radius: 14px;
    font-weight: 700;
    font-size: 1rem;
    background: linear-gradient(135deg, var(--mobile-primary) 0%, color-mix(in srgb, var(--mobile-primary) 80%, white) 100%);
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--mobile-primary) 20%, transparent);
}

.btn-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px color-mix(in srgb, var(--mobile-primary) 35%, transparent);
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

