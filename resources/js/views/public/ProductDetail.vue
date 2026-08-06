<template>
    <div class="product-detail-wrapper">
        <div v-if="product" class="product-details-page-view">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ $p(product, 'name') }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <span class="sep">›</span>
                    <router-link v-if="product.category" :to="'/category/' + product.category.slug">{{ $p(product.category, 'name') }}</router-link>
                    <span class="sep" v-if="product.category">›</span>
                    <span>{{ $p(product, 'name') }}</span>
                </div>
            </div>
        </section>

        <section class="product-details-section fade-up">
            <div class="container">
                <div class="product-details-grid">
                    
                    <!-- Product Images -->
                    <div class="product-images">
                        <div class="product-image-carousel">
                            <!-- Main Image Display -->
                            <div class="carousel-main">
                                <div class="carousel-container">
                                    <div class="carousel-track" ref="trackElement">
                                        <div v-for="(img, idx) in allImages" 
                                             :key="idx" 
                                             class="carousel-slide" 
                                             :class="{ active: idx === currentImageIndex }">
                                            <img :src="img" :alt="product.name_ar" @error="handleImageError">
                                        </div>
                                        <div v-if="!allImages.length" class="carousel-slide active">
                                            <img :src="'/assets/images/placeholder.jpg'" :alt="product.name_ar">
                                        </div>
                                    </div>
                                    
                                    <!-- Navigation Arrows -->
                                    <template v-if="allImages.length > 1">
                                        <button class="carousel-nav carousel-prev" type="button" @click="prevSlide">
                                            <i class="fas fa-chevron-right"></i>
                                        </button>
                                        <button class="carousel-nav carousel-next" type="button" @click="nextSlide">
                                            <i class="fas fa-chevron-left"></i>
                                        </button>
                                    </template>
                                </div>
                                
                                <!-- Image Counter -->
                                <div v-if="allImages.length > 1" class="carousel-counter">
                                    <span class="current-index">{{ currentImageIndex + 1 }}</span> / <span class="total-images">{{ allImages.length }}</span>
                                </div>
                            </div>
                            
                            <!-- Thumbnail Navigation -->
                            <div v-if="allImages.length > 1" class="carousel-thumbnails">
                                <div class="thumbnails-container" ref="thumbnailsContainer">
                                    <button v-for="(img, idx) in allImages" 
                                            :key="idx" 
                                            class="thumbnail-btn" 
                                            :class="{ active: idx === currentImageIndex }"
                                            type="button" 
                                            @click="goToSlide(idx)">
                                        <img :src="img" :alt="product.name_ar" @error="handleImageError">
                                        <div class="thumbnail-overlay"></div>
                                    </button>
                                </div>
                                
                                <button class="thumbnails-nav thumbnails-prev" type="button" @click="scrollThumbnails('prev')">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                                <button class="thumbnails-nav thumbnails-next" type="button" @click="scrollThumbnails('next')">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Product Details Info -->
                    <div class="product-details-info">
                        <div class="product-details-header">
                            <span class="product-category-badge">{{ $p(product.category, 'name') || t('general_category') || 'تصنيف عام' }}</span>
                            <h1 class="product-title">{{ $p(product, 'name') }}</h1>
                        </div>

                        <div v-if="settings.show_product_price === '1' && product.show_price && parseFloat(product.price) > 0" class="product-details-price">
                            <span class="price-label">{{ t('price') || 'السعر:' }}</span>
                            <span class="price-value" v-if="product.has_sale" style="display: inline-flex; flex-direction: column;">
                                <span class="sale-price" style="color:#dc2626; font-size:1.8rem; font-weight:700;">${{ parseFloat(product.sale_price).toFixed(2) }}</span>
                                <span class="original-price" style="text-decoration:line-through; color:#999; font-size:1rem; margin-top:-5px;">${{ parseFloat(product.price).toFixed(2) }}</span>
                            </span>
                            <span class="price-value" v-else>${{ parseFloat(product.price).toFixed(2) }}</span>
                        </div>

                        <div class="product-description">
                            <h3>{{ t('description') || 'الوصف' }}</h3>
                            <p>{{ $p(product, 'description') || t('no_description') || 'لا يوجد وصف متاح' }}</p>
                        </div>

                        <div class="product-meta">
                            <div v-if="product.brand" class="meta-item">
                                <span class="meta-label">{{ t('brand') || 'العلامة التجارية:' }}</span>
                                <span class="meta-value">{{ product.brand }}</span>
                            </div>
                            <div v-if="product.model" class="meta-item">
                                <span class="meta-label">{{ t('model') || 'الموديل:' }}</span>
                                <span class="meta-value">{{ product.model }}</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">{{ t('availability') || 'التوفر:' }}</span>
                                <span class="meta-value" :class="product.in_stock ? 'in-stock' : 'out-of-stock'">
                                    {{ product.in_stock ? (t('in_stock') || 'متوفر') : (t('out_of_stock') || 'غير متوفر') }}
                                </span>
                            </div>
                        </div>

                        <div class="product-actions">
                            <button class="btn-add-to-cart-product" @click="handleAddToCart(product)">
                                <i class="fas fa-cart-plus"></i>
                                {{ t('add_to_cart') || 'أضف للسلة' }}
                            </button>
                            <button class="btn-inquire-product" @click="showInquiryModal = true">
                                <i class="fas fa-question-circle"></i>
                                {{ t('product_inquiry') || 'استفسار عن المنتج' }}
                            </button>
                            <a :href="'https://wa.me/' + (settings.contact_whatsapp || '963900000000') + '?text=' + encodeURIComponent('مرحباً، أنا مهتم بمنتج: ' + $p(product, 'name'))" class="btn-whatsapp" target="_blank">
                                <i class="fab fa-whatsapp"></i>
                                {{ t('whatsapp_inquiry') || 'استفسار عبر واتساب' }}
                            </a>
                            <a :href="'tel:' + (settings.contact_phone || '+963900000000')" class="btn-phone">
                                <i class="fas fa-phone"></i>
                                {{ t('contact_us') || 'اتصل بنا' }}
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Related Products Section -->
        <section class="related-products-section fade-up" v-if="relatedProducts.length">
            <div class="container">
                <div class="section-header">
                    <h2>{{ t('related_products') || 'منتجات ذات صلة' }}</h2>
                    <p>{{ t('related_products_subtitle') || 'منتجات أخرى من نفس الفئة' }}</p>
                </div>
                <div class="related-slider-container">
                    <button class="slider-btn prev" type="button" @click="scrollRelated('prev')" aria-label="السابق">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    
                    <div class="related-slider-track" ref="relatedTrack">
                        <div v-for="related in relatedProducts" :key="related.id" class="product-card related-card">
                            <div class="product-image">
                                <div class="badges-container">
                                    <span v-if="related.has_sale" class="badge badge-sale">{{ t('sale_badge') || 'خصم' }}</span>
                                    <span v-if="!related.in_stock" class="badge badge-out">{{ t('out_of_stock') || 'غير متوفر' }}</span>
                                    <span v-else class="badge badge-in">{{ t('in_stock') || 'متوفر' }}</span>
                                </div>
                                <img :src="getImageUrl(related.image_main)" :alt="related.name_ar" @error="handleImageError" loading="lazy">
                                <router-link :to="'/product/' + related.slug" class="product-overlay">
                                    <span class="view-btn"><i class="fas fa-eye"></i></span>
                                </router-link>
                            </div>
                            <div class="product-info">
                                <!-- Row 1: Title -->
                                <div class="product-title-row">
                                    <h3 class="product-title">{{ $p(related, 'name') }}</h3>
                                </div>
                                <!-- Row 2: Details -->
                                <div class="product-details-row">
                                    <div class="product-category">{{ $p(related.category, 'name') || t('general_category') || 'تصنيف عام' }}</div>
                                    <div v-if="related.brand || related.model" class="product-meta-info">
                                        <span v-if="related.brand">{{ related.brand }}</span>
                                        <span v-if="related.model">{{ related.model }}</span>
                                    </div>
                                    <div v-if="settings.show_product_price === '1' && related.show_price && parseFloat(related.price) > 0" class="product-price">
                                        <template v-if="related.has_sale">
                                            <span style="text-decoration: line-through; color: #888; font-size: 0.85rem; margin-left: 0.3rem;">${{ parseFloat(related.price).toFixed(2) }}</span>
                                            <span>${{ parseFloat(related.sale_price).toFixed(2) }}</span>
                                        </template>
                                        <template v-else>
                                            <span>${{ parseFloat(related.price).toFixed(2) }}</span>
                                        </template>
                                    </div>
                                </div>
                                <!-- Row 3: Action Buttons -->
                                <div class="product-actions-row">
                                    <button class="btn-add-to-cart" @click="handleAddToCart(related)">
                                        <i class="fas fa-cart-plus"></i>
                                        <span>{{ t('add_to_cart') || 'أضف للسلة' }}</span>
                                    </button>
                                    <a :href="'https://wa.me/' + (settings.contact_whatsapp || '963900000000') + '?text=' + encodeURIComponent('مرحباً، أنا مهتم بمنتج: ' + $p(related, 'name'))" class="btn-whatsapp" target="_blank">
                                        <i class="fab fa-whatsapp"></i>
                                        <span>WhatsApp</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button class="slider-btn next" type="button" @click="scrollRelated('next')" aria-label="التالي">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                </div>
            </div>
        </section>

        <!-- Product Inquiry Modal -->
        <div v-if="showInquiryModal" class="inquiry-modal-overlay" @click.self="showInquiryModal = false">
            <div class="inquiry-modal-content">
                <div class="inquiry-modal-header">
                    <h2>{{ t('product_inquiry') || 'استفسار عن المنتج' }}</h2>
                    <span class="inquiry-modal-close" @click="showInquiryModal = false">&times;</span>
                </div>
                <div class="inquiry-modal-body">
                    <div class="inquiry-product-info">
                        <img :src="getImageUrl(product.image_main)" :alt="$p(product, 'name')" class="inquiry-product-img" @error="handleImageError">
                        <div class="inquiry-product-details">
                            <h3>{{ $p(product, 'name') }}</h3>
                            <p v-if="settings.show_product_price === '1' && product.show_price && parseFloat(product.price) > 0" class="product-price">
                                ${{ parseFloat(product.has_sale ? product.sale_price : product.price).toFixed(2) }}
                            </p>
                        </div>
                    </div>

                    <div v-if="inquiryState.successMsg" class="alert alert-success" style="background:#d4edda; color:#155724; padding:15px; border-radius:10px; margin-bottom:15px;">
                        <i class="fas fa-check-circle"></i>
                        {{ inquiryState.successMsg }}
                    </div>

                    <div v-if="inquiryState.errorMsg" class="alert alert-danger" style="background:#f8d7da; color:#721c24; padding:15px; border-radius:10px; margin-bottom:15px;">
                        <i class="fas fa-exclamation-circle"></i>
                        {{ inquiryState.errorMsg }}
                    </div>

                    <form @submit.prevent="submitInquiry" class="product-inquiry-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="inquiry_name">{{ t('full_name') || 'الاسم الكامل' }} <span class="required">*</span></label>
                                <input type="text" id="inquiry_name" v-model="inquiryState.name" required>
                            </div>
                            <div class="form-group">
                                <label for="inquiry_phone">{{ t('phone_label') || 'رقم الهاتف' }} <span class="required">*</span></label>
                                <input type="tel" id="inquiry_phone" v-model="inquiryState.phone" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="inquiry_email">{{ t('email_label') || 'البريد الإلكتروني' }}</label>
                            <input type="email" id="inquiry_email" v-model="inquiryState.email">
                        </div>

                        <div class="form-group">
                            <label for="inquiry_subject">{{ t('inquiry_subject') || 'موضوع الاستفسار' }} <span class="required">*</span></label>
                            <select id="inquiry_subject" v-model="inquiryState.subject" required>
                                <option value="">{{ t('inquiry_subject') }}</option>
                                <option value="product_details">{{ t('inquiry_subject_options.details') || 'تفاصيل أكثر عن المنتج' }}</option>
                                <option value="availability">{{ t('inquiry_subject_options.availability') || 'التوفر والكمية' }}</option>
                                <option value="price_inquiry">{{ t('inquiry_subject_options.price') || 'استفسار عن السعر' }}</option>
                                <option value="delivery">{{ t('inquiry_subject_options.delivery') || 'التوصيل والشحن' }}</option>
                                <option value="installation">{{ t('inquiry_subject_options.installation') || 'التركيب والصيانة' }}</option>
                                <option value="other">{{ t('inquiry_subject_options.other') || 'أخرى' }}</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="inquiry_message">{{ t('inquiry_details') || 'تفاصيل الاستفسار' }} <span class="required">*</span></label>
                            <textarea id="inquiry_message" v-model="inquiryState.message" rows="4" required></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-cancel" @click="showInquiryModal = false">
                                <i class="fas fa-times"></i>
                                {{ t('cancel') || 'إلغاء' }}
                            </button>
                            <button type="submit" class="btn-submit-inquiry" :disabled="inquiryState.submitting">
                                <i class="fas" :class="inquiryState.submitting ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                                {{ t('send_inquiry') || 'إرسال الاستفسار' }}
                            </button>
                        </div>
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
        
        <div v-else-if="loading" style="text-align: center; padding: 5rem 0;">
            <i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: var(--mobile-primary);"></i>
            <p style="margin-top:15px; color:#666;">جاري تحميل تفاصيل المنتج...</p>
        </div>

        <div v-else style="text-align: center; padding: 5rem 0; color:#666;">
            <i class="fas fa-box-open" style="font-size: 4rem; color: #ccc; margin-bottom: 20px;"></i>
            <h2>{{ t('product_not_found') || 'المنتج غير موجود أو غير مفعل' }}</h2>
            <router-link to="/" class="btn-continue-shopping" style="display:inline-flex; margin-top:20px;">{{ t('back_to_home') || 'العودة للرئيسية' }}</router-link>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, reactive } from 'vue';
import { useRoute } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { useCartStore } from '@/stores/cart';
import { getImageUrl } from '@/utils/imageUrl';
import { triggerFadeUp } from '@/utils/fadeUp';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

// Stores
const settingsStore = useSettingsStore();
const cartStore = useCartStore();
const { t, locale } = useI18n();

// Route
const route = useRoute();

// State
const product = ref(null);
const relatedProducts = ref([]);
const loading = ref(true);
const toast = reactive({ show: false, message: '' });
const currentImageIndex = ref(0);
const showInquiryModal = ref(false);
const trackElement = ref(null);
const thumbnailsContainer = ref(null);
const relatedTrack = ref(null);

const inquiryState = reactive({
    name: '',
    phone: '',
    email: '',
    subject: '',
    message: '',
    submitting: false,
    successMsg: '',
    errorMsg: ''
});

// Computed
const settings = computed(() => settingsStore.data);
const productSlug = computed(() => route.params.slug);

// SEO Meta Tags
const dispatchSeoEvent = () => {
    if (!product.value) return;
    const currentLocale = locale.value;
    const productName = currentLocale === 'en' ? (product.value.name_en || product.value.name_ar || product.value.name) : (product.value.name_ar || product.value.name);
    const productDesc = currentLocale === 'en' ? (product.value.short_description_en || product.value.description_en) : (product.value.short_description_ar || product.value.description_ar || product.value.description);
    const seoTitleVal = product.value.seo?.meta_title || productName;
    const seoDescVal = product.value.seo?.meta_description || productDesc;
    
    window.dispatchEvent(new CustomEvent('set-dynamic-seo', {
        detail: {
            title: seoTitleVal,
            description: seoDescVal,
            keywords: product.value.brand || '',
            image: product.value.image_main || ''
        }
    }));
};

watch(locale, () => {
    if (product.value) {
        dispatchSeoEvent();
    }
});

const allImages = computed(() => {
    const list = [];
    if (product.value) {
        if (product.value.image_main) {
            list.push(getImageUrl(product.value.image_main));
        }
        if (product.value.image_gallery && product.value.image_gallery.length) {
            list.push(...product.value.image_gallery.map(img => getImageUrl(img)));
        }
    }
    return list;
});

// Helpers
const handleImageError = (e) => {
    e.target.src = '/assets/images/placeholder.jpg';
};

const handleAddToCart = async (prod) => {
    try {
        await cartStore.addToCart(prod.id, 1);
        showToast(`تم إضافة "${prod.name_ar}" إلى السلة بنجاح`);
    } catch (e) {
        showToast('حدث خطأ أثناء إضافة المنتج إلى السلة');
    }
};

const showToast = (msg) => {
    toast.message = msg;
    toast.show = true;
    setTimeout(() => {
        toast.show = false;
    }, 3000);
};

// Carousel logic
const goToSlide = (idx) => {
    currentImageIndex.value = idx;
};

const nextSlide = () => {
    let nextIdx = currentImageIndex.value + 1;
    if (nextIdx >= allImages.value.length) {
        nextIdx = 0;
    }
    goToSlide(nextIdx);
};

const prevSlide = () => {
    let prevIdx = currentImageIndex.value - 1;
    if (prevIdx < 0) {
        prevIdx = allImages.value.length - 1;
    }
    goToSlide(prevIdx);
};

const scrollThumbnails = (dir) => {
    if (thumbnailsContainer.value) {
        const amount = dir === 'next' ? 100 : -100;
        thumbnailsContainer.value.scrollBy({ left: amount, behavior: 'smooth' });
    }
};

const scrollRelated = (dir) => {
    if (relatedTrack.value) {
        const scrollAmount = relatedTrack.value.clientWidth * 0.75;
        const amount = dir === 'next' ? -scrollAmount : scrollAmount;
        relatedTrack.value.scrollBy({ left: amount, behavior: 'smooth' });
    }
};

// Autoplay
let autoplayInterval = null;
const startAutoplay = () => {
    if (allImages.value.length > 1) {
        autoplayInterval = setInterval(nextSlide, 5000);
    }
};
const stopAutoplay = () => {
    if (autoplayInterval) {
        clearInterval(autoplayInterval);
        autoplayInterval = null;
    }
};

// Load details
const loadProductDetails = async () => {
    loading.value = true;
    currentImageIndex.value = 0;
    try {
        const res = await axios.get(`/api/v1/products/${productSlug.value}`);
        if (res.data?.success) {
            product.value = res.data.data;
            dispatchSeoEvent();
            
            // Prefill product name and ID in inquiry modal
            inquiryState.subject = 'product_details';
            inquiryState.message = `مرحباً، أود الاستفسار عن منتج: ${product.value.name_ar}`;

            // Fetch related
            const relRes = await axios.get(`/api/v1/products/${productSlug.value}/related`);
            if (relRes.data?.success) {
                relatedProducts.value = relRes.data.data || [];
            }
        } else {
            product.value = null;
        }
    } catch (e) {
        console.error('Error fetching product details', e);
        product.value = null;
    } finally {
        loading.value = false;
        triggerFadeUp();
    }
};

// Submit inquiry
const submitInquiry = async () => {
    inquiryState.submitting = true;
    inquiryState.successMsg = '';
    inquiryState.errorMsg = '';
    try {
        const payload = {
            name: inquiryState.name,
            phone: inquiryState.phone,
            email: inquiryState.email || null,
            subject: inquiryState.subject,
            message: inquiryState.message,
            product_id: product.value?.id || null
        };
        const res = await axios.post('/api/v1/inquiries', payload);
        if (res.data?.success) {
            inquiryState.successMsg = res.data.message || 'تم إرسال استفسارك بنجاح.';
            // Reset form details except contact info
            inquiryState.message = '';
            setTimeout(() => {
                showInquiryModal.value = false;
                inquiryState.successMsg = '';
            }, 2000);
        } else {
            inquiryState.errorMsg = res.data?.message || 'فشل إرسال الاستفسار.';
        }
    } catch (e) {
        inquiryState.errorMsg = e.response?.data?.message || 'حدث خطأ في الاتصال بالخادم.';
    } finally {
        inquiryState.submitting = false;
    }
};

onMounted(() => {
    loadProductDetails();
    startAutoplay();
    // Update SEO meta tags after product is loaded
    watch(product, () => {
        if (product.value) {
            updateSEOMetaTags();
        }
    });
});

watch(productSlug, () => {
    stopAutoplay();
    loadProductDetails();
    startAutoplay();
});
</script>

<style scoped>
.product-details-page-view {
    min-height: 80vh;
}

.product-images {
    width: 100%;
}

.product-image-carousel {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.carousel-main {
    position: relative;
}

.carousel-container {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    background: #f8f9fa;
    border: 1px solid #eee;
    height: 450px;
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
    pointer-events: none;
    transition: opacity 0.4s ease-in-out;
    display: flex;
    justify-content: center;
    align-items: center;
}

.carousel-slide.active {
    opacity: 1;
    pointer-events: auto;
}

.carousel-slide img {
    width: 100%;
    height: 450px;
    object-fit: contain;
    background: white;
}

.carousel-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.4);
    color: white;
    border: none;
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 10;
    font-size: 16px;
}

.carousel-nav:hover {
    background: rgba(0, 0, 0, 0.7);
    transform: translateY(-50%) scale(1.08);
}

.carousel-prev {
    right: 15px;
}

.carousel-next {
    left: 15px;
}

.carousel-counter {
    position: absolute;
    bottom: 15px;
    left: 15px;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    z-index: 10;
}

.carousel-thumbnails {
    position: relative;
    background: #f8f9fa;
    border-radius: 12px;
    padding: 8px 35px;
    border: 1px solid #eee;
}

.thumbnails-container {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-behavior: smooth;
    scrollbar-width: none;
}

.thumbnails-container::-webkit-scrollbar {
    display: none;
}

.thumbnail-btn {
    flex: 0 0 auto;
    width: 60px;
    height: 60px;
    border: 2px solid transparent;
    border-radius: 6px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    background: white;
    padding: 0;
}

.thumbnail-btn:hover {
    border-color: var(--mobile-primary);
}

.thumbnail-btn.active {
    border-color: var(--mobile-primary);
    box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
}

.thumbnail-btn img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.thumbnail-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0);
    transition: background 0.3s ease;
}

.thumbnails-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.4);
    color: white;
    border: none;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 10px;
    z-index: 10;
}

.thumbnails-nav:hover {
    background: rgba(0, 0, 0, 0.7);
}

.thumbnails-prev {
    right: 6px;
}

.thumbnails-next {
    left: 6px;
}

.btn-inquire-product {
    background: #e2e8f0;
    color: #475569;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-inquire-product:hover {
    background: #cbd5e1;
    color: #1e293b;
    transform: translateY(-2px);
}

/* Premium Add to Cart Button */
.btn-add-to-cart-product {
    width: 100%;
    background: linear-gradient(135deg, var(--mobile-primary, var(--mobile-primary)) 0%, var(--mobile-primary-dark, var(--mobile-primary-dark)) 100%);
    color: white;
    border: none;
    padding: 16px 25px;
    border-radius: 14px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 15px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--mobile-primary) 25%, transparent);
    letter-spacing: 0.3px;
    font-family: inherit;
}

.btn-add-to-cart-product::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.18), transparent);
    transition: left 0.6s ease;
}

.btn-add-to-cart-product:hover::before {
    left: 100%;
}

.btn-add-to-cart-product:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px color-mix(in srgb, var(--mobile-primary) 40%, transparent);
    filter: brightness(1.05);
}

.btn-add-to-cart-product:active {
    transform: translateY(0) scale(0.98);
}

.btn-add-to-cart-product i {
    font-size: 1.15rem;
    transition: transform 0.35s ease;
}

.btn-add-to-cart-product:hover i {
    transform: scale(1.15);
}

/* Premium Inquiry Modal Glassmorphism */
.inquiry-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(12px) saturate(140%);
    -webkit-backdrop-filter: blur(12px) saturate(140%);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeInOverlay 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.inquiry-modal-content {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(25px) saturate(180%);
    -webkit-backdrop-filter: blur(25px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.45);
    border-radius: 24px;
    padding: 25px 30px;
    max-width: 580px;
    width: 100%;
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
    direction: rtl;
    box-shadow: 
        0 4px 30px rgba(0, 0, 0, 0.03),
        0 30px 60px rgba(0, 0, 0, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.5);
    animation: scaleInContent 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.inquiry-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 18px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.inquiry-modal-header h2 {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--mobile-primary-dark, var(--mobile-primary));
    margin: 0;
}

.inquiry-modal-close {
    font-size: 1.8rem;
    color: #475569;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.03);
}

.inquiry-modal-close:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    transform: rotate(90deg);
}

.inquiry-modal-body {
    padding: 20px 0 0 0;
}

.inquiry-product-info {
    display: flex;
    gap: 15px;
    align-items: center;
    background: rgba(0, 0, 0, 0.03);
    padding: 16px;
    border-radius: 14px;
    margin-bottom: 20px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.inquiry-product-img {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 6px;
    background: white;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.inquiry-product-details h3 {
    margin: 0 0 5px 0;
    font-size: 1.15rem;
    color: #1e293b;
    font-weight: 700;
}

.inquiry-product-details .product-en {
    margin: 0 0 5px 0;
    color: #64748b;
    font-size: 0.85rem;
}

.inquiry-product-details .product-price {
    margin: 0;
    font-weight: 700;
    color: var(--mobile-primary, var(--mobile-primary));
    font-size: 1.1rem;
}

.product-inquiry-form .form-row {
    display: flex;
    gap: 15px;
}

.product-inquiry-form .form-group {
    margin-bottom: 15px;
    flex: 1;
}

.product-inquiry-form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    color: #334155;
}

.product-inquiry-form input,
.product-inquiry-form select,
.product-inquiry-form textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid rgba(0, 0, 0, 0.08);
    border-radius: 12px;
    font-size: 0.95rem;
    font-family: inherit;
    box-sizing: border-box;
    background: rgba(255, 255, 255, 0.6);
    color: #1e293b;
    transition: all 0.3s ease;
}

.product-inquiry-form input:focus,
.product-inquiry-form select:focus,
.product-inquiry-form textarea:focus {
    outline: none;
    border-color: var(--mobile-primary, var(--mobile-primary));
    background: #fff;
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--mobile-primary) 12%, transparent);
}

.product-inquiry-form .required {
    color: #ef4444;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
}

.btn-cancel {
    background: rgba(0, 0, 0, 0.05);
    color: #475569;
    border: 1px solid rgba(0, 0, 0, 0.08);
    padding: 12px 24px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-cancel:hover {
    background: rgba(0, 0, 0, 0.1);
    color: #1e293b;
}

.btn-submit-inquiry {
    background: linear-gradient(135deg, var(--mobile-primary, var(--mobile-primary)), var(--mobile-primary-dark, var(--mobile-primary-dark)));
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--mobile-primary) 20%, transparent);
}

.btn-submit-inquiry:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px color-mix(in srgb, var(--mobile-primary) 30%, transparent);
}

.btn-submit-inquiry:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* Animations */
@keyframes fadeInOverlay {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes scaleInContent {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(15px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@media(max-width:768px) {
    .product-inquiry-form .form-row {
        flex-direction: column;
        gap: 0;
    }
    .carousel-container {
        height: 280px;
    }
    .carousel-slide img {
        height: 280px;
    }
}

/* ====================================================================
   DARK MODE OVERRIDES
   ==================================================================== */
[data-theme="dark"] .carousel-container {
    background: #1e293b;
    border-color: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .carousel-slide img {
    background: #0f172a;
}

[data-theme="dark"] .carousel-thumbnails {
    background: #1e293b;
    border-color: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .thumbnail-btn {
    background: #0f172a;
}

[data-theme="dark"] .inquiry-modal-overlay {
    background: rgba(2, 6, 23, 0.6);
}

[data-theme="dark"] .inquiry-modal-content {
    background: rgba(30, 41, 59, 0.9);
    border-color: rgba(255, 255, 255, 0.08);
    color: #f1f5f9;
    box-shadow: 
        0 4px 30px rgba(0, 0, 0, 0.2),
        0 30px 60px rgba(0, 0, 0, 0.4);
}

[data-theme="dark"] .inquiry-modal-header {
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .inquiry-modal-header h2 {
    color: var(--mobile-primary);
}

[data-theme="dark"] .inquiry-modal-close {
    border-color: rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.03);
    color: #94a3b8;
}

[data-theme="dark"] .inquiry-modal-close:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
}

[data-theme="dark"] .inquiry-product-info {
    background: rgba(15, 23, 42, 0.4);
    border-color: rgba(255, 255, 255, 0.05);
}

[data-theme="dark"] .inquiry-product-details h3 {
    color: #f1f5f9;
}

[data-theme="dark"] .product-inquiry-form label {
    color: #cbd5e1;
}

[data-theme="dark"] .product-inquiry-form input,
[data-theme="dark"] .product-inquiry-form select,
[data-theme="dark"] .product-inquiry-form textarea {
    background: rgba(15, 23, 42, 0.4);
    border-color: rgba(255, 255, 255, 0.08);
    color: #f1f5f9;
}

[data-theme="dark"] .product-inquiry-form input:focus,
[data-theme="dark"] .product-inquiry-form select:focus,
[data-theme="dark"] .product-inquiry-form textarea:focus {
    border-color: var(--mobile-primary);
    background: rgba(15, 23, 42, 0.6);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--mobile-primary) 15%, transparent);
}

[data-theme="dark"] .btn-cancel {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.08);
    color: #94a3b8;
}

[data-theme="dark"] .btn-cancel:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #f1f5f9;
}

[data-theme="dark"] .btn-submit-inquiry {
    background: linear-gradient(135deg, var(--mobile-primary), #689f38);
    box-shadow: 0 4px 15px color-mix(in srgb, var(--mobile-primary) 20%, transparent);
}

[data-theme="dark"] .btn-submit-inquiry:hover:not(:disabled) {
    box-shadow: 0 8px 25px color-mix(in srgb, var(--mobile-primary) 35%, transparent);
}

[data-theme="dark"] .product-description h3,
[data-theme="dark"] .product-description p,
[data-theme="dark"] .product-meta .meta-label,
[data-theme="dark"] .product-meta .meta-value {
    color: #cbd5e1;
}

[data-theme="dark"] .product-description .description-en {
    color: #94a3b8;
}

/* Related Products Slider Styles */
.related-slider-container {
    position: relative;
    width: 100%;
    display: flex;
    align-items: center;
}

.related-slider-track {
    display: flex;
    gap: 24px;
    overflow-x: auto;
    scroll-behavior: smooth;
    scrollbar-width: none; /* Hide scrollbar Firefox */
    -ms-overflow-style: none;  /* Hide scrollbar IE/Edge */
    padding: 15px 5px;
    width: 100%;
    scroll-snap-type: x mandatory;
}

.related-slider-track::-webkit-scrollbar {
    display: none; /* Hide scrollbar Chrome/Safari/Opera */
}

.related-card {
    flex: 0 0 calc(25% - 18px); /* 4 items per row on desktop */
    min-width: 250px;
    scroll-snap-align: start;
    box-sizing: border-box;
}

.slider-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(0, 0, 0, 0.12);
    color: var(--mobile-primary, var(--mobile-primary));
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    z-index: 10;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.slider-btn:hover {
    background: var(--mobile-primary, var(--mobile-primary));
    color: white;
    box-shadow: 0 6px 16px color-mix(in srgb, var(--mobile-primary) 25%, transparent);
    transform: translateY(-50%) scale(1.08);
}

.slider-btn.prev {
    right: -22px;
}

.slider-btn.next {
    left: -22px;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .related-card {
        flex: 0 0 calc(33.333% - 16px); /* 3 items on tablet */
    }
}

@media (max-width: 992px) {
    .related-card {
        flex: 0 0 calc(50% - 12px); /* 2 items on mobile landscape */
    }
    .slider-btn.prev {
        right: -10px;
    }
    .slider-btn.next {
        left: -10px;
    }
}

@media (max-width: 576px) {
    .related-card {
        flex: 0 0 85%; /* 1.15 items on mobile to show offset next */
    }
    .slider-btn {
        width: 38px;
        height: 38px;
    }
    .slider-btn.prev {
        right: -5px;
    }
    .slider-btn.next {
        left: -5px;
    }
}

/* Dark mode overrides for slider */
[data-theme="dark"] .slider-btn {
    background: rgba(30, 41, 59, 0.95);
    border-color: rgba(255, 255, 255, 0.15);
    color: var(--mobile-primary);
}
[data-theme="dark"] .slider-btn:hover {
    background: var(--mobile-primary);
    color: #0f172a;
    box-shadow: 0 6px 16px color-mix(in srgb, var(--mobile-primary) 35%, transparent);
}
</style>
