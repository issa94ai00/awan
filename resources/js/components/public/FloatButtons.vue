<template>
    <div class="fixed bottom-8 left-8 flex flex-col gap-4 z-50">
        <a :href="whatsappUrl" class="w-12 h-12 rounded-full bg-green-500 text-white flex items-center justify-center shadow-lg hover:scale-110 hover:shadow-xl transition-all" target="_blank" title="تواصل معنا واتساب">
            <i class="fab fa-whatsapp text-xl"></i>
        </a>
        <a :href="facebookUrl" class="w-12 h-12 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-lg hover:scale-110 hover:shadow-xl transition-all" target="_blank" title="تابعنا على فيسبوك">
            <i class="fab fa-facebook text-xl"></i>
        </a>
        <router-link to="/inquiry" class="w-12 h-12 rounded-full bg-blue-500 text-white flex items-center justify-center shadow-lg hover:scale-110 hover:shadow-xl transition-all" title="إرسال استفسار">
            <i class="fas fa-comment-dots text-xl"></i>
        </router-link>
    </div>
</template>

<script setup>
// Settings (these should come from API in production)
const whatsappUrl = 'https://wa.me/963900000000';
const facebookUrl = '#';
</script>
