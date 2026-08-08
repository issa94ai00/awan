<!-- resources/js/Components/Forms/ProductForm.vue -->
<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    product: {
        type: Object,
        default: null,
    },
    categories: {
        type: Array,
        required: true,
    },
    units: {
        type: Array,
        default: () => ['piece', 'kg', 'liter', 'meter', 'box', 'pallet'],
    },
});

const emit = defineEmits(['success', 'cancel']);

const form = useForm({
    name: props.product?.name || '',
    name_ar: props.product?.name_ar || '',
    name_en: props.product?.name_en || '',
    slug: props.product?.slug || '',
    description: props.product?.description || '',
    description_ar: props.product?.description_ar || '',
    description_en: props.product?.description_en || '',
    price: props.product?.price || 0,
    stock_quantity: props.product?.stock_quantity || 0,
    image_main: props.product?.image_main || '',
    image_gallery: props.product?.image_gallery || [],
    status: props.product?.status || 'active',
    category_id: props.product?.category_id || null,
    show_price: props.product?.show_price ?? true,
    seo: props.product?.seo || {},
    sku: props.product?.sku || '',
    barcode: props.product?.barcode || '',
    cost_price: props.product?.cost_price || 0,
    tax_rate: props.product?.tax_rate || 0,
    taxable: props.product?.taxable ?? true,
    unit: props.product?.unit || 'piece',
    min_stock: props.product?.min_stock || 0,
    max_stock: props.product?.max_stock || 0,
    reorder_point: props.product?.reorder_point || 0,
    weight: props.product?.weight || 0,
    length: props.product?.length || 0,
    width: props.product?.width || 0,
    height: props.product?.height || 0,
    color: props.product?.color || '',
    size: props.product?.size || '',
    variant_group: props.product?.variant_group || '',
    short_description_ar: props.product?.short_description_ar || '',
    short_description_en: props.product?.short_description_en || '',
    sort_order: props.product?.sort_order || 0,
    brand: props.product?.brand || '',
    model: props.product?.model || '',
    in_stock: props.product?.in_stock ?? true,
    is_featured: props.product?.is_featured ?? false,
    is_active: props.product?.is_active ?? true,
});

function submit() {
    const url = props.product 
        ? `/products/${props.product.id}`
        : '/products';
    
    const method = props.product ? 'put' : 'post';
    
    form[method](url, {
        onSuccess: () => {
            emit('success');
        },
    });
}

function cancel() {
    emit('cancel');
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <!-- المعلومات الأساسية -->
        <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4">المعلومات الأساسية</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium mb-2">اسم المنتج *</label>
                    <input 
                        v-model="form.name"
                        type="text"
                        class="w-full border rounded-lg p-2"
                        :class="{ 'border-red-500': form.errors.name }"
                    />
                    <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">
                        {{ form.errors.name }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">الاسم (عربي)</label>
                    <input 
                        v-model="form.name_ar"
                        type="text"
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">الاسم (إنجليزي)</label>
                    <input 
                        v-model="form.name_en"
                        type="text"
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">SKU *</label>
                    <input 
                        v-model="form.sku"
                        type="text"
                        class="w-full border rounded-lg p-2"
                        :class="{ 'border-red-500': form.errors.sku }"
                    />
                    <div v-if="form.errors.sku" class="text-red-500 text-sm mt-1">
                        {{ form.errors.sku }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">الباركود</label>
                    <input 
                        v-model="form.barcode"
                        type="text"
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">الفئة *</label>
                    <select 
                        v-model="form.category_id"
                        class="w-full border rounded-lg p-2"
                        :class="{ 'border-red-500': form.errors.category_id }"
                    >
                        <option value="">اختر الفئة</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                    <div v-if="form.errors.category_id" class="text-red-500 text-sm mt-1">
                        {{ form.errors.category_id }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">وحدة القياس</label>
                    <select 
                        v-model="form.unit"
                        class="w-full border rounded-lg p-2"
                    >
                        <option v-for="unit in units" :key="unit" :value="unit">
                            {{ unit }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- التسعير -->
        <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4">التسعير</h3>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">سعر البيع *</label>
                    <input 
                        v-model="form.price"
                        type="number"
                        step="0.01"
                        class="w-full border rounded-lg p-2"
                        :class="{ 'border-red-500': form.errors.price }"
                    />
                    <div v-if="form.errors.price" class="text-red-500 text-sm mt-1">
                        {{ form.errors.price }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">سعر التكلفة</label>
                    <input 
                        v-model="form.cost_price"
                        type="number"
                        step="0.01"
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">نسبة الضريبة (%)</label>
                    <input 
                        v-model="form.tax_rate"
                        type="number"
                        step="0.1"
                        class="w-full border rounded-lg p-2"
                    />
                </div>
            </div>
        </div>

        <!-- المخزون -->
        <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4">المخزون</h3>
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">الحد الأدنى</label>
                    <input 
                        v-model="form.min_stock"
                        type="number"
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">الحد الأقصى</label>
                    <input 
                        v-model="form.max_stock"
                        type="number"
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">نقطة إعادة الطلب</label>
                    <input 
                        v-model="form.reorder_point"
                        type="number"
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">الرصيد الحالي</label>
                    <input 
                        v-model="form.stock_quantity"
                        type="number"
                        class="w-full border rounded-lg p-2"
                    />
                </div>
            </div>
        </div>

        <!-- الأبعاد والوزن -->
        <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4">الأبعاد والوزن</h3>
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">الوزن (كجم)</label>
                    <input 
                        v-model="form.weight"
                        type="number"
                        step="0.01"
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">الطول (سم)</label>
                    <input 
                        v-model="form.length"
                        type="number"
                        step="0.1"
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">العرض (سم)</label>
                    <input 
                        v-model="form.width"
                        type="number"
                        step="0.1"
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">الارتفاع (سم)</label>
                    <input 
                        v-model="form.height"
                        type="number"
                        step="0.1"
                        class="w-full border rounded-lg p-2"
                    />
                </div>
            </div>
        </div>

        <!-- الوصف -->
        <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4">الوصف</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">الوصف الكامل</label>
                    <textarea 
                        v-model="form.description"
                        class="w-full border rounded-lg p-2"
                        rows="4"
                    ></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">الوصف المختصر (عربي)</label>
                    <textarea 
                        v-model="form.short_description_ar"
                        class="w-full border rounded-lg p-2"
                        rows="2"
                    ></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">الوصف المختصر (إنجليزي)</label>
                    <textarea 
                        v-model="form.short_description_en"
                        class="w-full border rounded-lg p-2"
                        rows="2"
                    ></textarea>
                </div>
            </div>
        </div>

        <!-- الخيارات الإضافية -->
        <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4">الخيارات الإضافية</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-center">
                    <input 
                        v-model="form.is_active"
                        type="checkbox"
                        id="is_active"
                        class="mr-2"
                    />
                    <label for="is_active" class="text-sm font-medium">نشط</label>
                </div>

                <div class="flex items-center">
                    <input 
                        v-model="form.is_featured"
                        type="checkbox"
                        id="is_featured"
                        class="mr-2"
                    />
                    <label for="is_featured" class="text-sm font-medium">مميز</label>
                </div>

                <div class="flex items-center">
                    <input 
                        v-model="form.in_stock"
                        type="checkbox"
                        id="in_stock"
                        class="mr-2"
                    />
                    <label for="in_stock" class="text-sm font-medium">متوفر</label>
                </div>

                <div class="flex items-center">
                    <input 
                        v-model="form.show_price"
                        type="checkbox"
                        id="show_price"
                        class="mr-2"
                    />
                    <label for="show_price" class="text-sm font-medium">عرض السعر</label>
                </div>

                <div class="flex items-center">
                    <input 
                        v-model="form.taxable"
                        type="checkbox"
                        id="taxable"
                        class="mr-2"
                    />
                    <label for="taxable" class="text-sm font-medium">خاضع للضريبة</label>
                </div>
            </div>
        </div>

        <!-- الأزرار -->
        <div class="flex justify-end gap-4">
            <button 
                type="button"
                @click="cancel"
                class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400"
            >
                إلغاء
            </button>
            <button 
                type="submit"
                :disabled="form.processing"
                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
                {{ form.processing ? 'جاري الحفظ...' : (product ? 'تحديث' : 'حفظ') }}
            </button>
        </div>
    </form>
</template>
