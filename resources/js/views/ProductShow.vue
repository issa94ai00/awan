<template>
    <div class="product-show-page">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ product?.name_ar }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">الرئيسية</router-link>
                    <span class="sep">›</span>
                    <router-link v-if="product?.category" :to="{ name: 'category.show', params: { slug: product.category.slug } }">
                        {{ product.category.name_ar }}
                    </router-link>
                    <span v-if="product?.category">/</span>
                    <span>{{ product?.name_ar }}</span>
                </div>
            </div>
        </section>

        <!-- Product Details Section -->
        <section class="product-details-section fade-up" v-loading="loading">
            <div class="container">
                <div class="product-details-grid" v-if="product">
                    <!-- Product Images -->
                    <div class="product-images">
                        <div class="product-image-carousel">
                            <div class="carousel-main">
                                <div class="carousel-container">
                                    <div class="carousel-track">
                                        <div 
                                            v-for="(image, index) in allImages" 
                                            :key="index"
                                            class="carousel-slide"
                                            :class="{ active: currentIndex === index }"
                                        >
                                            <img :src="image" :alt="product.name_ar">
                                        </div>
                                    </div>
                                    
                                    <button 
                                        v-if="allImages.length > 1"
                                        class="carousel-nav carousel-prev" 
                                        type="button"
                                        @click="prevImage"
                                    >
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                    <button 
                                        v-if="allImages.length > 1"
                                        class="carousel-nav carousel-next" 
                                        type="button"
                                        @click="nextImage"
                                    >
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                </div>
                                
                                <div v-if="allImages.length > 1" class="carousel-counter">
                                    <span class="current-index">{{ currentIndex + 1 }}</span> / <span class="total-images">{{ allImages.length }}</span>
                                </div>
                            </div>
                            
                            <div v-if="allImages.length > 1" class="carousel-thumbnails">
                                <div class="thumbnails-container">
                                    <button 
                                        v-for="(image, index) in allImages" 
                                        :key="index"
                                        class="thumbnail-btn"
                                        :class="{ active: currentIndex === index }"
                                        type="button"
                                        @click="currentIndex = index"
                                    >
                                        <img :src="image" :alt="`${product.name_ar} ${index + 1}`">
                                        <div class="thumbnail-overlay"></div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Info -->
                    <div class="product-details-info">
                        <div class="product-details-header">
                            <span class="product-category-badge">{{ product.category?.name_ar || 'منتجات بناء' }}</span>
                            <h1 class="product-title">{{ product.name_ar }}</h1>
                            <h2 v-if="product.name_en" class="product-subtitle">{{ product.name_en }}</h2>
                        </div>

                        <div v-if="product.price" class="product-details-price">
                            <span class="price-label">السعر:</span>
                            <span class="price-value">${{ formatPrice(product.price) }}</span>
                        </div>

                        <div class="product-description">
                            <h3>الوصف</h3>
                            <p>{{ product.description_ar || 'لا يوجد وصف متاح' }}</p>
                            <p v-if="product.description_en" class="description-en">{{ product.description_en }}</p>
                        </div>

                        <div class="product-meta">
                            <div v-if="product.brand" class="meta-item">
                                <span class="meta-label">العلامة التجارية:</span>
                                <span class="meta-value">{{ product.brand }}</span>
                            </div>
                            <div v-if="product.model" class="meta-item">
                                <span class="meta-label">الموديل:</span>
                                <span class="meta-value">{{ product.model }}</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">التوفر:</span>
                                <span class="meta-value" :class="{ 'in-stock': product.in_stock, 'out-of-stock': !product.in_stock }">
                                    {{ product.in_stock ? 'متوفر' : 'غير متوفر' }}
                                </span>
                            </div>
                        </div>

                        <div class="product-actions">
                            <a :href="`https://wa.me/${whatsappNumber}?text=${encodeURIComponent('مرحباً، أنا مهتم بمنتج: ' + product.name_ar)}`" class="btn-whatsapp" target="_blank">
                                <i class="fab fa-whatsapp"></i>
                                استفسار عبر واتساب
                            </a>
                            <a :href="`tel:${phoneNumber}`" class="btn-phone">
                                <i class="fas fa-phone"></i>
                                اتصل بنا
                            </a>
                            <button type="button" class="btn-inquiry" @click="openInquiry">
                                <i class="fas fa-question-circle"></i>
                                إرسال استفسار
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Product Inquiry Modal -->
        <div v-if="showInquiryModal" class="inquiry-modal">
            <div class="inquiry-modal-content">
                <div class="inquiry-modal-header">
                    <h2>استفسار عن المنتج</h2>
                    <span class="inquiry-modal-close" @click="showInquiryModal = false">&times;</span>
                </div>
                <div class="inquiry-modal-body">
                    <div class="inquiry-product-info">
                        <img :src="product?.image_main ? product.image_main : '/assets/images/products/default-product.jpg'" :alt="product?.name_ar" class="inquiry-product-img">
                        <div class="inquiry-product-details">
                            <h3>{{ product?.name_ar }}</h3>
                            <p v-if="product?.name_en" class="product-en">{{ product.name_en }}</p>
                            <p v-if="product?.price" class="product-price">${{ formatPrice(product.price) }}</p>
                        </div>
                    </div>

                    <div v-if="inquirySuccess" class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        تم إرسال استفسارك بنجاح، سنتواصل معك في أقرب وقت ممكن.
                    </div>

                    <div v-if="inquiryError" class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        {{ inquiryError }}
                    </div>

                    <form class="product-inquiry-form" @submit.prevent="submitInquiry">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="inquiry_name">الاسم الكامل <span class="required">*</span></label>
                                <input type="text" id="inquiry_name" v-model="inquiryForm.name" required placeholder="أدخل اسمك الكامل">
                            </div>
                            <div class="form-group">
                                <label for="inquiry_phone">رقم الهاتف <span class="required">*</span></label>
                                <input type="tel" id="inquiry_phone" v-model="inquiryForm.phone" required placeholder="+963 ...">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="inquiry_email">البريد الإلكتروني (اختياري)</label>
                            <input type="email" id="inquiry_email" v-model="inquiryForm.email" placeholder="example@email.com">
                        </div>

                        <div class="form-group">
                            <label for="inquiry_subject">موضوع الاستفسار <span class="required">*</span></label>
                            <select id="inquiry_subject" v-model="inquiryForm.subject" required>
                                <option value="">اختر موضوع الاستفسار</option>
                                <option value="product_details">تفاصيل أكثر عن المنتج</option>
                                <option value="availability">التوفر والكمية</option>
                                <option value="price_inquiry">استفسار عن السعر</option>
                                <option value="delivery">التوصيل والشحن</option>
                                <option value="installation">التركيب والصيانة</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="inquiry_message">تفاصيل الاستفسار <span class="required">*</span></label>
                            <textarea id="inquiry_message" v-model="inquiryForm.message" rows="4" required placeholder="اكتب تفاصيل استفسارك هنا..."></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-cancel" @click="showInquiryModal = false">
                                <i class="fas fa-times"></i>
                                إلغاء
                            </button>
                            <button type="submit" class="btn-submit-inquiry">
                                <i class="fas fa-paper-plane"></i>
                                إرسال الاستفسار
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Related Products Section -->
        <section v-if="relatedProducts.length > 0" class="related-products-section fade-up">
            <div class="container">
                <div class="section-header">
                    <h2>منتجات ذات صلة</h2>
                    <p>منتجات أخرى من نفس الفئة</p>
                </div>
                <div class="products-grid">
                    <div 
                        v-for="related in relatedProducts" 
                        :key="related.id" 
                        class="product-card"
                    >
                        <div class="product-image">
                            <div class="badges-container">
                                <span v-if="!related.in_stock" class="badge badge-out">غير متوفر</span>
                                <span v-else class="badge badge-in">متوفر</span>
                            </div>
                            <img 
                                :src="related.image_main ? related.image_main : '/assets/images/products/default-product.jpg'" 
                                :alt="related.name_ar"
                                loading="lazy"
                            >
                            <router-link :to="{ name: 'product.show', params: { slug: related.slug } }" class="product-overlay">
                                <span class="view-btn"><i class="fas fa-eye"></i></span>
                            </router-link>
                        </div>
                        <div class="product-info">
                            <div class="product-title-row">
                                <h3 class="product-title">{{ related.name_ar }}</h3>
                                <span v-if="related.name_en" class="product-subtitle">{{ related.name_en }}</span>
                            </div>
                            <div class="product-details-row">
                                <div class="product-category">{{ related.category?.name_ar || 'منتجات بناء' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useProductsStore } from '@/stores/products';
import { useInquiriesStore } from '@/stores/inquiries';

const route = useRoute();
const router = useRouter();
const productsStore = useProductsStore();
const inquiriesStore = useInquiriesStore();

const loading = ref(false);
const product = ref(null);
const currentIndex = ref(0);
const relatedProducts = ref([]);
const showInquiryModal = ref(false);
const inquirySuccess = ref(false);
const inquiryError = ref('');
const whatsappNumber = ref('963900000000');
const phoneNumber = ref('+963900000000');
const productId = computed(() => route.params.id || route.params.slug);

const inquiryForm = ref({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
    product_id: null
});

const allImages = computed(() => {
    if (!product.value) return [];
    const images = [];
    if (product.value.image_main) {
        images.push(product.value.image_main);
    }
    if (product.value.image_gallery) {
        try {
            const gallery = product.value.image_gallery;
            if (Array.isArray(gallery)) {
                gallery.forEach(img => {
                    if (img) images.push(img);
                });
            }
        } catch (e) {
            console.error('Failed to load image gallery:', e);
        }
    }
    return images.length > 0 ? images : ['/assets/images/products/default-product.jpg'];
});

async function loadProduct() {
    loading.value = true;
    try {
        const res = await productsStore.show(productId.value);
        product.value = res;
        
        // Load related products
        if (product.value.category_id) {
            await productsStore.fetch({ category_id: product.value.category_id, limit: 4, exclude: product.value.id });
            relatedProducts.value = productsStore.items;
        }
    } catch (error) {
        console.error('Failed to load product:', error);
    } finally {
        loading.value = false;
    }
}

function formatPrice(price) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(price);
}

function nextImage() {
    if (currentIndex.value < allImages.value.length - 1) {
        currentIndex.value++;
    } else {
        currentIndex.value = 0;
    }
}

function prevImage() {
    if (currentIndex.value > 0) {
        currentIndex.value--;
    } else {
        currentIndex.value = allImages.value.length - 1;
    }
}

function openInquiry() {
    inquiryForm.value.product_id = product.value.id;
    inquiryForm.value.message = `أرغب في الاستفسار عن المنتج: ${product.value.name_ar}`;
    showInquiryModal.value = true;
}

async function submitInquiry() {
    inquiryError.value = '';
    inquirySuccess.value = false;
    
    if (!inquiryForm.value.name || !inquiryForm.value.phone || !inquiryForm.value.subject || !inquiryForm.value.message) {
        inquiryError.value = 'يرجى ملء جميع الحقول المطلوبة';
        return;
    }

    try {
        await inquiriesStore.create(inquiryForm.value);
        inquirySuccess.value = true;
        inquiryForm.value = { name: '', email: '', phone: '', subject: '', message: '', product_id: null };
        
        setTimeout(() => {
            showInquiryModal.value = false;
            inquirySuccess.value = false;
        }, 3000);
    } catch (error) {
        inquiryError.value = 'فشل في إرسال الاستفسار، يرجى المحاولة مرة أخرى';
        console.error('Failed to submit inquiry:', error);
    }
}

onMounted(() => loadProduct());
</script>

<style scoped>
.product-show-page {
    min-height: 100vh;
}

/* Page Header */
.page-header {
    margin-top: 80px;
    padding: 2rem 0;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

.page-header h1 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #1a1a1a;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: #6b7280;
}

.breadcrumb a {
    color: #3b82f6;
    text-decoration: none;
    transition: color 0.3s;
}

.breadcrumb a:hover {
    color: #2563eb;
}

/* Product Details Section */
.product-details-section {
    padding: 3rem 0;
}

.product-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
}

/* Product Images */
.product-images {
    position: relative;
}

.product-image-carousel {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.carousel-main {
    position: relative;
}

.carousel-container {
    position: relative;
    aspect-ratio: 1;
}

.carousel-track {
    position: relative;
    width: 100%;
    height: 100%;
}

.carousel-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 0.3s;
}

.carousel-slide.active {
    opacity: 1;
}

.carousel-slide img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #f9fafb;
}

.carousel-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    z-index: 10;
}

.carousel-nav:hover {
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.carousel-prev {
    right: 1rem;
}

.carousel-next {
    left: 1rem;
}

.carousel-counter {
    position: absolute;
    bottom: 1rem;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
}

.carousel-thumbnails {
    padding: 1rem;
    display: flex;
    gap: 0.5rem;
    overflow-x: auto;
    background: #f9fafb;
}

.thumbnails-container {
    display: flex;
    gap: 0.5rem;
}

.thumbnail-btn {
    width: 80px;
    height: 80px;
    border: 2px solid transparent;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    padding: 0;
    position: relative;
    transition: border-color 0.3s;
}

.thumbnail-btn.active {
    border-color: #3b82f6;
}

.thumbnail-btn img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.thumbnail-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(59, 130, 246, 0.1);
}

/* Product Info */
.product-details-info {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.product-details-header {
    margin-bottom: 1rem;
}

.product-category-badge {
    display: inline-block;
    background: #3b82f6;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    margin-bottom: 1rem;
}

.product-title {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #1a1a1a;
}

.product-subtitle {
    font-size: 1.125rem;
    color: #6b7280;
    font-weight: 400;
}

.product-details-price {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 8px;
}

.price-label {
    font-size: 1rem;
    color: #6b7280;
}

.price-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #3b82f6;
}

.product-description h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #1a1a1a;
}

.product-description p {
    line-height: 1.8;
    color: #374151;
}

.description-en {
    color: #6b7280;
    font-style: italic;
}

.product-meta {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 8px;
}

.meta-item {
    display: flex;
    gap: 0.5rem;
}

.meta-label {
    color: #6b7280;
    font-weight: 500;
}

.meta-value {
    color: #1a1a1a;
}

.meta-value.in-stock {
    color: #10b981;
}

.meta-value.out-of-stock {
    color: #ef4444;
}

.product-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.85rem;
    margin-top: 0.5rem;
}

.btn-whatsapp,
.btn-phone,
.btn-inquiry {
    flex: 1;
    min-width: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.9rem 1.5rem;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    letter-spacing: 0.2px;
    font-size: 0.95rem;
}

.btn-whatsapp::before,
.btn-phone::before,
.btn-inquiry::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.18), transparent);
    transition: left 0.6s ease;
}

.btn-whatsapp:hover::before,
.btn-phone:hover::before,
.btn-inquiry:hover::before {
    left: 100%;
}

.btn-whatsapp {
    background: linear-gradient(135deg, #25d366 0%, #20ba58 100%);
    color: white;
    box-shadow: 0 3px 10px rgba(37, 211, 102, 0.25);
}

.btn-whatsapp:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(37, 211, 102, 0.4);
}

.btn-whatsapp:active {
    transform: translateY(0) scale(0.97);
}

.btn-phone {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 3px 10px rgba(59, 130, 246, 0.25);
}

.btn-phone:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4);
}

.btn-phone:active {
    transform: translateY(0) scale(0.97);
}

.btn-inquiry {
    background: linear-gradient(135deg, var(--mobile-primary) 0%, var(--mobile-primary-dark) 100%);
    color: white;
    box-shadow: 0 3px 10px color-mix(in srgb, var(--mobile-primary) 25%, transparent);
}

.btn-inquiry:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px color-mix(in srgb, var(--mobile-primary) 40%, transparent);
}

.btn-inquiry:active {
    transform: translateY(0) scale(0.97);
}

.btn-whatsapp i,
.btn-phone i,
.btn-inquiry i {
    font-size: 1.1rem;
    transition: transform 0.35s ease;
}

.btn-whatsapp:hover i,
.btn-phone:hover i,
.btn-inquiry:hover i {
    transform: scale(1.15);
}

/* Inquiry Modal */
.inquiry-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.inquiry-modal-content {
    background: white;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.inquiry-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.inquiry-modal-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a1a1a;
}

.inquiry-modal-close {
    font-size: 2rem;
    cursor: pointer;
    color: #6b7280;
    transition: color 0.3s;
}

.inquiry-modal-close:hover {
    color: #1a1a1a;
}

.inquiry-modal-body {
    padding: 1.5rem;
}

.inquiry-product-info {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.inquiry-product-img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
}

.inquiry-product-details h3 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: #1a1a1a;
}

.product-en {
    color: #6b7280;
    font-size: 0.875rem;
}

.product-price {
    color: #3b82f6;
    font-weight: 700;
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
}

.product-inquiry-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
}

.required {
    color: #ef4444;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 0.875rem;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3b82f6;
}

.form-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
}

.btn-cancel,
.btn-submit-inquiry {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.btn-cancel {
    background: #e5e7eb;
    color: #374151;
}

.btn-cancel:hover {
    background: #d1d5db;
}

.btn-submit-inquiry {
    background: #3b82f6;
    color: white;
}

.btn-submit-inquiry:hover {
    background: #2563eb;
}

/* Related Products */
.related-products-section {
    padding: 4rem 0;
    background: #f9fafb;
}

.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-header h2 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #1e3a8a;
}

.section-header p {
    color: #6b7280;
    font-size: 1.125rem;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2rem;
}

.product-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.product-image {
    position: relative;
    height: 250px;
    overflow: hidden;
}

.badges-container {
    position: absolute;
    top: 1rem;
    right: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    z-index: 5;
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-in {
    background: #10b981;
    color: white;
}

.badge-out {
    background: #ef4444;
    color: white;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.product-card:hover .product-image img {
    transform: scale(1.1);
}

.product-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.product-card:hover .product-overlay {
    opacity: 1;
}

.view-btn {
    width: 50px;
    height: 50px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1e3a8a;
    text-decoration: none;
    transition: all 0.3s;
}

.view-btn:hover {
    background: #3b82f6;
    color: white;
    transform: scale(1.1);
}

.product-info {
    padding: 1.5rem;
}

.product-title-row {
    margin-bottom: 1rem;
}

.product-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: #1a1a1a;
}

.product-subtitle {
    font-size: 0.875rem;
    color: #6b7280;
}

.product-details-row {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.product-category {
    color: #6b7280;
    font-size: 0.875rem;
}

/* Fade Up Animation */
.fade-up {
    opacity: 0;
    transform: translateY(30px);
    animation: fadeUp 0.6s ease-out forwards;
}

@keyframes fadeUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .product-details-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }

    .page-header h1 {
        font-size: 1.5rem;
    }

    .product-title {
        font-size: 1.5rem;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .products-grid {
        grid-template-columns: 1fr;
    }

    .product-actions {
        flex-direction: column;
    }
}
</style>
