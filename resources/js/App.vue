<template>
    <div id="app">
        <router-view />
    </div>
</template>

<script setup>
import { onMounted } from 'vue';

onMounted(() => {
    console.log('Vue app mounted');
});
</script>

<style>
#app {
    min-height: 100vh;
}
</style>
