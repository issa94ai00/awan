<!-- resources/js/Components/Common/DataTable.vue -->
<script setup>
const props = defineProps({
    columns: {
        type: Array,
        required: true,
    },
    data: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    emptyMessage: {
        type: String,
        default: 'لا توجد بيانات',
    },
});

const emit = defineEmits(['row-click', 'action-click']);

const handleRowClick = (row, index) => {
    emit('row-click', row, index);
};

const handleActionClick = (action, row, index) => {
    emit('action-click', action, row, index);
};
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b bg-gray-50">
                    <th 
                        v-for="column in columns" 
                        :key="column.key"
                        class="p-3 font-medium text-gray-700 text-right"
                        :class="column.class"
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="loading">
                    <td :colspan="columns.length" class="p-4 text-center">
                        <div class="flex justify-center">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        </div>
                    </td>
                </tr>
                <tr v-else-if="data.length === 0">
                    <td :colspan="columns.length" class="p-4 text-center text-gray-500">
                        {{ emptyMessage }}
                    </td>
                </tr>
                <tr 
                    v-else
                    v-for="(row, index) in data" 
                    :key="row.id || index"
                    class="border-b hover:bg-gray-50 cursor-pointer transition-colors"
                    @click="handleRowClick(row, index)"
                >
                    <td 
                        v-for="column in columns" 
                        :key="column.key"
                        class="p-3"
                        :class="column.class"
                    >
                        <slot 
                            v-if="$slots[`cell-${column.key}`]" 
                            :name="`cell-${column.key}`" 
                            :row="row" 
                            :value="row[column.key]"
                        >
                        </slot>
                        <span v-else-if="column.formatter">
                            {{ column.formatter(row[column.key], row) }}
                        </span>
                        <span v-else>{{ row[column.key] }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
