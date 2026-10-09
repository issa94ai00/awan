<template>
    <div class="contact-page-view">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ t('nav_contact') }}</h1>
                <nav class="breadcrumb" :aria-label="t('nav_contact')">
                    <router-link to="/">{{ t('nav_home') }}</router-link>
                    <span class="sep">›</span>
                    <span aria-current="page">{{ t('nav_contact') }}</span>
                </nav>
            </div>
        </section>

        <section class="contact-section fade-up" id="contact">
            <div class="container">
                <div class="section-header">
                    <h2>{{ t('here_to_help') }}</h2>
                    <p>{{ t('contact_desc') }}</p>
                </div>

                <!-- The fastest ways first: most visitors want to call or
                     message, and these used to sit under the form. -->
                <div class="quick-actions">
                    <a v-if="phoneHref" :href="phoneHref" class="quick-card">
                        <span class="quick-icon is-phone"><i class="fas fa-phone" aria-hidden="true"></i></span>
                        <span class="quick-text">
                            <strong>{{ t('call_now') }}</strong>
                            <span dir="ltr">{{ phoneLabel }}</span>
                        </span>
                    </a>
                    <a v-if="whatsappUrl" :href="whatsappUrl" class="quick-card" target="_blank" rel="noopener">
                        <span class="quick-icon is-whatsapp"><i class="fab fa-whatsapp" aria-hidden="true"></i></span>
                        <span class="quick-text">
                            <strong>{{ t('whatsapp_name') }}</strong>
                            <span>{{ t('cp_whatsapp_hint') }}</span>
                        </span>
                    </a>
                    <a v-if="settings.contact_email" :href="`mailto:${settings.contact_email}`" class="quick-card">
                        <span class="quick-icon is-email"><i class="fas fa-envelope" aria-hidden="true"></i></span>
                        <span class="quick-text">
                            <strong>{{ t('email_label') }}</strong>
                            <span dir="ltr">{{ settings.contact_email }}</span>
                        </span>
                    </a>
                </div>

                <div class="contact-wrapper">
                    <!-- Form -->
                    <div class="contact-card contact-form-card">
                        <h3>{{ t('send_us_message') }}</h3>
                        <p class="card-lead">{{ t('cp_form_lead') }}</p>

                        <div v-if="submitted" class="success-box" role="status">
                            <i class="fas fa-circle-check" aria-hidden="true"></i>
                            <strong>{{ t('contact_success_title') }}</strong>
                            <p>{{ t('contact_success_desc') }}</p>
                            <button type="button" class="btn-secondary" @click="resetForm">{{ t('cp_send_another') }}</button>
                        </div>

                        <form v-else class="contact-form" novalidate @submit.prevent="submitForm">
                            <div class="form-row">
                                <div class="form-group" :class="{ 'has-error': fieldErrors.name }">
                                    <label for="cp-name">{{ t('full_name') }} <span class="req" aria-hidden="true">*</span></label>
                                    <input
                                        id="cp-name"
                                        v-model.trim="form.name"
                                        type="text"
                                        autocomplete="name"
                                        required
                                        maxlength="255"
                                        :placeholder="t('enter_full_name')"
                                        :aria-invalid="Boolean(fieldErrors.name)"
                                        :aria-describedby="fieldErrors.name ? 'cp-name-error' : null"
                                    >
                                    <p v-if="fieldErrors.name" id="cp-name-error" class="field-error">{{ fieldErrors.name }}</p>
                                </div>

                                <div class="form-group" :class="{ 'has-error': fieldErrors.phone }">
                                    <label for="cp-phone">{{ t('phone_label') }} <span class="req" aria-hidden="true">*</span></label>
                                    <input
                                        id="cp-phone"
                                        v-model.trim="form.phone"
                                        type="tel"
                                        inputmode="tel"
                                        autocomplete="tel"
                                        dir="ltr"
                                        required
                                        maxlength="50"
                                        placeholder="+963 9xx xxx xxx"
                                        :aria-invalid="Boolean(fieldErrors.phone)"
                                        :aria-describedby="fieldErrors.phone ? 'cp-phone-error' : null"
                                    >
                                    <p v-if="fieldErrors.phone" id="cp-phone-error" class="field-error">{{ fieldErrors.phone }}</p>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group" :class="{ 'has-error': fieldErrors.email }">
                                    <label for="cp-email">{{ t('email_label') }} <span class="optional">({{ t('cp_optional') }})</span></label>
                                    <input
                                        id="cp-email"
                                        v-model.trim="form.email"
                                        type="email"
                                        autocomplete="email"
                                        dir="ltr"
                                        maxlength="255"
                                        placeholder="example@email.com"
                                        :aria-invalid="Boolean(fieldErrors.email)"
                                        :aria-describedby="fieldErrors.email ? 'cp-email-error' : null"
                                    >
                                    <p v-if="fieldErrors.email" id="cp-email-error" class="field-error">{{ fieldErrors.email }}</p>
                                </div>

                                <div class="form-group" :class="{ 'has-error': fieldErrors.subject }">
                                    <label for="cp-subject">{{ t('subject_label') }} <span class="req" aria-hidden="true">*</span></label>
                                    <select
                                        id="cp-subject"
                                        v-model="form.subject"
                                        required
                                        :aria-invalid="Boolean(fieldErrors.subject)"
                                        :aria-describedby="fieldErrors.subject ? 'cp-subject-error' : null"
                                    >
                                        <option value="" disabled>{{ t('choose_subject') }}</option>
                                        <option v-for="option in subjects" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <p v-if="fieldErrors.subject" id="cp-subject-error" class="field-error">{{ fieldErrors.subject }}</p>
                                </div>
                            </div>

                            <div class="form-group" :class="{ 'has-error': fieldErrors.message }">
                                <label for="cp-message">{{ t('message_label') }} <span class="req" aria-hidden="true">*</span></label>
                                <textarea
                                    id="cp-message"
                                    v-model="form.message"
                                    rows="5"
                                    required
                                    maxlength="3000"
                                    :placeholder="t('write_message_here')"
                                    :aria-invalid="Boolean(fieldErrors.message)"
                                    :aria-describedby="fieldErrors.message ? 'cp-message-error' : 'cp-message-count'"
                                ></textarea>
                                <div class="field-foot">
                                    <p v-if="fieldErrors.message" id="cp-message-error" class="field-error">{{ fieldErrors.message }}</p>
                                    <span id="cp-message-count" class="char-count">{{ form.message.length }} / 3000</span>
                                </div>
                            </div>

                            <div v-if="error" class="error-box" role="alert">
                                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                                <span>{{ error }}</span>
                            </div>

                            <button type="submit" class="btn-submit" :disabled="submitting">
                                <i :class="submitting ? 'fas fa-spinner fa-spin' : 'fas fa-paper-plane'" aria-hidden="true"></i>
                                {{ submitting ? t('sending_message') : t('send_message_btn') }}
                            </button>
                        </form>
                    </div>

                    <!-- Where to find us -->
                    <aside class="contact-side">
                        <div class="contact-card">
                            <h3>{{ branches.length > 1 ? t('cp_our_branches') : t('location_label') }}</h3>

                            <ul v-if="branches.length" class="branch-list">
                                <li v-for="(branch, index) in branches" :key="index" class="branch">
                                    <span class="branch-pin"><i class="fas fa-location-dot" aria-hidden="true"></i></span>
                                    <div class="branch-body">
                                        <strong v-if="branch.name">
                                            {{ branch.name }}
                                            <span v-if="branch.is_main && branches.length > 1" class="branch-badge">{{ t('settings_branch_main') }}</span>
                                        </strong>
                                        <address v-if="branch.address">{{ branch.address }}</address>
                                        <div v-if="branch.phone || branch.map_url" class="branch-actions">
                                            <a v-if="branch.phone" :href="telHref(branch.phone)" class="chip" dir="ltr">
                                                <i class="fas fa-phone" aria-hidden="true"></i> {{ formatPhone(branch.phone) }}
                                            </a>
                                            <a v-if="branch.map_url" :href="branch.map_url" class="chip" target="_blank" rel="noopener">
                                                <i class="fas fa-diamond-turn-right" aria-hidden="true"></i> {{ t('ft_directions') }}
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                            <ul v-else-if="legacyAddress.length" class="branch-list">
                                <li class="branch">
                                    <span class="branch-pin"><i class="fas fa-location-dot" aria-hidden="true"></i></span>
                                    <address class="branch-body">
                                        <span v-for="(line, index) in legacyAddress" :key="index">{{ line }}</span>
                                    </address>
                                </li>
                            </ul>
                        </div>

                        <div v-if="workingHours" class="contact-card hours-card">
                            <span class="branch-pin"><i class="far fa-clock" aria-hidden="true"></i></span>
                            <div>
                                <h3>{{ t('working_hours') }}</h3>
                                <p>{{ workingHours }}</p>
                            </div>
                        </div>

                        <div v-if="socials.length" class="contact-card">
                            <h3>{{ t('follow_us') }}</h3>
                            <div class="social-icons">
                                <a
                                    v-for="social in socials"
                                    :key="social.key"
                                    :href="social.url"
                                    class="social-icon"
                                    :class="social.key"
                                    target="_blank"
                                    rel="noopener"
                                    :aria-label="social.label"
                                >
                                    <i :class="social.icon" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted, nextTick } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { formatPhone, telHref, whatsappHref, contactBranches, addressLines } from '@/utils/contactInfo';

const settingsStore = useSettingsStore();
const { t, locale } = useI18n();

// SEO <head> for this page is fully covered by PublicLayout's route defaults
// (localized title/description/OG matching the server), so no page-level code
// is needed here.

// The page shell's values first, the API's once they land.
const settings = computed(() => ({
    ...(window.systemData?.settings || {}),
    ...(settingsStore.data || {}),
}));
const isEn = computed(() => locale.value === 'en');

const phoneHref = computed(() => telHref(settings.value.contact_phone));
const phoneLabel = computed(() => formatPhone(settings.value.contact_phone));
const whatsappUrl = computed(() => whatsappHref(settings.value.contact_whatsapp, t('whatsapp_default_msg')));
const branches = computed(() => contactBranches(settings.value, isEn.value));
const legacyAddress = computed(() => addressLines(settings.value, isEn.value));
const workingHours = computed(() => (isEn.value
    ? (settings.value.working_hours_en || settings.value.working_hours)
    : settings.value.working_hours) || '');

// Only networks the shop has; the old page linked "#" for the rest.
const socials = computed(() => [
    { key: 'facebook', icon: 'fab fa-facebook-f', label: 'Facebook', url: settings.value.contact_facebook || settings.value.facebook },
    { key: 'instagram', icon: 'fab fa-instagram', label: 'Instagram', url: settings.value.contact_instagram || settings.value.instagram },
    { key: 'whatsapp', icon: 'fab fa-whatsapp', label: 'WhatsApp', url: whatsappHref(settings.value.contact_whatsapp) },
].filter((social) => social.url));

const subjects = computed(() => [
    { value: 'inquiry', label: t('subject_general') },
    { value: 'order', label: t('subject_order') },
    { value: 'support', label: t('subject_support') },
    { value: 'partnership', label: t('subject_partnership') },
    { value: 'other', label: t('subject_other') },
]);

/* ------------------------------------------------------------------ *
 * Form
 * ------------------------------------------------------------------ */
const blank = () => ({ name: '', email: '', phone: '', subject: '', message: '' });
const form = reactive(blank());
const fieldErrors = reactive({});
const submitting = ref(false);
const submitted = ref(false);
const error = ref(null);

const clearErrors = () => {
    Object.keys(fieldErrors).forEach((key) => { delete fieldErrors[key]; });
    error.value = null;
};

/** The same rules the server applies, checked before the round trip. */
const validate = () => {
    if (!form.name) fieldErrors.name = t('cp_err_name');
    if (!form.phone || form.phone.replace(/\D/g, '').length < 7) fieldErrors.phone = t('cp_err_phone');
    if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) fieldErrors.email = t('cp_err_email');
    if (!form.subject) fieldErrors.subject = t('cp_err_subject');
    if (!form.message.trim()) fieldErrors.message = t('cp_err_message');
    return !Object.keys(fieldErrors).length;
};

const focusFirstError = () => nextTick(() => {
    document.querySelector('.contact-form [aria-invalid="true"]')?.focus();
});

const submitForm = async () => {
    clearErrors();
    if (!validate()) {
        focusFirstError();
        return;
    }

    submitting.value = true;
    try {
        const res = await axios.post('/api/v1/inquiries', { ...form, message: form.message.trim() });
        if (res.data?.success) {
            submitted.value = true;
        } else {
            error.value = res.data?.message || t('contact_error_sending');
        }
    } catch (err) {
        const serverErrors = err.response?.data?.errors;
        if (serverErrors) {
            Object.entries(serverErrors).forEach(([key, messages]) => {
                fieldErrors[key] = Array.isArray(messages) ? messages[0] : messages;
            });
            focusFirstError();
        } else {
            error.value = err.response?.data?.message || t('contact_error_connection');
        }
    } finally {
        submitting.value = false;
    }
};

const resetForm = () => {
    Object.assign(form, blank());
    clearErrors();
    submitted.value = false;
};

onMounted(() => {
    settingsStore.fetch().catch((err) => console.warn(err));
});
</script>

<style scoped>
.contact-page-view {
    padding-bottom: 3rem;
}

.contact-section {
    padding: 3rem 0 5rem;
}

/* ── Quick actions ── */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr));
    gap: 16px;
    margin-top: 2rem;
}

.quick-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px;
    border-radius: 18px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.07);
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
    color: inherit;
    text-decoration: none;
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.quick-card:hover {
    transform: translateY(-3px);
    border-color: color-mix(in srgb, var(--mobile-primary) 30%, transparent);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
}

.quick-card:focus-visible {
    outline: 3px solid var(--mobile-primary);
    outline-offset: 2px;
}

.quick-icon {
    flex: none;
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #fff;
}

.quick-icon.is-phone { background: var(--mobile-primary); }
.quick-icon.is-whatsapp { background: #16a34a; }
.quick-icon.is-email { background: #0f766e; }

.quick-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.quick-text strong {
    font-size: 1rem;
    color: #0f172a;
}

.quick-text span {
    font-size: 0.88rem;
    color: #64748b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

[dir='rtl'] .quick-text span[dir='ltr'] {
    text-align: end;
}

/* ── Two columns ── */
.contact-wrapper {
    display: grid;
    grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
    gap: 24px;
    align-items: start;
    margin-top: 24px;
}

.contact-card {
    padding: 26px;
    border-radius: 20px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.07);
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
}

.contact-card h3 {
    margin: 0 0 6px;
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
}

.card-lead {
    margin: 0 0 20px;
    font-size: 0.9rem;
    color: #64748b;
}

.contact-side {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.contact-side .contact-card {
    padding: 22px;
}

/* ── Form ── */
.contact-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

.form-group label {
    font-size: 0.88rem;
    font-weight: 700;
    color: #334155;
}

.req {
    color: #dc2626;
}

.optional {
    font-weight: 500;
    color: #94a3b8;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    min-height: 46px;
    padding: 10px 14px;
    border: 1px solid #d7dde5;
    border-radius: 12px;
    background: #fff;
    font: inherit;
    font-size: 0.95rem;
    color: #0f172a;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.form-group textarea {
    resize: vertical;
    min-height: 130px;
}

[dir='rtl'] .form-group input[dir='ltr'] {
    text-align: end;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--mobile-primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--mobile-primary) 18%, transparent);
}

.form-group.has-error input,
.form-group.has-error select,
.form-group.has-error textarea {
    border-color: #dc2626;
}

.field-error {
    margin: 0;
    font-size: 0.8rem;
    color: #dc2626;
}

.field-foot {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.char-count {
    margin-inline-start: auto;
    font-size: 0.75rem;
    color: #94a3b8;
    font-variant-numeric: tabular-nums;
}

.error-box {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 0.9rem;
}

.btn-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 50px;
    border: none;
    border-radius: 12px;
    background: var(--mobile-primary);
    color: #fff;
    font: inherit;
    font-size: 1rem;
    font-weight: 800;
    cursor: pointer;
    transition: filter 0.15s ease;
}

.btn-submit:hover:not(:disabled) {
    filter: brightness(1.1);
}

.btn-submit:disabled {
    opacity: 0.7;
    cursor: progress;
}

.btn-submit:focus-visible,
.btn-secondary:focus-visible {
    outline: 3px solid var(--mobile-primary);
    outline-offset: 2px;
}

.success-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 32px 20px;
    border-radius: 16px;
    background: #ecfdf5;
    color: #065f46;
    text-align: center;
}

.success-box > i {
    font-size: 2.4rem;
    margin-bottom: 4px;
}

.success-box p {
    margin: 0 0 12px;
    font-size: 0.92rem;
}

.btn-secondary {
    min-height: 42px;
    padding: 0 18px;
    border: 1px solid currentColor;
    border-radius: 10px;
    background: transparent;
    color: inherit;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}

/* ── Branches ── */
.branch-list {
    display: flex;
    flex-direction: column;
    gap: 18px;
    margin: 14px 0 0;
    padding: 0;
    list-style: none;
}

.branch {
    display: flex;
    gap: 12px;
}

.branch-pin {
    flex: none;
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--mobile-primary) 10%, transparent);
    color: var(--mobile-primary);
}

.branch-body {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
    font-style: normal;
}

.branch-body strong {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    color: #0f172a;
}

.branch-badge {
    padding: 1px 8px;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
    font-size: 0.7rem;
    font-weight: 700;
}

.branch-body address,
.branch-body > span {
    font-style: normal;
    font-size: 0.9rem;
    line-height: 1.6;
    color: #475569;
}

.branch-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 6px;
}

.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 34px;
    padding: 0 12px;
    border-radius: 999px;
    border: 1px solid rgba(15, 23, 42, 0.1);
    color: #334155;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
}

.chip:hover {
    border-color: var(--mobile-primary);
    color: var(--mobile-primary);
}

.hours-card {
    display: flex;
    gap: 12px;
}

.hours-card h3 {
    font-size: 1rem;
}

.hours-card p {
    margin: 0;
    font-size: 0.9rem;
    color: #475569;
}

.social-icons {
    display: flex;
    gap: 10px;
    margin-top: 12px;
}

.social-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.1rem;
    text-decoration: none;
    transition: transform 0.2s ease;
}

.social-icon:hover {
    transform: translateY(-2px);
}

.social-icon.facebook { background: #1877f2; }
.social-icon.instagram { background: linear-gradient(45deg, #f09433, #dc2743, #bc1888); }
.social-icon.whatsapp { background: #16a34a; }

.chip:focus-visible,
.social-icon:focus-visible {
    outline: 2px solid var(--mobile-primary);
    outline-offset: 2px;
}

/* ── Dark ── */
[data-theme="dark"] .quick-card,
[data-theme="dark"] .contact-card {
    background: rgba(30, 41, 59, 0.6);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: none;
}

[data-theme="dark"] .quick-text strong,
[data-theme="dark"] .contact-card h3,
[data-theme="dark"] .branch-body strong {
    color: #f1f5f9;
}

[data-theme="dark"] .quick-text span,
[data-theme="dark"] .card-lead,
[data-theme="dark"] .branch-body address,
[data-theme="dark"] .branch-body > span,
[data-theme="dark"] .hours-card p {
    color: #94a3b8;
}

[data-theme="dark"] .form-group label {
    color: #cbd5e1;
}

[data-theme="dark"] .form-group input,
[data-theme="dark"] .form-group select,
[data-theme="dark"] .form-group textarea {
    background: #0f172a;
    border-color: rgba(255, 255, 255, 0.12);
    color: #f1f5f9;
}

[data-theme="dark"] .chip {
    border-color: rgba(255, 255, 255, 0.15);
    color: #e2e8f0;
}

[data-theme="dark"] .branch-pin {
    background: rgba(255, 255, 255, 0.08);
    color: #e2e8f0;
}

[data-theme="dark"] .success-box {
    background: rgba(16, 185, 129, 0.12);
    color: #6ee7b7;
}

[data-theme="dark"] .error-box {
    background: rgba(239, 68, 68, 0.12);
    color: #fca5a5;
}

/* ── Responsive ── */
@media (max-width: 992px) {
    .contact-wrapper {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .contact-section {
        padding: 2rem 0 3rem;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .contact-card {
        padding: 20px 16px;
    }

    .quick-card {
        padding: 14px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .quick-card,
    .social-icon {
        transition: none;
    }

    .quick-card:hover,
    .social-icon:hover {
        transform: none;
    }
}
</style>
