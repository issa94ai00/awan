<!-- resources/js/Components/Common/Pagination.vue -->
<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    links: {
        type: Array,
        required: true,
    },
});

const isCurrent = (url) => {
    return url === null;
};
</script>

<template>
    <nav v-if="links.length > 3" class="flex justify-center items-center gap-2 mt-6">
        <template v-for="(link, index) in links" :key="index">
            <Link
                v-if="link.url"
                :href="link.url"
                v-html="link.label"
                class="px-3 py-1 rounded border hover:bg-gray-50"
                :class="{
                    'bg-blue-600 text-white border-blue-600': link.active,
                    'text-gray-700 border-gray-300': !link.active,
                }"
            />
            <span
                v-else
                v-html="link.label"
                class="px-3 py-1 rounded border border-gray-300 text-gray-400"
            />
        </template>
    </nav>
</template>
