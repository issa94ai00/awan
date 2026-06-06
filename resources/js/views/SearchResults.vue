<template>
  <div class="search-results-page container mx-auto py-8 px-4">
    <h1 class="text-3xl font-bold mb-6">نتائج البحث</h1>
    <p v-if="!query" class="mb-4">أدخل نص البحث في شريط البحث أعلاه.</p>
    <p v-else class="mb-4">نتائج البحث عن: <strong>{{ query }}</strong></p>
    <div v-if="query">
      <p class="text-gray-600">يمكنك هنا عرض النتائج باستخدام API البحث.</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const query = computed(() => route.query.q || '');
</script>
