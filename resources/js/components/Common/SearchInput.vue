<!-- resources/js/Components/Common/SearchInput.vue -->
<script setup>
import { ref, watch } from 'vue';
import { debounce } from 'lodash';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'بحث...',
    },
    debounceDelay: {
        type: Number,
        default: 300,
    },
});

const emit = defineEmits(['update:modelValue', 'search']);

const searchQuery = ref(props.modelValue);

const debouncedSearch = debounce((value) => {
    emit('search', value);
}, props.debounceDelay);

watch(searchQuery, (newValue) => {
    emit('update:modelValue', newValue);
    debouncedSearch(newValue);
});

watch(() => props.modelValue, (newValue) => {
    searchQuery.value = newValue;
});

function clearSearch() {
    searchQuery.value = '';
}
</script>

<template>
    <div class="relative">
        <input
            v-model="searchQuery"
            type="text"
            :placeholder="placeholder"
            class="w-full border rounded-lg p-2 pl-10 pr-10"
        />
        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
            🔍
        </span>
        <button
            v-if="searchQuery"
            @click="clearSearch"
            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
        >
            ✕
        </button>
    </div>
</template>
