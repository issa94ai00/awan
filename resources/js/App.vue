<template>
    <router-view />
</template>

<script setup>
import { onMounted } from 'vue';

onMounted(() => {
    console.log('Vue app mounted');
});
</script>

<style>
body {
    margin: 0;
    padding: 0;
}
</style>
