<!-- resources/js/Components/Forms/WarehouseForm.vue -->
<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    warehouse: {
        type: Object,
        default: null,
    },
    users: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['success', 'cancel']);

const form = useForm({
    name: props.warehouse?.name || '',
    code: props.warehouse?.code || '',
    address: props.warehouse?.address || '',
    city: props.warehouse?.city || '',
    country: props.warehouse?.country || '',
    manager_name: props.warehouse?.manager_name || '',
    manager_phone: props.warehouse?.manager_phone || '',
    location_type: props.warehouse?.location_type || 'warehouse',
    latitude: props.warehouse?.latitude || null,
    longitude: props.warehouse?.longitude || null,
    capacity: props.warehouse?.capacity || null,
    operating_hours: props.warehouse?.operating_hours || null,
    is_active: props.warehouse?.is_active ?? true,
    is_primary: props.warehouse?.is_primary ?? false,
    manager_id: props.warehouse?.manager_id || null,
});

// أنواع المواقع
const locationTypes = [
    { value: 'warehouse', label: 'مستودع' },
    { value: 'branch', label: 'فرع' },
    { value: 'distribution_center', label: 'مركز توزيع' },
    { value: '3pl', label: 'طرف ثالث' },
];

// ساعات العمل الافتراضية
const defaultOperatingHours = {
    sunday: { open: '09:00', close: '17:00', is_closed: false },
    monday: { open: '09:00', close: '17:00', is_closed: false },
    tuesday: { open: '09:00', close: '17:00', is_closed: false },
    wednesday: { open: '09:00', close: '17:00', is_closed: false },
    thursday: { open: '09:00', close: '17:00', is_closed: false },
    friday: { open: '09:00', close: '17:00', is_closed: false },
    saturday: { open: '09:00', close: '17:00', is_closed: false },
};

// تهيئة ساعات العمل
if (!form.operating_hours) {
    form.operating_hours = defaultOperatingHours;
}

function submit() {
    const url = props.warehouse 
        ? `/warehouses/${props.warehouse.id}`
        : '/warehouses';
    
    const method = props.warehouse ? 'put' : 'post';
    
    form[method](url, {
        onSuccess: () => {
            emit('success');
        },
    });
}

function cancel() {
    emit('cancel');
}

// الحصول على الموقع الجغرافي
function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                form.latitude = position.coords.latitude;
                form.longitude = position.coords.longitude;
            },
            (error) => {
                console.error('Error getting location:', error);
            }
        );
    }
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <!-- المعلومات الأساسية -->
        <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4">المعلومات الأساسية</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">اسم المستودع *</label>
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
                    <label class="block text-sm font-medium mb-2">كود المستودع *</label>
                    <input 
                        v-model="form.code"
                        type="text"
                        class="w-full border rounded-lg p-2"
                        :class="{ 'border-red-500': form.errors.code }"
                    />
                    <div v-if="form.errors.code" class="text-red-500 text-sm mt-1">
                        {{ form.errors.code }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">نوع الموقع *</label>
                    <select 
                        v-model="form.location_type"
                        class="w-full border rounded-lg p-2"
                        :class="{ 'border-red-500': form.errors.location_type }"
                    >
                        <option v-for="type in locationTypes" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                    <div v-if="form.errors.location_type" class="text-red-500 text-sm mt-1">
                        {{ form.errors.location_type }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">السعة</label>
                    <input 
                        v-model="form.capacity"
                        type="number"
                        class="w-full border rounded-lg p-2"
                    />
                </div>
            </div>
        </div>

        <!-- العنوان -->
        <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4">العنوان</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">العنوان الكامل</label>
                    <textarea 
                        v-model="form.address"
                        class="w-full border rounded-lg p-2"
                        rows="3"
                    ></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">المدينة</label>
                        <input 
                            v-model="form.city"
                            type="text"
                            class="w-full border rounded-lg p-2"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">الدولة</label>
                        <input 
                            v-model="form.country"
                            type="text"
                            class="w-full border rounded-lg p-2"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- الموقع الجغرافي -->
        <div class="bg-gray-50 p-6 rounded-lg">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">الموقع الجغرافي</h3>
                <button 
                    type="button"
                    @click="getCurrentLocation"
                    class="text-blue-600 hover:text-blue-700 text-sm"
                >
                    📍 الحصول على الموقع الحالي
                </button>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">خط العرض (Latitude)</label>
                    <input 
                        v-model="form.latitude"
                        type="number"
                        step="0.000001"
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">خط الطول (Longitude)</label>
                    <input 
                        v-model="form.longitude"
                        type="number"
                        step="0.000001"
                        class="w-full border rounded-lg p-2"
                    />
                </div>
            </div>
        </div>

        <!-- المدير -->
        <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4">المدير</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">اسم المدير</label>
                    <input 
                        v-model="form.manager_name"
                        type="text"
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">هاتف المدير</label>
                    <input 
                        v-model="form.manager_phone"
                        type="text"
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium mb-2">المستخدم المسؤول</label>
                    <select 
                        v-model="form.manager_id"
                        class="w-full border rounded-lg p-2"
                    >
                        <option value="">اختر المستخدم</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }} ({{ user.email }})
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- ساعات العمل -->
        <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4">ساعات العمل</h3>
            <div class="space-y-3">
                <div 
                    v-for="(day, key) in form.operating_hours" 
                    :key="key"
                    class="flex items-center gap-4"
                >
                    <div class="w-32">
                        <span class="text-sm font-medium capitalize">{{ key }}</span>
                    </div>
                    
                    <label class="flex items-center">
                        <input 
                            v-model="day.is_closed"
                            type="checkbox"
                            class="mr-2"
                        />
                        <span class="text-sm">مغلق</span>
                    </label>
                    
                    <template v-if="!day.is_closed">
                        <div>
                            <label class="text-xs text-gray-600">من</label>
                            <input 
                                v-model="day.open"
                                type="time"
                                class="border rounded p-1 text-sm"
                            />
                        </div>
                        
                        <div>
                            <label class="text-xs text-gray-600">إلى</label>
                            <input 
                                v-model="day.close"
                                type="time"
                                class="border rounded p-1 text-sm"
                            />
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- الخيارات الإضافية -->
        <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4">الخيارات الإضافية</h3>
            <div class="flex gap-6">
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
                        v-model="form.is_primary"
                        type="checkbox"
                        id="is_primary"
                        class="mr-2"
                    />
                    <label for="is_primary" class="text-sm font-medium">مستودع رئيسي</label>
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
                {{ form.processing ? 'جاري الحفظ...' : (warehouse ? 'تحديث' : 'حفظ') }}
            </button>
        </div>
    </form>
</template>
