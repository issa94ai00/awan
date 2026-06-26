<template>
  <div class="login-page">
    <h2>تسجيل الدخول</h2>

    <form @submit.prevent="submit" class="login-form">
      <div class="field">
        <label>البريد الإلكتروني</label>
        <input v-model="form.email" type="email" required />
      </div>

      <div class="field">
        <label>كلمة المرور</label>
        <input v-model="form.password" type="password" required />
      </div>

      <div class="actions">
        <button :disabled="loading" class="btn">{{ loading ? 'جارٍ...' : 'دخول' }}</button>
      </div>

      <p v-if="error" class="error">{{ error }}</p>
    </form>
  </div>
</template>

<script>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export default {
  name: 'LoginView',
  setup() {
    const auth = useAuthStore();
    const form = ref({ email: '', password: '' });
    const loading = ref(false);
    const error = ref('');

    async function submit() {
      error.value = '';
      loading.value = true;
      try {
        await auth.login(form.value);
        const redirect = router.currentRoute.value.query.redirect || '/admin/dashboard';
        router.push(redirect);
      } catch (err) {
        error.value = err.response?.data?.message || err.message || 'فشل تسجيل الدخول';
      } finally {
        loading.value = false;
      }
    }

    return { form, loading, error, submit };
  }
};
</script>
<style scoped>
.login-page { max-width: 420px; margin: 3rem auto; padding: 2rem; background: #fff; border-radius:6px }
.login-form .field { margin-bottom: 1rem }
.login-form label { display:block; margin-bottom:.25rem; font-weight:600 }
.login-form input { width:100%; padding:.5rem; border:1px solid #ddd; border-radius:4px }
.actions { text-align:right }
.btn { padding:.5rem 1rem; background:#2b6cb0; color:#fff; border:none; border-radius:4px }
.error { color:#c53030; margin-top:1rem }
</style>
