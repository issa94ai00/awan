<template>
    <div class="secondary-navbar-page">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('secondary_navbar') || 'الشريط الثانوي' }}</h1>
                <p>{{ $t('secondary_navbar_desc') || 'إدارة عناصر الشريط الثانوي في الصفحة الرئيسية' }}</p>
            </div>
            <div>
                <el-button type="primary" :icon="Plus" @click="addItem">
                    {{ $t('add_item') || 'إضافة عنصر' }}
                </el-button>
                <el-button type="success" :icon="Check" :loading="saving" @click="saveItems">
                    {{ $t('save') || 'حفظ' }}
                </el-button>
            </div>
        </div>

        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <div>
                        <h2>{{ $t('navbar_items') || 'عناصر الشريط' }}</h2>
                        <p class="header-note">{{ $t('navbar_items_desc') || 'اسحب وأفلت لإعادة الترتيب. العناصر غير النشطة لن تظهر في الموقع' }}</p>
                    </div>
                </div>
            </template>

            <div v-if="!items.length" class="empty-state">
                <el-icon :size="48"><Plus /></el-icon>
                <p>{{ $t('no_items_yet') || 'لا توجد عناصر بعد. أضف عنصراً جديداً' }}</p>
            </div>

            <div v-else class="items-list">
                <div v-for="(element, index) in items" :key="element.id" class="nav-item-card" :class="{ inactive: !element.active }">
                    <div class="item-header">
                        <div class="drag-handles">
                            <el-button text :icon="Top" size="small" :disabled="index === 0" @click="moveItem(index, -1)" />
                            <el-button text :icon="Bottom" size="small" :disabled="index === items.length - 1" @click="moveItem(index, 1)" />
                        </div>
                        <el-switch v-model="element.active" />
                        <i :class="element.icon" class="item-icon-preview"></i>
                        <span class="item-label">{{ element.label_ar }}</span>
                        <el-tag size="small" :type="element.type === 'dropdown' ? 'primary' : 'info'">
                            {{ element.type === 'dropdown' ? ($t('dropdown') || 'قائمة منسدلة') : ($t('link') || 'رابط') }}
                        </el-tag>
                        <div class="item-actions">
                            <el-button text :icon="Edit" @click="editItem(index)" />
                            <el-button text :icon="Delete" type="danger" @click="removeItem(index)" />
                        </div>
                    </div>

                    <div v-if="element.type === 'dropdown'" class="children-section">
                        <div class="children-header">
                            <span class="children-title">{{ $t('sub_items') || 'عناصر فرعية' }}</span>
                            <el-button size="small" :icon="Plus" @click="addChild(index)">
                                {{ $t('add') || 'إضافة' }}
                            </el-button>
                        </div>
                        <div v-for="(child, childIdx) in element.children" :key="child.id" class="child-item" :class="{ inactive: !child.active }">
                            <div class="child-drag-handles">
                                <el-button text :icon="Top" size="small" :disabled="childIdx === 0" @click="moveChild(index, childIdx, -1)" />
                                <el-button text :icon="Bottom" size="small" :disabled="childIdx === element.children.length - 1" @click="moveChild(index, childIdx, 1)" />
                            </div>
                            <el-switch v-model="child.active" size="small" />
                            <i :class="child.icon" class="item-icon-preview" style="font-size:14px"></i>
                            <span class="child-label">{{ child.label_ar }}</span>
                            <div class="child-actions">
                                <el-button text :icon="Edit" size="small" @click="editChild(index, childIdx)" />
                                <el-button text :icon="Delete" type="danger" size="small" @click="removeChild(index, childIdx)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </el-card>

        <el-dialog
            v-model="dialog.visible"
            :title="dialog.isNew ? ($t('add_item') || 'إضافة عنصر') : ($t('edit_item') || 'تعديل العنصر')"
            width="600px"
            :close-on-click-modal="false"
        >
            <el-form :model="dialog.form" label-position="top">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item :label="$t('arabic_label') || 'التسمية بالعربية'">
                            <el-input v-model="dialog.form.label_ar" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('english_label') || 'التسمية بالإنجليزية'">
                            <el-input v-model="dialog.form.label_en" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item :label="$t('type') || 'النوع'">
                            <el-select v-model="dialog.form.type" style="width: 100%">
                                <el-option :label="$t('link') || 'رابط'" value="link" />
                                <el-option :label="$t('dropdown') || 'قائمة منسدلة'" value="dropdown" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('icon') || 'أيقونة'">
                            <el-input v-model="dialog.form.icon" placeholder="fas fa-star" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item v-if="dialog.form.type === 'link'" :label="$t('route') || 'الرابط'">
                    <el-input v-model="dialog.form.route" placeholder="/categories" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialog.visible = false">{{ $t('cancel') || 'إلغاء' }}</el-button>
                <el-button type="primary" @click="confirmDialog">{{ $t('confirm') || 'تأكيد' }}</el-button>
            </template>
        </el-dialog>

        <el-dialog
            v-model="childDialog.visible"
            :title="childDialog.isNew ? ($t('add_sub_item') || 'إضافة عنصر فرعي') : ($t('edit_sub_item') || 'تعديل العنصر الفرعي')"
            width="500px"
            :close-on-click-modal="false"
        >
            <el-form :model="childDialog.form" label-position="top">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item :label="$t('arabic_label') || 'التسمية بالعربية'">
                            <el-input v-model="childDialog.form.label_ar" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('english_label') || 'التسمية بالإنجليزية'">
                            <el-input v-model="childDialog.form.label_en" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item :label="$t('icon') || 'أيقونة'">
                            <el-input v-model="childDialog.form.icon" placeholder="fas fa-star" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('route') || 'الرابط'">
                            <el-input v-model="childDialog.form.route" placeholder="/products" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="childDialog.visible = false">{{ $t('cancel') || 'إلغاء' }}</el-button>
                <el-button type="primary" @click="confirmChildDialog">{{ $t('confirm') || 'تأكيد' }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import { ElMessage } from 'element-plus';
import { Plus, Check, Edit, Delete, Top, Bottom } from '@element-plus/icons-vue';

const { t } = useI18n();
const settingsStore = useSettingsStore();

const items = ref([]);
const saving = ref(false);

onMounted(() => {
    const saved = settingsStore.data?.secondary_navbar_items;
    if (saved) {
        try {
            items.value = JSON.parse(saved);
        } catch (e) {
            items.value = getDefaultItems();
        }
    } else {
        items.value = getDefaultItems();
    }
});

function getDefaultItems() {
    return [
        {
            id: 'products', type: 'dropdown', active: true,
            label_ar: 'المنتجات', label_en: 'Products', icon: 'fas fa-th-list',
            children: [
                { id: 'all_products', active: true, label_ar: 'جميع المنتجات', label_en: 'All Products', icon: 'fas fa-th-large', route: '/products' },
                { id: 'electronics', active: false, label_ar: 'إلكترونيات', label_en: 'Electronics', icon: 'fas fa-laptop', route: '/products?category=electronics' },
                { id: 'clothing', active: false, label_ar: 'ملابس', label_en: 'Clothing', icon: 'fas fa-tshirt', route: '/products?category=clothing' },
                { id: 'home_garden', active: false, label_ar: 'المنزل والحديقة', label_en: 'Home & Garden', icon: 'fas fa-home', route: '/products?category=home' },
            ]
        },
        {
            id: 'featured', type: 'dropdown', active: true,
            label_ar: 'منتجات مميزة', label_en: 'Featured Products', icon: 'fas fa-star',
            children: [
                { id: 'view_all_featured', active: true, label_ar: 'عرض جميع المنتجات المميزة', label_en: 'View All Featured', icon: 'fas fa-fire', route: '/featured-products' },
                { id: 'new_arrivals', active: true, label_ar: 'وصل حديثاً', label_en: 'New Arrivals', icon: 'fas fa-sparkles', route: '/featured-products?new' },
                { id: 'best_sellers', active: true, label_ar: 'الأكثر مبيعاً', label_en: 'Best Sellers', icon: 'fas fa-trophy', route: '/featured-products?best' },
            ]
        },
        {
            id: 'offers', type: 'dropdown', active: true,
            label_ar: 'العروض المميزة', label_en: 'Special Offers', icon: 'fas fa-tag',
            children: [
                { id: 'current_offers', active: true, label_ar: 'العروض الحالية', label_en: 'Current Offers', icon: 'fas fa-fire', route: '/special-offers' },
                { id: 'flash_sales', active: true, label_ar: 'التخفيضات السريعة', label_en: 'Flash Sales', icon: 'fas fa-bolt', route: '/special-offers?flash' },
                { id: 'clearance', active: true, label_ar: 'التصفية', label_en: 'Clearance', icon: 'fas fa-percent', route: '/special-offers?clearance' },
            ]
        },
        {
            id: 'categories', type: 'link', active: true,
            label_ar: 'الفئات', label_en: 'Categories', icon: 'fas fa-folder', route: '/categories'
        },
        {
            id: 'contact', type: 'link', active: true,
            label_ar: 'تواصل معنا', label_en: 'Contact Us', icon: 'fas fa-headset', route: '/contact'
        },
    ];
}

let idCounter = Date.now();
function generateId() {
    return `item_${idCounter++}`;
}

function addItem() {
    dialog.isNew = true;
    dialog.form = {
        id: generateId(),
        type: 'link',
        active: true,
        label_ar: '',
        label_en: '',
        icon: 'fas fa-link',
        route: '/',
        children: [],
    };
    dialog.visible = true;
}

function editItem(index) {
    const item = items.value[index];
    dialog.isNew = false;
    dialog.editIndex = index;
    dialog.form = { ...item, children: [...(item.children || [])] };
    dialog.visible = true;
}

function removeItem(index) {
    items.value.splice(index, 1);
}

const dialog = reactive({
    visible: false,
    isNew: true,
    editIndex: -1,
    form: { id: '', type: 'link', active: true, label_ar: '', label_en: '', icon: 'fas fa-link', route: '/', children: [] },
});

function confirmDialog() {
    if (!dialog.form.label_ar) {
        ElMessage.warning(t('please_enter_label') || 'يرجى إدخال التسمية بالعربية');
        return;
    }
    if (dialog.isNew) {
        items.value.push({ ...dialog.form });
    } else {
        items.value[dialog.editIndex] = { ...dialog.form };
    }
    dialog.visible = false;
}

const childDialog = reactive({
    visible: false,
    isNew: true,
    parentIndex: -1,
    editIndex: -1,
    form: { id: '', active: true, label_ar: '', label_en: '', icon: 'fas fa-link', route: '/' },
});

function addChild(parentIndex) {
    childDialog.isNew = true;
    childDialog.parentIndex = parentIndex;
    childDialog.form = { id: generateId(), active: true, label_ar: '', label_en: '', icon: 'fas fa-link', route: '/' };
    childDialog.visible = true;
}

function editChild(parentIndex, childIndex) {
    const child = items.value[parentIndex].children[childIndex];
    childDialog.isNew = false;
    childDialog.parentIndex = parentIndex;
    childDialog.editIndex = childIndex;
    childDialog.form = { ...child };
    childDialog.visible = true;
}

function removeChild(parentIndex, childIndex) {
    items.value[parentIndex].children.splice(childIndex, 1);
}

function moveItem(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= items.value.length) return;
    const arr = items.value;
    [arr[index], arr[target]] = [arr[target], arr[index]];
}

function moveChild(parentIndex, childIndex, direction) {
    const children = items.value[parentIndex].children;
    if (!children) return;
    const target = childIndex + direction;
    if (target < 0 || target >= children.length) return;
    [children[childIndex], children[target]] = [children[target], children[childIndex]];
}

function confirmChildDialog() {
    if (!childDialog.form.label_ar) {
        ElMessage.warning(t('please_enter_label') || 'يرجى إدخال التسمية بالعربية');
        return;
    }
    const parent = items.value[childDialog.parentIndex];
    if (!parent.children) parent.children = [];
    if (childDialog.isNew) {
        parent.children.push({ ...childDialog.form });
    } else {
        parent.children[childDialog.editIndex] = { ...childDialog.form };
    }
    childDialog.visible = false;
}

async function saveItems() {
    saving.value = true;
    try {
        const formData = new FormData();
        formData.append('settings[secondary_navbar_items]', JSON.stringify(items.value));
        await settingsStore.save(formData);
        ElMessage.success(t('saved_successfully') || 'تم الحفظ بنجاح');
    } catch (e) {
        ElMessage.error(t('save_failed') || 'فشل الحفظ');
    } finally {
        saving.value = false;
    }
}
</script>

<style scoped>
.secondary-navbar-page {
    padding: 20px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.page-title h1 {
    margin: 0 0 4px;
    font-size: 1.5rem;
}
.page-title p {
    margin: 0;
    color: #64748b;
    font-size: 0.9rem;
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-header h2 {
    margin: 0 0 4px;
    font-size: 1.1rem;
}
.header-note {
    margin: 0;
    color: #94a3b8;
    font-size: 0.82rem;
}
.empty-state {
    text-align: center;
    padding: 48px 20px;
    color: #94a3b8;
}
.nav-item-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    margin-bottom: 12px;
    padding: 12px 16px;
    background: #fff;
    transition: all 0.2s;
}
.nav-item-card.inactive {
    opacity: 0.6;
    background: #f8fafc;
}
.item-header {
    display: flex;
    align-items: center;
    gap: 10px;
}
.drag-handles {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.drag-handles .el-button {
    min-width: 20px;
    width: 20px;
    height: 16px;
    padding: 0;
}
.item-icon-preview {
    font-size: 1rem;
    color: #64748b;
    width: 20px;
    text-align: center;
}
.item-label {
    flex: 1;
    font-weight: 500;
}
.item-actions {
    display: flex;
    gap: 4px;
}
.children-section {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed #e2e8f0;
}
.children-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.children-title {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}
.child-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    border-radius: 6px;
    margin-bottom: 4px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
}
.child-item.inactive {
    opacity: 0.5;
}
.child-drag-handles {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.child-drag-handles .el-button {
    min-width: 18px;
    width: 18px;
    height: 14px;
    padding: 0;
}
.child-label {
    flex: 1;
    font-size: 0.9rem;
}
.child-actions {
    display: flex;
    gap: 2px;
}
[data-theme="dark"] .nav-item-card {
    background: #1e293b;
    border-color: #334155;
}
[data-theme="dark"] .nav-item-card.inactive {
    background: #0f172a;
}
[data-theme="dark"] .child-item {
    background: #0f172a;
    border-color: #1e293b;
}
[data-theme="dark"] .page-title p,
[data-theme="dark"] .header-note {
    color: #94a3b8;
}
</style>
