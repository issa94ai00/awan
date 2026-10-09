<template>
  <Transition
    :enter-active-class="mode === 'toast' ? 'transition ease-out duration-300' : 'transition ease-out duration-200'"
    :enter-from-class="mode === 'toast' ? 'transform translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2' : 'opacity-0 -translate-y-2'"
    :enter-to-class="mode === 'toast' ? 'transform translate-y-0 opacity-100 sm:translate-x-0' : 'opacity-100 translate-y-0'"
    :leave-active-class="mode === 'toast' ? 'transition ease-in duration-200' : 'transition ease-in duration-150'"
    :leave-from-class="mode === 'toast' ? 'opacity-100' : 'opacity-100 translate-y-0'"
    :leave-to-class="mode === 'toast' ? 'opacity-0 scale-95' : 'opacity-0 -translate-y-2'"
  >
    <div
      v-if="show"
      :class="[
        'custom-alert-box',
        mode === 'toast' ? 'alert-toast ' + positionClasses[position] : 'alert-inline',
        typeClasses[type],
      ]"
      role="alert"
    >
      <!-- Icon Container -->
      <div class="alert-icon-wrap" :class="`icon-${type}`">
        <svg v-if="type === 'success'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <svg v-else-if="type === 'error'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        <svg v-else-if="type === 'warning'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>

      <!-- Content Area -->
      <div class="alert-content">
        <h5 v-if="title" class="alert-title">{{ title }}</h5>
        <div class="alert-message">
          <slot>{{ message }}</slot>
        </div>

        <div v-if="actionText" class="alert-action-row">
          <button
            type="button"
            class="alert-action-btn"
            @click="handleAction"
          >
            {{ actionText }}
          </button>
        </div>
      </div>

      <!-- Dismiss Button -->
      <button
        v-if="dismissible"
        type="button"
        class="alert-close-btn"
        @click="close"
        aria-label="Close"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <!-- Auto-dismiss Progress Bar -->
      <div
        v-if="duration > 0 && mode === 'toast'"
        class="alert-progress-bar"
        :style="{ animationDuration: `${duration}ms` }"
      ></div>
    </div>
  </Transition>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: true,
  },
  type: {
    type: String,
    default: 'info',
    validator: (value) => ['success', 'error', 'warning', 'info'].includes(value),
  },
  title: {
    type: String,
    default: '',
  },
  message: {
    type: String,
    default: '',
  },
  mode: {
    type: String,
    default: 'inline',
    validator: (value) => ['inline', 'toast'].includes(value),
  },
  duration: {
    type: Number,
    default: 0,
  },
  position: {
    type: String,
    default: 'top-right',
    validator: (value) => ['top-right', 'top-left', 'bottom-right', 'bottom-left', 'top-center', 'bottom-center'].includes(value),
  },
  dismissible: {
    type: Boolean,
    default: true,
  },
  actionText: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['close', 'action']);

let timeoutId = null;

const typeClasses = {
  success: 'alert-type-success',
  error: 'alert-type-error',
  warning: 'alert-type-warning',
  info: 'alert-type-info',
};

const positionClasses = {
  'top-right': 'top-5 right-5',
  'top-left': 'top-5 left-5',
  'bottom-right': 'bottom-5 right-5',
  'bottom-left': 'bottom-5 left-5',
  'top-center': 'top-5 left-1/2 -translate-x-1/2',
  'bottom-center': 'bottom-5 left-1/2 -translate-x-1/2',
};

onMounted(() => {
  if (props.duration > 0) {
    timeoutId = setTimeout(() => {
      close();
    }, props.duration);
  }
});

onUnmounted(() => {
  if (timeoutId) {
    clearTimeout(timeoutId);
  }
});

function close() {
  emit('close');
}

function handleAction() {
  emit('action');
}
</script>

<style scoped>
.custom-alert-box {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  padding: 1rem 1.15rem;
  border-radius: 12px;
  overflow: hidden;
  box-sizing: border-box;
}

.alert-toast {
  position: fixed;
  z-index: 9999;
  min-width: 320px;
  max-width: 440px;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15), 0 2px 6px rgba(15, 23, 42, 0.08);
  backdrop-filter: blur(12px);
}

.alert-inline {
  width: 100%;
  margin-bottom: 1rem;
}

/* Color Themes */
.alert-type-success {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #14532d;
}
.alert-type-success .icon-success {
  background: #dcfce7;
  color: #16a34a;
}

.alert-type-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #7f1d1d;
}
.alert-type-error .icon-error {
  background: #fee2e2;
  color: #dc2626;
}

.alert-type-warning {
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #78350f;
}
.alert-type-warning .icon-warning {
  background: #fef3c7;
  color: #d97706;
}

.alert-type-info {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #1e3a8a;
}
.alert-type-info .icon-info {
  background: #dbeafe;
  color: #2563eb;
}

.alert-icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  flex-shrink: 0;
}

.alert-content {
  flex: 1;
  min-width: 0;
}

.alert-title {
  margin: 0 0 0.25rem 0;
  font-size: 0.92rem;
  font-weight: 700;
  line-height: 1.3;
}

.alert-message {
  font-size: 0.85rem;
  line-height: 1.45;
}

.alert-action-row {
  margin-top: 0.5rem;
}

.alert-action-btn {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.65rem;
  border-radius: 6px;
  font-size: 0.76rem;
  font-weight: 600;
  border: 1px solid currentColor;
  background: transparent;
  cursor: pointer;
  transition: opacity 0.15s ease;
}

.alert-action-btn:hover {
  opacity: 0.8;
}

.alert-close-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: inherit;
  opacity: 0.6;
  cursor: pointer;
  transition: all 0.15s ease;
  margin-top: -0.2rem;
  margin-inline-end: -0.3rem;
}

.alert-close-btn:hover {
  opacity: 1;
  background: rgba(0, 0, 0, 0.05);
}

.alert-progress-bar {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: currentColor;
  opacity: 0.4;
  animation: alertProgress linear forwards;
}

@keyframes alertProgress {
  from { width: 100%; }
  to { width: 0%; }
}
</style>
