<template>
    <div class="product-show">
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span>تفاصيل المنتج</span>
                    <el-button type="primary" @click="goBack">رجوع</el-button>
                </div>
            </template>

            <el-skeleton :loading="loading" animated>
                <el-row :gutter="20">
                    <el-col :xs="24" :md="8">
                        <el-card>
                            <el-image
                                style="width: 100%; height: 320px; object-fit: cover"
                                :src="product.image_main || '/placeholder.jpg'"
                                fit="cover"
                            />
                        </el-card>
                        <el-card style="margin-top: 1rem">
                            <div class="detail-item">
                                <strong>الحالة:</strong>
                                <span>{{ product.is_active ? 'نشط' : 'غير نشط' }}</span>
                            </div>
                            <div class="detail-item">
                                <strong>مميز:</strong>
                                <span>{{ product.is_featured ? 'نعم' : 'لا' }}</span>
                            </div>
                            <div class="detail-item">
                                <strong>الكمية في المخزون:</strong>
                                <span>{{ product.stock_quantity }}</span>
                            </div>
                            <div class="detail-item">
                                <strong>السعر:</strong>
                                <span>{{ product.price }}</span>
                            </div>
                            <div class="detail-item" v-if="product.sale_price">
                                <strong>السعر المخفض:</strong>
                                <span>{{ product.sale_price }}</span>
                            </div>
                        </el-card>
                    </el-col>

                    <el-col :xs="24" :md="16">
                        <el-card>
                            <h2>{{ product.name_ar || product.name_en }}</h2>
                            <p v-if="product.category">الفئة: {{ product.category.name_ar || product.category.name_en }}</p>
                            <p v-if="product.slug">الـ slug: {{ product.slug }}</p>
                            <el-divider />
                            <el-row :gutter="20">
                                <el-col :xs="24" :md="12">
                                    <div class="detail-item">
                                        <strong>الوزن:</strong>
                                        <span>{{ product.weight || 'غير محدد' }}</span>
                                    </div>
                                </el-col>
                                <el-col :xs="24" :md="12">
                                    <div class="detail-item">
                                        <strong>العملة:</strong>
                                        <span>{{ product.currency || 'غير محدد' }}</span>
                                    </div>
                                </el-col>
                            </el-row>
                            <el-divider />
                            <h4>الوصف</h4>
                            <div v-if="product.description_ar" class="description">
                                <p>{{ product.description_ar }}</p>
                            </div>
                            <div v-else-if="product.description_en" class="description">
                                <p>{{ product.description_en }}</p>
                            </div>
                            <div v-else class="description">
                                <p>لا يوجد وصف متاح لهذا المنتج.</p>
                            </div>
                        </el-card>

                        <el-card style="margin-top: 1rem">
                            <h4>SEO</h4>
                            <div class="detail-item">
                                <strong>عنوان SEO:</strong>
                                <span>{{ product.seo?.title || 'غير متوفر' }}</span>
                            </div>
                            <div class="detail-item">
                                <strong>الوصف الميتا:</strong>
                                <span>{{ product.seo?.description || 'غير متوفر' }}</span>
                            </div>
                            <div class="detail-item">
                                <strong>الكلمات المفتاحية:</strong>
                                <span>{{ product.seo?.keywords || 'غير متوفر' }}</span>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-skeleton>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useProductsStore } from '@/stores/products';

const route = useRoute();
const router = useRouter();
const productsStore = useProductsStore();
const loading = ref(true);
const product = ref({});

const loadProduct = async () => {
    loading.value = true;
    try {
        const response = await productsStore.fetchProduct(route.params.id);
        product.value = response || productsStore.currentProduct || {};
    } catch (error) {
        ElMessage.error('فشل في جلب تفاصيل المنتج');
        router.push({ name: 'admin.products.index' });
    } finally {
        loading.value = false;
    }
};

const goBack = () => {
    router.push({ name: 'admin.products.index' });
};

onMounted(() => {
    loadProduct();
});
</script>

<style scoped>
.product-show {
    padding: 0;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}

.description {
    margin-top: 0.75rem;
    color: #4a4a4a;
}
</style>
