<template>
    <div class="profile-page">
        <!-- Identity -->
        <section class="profile-hero">
            <div class="hero-banner"></div>
            <div class="hero-body">
                <div class="hero-avatar" aria-hidden="true">{{ initials }}</div>
                <div class="hero-identity">
                    <h1 class="hero-name">{{ user?.name || '—' }}</h1>
                    <div class="hero-meta">
                        <span class="meta-item" dir="ltr">
                            <el-icon><Message /></el-icon>{{ user?.email || '—' }}
                        </span>
                        <span v-if="user?.phone" class="meta-item" dir="ltr">
                            <el-icon><Phone /></el-icon>{{ user.phone }}
                        </span>
                        <span v-if="memberSince" class="meta-item">
                            <el-icon><Calendar /></el-icon>{{ t('profile_member_since', { date: memberSince }) }}
                        </span>
                    </div>
                </div>
                <div class="hero-badges">
                    <el-tag v-if="roleLabel" effect="dark" round class="role-tag">
                        <el-icon><Key /></el-icon>{{ roleLabel }}
                    </el-tag>
                    <el-tag :type="user?.is_email_verified ? 'success' : 'info'" effect="plain" round>
                        <el-icon><component :is="user?.is_email_verified ? CircleCheck : Warning" /></el-icon>
                        {{ user?.is_email_verified ? t('profile_email_verified') : t('profile_email_unverified') }}
                    </el-tag>
                </div>
            </div>
        </section>

        <div class="profile-grid">
            <!-- Personal information -->
            <section class="panel">
                <header class="panel-header">
                    <div class="panel-icon"><el-icon><User /></el-icon></div>
                    <div>
                        <h2>{{ t('profile_personal_info') }}</h2>
                        <p>{{ t('profile_personal_info_hint') }}</p>
                    </div>
                </header>

                <el-form
                    ref="infoFormRef"
                    :model="info"
                    :rules="infoRules"
                    label-position="top"
                    class="panel-body"
                    @submit.prevent="saveInfo"
                >
                    <el-form-item :label="t('full_name')" prop="name" :error="infoErrors.name">
                        <el-input v-model="info.name" :prefix-icon="User" maxlength="255" autocomplete="name" />
                    </el-form-item>
                    <el-form-item :label="t('profile_email')" prop="email" :error="infoErrors.email">
                        <el-input v-model="info.email" :prefix-icon="Message" type="email" dir="ltr" autocomplete="email" />
                    </el-form-item>
                    <el-form-item :label="t('phone_number')" prop="phone" :error="infoErrors.phone">
                        <el-input v-model="info.phone" :prefix-icon="Phone" dir="ltr" maxlength="20" autocomplete="tel" :placeholder="t('profile_optional')" />
                    </el-form-item>

                    <footer class="panel-actions">
                        <transition name="fade">
                            <span v-if="infoDirty" class="dirty-hint">
                                <span class="dirty-dot"></span>{{ t('profile_unsaved_changes') }}
                            </span>
                        </transition>
                        <el-button :disabled="!infoDirty || savingInfo" @click="resetInfo">{{ t('profile_discard') }}</el-button>
                        <el-button type="primary" native-type="submit" :loading="savingInfo" :disabled="!infoDirty">
                            {{ t('save_changes') }}
                        </el-button>
                    </footer>
                </el-form>
            </section>

            <!-- Password -->
            <section class="panel">
                <header class="panel-header">
                    <div class="panel-icon warn"><el-icon><Lock /></el-icon></div>
                    <div>
                        <h2>{{ t('profile_change_password') }}</h2>
                        <p>{{ t('profile_change_password_hint') }}</p>
                    </div>
                </header>

                <el-form
                    ref="passwordFormRef"
                    :model="password"
                    label-position="top"
                    class="panel-body"
                    @submit.prevent="savePassword"
                >
                    <el-form-item :label="t('profile_current_password')" :error="passwordErrors.current_password">
                        <el-input v-model="password.current_password" type="password" show-password dir="ltr" autocomplete="current-password" />
                    </el-form-item>
                    <el-form-item :label="t('profile_new_password')" :error="passwordErrors.password">
                        <el-input v-model="password.password" type="password" show-password dir="ltr" autocomplete="new-password" />
                    </el-form-item>

                    <div v-if="password.password" class="strength" :class="`level-${strength.level}`">
                        <div class="strength-bars">
                            <span v-for="n in 4" :key="n" :class="{ on: n <= strength.level }"></span>
                        </div>
                        <span class="strength-label">{{ strength.label }}</span>
                    </div>

                    <el-form-item :label="t('confirm_password')" :error="passwordErrors.password_confirmation">
                        <el-input v-model="password.password_confirmation" type="password" show-password dir="ltr" autocomplete="new-password" />
                    </el-form-item>

                    <ul class="checklist">
                        <li v-for="rule in passwordChecks" :key="rule.key" :class="{ ok: rule.ok }">
                            <el-icon><component :is="rule.ok ? CircleCheck : CircleClose" /></el-icon>
                            {{ rule.label }}
                        </li>
                    </ul>

                    <footer class="panel-actions">
                        <el-button type="primary" native-type="submit" :loading="savingPassword" :disabled="!passwordValid">
                            {{ t('profile_update_password') }}
                        </el-button>
                    </footer>
                </el-form>
            </section>
        </div>

        <!-- Devices -->
        <section class="panel">
            <header class="panel-header">
                <div class="panel-icon info"><el-icon><Monitor /></el-icon></div>
                <div>
                    <h2>{{ t('profile_sessions') }}</h2>
                    <p>{{ t('profile_sessions_hint') }}</p>
                </div>
                <el-button
                    class="panel-header-action"
                    type="danger"
                    plain
                    :icon="SwitchButton"
                    :disabled="otherSessions.length === 0"
                    :loading="revokingAll"
                    @click="revokeOthers"
                >
                    {{ t('profile_sign_out_others') }}
                </el-button>
            </header>

            <div class="panel-body" v-loading="loadingSessions">
                <ul v-if="sessions.length" class="session-list">
                    <li v-for="session in sessions" :key="session.id" class="session" :class="{ current: session.is_current }">
                        <div class="session-icon">
                            <el-icon><component :is="session.mobile ? Iphone : Monitor" /></el-icon>
                        </div>
                        <div class="session-info">
                            <div class="session-title">
                                {{ session.label }}
                                <el-tag v-if="session.is_current" type="success" size="small" round>{{ t('profile_this_device') }}</el-tag>
                            </div>
                            <div class="session-sub">
                                {{ t('profile_last_active') }}: {{ relativeTime(session.last_used_at || session.created_at) }}
                                <span class="sep">·</span>
                                {{ t('profile_signed_in') }}: {{ formatDate(session.created_at) }}
                            </div>
                        </div>
                        <el-button
                            v-if="!session.is_current"
                            text
                            type="danger"
                            :loading="revokingId === session.id"
                            @click="revokeOne(session)"
                        >
                            {{ t('profile_sign_out') }}
                        </el-button>
                    </li>
                </ul>
                <el-empty v-else-if="!loadingSessions" :image-size="70" :description="t('profile_no_sessions')" />
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    User, Message, Phone, Calendar, Key, Lock, Monitor, Iphone,
    CircleCheck, CircleClose, Warning, SwitchButton
} from '@element-plus/icons-vue';
import { useAuthStore } from '@/stores/auth';
import * as authApi from '@/api/auth';

const { t, locale } = useI18n();
const authStore = useAuthStore();
const user = computed(() => authStore.user);

const initials = computed(() => {
    const parts = (user.value?.name || '').trim().split(/\s+/).filter(Boolean);
    const first = (parts[0] || '')[0] || '';
    const second = parts[1]?.[0] || (parts[0] || '')[1] || '';
    return (first + second).toUpperCase() || '؟';
});

const roleLabel = computed(() => user.value?.role?.display_name || user.value?.role?.name || (user.value?.is_admin ? t('profile_admin') : ''));

const formatDate = (value) => {
    if (!value) return '—';
    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SY' : 'en-GB', { dateStyle: 'medium' }).format(new Date(value));
};

const memberSince = computed(() => (user.value?.created_at ? formatDate(user.value.created_at) : ''));

const relativeTime = (value) => {
    if (!value) return '—';
    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
    if (Math.abs(seconds) < 60) return t('profile_just_now');
    const units = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];
    const rtf = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' });
    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return rtf.format(Math.round(seconds / size), unit);
        }
    }
    return t('profile_just_now');
};

// Laravel returns field errors as { field: [message] }; keep the first.
const firstErrors = (errors = {}) => Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages])
);

/* ---- Personal information ---- */

const infoFormRef = ref(null);
const info = reactive({ name: '', email: '', phone: '' });
const infoErrors = ref({});
const savingInfo = ref(false);

const fillInfo = () => {
    info.name = user.value?.name || '';
    info.email = user.value?.email || '';
    info.phone = user.value?.phone || '';
    infoErrors.value = {};
};

watch(user, (value, previous) => {
    // Refill only when the account itself arrives or changes, never over edits.
    if (value && value.id !== previous?.id) fillInfo();
}, { immediate: true });

const infoDirty = computed(() => (
    info.name.trim() !== (user.value?.name || '')
    || info.email.trim() !== (user.value?.email || '')
    || info.phone.trim() !== (user.value?.phone || '')
));

const infoRules = computed(() => ({
    name: [{ required: true, whitespace: true, message: t('profile_name_required'), trigger: 'blur' }],
    email: [
        { required: true, message: t('profile_email_required'), trigger: 'blur' },
        { type: 'email', message: t('profile_email_invalid'), trigger: 'blur' },
    ],
}));

const resetInfo = () => {
    fillInfo();
    infoFormRef.value?.clearValidate();
};

watch(() => [info.name, info.email, info.phone], () => {
    infoErrors.value = {};
});

const saveInfo = async () => {
    if (!infoDirty.value || savingInfo.value) return;
    try {
        await infoFormRef.value.validate();
    } catch {
        return;
    }

    savingInfo.value = true;
    try {
        const res = await authApi.updateProfile({
            name: info.name.trim(),
            email: info.email.trim(),
            phone: info.phone.trim() || null,
        });
        const updated = res.data?.data?.user;
        if (updated) authStore.user = updated;
        fillInfo();
        ElMessage.success(res.data?.message || t('profile_saved'));
    } catch (error) {
        infoErrors.value = firstErrors(error.response?.data?.errors);
        ElMessage.error(error.response?.data?.message || t('profile_save_failed'));
    } finally {
        savingInfo.value = false;
    }
};

/* ---- Password ---- */

const passwordFormRef = ref(null);
const password = reactive({ current_password: '', password: '', password_confirmation: '' });
const passwordErrors = ref({});
const savingPassword = ref(false);

watch(() => ({ ...password }), () => {
    passwordErrors.value = {};
});

const strength = computed(() => {
    const value = password.password;
    let score = 0;
    if (value.length >= 8) score++;
    if (value.length >= 12) score++;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;
    const level = value.length < 8 ? 1 : Math.min(4, Math.max(1, score - 1));
    const labels = { 1: t('profile_strength_weak'), 2: t('profile_strength_fair'), 3: t('profile_strength_good'), 4: t('profile_strength_strong') };
    return { level, label: labels[level] };
});

const passwordChecks = computed(() => [
    { key: 'length', ok: password.password.length >= 8, label: t('profile_rule_length') },
    { key: 'different', ok: !!password.password && password.password !== password.current_password, label: t('profile_rule_different') },
    { key: 'match', ok: !!password.password && password.password === password.password_confirmation, label: t('profile_rule_match') },
]);

const passwordValid = computed(() => !!password.current_password && passwordChecks.value.every((rule) => rule.ok));

const passwordDirty = computed(() => !!(password.current_password || password.password || password.password_confirmation));

const savePassword = async () => {
    if (!passwordValid.value || savingPassword.value) return;
    savingPassword.value = true;
    try {
        const res = await authApi.changePassword({ ...password });
        password.current_password = '';
        password.password = '';
        password.password_confirmation = '';
        ElMessage.success(res.data?.message || t('profile_password_changed'));
        loadSessions();
    } catch (error) {
        passwordErrors.value = firstErrors(error.response?.data?.errors);
        ElMessage.error(error.response?.data?.message || t('profile_save_failed'));
    } finally {
        savingPassword.value = false;
    }
};

/* ---- Devices ---- */

const sessions = ref([]);
const loadingSessions = ref(false);
const revokingId = ref(null);
const revokingAll = ref(false);

// Tokens are named after the User-Agent they were issued to.
const describeDevice = (agent = '') => {
    const browser = /Edg\//.test(agent) ? 'Edge'
        : /OPR\/|Opera/.test(agent) ? 'Opera'
        : /Firefox\//.test(agent) ? 'Firefox'
        : /Chrome\//.test(agent) ? 'Chrome'
        : /Safari\//.test(agent) ? 'Safari'
        : /Dart|okhttp|Expo|CFNetwork/i.test(agent) ? t('profile_mobile_app')
        : null;
    const os = /Windows/.test(agent) ? 'Windows'
        : /Android/.test(agent) ? 'Android'
        : /iPhone|iPad|iOS/.test(agent) ? 'iOS'
        : /Mac OS X|Macintosh/.test(agent) ? 'macOS'
        : /Linux/.test(agent) ? 'Linux'
        : null;
    const label = [browser, os].filter(Boolean).join(` ${t('profile_on')} `);
    return {
        label: label || agent.slice(0, 60) || t('profile_unknown_device'),
        mobile: /Android|iPhone|iPad|Mobile|Dart|okhttp/i.test(agent),
    };
};

const otherSessions = computed(() => sessions.value.filter((session) => !session.is_current));

const loadSessions = async () => {
    loadingSessions.value = true;
    try {
        const res = await authApi.fetchSessions();
        sessions.value = (res.data?.data?.sessions || []).map((session) => ({ ...session, ...describeDevice(session.device) }));
    } catch {
        ElMessage.error(t('profile_sessions_failed'));
    } finally {
        loadingSessions.value = false;
    }
};

const revokeOne = async (session) => {
    try {
        await ElMessageBox.confirm(
            t('profile_sign_out_confirm', { device: session.label }),
            t('profile_sign_out'),
            { type: 'warning', confirmButtonText: t('profile_sign_out'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }
    revokingId.value = session.id;
    try {
        await authApi.revokeSession(session.id);
        sessions.value = sessions.value.filter((item) => item.id !== session.id);
        ElMessage.success(t('profile_signed_out'));
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('profile_save_failed'));
    } finally {
        revokingId.value = null;
    }
};

const revokeOthers = async () => {
    try {
        await ElMessageBox.confirm(
            t('profile_sign_out_others_confirm', { count: otherSessions.value.length }),
            t('profile_sign_out_others'),
            { type: 'warning', confirmButtonText: t('profile_sign_out_others'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }
    revokingAll.value = true;
    try {
        await authApi.revokeOtherSessions();
        sessions.value = sessions.value.filter((session) => session.is_current);
        ElMessage.success(t('profile_signed_out_others'));
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('profile_save_failed'));
    } finally {
        revokingAll.value = false;
    }
};

/* ---- Unsaved changes guard ---- */

const hasUnsaved = computed(() => infoDirty.value || passwordDirty.value);

onBeforeRouteLeave(async () => {
    if (!hasUnsaved.value) return true;
    try {
        await ElMessageBox.confirm(t('profile_leave_confirm'), t('profile_unsaved_changes'), {
            type: 'warning',
            confirmButtonText: t('profile_discard'),
            cancelButtonText: t('cancel'),
        });
        return true;
    } catch {
        return false;
    }
});

const onBeforeUnload = (event) => {
    if (hasUnsaved.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};

onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload);
    if (!authStore.user) authStore.fetchUser().catch(() => {});
    loadSessions();
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', onBeforeUnload);
});
</script>

<style scoped>
.profile-page {
    --pf-accent: #0d9488;
    --pf-accent-2: #0891b2;
    --pf-surface: #fff;
    --pf-border: var(--border-color, #e5e7eb);
    --pf-text: var(--text-dark, #1f2937);
    --pf-muted: var(--text-muted, #6b7280);

    max-width: 1180px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ---- Hero ---- */
.profile-hero {
    position: relative;
    border-radius: 18px;
    overflow: hidden;
    background: var(--pf-surface);
    border: 1px solid var(--pf-border);
    box-shadow: 0 10px 30px -18px rgba(15, 23, 42, 0.35);
}

.hero-banner {
    height: 112px;
    background:
        radial-gradient(60% 120% at 85% 0%, rgba(103, 232, 249, 0.35), transparent 60%),
        radial-gradient(50% 120% at 10% 100%, rgba(129, 140, 248, 0.3), transparent 60%),
        linear-gradient(120deg, #0a0f1e 0%, #0f2a3a 55%, #0d4f4a 100%);
}

.hero-body {
    display: flex;
    align-items: flex-end;
    gap: 1.25rem;
    padding: 0 1.75rem 1.5rem;
    margin-top: -46px;
    flex-wrap: wrap;
}

.hero-avatar {
    flex-shrink: 0;
    width: 96px;
    height: 96px;
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 800;
    color: #062a24;
    background: linear-gradient(135deg, #2dd4bf 0%, #67e8f9 100%);
    border: 4px solid var(--pf-surface);
    box-shadow: 0 12px 28px -10px rgba(13, 148, 136, 0.6);
}

.hero-identity {
    flex: 1;
    min-width: 220px;
    padding-bottom: 0.2rem;
}

.hero-name {
    margin: 0 0 0.45rem;
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--pf-text);
    line-height: 1.2;
}

.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem 1.1rem;
    color: var(--pf-muted);
    font-size: 0.87rem;
}

.meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.hero-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding-bottom: 0.3rem;
}

.hero-badges :deep(.el-tag) {
    gap: 0.3rem;
    font-weight: 600;
}

.hero-badges :deep(.el-tag__content) {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.role-tag {
    --el-tag-bg-color: var(--pf-accent);
    --el-tag-border-color: var(--pf-accent);
}

/* ---- Panels ---- */
.profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.25rem;
    align-items: start;
}

.panel {
    background: var(--pf-surface);
    border: 1px solid var(--pf-border);
    border-radius: 16px;
    box-shadow: 0 6px 20px -16px rgba(15, 23, 42, 0.35);
}

.panel-header {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1.15rem 1.4rem;
    border-bottom: 1px solid var(--pf-border);
}

.panel-header h2 {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 700;
    color: var(--pf-text);
}

.panel-header p {
    margin: 0.15rem 0 0;
    font-size: 0.8rem;
    color: var(--pf-muted);
}

.panel-header-action {
    margin-inline-start: auto;
}

.panel-icon {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    color: var(--pf-accent);
    background: rgba(13, 148, 136, 0.1);
}

.panel-icon.warn {
    color: #d97706;
    background: rgba(217, 119, 6, 0.1);
}

.panel-icon.info {
    color: #4f46e5;
    background: rgba(79, 70, 229, 0.1);
}

.panel-body {
    padding: 1.25rem 1.4rem 1.4rem;
}

.panel-body :deep(.el-form-item__label) {
    font-weight: 600;
    color: var(--pf-text);
}

.panel-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.6rem;
    margin-top: 0.5rem;
    padding-top: 1rem;
    border-top: 1px dashed var(--pf-border);
}

.dirty-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin-inline-end: auto;
    font-size: 0.8rem;
    color: #b45309;
}

.dirty-dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
}

/* ---- Password strength ---- */
.strength {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: -0.6rem 0 1.1rem;
}

.strength-bars {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 4px;
}

.strength-bars span {
    height: 5px;
    border-radius: 999px;
    background: #e5e7eb;
    transition: background 0.2s ease;
}

.strength-label {
    min-width: 56px;
    font-size: 0.78rem;
    font-weight: 700;
    text-align: end;
}

.level-1 .strength-bars .on { background: #ef4444; }
.level-1 .strength-label { color: #dc2626; }
.level-2 .strength-bars .on { background: #f59e0b; }
.level-2 .strength-label { color: #d97706; }
.level-3 .strength-bars .on { background: #3b82f6; }
.level-3 .strength-label { color: #2563eb; }
.level-4 .strength-bars .on { background: #10b981; }
.level-4 .strength-label { color: #059669; }

.checklist {
    list-style: none;
    margin: 0;
    padding: 0.75rem 0.9rem;
    border-radius: 10px;
    background: var(--bg-light, #f8fafc);
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.checklist li {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.82rem;
    color: var(--pf-muted);
    transition: color 0.2s ease;
}

.checklist li .el-icon {
    color: #cbd5e1;
    transition: color 0.2s ease;
}

.checklist li.ok {
    color: #047857;
}

.checklist li.ok .el-icon {
    color: #10b981;
}

/* ---- Sessions ---- */
.session-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.session {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 0.8rem 1rem;
    border: 1px solid var(--pf-border);
    border-radius: 12px;
    transition: border-color 0.2s ease, background 0.2s ease;
}

.session:hover {
    border-color: #cbd5e1;
}

.session.current {
    border-color: rgba(16, 185, 129, 0.4);
    background: rgba(16, 185, 129, 0.05);
}

.session-icon {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    color: #475569;
    background: #f1f5f9;
}

.session.current .session-icon {
    color: #059669;
    background: rgba(16, 185, 129, 0.12);
}

.session-info {
    flex: 1;
    min-width: 0;
}

.session-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--pf-text);
    overflow: hidden;
    text-overflow: ellipsis;
}

.session-sub {
    margin-top: 0.15rem;
    font-size: 0.78rem;
    color: var(--pf-muted);
}

.session-sub .sep {
    margin: 0 0.35rem;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

@media (max-width: 992px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .hero-body {
        padding: 0 1rem 1.2rem;
        gap: 0.9rem;
    }

    .hero-avatar {
        width: 76px;
        height: 76px;
        font-size: 1.6rem;
        border-radius: 20px;
    }

    .hero-name {
        font-size: 1.25rem;
    }

    .panel-header,
    .panel-body {
        padding-inline: 1rem;
    }

    .panel-header {
        flex-wrap: wrap;
    }

    .panel-header-action {
        width: 100%;
        margin-inline-start: 0;
    }

    .session {
        flex-wrap: wrap;
    }
}
</style>
