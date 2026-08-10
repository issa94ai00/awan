<template>
  <div class="auth-page">
    <!-- Decorative background: purely presentational, hidden from assistive tech -->
    <div class="auth-page__backdrop" aria-hidden="true">
      <span class="blob blob--one"></span>
      <span class="blob blob--two"></span>
      <span class="grid-veil"></span>
    </div>

    <div class="auth-shell">
      <!-- ── Brand panel ─────────────────────────────────────────────── -->
      <aside class="brand-panel">
        <div class="brand-panel__glow" aria-hidden="true"></div>

        <div class="brand-panel__top">
          <div class="brand-mark">
            <img :src="siteLogo" :alt="siteName" @error="onLogoError" />
          </div>
          <div class="brand-mark__text">
            <h1 class="brand-name">{{ siteName }}</h1>
            <p class="brand-tagline">{{ siteTagline }}</p>
          </div>
        </div>

        <ul class="brand-points">
          <li v-for="point in brandPoints" :key="point.key">
            <span class="brand-points__icon" aria-hidden="true" v-html="point.icon"></span>
            <span>{{ point.label }}</span>
          </li>
        </ul>

        <p class="brand-panel__legal">
          &copy; {{ currentYear }} {{ siteName }} — {{ t('all_rights_reserved') }}
        </p>
      </aside>

      <!-- ── Form panel ──────────────────────────────────────────────── -->
      <main class="form-panel">
        <div class="form-panel__toolbar">
          <a class="ghost-link" href="/">
            <svg viewBox="0 0 24 24" class="ghost-link__icon" aria-hidden="true">
              <path d="M15 5l-7 7 7 7" />
            </svg>
            <span>{{ t('back_to_site') }}</span>
          </a>

          <button type="button" class="lang-toggle" @click="toggleLocale">
            {{ locale === 'ar' ? 'EN' : 'ع' }}
          </button>
        </div>

        <div class="form-panel__body">
          <!-- Compact logo, shown only when the brand panel is collapsed -->
          <div class="brand-mark brand-mark--compact">
            <img :src="siteLogo" :alt="siteName" @error="onLogoError" />
          </div>

          <span class="eyebrow">{{ t('admin_portal') }}</span>
          <h2 class="form-title">{{ t('login_welcome_back') }}</h2>
          <p class="form-subtitle">{{ t('login_page_subtitle') }}</p>

          <transition name="alert">
            <p v-if="error" class="alert" role="alert">
              <svg viewBox="0 0 24 24" class="alert__icon" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7.5v5M12 16h.01" />
              </svg>
              <span>{{ error }}</span>
            </p>
          </transition>

          <form class="auth-form" novalidate @submit.prevent="submit">
            <div class="field">
              <label class="field__label" for="login-identifier">{{ t('login_identifier') }}</label>
              <div class="field__control">
                <svg viewBox="0 0 24 24" class="field__icon" aria-hidden="true">
                  <rect x="3" y="5" width="18" height="14" rx="3" />
                  <path d="M4 8l8 5 8-5" />
                </svg>
                <input
                  id="login-identifier"
                  ref="identifierInput"
                  v-model.trim="form.identifier"
                  type="text"
                  inputmode="email"
                  autocomplete="username"
                  spellcheck="false"
                  dir="ltr"
                  :aria-invalid="!!error"
                  :placeholder="t('login_identifier_placeholder')"
                  required
                />
              </div>
            </div>

            <div class="field">
              <label class="field__label" for="login-password">{{ t('password') }}</label>
              <div class="field__control">
                <svg viewBox="0 0 24 24" class="field__icon" aria-hidden="true">
                  <rect x="4" y="10" width="16" height="10" rx="3" />
                  <path d="M8 10V7.5a4 4 0 0 1 8 0V10" />
                </svg>
                <input
                  id="login-password"
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  dir="ltr"
                  :aria-invalid="!!error"
                  :placeholder="t('login_password_placeholder')"
                  required
                  @keyup="detectCapsLock"
                  @keydown="detectCapsLock"
                />
                <button
                  type="button"
                  class="field__toggle"
                  :aria-label="showPassword ? t('hide_password') : t('show_password')"
                  :aria-pressed="showPassword"
                  @click="showPassword = !showPassword"
                >
                  <svg v-if="!showPassword" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                  <svg v-else viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M2.5 12S6 5.5 12 5.5c1.6 0 3 .45 4.2 1.1M21.5 12s-3.5 6.5-9.5 6.5c-1.6 0-3-.45-4.2-1.1" />
                    <path d="M4 4l16 16" />
                  </svg>
                </button>
              </div>
              <p v-if="capsLockOn" class="field__hint">{{ t('caps_lock_on') }}</p>
            </div>

            <div class="form-row">
              <label class="checkbox">
                <input v-model="rememberIdentifier" type="checkbox" />
                <span class="checkbox__box" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                </span>
                <span>{{ t('remember_identifier') }}</span>
              </label>

              <a class="ghost-link ghost-link--muted" href="/forgot-password">{{ t('forgot_password') }}</a>
            </div>

            <button type="submit" class="submit-btn" :disabled="loading || !canSubmit">
              <span v-if="loading" class="spinner" aria-hidden="true"></span>
              <span>{{ loading ? t('login_in_progress') : t('login_button') }}</span>
            </button>
          </form>

          <p class="secure-note">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 3l7 3v5.5c0 4.3-2.9 7.9-7 9.5-4.1-1.6-7-5.2-7-9.5V6l7-3z" />
              <path d="M9.5 12.2l1.8 1.8 3.4-3.6" />
            </svg>
            <span>{{ t('secure_connection_note') }}</span>
          </p>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useSettingsStore } from '@/stores/settings';
import { updateDirection } from '@/app';
import router from '@/router';

const FALLBACK_LOGO = '/assets/images/logo.png';
const REMEMBERED_KEY = 'login:identifier';

const { t, locale } = useI18n();
const auth = useAuthStore();
const settingsStore = useSettingsStore();

const form = ref({ identifier: '', password: '' });
const loading = ref(false);
const error = ref('');
const showPassword = ref(false);
const capsLockOn = ref(false);
const rememberIdentifier = ref(false);
const identifierInput = ref(null);
const logoFailed = ref(false);

const currentYear = new Date().getFullYear();

// The blade shell hands us the settings synchronously, so the brand renders on
// the first paint instead of flashing a fallback while the API round-trips.
const bootSettings = computed(() => ({ ...(window.systemData?.settings || {}), ...settingsStore.data }));

const siteName = computed(() => {
    const s = bootSettings.value;
    return (locale.value === 'en' ? s.site_name_en || s.site_name : s.site_name) || 'أوان التقدم';
});

const siteTagline = computed(() => {
    const s = bootSettings.value;
    return (locale.value === 'en' ? s.site_tagline_en || s.site_tagline : s.site_tagline) || '';
});

const siteLogo = computed(() => {
    if (logoFailed.value) return FALLBACK_LOGO;
    const logo = bootSettings.value.site_logo;
    if (!logo) return FALLBACK_LOGO;
    if (/^https?:\/\//.test(logo) || logo.startsWith('/')) return logo;
    if (logo.startsWith('assets/')) return `/${logo}`;
    return `/storage/${logo}`;
});

const brandPoints = computed(() => [
    {
        key: 'modules',
        label: t('login_point_modules'),
        icon: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg>',
    },
    {
        key: 'reports',
        label: t('login_point_reports'),
        icon: '<svg viewBox="0 0 24 24"><path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 16v-5M12.5 16V8M17 16v-3"/></svg>',
    },
    {
        key: 'roles',
        label: t('login_point_roles'),
        icon: '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6"/></svg>',
    },
]);

const canSubmit = computed(() => form.value.identifier.length > 0 && form.value.password.length > 0);

function onLogoError() {
    logoFailed.value = true;
}

function detectCapsLock(event) {
    if (typeof event.getModifierState !== 'function') return;
    capsLockOn.value = event.getModifierState('CapsLock');
}

function toggleLocale() {
    const next = locale.value === 'ar' ? 'en' : 'ar';
    locale.value = next;
    localStorage.setItem('locale', next);
    updateDirection(next);
}

async function submit() {
    if (loading.value || !canSubmit.value) return;

    error.value = '';
    loading.value = true;
    try {
        const identifier = form.value.identifier.trim();
        const credentials = {
            password: form.value.password,
            ...(identifier.includes('@') ? { email: identifier } : { phone: identifier }),
        };
        await auth.login(credentials);

        if (rememberIdentifier.value) {
            localStorage.setItem(REMEMBERED_KEY, identifier);
        } else {
            localStorage.removeItem(REMEMBERED_KEY);
        }

        const redirect = router.currentRoute.value.query.redirect || '/admin/dashboard';
        router.push(redirect);
    } catch (err) {
        error.value = err.response?.data?.message || err.message || t('login_failed');
        form.value.password = '';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    const remembered = localStorage.getItem(REMEMBERED_KEY);
    if (remembered) {
        form.value.identifier = remembered;
        rememberIdentifier.value = true;
    }
    identifierInput.value?.focus();
    // Refresh in the background: systemData already covers the first paint.
    settingsStore.fetch().catch((err) => console.warn('Login: settings fetch failed', err));
});
</script>

<style scoped>
.auth-page {
    --auth-ink: #0f172a;
    --auth-muted: #64748b;
    --auth-line: #e2e8f0;
    --auth-surface: #ffffff;
    --auth-primary: var(--accent-blue, #1e3a8a);
    --auth-primary-light: var(--accent-blue-light, #3b82f6);
    --auth-primary-dark: var(--primary-dark-light, #1e1b4b);
    --auth-accent: var(--accent-gold, #f59e0b);
    --auth-radius: 22px;

    position: relative;
    min-height: 100vh;
    min-height: 100dvh;
    display: grid;
    place-items: center;
    padding: clamp(0.75rem, 3vw, 2.5rem);
    background: #eef2f8;
    font-family: 'Cairo', 'Inter', system-ui, sans-serif;
    color: var(--auth-ink);
    overflow: hidden;
}

/* ── Backdrop ─────────────────────────────────────────────────────── */
.auth-page__backdrop {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(90px);
    opacity: 0.5;
}

.blob--one {
    width: 42vw;
    height: 42vw;
    min-width: 320px;
    min-height: 320px;
    inset-block-start: -14vw;
    inset-inline-start: -10vw;
    background: var(--auth-primary-light);
    animation: drift 18s ease-in-out infinite alternate;
}

.blob--two {
    width: 36vw;
    height: 36vw;
    min-width: 280px;
    min-height: 280px;
    inset-block-end: -14vw;
    inset-inline-end: -8vw;
    background: var(--auth-accent);
    opacity: 0.32;
    animation: drift 22s ease-in-out infinite alternate-reverse;
}

.grid-veil {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(to right, rgba(15, 23, 42, 0.045) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(15, 23, 42, 0.045) 1px, transparent 1px);
    background-size: 46px 46px;
    mask-image: radial-gradient(ellipse at center, #000 25%, transparent 72%);
    -webkit-mask-image: radial-gradient(ellipse at center, #000 25%, transparent 72%);
}

@keyframes drift {
    from { transform: translate3d(0, 0, 0) scale(1); }
    to { transform: translate3d(3rem, 2rem, 0) scale(1.12); }
}

/* ── Shell ────────────────────────────────────────────────────────── */
.auth-shell {
    position: relative;
    width: min(1080px, 100%);
    display: grid;
    grid-template-columns: 1.05fr 1fr;
    background: var(--auth-surface);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: var(--auth-radius);
    box-shadow: 0 32px 80px -32px rgba(15, 23, 42, 0.45);
    overflow: hidden;
}

/* ── Brand panel ──────────────────────────────────────────────────── */
.brand-panel {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 2.5rem;
    padding: clamp(2rem, 3.5vw, 3.25rem);
    color: #fff;
    background: linear-gradient(150deg, var(--auth-primary-dark) 0%, var(--auth-primary) 58%, var(--auth-primary-light) 130%);
    isolation: isolate;
}

.brand-panel__glow {
    position: absolute;
    inset-block-start: -30%;
    inset-inline-end: -25%;
    width: 70%;
    aspect-ratio: 1;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.28), transparent 68%);
    z-index: -1;
}

.brand-panel__top {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.brand-mark {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    width: 76px;
    height: 76px;
    padding: 10px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 14px 32px -14px rgba(0, 0, 0, 0.55);
}

.brand-mark img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.brand-mark--compact {
    display: none;
    width: 64px;
    height: 64px;
    margin-inline: auto;
    margin-block-end: 1.25rem;
    border: 1px solid var(--auth-line);
    box-shadow: 0 10px 24px -14px rgba(15, 23, 42, 0.5);
}

.brand-name {
    margin: 0;
    font-size: clamp(1.35rem, 2vw, 1.7rem);
    font-weight: 800;
    line-height: 1.3;
}

.brand-tagline {
    margin: 0.25rem 0 0;
    font-size: 0.95rem;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.78);
}

.brand-points {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 1rem;
}

.brand-points li {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    font-size: 0.95rem;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.92);
}

.brand-points__icon {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.brand-points__icon :deep(svg) {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: var(--auth-accent);
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.brand-panel__legal {
    margin: 0;
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.6);
}

/* ── Form panel ───────────────────────────────────────────────────── */
.form-panel {
    display: flex;
    flex-direction: column;
    padding: clamp(1.5rem, 3vw, 2.5rem);
}

.form-panel__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.form-panel__body {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding-block: clamp(1.5rem, 4vw, 2.5rem) 0;
}

.eyebrow {
    display: inline-flex;
    align-self: flex-start;
    padding: 0.3rem 0.8rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: var(--auth-primary);
    background: color-mix(in srgb, var(--auth-primary) 10%, transparent);
}

.form-title {
    margin: 0.85rem 0 0;
    font-size: clamp(1.6rem, 3vw, 2.1rem);
    font-weight: 800;
    letter-spacing: -0.01em;
}

.form-subtitle {
    margin: 0.4rem 0 1.75rem;
    font-size: 0.95rem;
    line-height: 1.7;
    color: var(--auth-muted);
}

/* ── Fields ───────────────────────────────────────────────────────── */
.auth-form {
    display: grid;
    gap: 1.15rem;
}

.field__label {
    display: block;
    margin-block-end: 0.45rem;
    font-size: 0.85rem;
    font-weight: 700;
}

.field__control {
    position: relative;
    display: flex;
    align-items: center;
    border: 1.5px solid var(--auth-line);
    border-radius: 14px;
    background: #f8fafc;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.field__control:focus-within {
    background: #fff;
    border-color: var(--auth-primary);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--auth-primary) 14%, transparent);
}

.field__icon {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    margin-inline: 0.9rem 0;
    fill: none;
    stroke: var(--auth-muted);
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.field__control input {
    flex: 1;
    min-width: 0;
    padding: 0.85rem 0.75rem;
    border: 0;
    background: transparent;
    font: inherit;
    font-size: 0.95rem;
    color: inherit;
    outline: none;
}

.field__control input::placeholder {
    color: #94a3b8;
}

.field__toggle {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    margin-inline-end: 0.35rem;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: var(--auth-muted);
    cursor: pointer;
    transition: color 0.2s ease, background 0.2s ease;
}

.field__toggle:hover,
.field__toggle:focus-visible {
    color: var(--auth-primary);
    background: color-mix(in srgb, var(--auth-primary) 8%, transparent);
}

.field__toggle svg {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.field__hint {
    margin: 0.45rem 0 0;
    font-size: 0.78rem;
    font-weight: 600;
    color: #b45309;
}

/* ── Row: remember + forgot ───────────────────────────────────────── */
.form-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.85rem;
}

.checkbox {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    cursor: pointer;
    color: var(--auth-muted);
    user-select: none;
}

.checkbox input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.checkbox__box {
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    border: 1.5px solid var(--auth-line);
    border-radius: 6px;
    background: #fff;
    transition: background 0.2s ease, border-color 0.2s ease;
}

.checkbox__box svg {
    width: 13px;
    height: 13px;
    fill: none;
    stroke: #fff;
    stroke-width: 3;
    stroke-linecap: round;
    stroke-linejoin: round;
    opacity: 0;
    transform: scale(0.6);
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.checkbox input:checked + .checkbox__box {
    background: var(--auth-primary);
    border-color: var(--auth-primary);
}

.checkbox input:checked + .checkbox__box svg {
    opacity: 1;
    transform: scale(1);
}

.checkbox input:focus-visible + .checkbox__box {
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--auth-primary) 18%, transparent);
}

/* ── Buttons & links ──────────────────────────────────────────────── */
.submit-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    width: 100%;
    margin-block-start: 0.35rem;
    padding: 0.95rem 1.25rem;
    border: 0;
    border-radius: 14px;
    font: inherit;
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(135deg, var(--auth-primary), var(--auth-primary-light));
    box-shadow: 0 14px 30px -14px color-mix(in srgb, var(--auth-primary) 85%, transparent);
    cursor: pointer;
    transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
}

.submit-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 20px 38px -16px color-mix(in srgb, var(--auth-primary) 90%, transparent);
}

.submit-btn:active:not(:disabled) {
    transform: translateY(0);
}

.submit-btn:disabled {
    filter: grayscale(0.35);
    opacity: 0.6;
    cursor: not-allowed;
    box-shadow: none;
}

.spinner {
    width: 18px;
    height: 18px;
    border: 2.5px solid rgba(255, 255, 255, 0.35);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.ghost-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--auth-primary);
    text-decoration: none;
    transition: opacity 0.2s ease;
}

.ghost-link:hover {
    opacity: 0.75;
    text-decoration: underline;
}

.ghost-link--muted {
    color: var(--auth-muted);
}

.ghost-link__icon {
    width: 16px;
    height: 16px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* The chevron points "back", which flips with the writing direction */
[dir='ltr'] .ghost-link__icon {
    transform: scaleX(1);
}

[dir='rtl'] .ghost-link__icon {
    transform: scaleX(-1);
}

.lang-toggle {
    min-width: 44px;
    padding: 0.4rem 0.75rem;
    border: 1.5px solid var(--auth-line);
    border-radius: 999px;
    background: #fff;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--auth-ink);
    cursor: pointer;
    transition: border-color 0.2s ease, color 0.2s ease;
}

.lang-toggle:hover {
    border-color: var(--auth-primary);
    color: var(--auth-primary);
}

/* ── Alert & footnote ─────────────────────────────────────────────── */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    margin: 0 0 1.25rem;
    padding: 0.8rem 1rem;
    border: 1px solid #fecaca;
    border-radius: 12px;
    background: #fef2f2;
    font-size: 0.88rem;
    line-height: 1.6;
    color: #b91c1c;
}

.alert__icon {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    margin-block-start: 1px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
}

.alert-enter-active,
.alert-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.alert-enter-from,
.alert-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

.secure-note {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    margin: 1.75rem 0 0;
    font-size: 0.78rem;
    color: var(--auth-muted);
}

.secure-note svg {
    width: 16px;
    height: 16px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* ── Responsive ───────────────────────────────────────────────────── */
@media (max-width: 900px) {
    .auth-shell {
        grid-template-columns: 1fr;
    }

    .brand-panel {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 1.25rem;
        padding: 1.5rem;
    }

    .brand-points,
    .brand-panel__legal {
        display: none;
    }

    .brand-mark {
        width: 60px;
        height: 60px;
        border-radius: 16px;
    }
}

@media (max-width: 560px) {
    .auth-page {
        padding: 0;
        background: #fff;
    }

    .auth-shell {
        width: 100%;
        min-height: 100dvh;
        border: 0;
        border-radius: 0;
        box-shadow: none;
        align-content: start;
    }

    /* The banner gives way to a compact logo above the form on small screens */
    .brand-panel {
        display: none;
    }

    .brand-mark--compact {
        display: grid;
    }

    .form-panel {
        padding: 1.25rem 1.25rem 2rem;
    }

    .form-panel__body {
        padding-block-start: 1.5rem;
    }

    .eyebrow {
        align-self: center;
        margin-inline: auto;
    }

    .form-title,
    .form-subtitle {
        text-align: center;
    }
}

@media (prefers-reduced-motion: reduce) {
    .blob,
    .spinner {
        animation: none;
    }

    .submit-btn,
    .field__control,
    .alert-enter-active,
    .alert-leave-active {
        transition: none;
    }

    .submit-btn:hover:not(:disabled) {
        transform: none;
    }
}
</style>
