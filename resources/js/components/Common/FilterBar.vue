<!-- resources/js/Components/Common/FilterBar.vue -->
<script setup>
const props = defineProps({
    filters: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['filter-change']);

const handleFilterChange = (key, value) => {
    emit('filter-change', { key, value });
};

const resetFilters = () => {
    props.filters.forEach(filter => {
        handleFilterChange(filter.key, filter.defaultValue || '');
    });
};
</script>

<template>
    <div class="bg-white p-4 rounded-lg shadow mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="filter in filters" :key="filter.key">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ filter.label }}
                </label>
                
                <select 
                    v-if="filter.type === 'select'"
                    :value="filter.value"
                    @change="handleFilterChange(filter.key, $event.target.value)"
                    class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">{{ filter.placeholder || 'الكل' }}</option>
                    <option 
                        v-for="option in filter.options" 
                        :key="option.value" 
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                
                <input 
                    v-else-if="filter.type === 'text'"
                    :type="filter.inputType || 'text'"
                    :value="filter.value"
                    @input="handleFilterChange(filter.key, $event.target.value)"
                    :placeholder="filter.placeholder"
                    class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-blue-500"
                />
                
                <input 
                    v-else-if="filter.type === 'date'"
                    type="date"
                    :value="filter.value"
                    @change="handleFilterChange(filter.key, $event.target.value)"
                    class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-blue-500"
                />
                
                <input 
                    v-else-if="filter.type === 'number'"
                    type="number"
                    :value="filter.value"
                    @input="handleFilterChange(filter.key, $event.target.value)"
                    :placeholder="filter.placeholder"
                    class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-blue-500"
                />
            </div>
        </div>
        
        <div class="mt-4 flex justify-end">
            <button 
                @click="resetFilters"
                class="text-gray-600 hover:text-gray-800 text-sm font-medium"
            >
                إعادة تعيين الفلاتر
            </button>
        </div>
    </div>
</template>
