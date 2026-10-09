<template>
  <div class="notification-preferences-page">
    <AdminPageHeader :title="t('notifications.preferences')">
      <template #actions>
        <el-button :icon="Refresh" @click="loadPreferences" :loading="loading">
          {{ t('common.refresh') || 'تحديث' }}
        </el-button>
        <el-button :icon="Check" type="primary" @click="saveAllPreferences" :loading="saving">
          {{ t('common.save_all') || 'حفظ الكل' }}
        </el-button>
      </template>
    </AdminPageHeader>

    <!-- Global App Sound & Browser Alerts Control Card -->
    <el-card shadow="never" class="global-controls-card">
      <div class="global-controls-grid">
        <!-- Sound Chime Control -->
        <div class="control-box">
          <div class="control-icon icon-audio">
            <el-icon :size="24"><component :is="notificationsStore.soundEnabled ? BellFilled : MuteNotification" /></el-icon>
          </div>
          <div class="control-info">
            <h4>{{ t('notifications.audio_chime') || 'التنبيه الصوتي للنظام' }}</h4>
            <p>{{ t('notifications.audio_chime_desc') || 'تشغيل نغمة صوتية خفيفة عند وصول تنبيه أو إشعار جديد أثناء تصفح النظام.' }}</p>
          </div>
          <div class="control-actions">
            <el-button size="small" :icon="Bell" @click="testSound" class="mr-2">
              {{ t('notifications.test_sound') || 'تجربة الصوت' }}
            </el-button>
            <el-switch
              v-model="notificationsStore.soundEnabled"
              @change="handleSoundToggle"
              active-color="#10b981"
            />
          </div>
        </div>

        <!-- Browser Push Notification Permission -->
        <div class="control-box">
          <div class="control-icon icon-push">
            <el-icon :size="24"><Notification /></el-icon>
          </div>
          <div class="control-info">
            <h4>{{ t('notifications.browser_push') || 'إشعارات سطح المكتب للمتصفح' }}</h4>
            <p>
              {{ t('notifications.browser_push_desc') || 'استلام تنبيهات فورية على الشاشة حتى عند تصغير نافذة المتصفح.' }}
              <span class="permission-status" :class="browserPermission">
                ({{ formatPermissionStatus(browserPermission) }})
              </span>
            </p>
          </div>
          <div class="control-actions">
            <el-button
              v-if="browserPermission !== 'granted'"
              size="small"
              type="primary"
              @click="requestBrowserPermission"
            >
              {{ t('notifications.request_permission') || 'تفعيل الإذن' }}
            </el-button>
            <el-button
              v-else
              size="small"
              type="success"
              plain
              @click="sendTestBrowserNotification"
            >
              {{ t('notifications.test_notification') || 'إشعار تجريبي' }}
            </el-button>
          </div>
        </div>
      </div>
    </el-card>

    <!-- Channels Global Quick Toggles -->
    <div class="quick-toggles-bar">
      <span class="quick-title">{{ t('notifications.quick_toggles') || 'تحكم سريع بالقنوات لجميع الفئات:' }}</span>
      <div class="quick-btns">
        <el-button size="small" @click="toggleAllChannel('in_app_enabled', true)">
          {{ t('notifications.enable_all_in_app') || 'تفعيل التنبيهات الداخلية' }}
        </el-button>
        <el-button size="small" @click="toggleAllChannel('email_enabled', true)">
          {{ t('notifications.enable_all_email') || 'تفعيل البريد' }}
        </el-button>
        <el-button size="small" @click="toggleAllChannel('push_enabled', true)">
          {{ t('notifications.enable_all_push') || 'تفعيل إشعارات الدفع' }}
        </el-button>
        <el-button size="small" type="danger" plain @click="disableAllChannels">
          {{ t('notifications.disable_all') || 'تعطيل الكل' }}
        </el-button>
      </div>
    </div>

    <!-- Category Preferences Cards -->
    <div v-loading="loading" class="preferences-cards-grid">
      <el-card
        v-for="pref in preferences"
        :key="pref.notification_type"
        shadow="hover"
        class="pref-category-card"
        :class="`type-${pref.notification_type}`"
      >
        <div class="pref-card-header">
          <div class="pref-card-heading">
            <div class="category-icon" :class="`icon-${pref.notification_type}`">
              <el-icon :size="22"><component :is="getCategoryIcon(pref.notification_type)" /></el-icon>
            </div>
            <div class="category-text">
              <h3 class="category-title">{{ getCategoryTitle(pref.notification_type) }}</h3>
              <p class="category-desc">{{ getCategoryDescription(pref.notification_type) }}</p>
            </div>
          </div>
        </div>

        <el-divider class="pref-divider" />

        <div class="channels-grid">
          <!-- In-App Feed -->
          <div class="channel-row">
            <div class="channel-info">
              <span class="channel-name">{{ t('notifications.in_app') }}</span>
              <span class="channel-hint">{{ t('notifications.in_app_hint') || 'ظهور في جرس الإشعارات ولوحة التحكم' }}</span>
            </div>
            <el-switch
              v-model="pref.in_app_enabled"
              @change="autoSavePreference(pref)"
              active-color="#3b82f6"
            />
          </div>

          <!-- Email -->
          <div class="channel-row">
            <div class="channel-info">
              <span class="channel-name">{{ t('notifications.email') }}</span>
              <span class="channel-hint">{{ t('notifications.email_hint') || 'إرسال ملخص عبر البريد الإلكتروني المعتمد' }}</span>
            </div>
            <el-switch
              v-model="pref.email_enabled"
              @change="autoSavePreference(pref)"
              active-color="#10b981"
            />
          </div>

          <!-- Push -->
          <div class="channel-row">
            <div class="channel-info">
              <span class="channel-name">{{ t('notifications.push') }}</span>
              <span class="channel-hint">{{ t('notifications.push_hint') || 'إشعار منبثق فوري للمتصفح' }}</span>
            </div>
            <el-switch
              v-model="pref.push_enabled"
              @change="autoSavePreference(pref)"
              active-color="#8b5cf6"
            />
          </div>

          <!-- SMS -->
          <div class="channel-row">
            <div class="channel-info">
              <span class="channel-name">{{ t('notifications.sms') }}</span>
              <span class="channel-hint">{{ t('notifications.sms_hint') || 'رسالة هاتفية قصيرة SMS (للطوارئ)' }}</span>
            </div>
            <el-switch
              v-model="pref.sms_enabled"
              @change="autoSavePreference(pref)"
              active-color="#f59e0b"
            />
          </div>
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { ref, onMounted } from 'vue';
import {
  Setting, Check, Refresh, Bell, BellFilled, MuteNotification,
  ShoppingCart, Box, Money, Notification
} from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import notificationsService from '@/services/notifications';
import { useNotificationsStore } from '@/stores/notifications';

const { t } = useI18n();
const notificationsStore = useNotificationsStore();

const loading = ref(false);
const saving = ref(false);
const browserPermission = ref(typeof Notification !== 'undefined' ? Notification.permission : 'default');

const defaultTypes = ['order', 'inventory', 'warehouse', 'financial', 'system'];

const preferences = ref(
  defaultTypes.map((type) => ({
    notification_type: type,
    email_enabled: true,
    sms_enabled: false,
    push_enabled: true,
    in_app_enabled: true,
  }))
);

const loadPreferences = async () => {
  loading.value = true;
  try {
    const res = await notificationsService.getPreferences();
    const rows = res.data || [];
    if (Array.isArray(rows) && rows.length > 0) {
      preferences.value = defaultTypes.map((type) => {
        const found = rows.find((r) => r.notification_type === type);
        return {
          notification_type: type,
          email_enabled: found ? Boolean(found.email_enabled) : true,
          sms_enabled: found ? Boolean(found.sms_enabled) : false,
          push_enabled: found ? Boolean(found.push_enabled) : true,
          in_app_enabled: found ? Boolean(found.in_app_enabled) : true,
        };
      });
    }
  } catch (error) {
    ElMessage.error(t('common.load_error'));
  } finally {
    loading.value = false;
  }
};

const autoSavePreference = async (pref) => {
  try {
    await notificationsService.updatePreferences({
      notification_type: pref.notification_type,
      email_enabled: pref.email_enabled,
      sms_enabled: pref.sms_enabled,
      push_enabled: pref.push_enabled,
      in_app_enabled: pref.in_app_enabled,
    });
    ElMessage.success(t('notifications.pref_saved') || 'تم حفظ التفضيل تلقائياً');
  } catch (error) {
    ElMessage.error(t('common.save_error'));
  }
};

const saveAllPreferences = async () => {
  saving.value = true;
  try {
    await notificationsService.updatePreferences({
      preferences: preferences.value,
    });
    ElMessage.success(t('common.update_success'));
  } catch (error) {
    ElMessage.error(t('common.save_error'));
  } finally {
    saving.value = false;
  }
};

const toggleAllChannel = (channelKey, value) => {
  preferences.value.forEach((pref) => {
    pref[channelKey] = value;
  });
  saveAllPreferences();
};

const disableAllChannels = () => {
  preferences.value.forEach((pref) => {
    pref.email_enabled = false;
    pref.sms_enabled = false;
    pref.push_enabled = false;
    pref.in_app_enabled = false;
  });
  saveAllPreferences();
};

// Sound Controls
const handleSoundToggle = (val) => {
  localStorage.setItem('notification_sound_enabled', String(val));
  if (val) {
    notificationsStore.playTestSound();
  }
};

const testSound = () => {
  notificationsStore.playTestSound();
  ElMessage.info(t('notifications.sound_tested') || 'تم تشغيل نغمة التنبيه التجريبية');
};

// Browser Push Notification Controls
const requestBrowserPermission = async () => {
  if (typeof Notification === 'undefined') {
    ElMessage.warning('متصفحك لا يدعم إشعارات سطح المكتب');
    return;
  }
  const result = await Notification.requestPermission();
  browserPermission.value = result;
  if (result === 'granted') {
    ElMessage.success(t('notifications.permission_granted') || 'تم منح إذن الإشعارات بنجاح!');
    sendTestBrowserNotification();
  } else {
    ElMessage.warning(t('notifications.permission_denied') || 'تم رفض الإذن من إعدادات المتصفح');
  }
};

const sendTestBrowserNotification = () => {
  if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
    new Notification('أوان التقدم - إشعار تجريبي', {
      body: 'تم ضبط إعدادات التنبيهات بنجاح.',
      icon: '/favicon.ico',
    });
  }
};

const formatPermissionStatus = (perm) => {
  if (perm === 'granted') return t('notifications.perm_granted') || 'مفعل';
  if (perm === 'denied') return t('notifications.perm_denied') || 'محظور';
  return t('notifications.perm_default') || 'غير مفعل';
};

// Categories Info
const getCategoryIcon = (type) => {
  switch (type) {
    case 'order': return ShoppingCart;
    case 'inventory': return Box;
    case 'warehouse': return Box;
    case 'financial': return Money;
    default: return Setting;
  }
};

const getCategoryTitle = (type) => {
  switch (type) {
    case 'order': return t('notifications.orders_sales_title') || 'الطلبات وفواتير المبيعات';
    case 'inventory': return t('notifications.inventory_stock_title') || 'المخزون والمنتجات';
    case 'warehouse': return t('notifications.warehouse_title') || 'المستودعات والحركات';
    case 'financial': return t('notifications.financial_title') || 'المالية والمدفوعات';
    default: return t('notifications.system_security_title') || 'أمان ورسائل النظام';
  }
};

const getCategoryDescription = (type) => {
  switch (type) {
    case 'order': return t('notifications.orders_sales_desc') || 'إشعارات تأكيد طلبات البيع، تغير حالات الطلبات، وإصدار الفواتير.';
    case 'inventory': return t('notifications.inventory_stock_desc') || 'تنبيهات نقص المخزون، المنتجات النافذة، والوصول لحد إعادة الطلب.';
    case 'warehouse': return t('notifications.warehouse_desc') || 'أوامر استلام البضائع، حركات التحويل الداخلي، ومواعيد الجرد الدوري.';
    case 'financial': return t('notifications.financial_desc') || 'تسجيل المدفوعات، الفواتير المتأخرة، وتنبيهات الأرصدة.';
    default: return t('notifications.system_security_desc') || 'تنبيهات النسخ الاحتياطي، الدخول للنظام، والصيانة والتحديثات.';
  }
};

onMounted(() => {
  loadPreferences();
});
</script>

<style scoped>
.notification-preferences-page {
  padding: 1.5rem;
}

.global-controls-card {
  border-radius: 14px;
  margin-bottom: 1.5rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.global-controls-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.5rem;
}

.control-box {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem;
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.control-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
}

.icon-audio {
  background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.icon-push {
  background: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%);
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
}

.control-info {
  flex: 1;
}

.control-info h4 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #0f172a;
}

.control-info p {
  margin: 0.25rem 0 0 0;
  font-size: 0.78rem;
  color: #64748b;
  line-height: 1.35;
}

.permission-status.granted { color: #10b981; font-weight: 700; }
.permission-status.denied { color: #ef4444; font-weight: 700; }
.permission-status.default { color: #f59e0b; }

.control-actions {
  display: flex;
  align-items: center;
}

.quick-toggles-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.85rem 1.25rem;
  background: #ffffff;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  margin-bottom: 1.5rem;
}

.quick-title {
  font-size: 0.84rem;
  font-weight: 600;
  color: #475569;
}

.quick-btns {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.preferences-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 1.25rem;
}

.pref-category-card {
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  transition: all 0.25s ease;
}

.pref-category-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}

.pref-card-heading {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
}

.category-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
}

.icon-order { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
.icon-inventory { background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%); }
.icon-warehouse { background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); }
.icon-financial { background: linear-gradient(135deg, #10b981 0%, #047857 100%); }
.icon-system { background: linear-gradient(135deg, #64748b 0%, #334155 100%); }

.category-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
}

.category-desc {
  margin: 0.25rem 0 0 0;
  font-size: 0.77rem;
  color: #64748b;
  line-height: 1.35;
}

.pref-divider {
  margin: 1rem 0;
}

.channels-grid {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.channel-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.4rem 0.5rem;
  border-radius: 8px;
  transition: background 0.15s ease;
}

.channel-row:hover {
  background: #f8fafc;
}

.channel-info {
  display: flex;
  flex-direction: column;
}

.channel-name {
  font-size: 0.85rem;
  font-weight: 600;
  color: #1e293b;
}

.channel-hint {
  font-size: 0.73rem;
  color: #94a3b8;
}
</style>
