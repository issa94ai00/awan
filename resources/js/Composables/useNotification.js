// resources/js/Composables/useNotification.js
import { ref } from 'vue';

export function useNotification() {
    const notifications = ref([]);

    /**
     * عرض إشعار
     * Show notification
     */
    function show(message, type = 'info', duration = 5000) {
        const id = Date.now();
        notifications.value.push({ 
            id, 
            message, 
            type,
            duration,
        });
        
        // إزالة الإشعار تلقائياً بعد المدة المحددة
        // Auto-remove notification after specified duration
        if (duration > 0) {
            setTimeout(() => {
                remove(id);
            }, duration);
        }
        
        return id;
    }

    /**
     * إزالة إشعار
     * Remove notification
     */
    function remove(id) {
        notifications.value = notifications.value.filter(n => n.id !== id);
    }

    /**
     * إزالة جميع الإشعارات
     * Remove all notifications
     */
    function clear() {
        notifications.value = [];
    }

    /**
     * إشعار نجاح
     * Success notification
     */
    function success(message, duration = 5000) {
        return show(message, 'success', duration);
    }

    /**
     * إشعار خطأ
     * Error notification
     */
    function error(message, duration = 7000) {
        return show(message, 'error', duration);
    }

    /**
     * إشعار تحذير
     * Warning notification
     */
    function warning(message, duration = 6000) {
        return show(message, 'warning', duration);
    }

    /**
     * إشعار معلومات
     * Info notification
     */
    function info(message, duration = 5000) {
        return show(message, 'info', duration);
    }

    /**
     * إشعار دائم (لا يختفي تلقائياً)
     * Persistent notification (doesn't auto-dismiss)
     */
    function persistent(message, type = 'info') {
        return show(message, type, 0);
    }

    return {
        notifications,
        show,
        remove,
        clear,
        success,
        error,
        warning,
        info,
        persistent,
    };
}
