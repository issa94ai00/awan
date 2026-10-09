import { ref } from 'vue';

const globalNotifications = ref([]);

export function useNotification() {
    /**
     * Show notification
     */
    function show(options) {
        const id = Date.now() + Math.floor(Math.random() * 1000);
        const item = typeof options === 'string'
            ? { id, message: options, type: 'info', duration: 5000, dismissible: true }
            : {
                id,
                title: options.title || '',
                message: options.message || '',
                type: options.type || 'info',
                duration: options.duration !== undefined ? options.duration : 5000,
                position: options.position || 'top-right',
                actionText: options.actionText || '',
                onAction: options.onAction || null,
                dismissible: options.dismissible !== false,
            };

        globalNotifications.value.push(item);

        if (item.duration > 0) {
            setTimeout(() => {
                remove(id);
            }, item.duration);
        }

        return id;
    }

    /**
     * Remove specific notification by id
     */
    function remove(id) {
        globalNotifications.value = globalNotifications.value.filter((n) => n.id !== id);
    }

    /**
     * Clear all active notifications
     */
    function clear() {
        globalNotifications.value = [];
    }

    /**
     * Helpers for specific types
     */
    function success(message, titleOrDuration = '', duration = 5000) {
        const title = typeof titleOrDuration === 'string' ? titleOrDuration : '';
        const dur = typeof titleOrDuration === 'number' ? titleOrDuration : duration;
        return show({ message, title, type: 'success', duration: dur });
    }

    function error(message, titleOrDuration = '', duration = 7000) {
        const title = typeof titleOrDuration === 'string' ? titleOrDuration : '';
        const dur = typeof titleOrDuration === 'number' ? titleOrDuration : duration;
        return show({ message, title, type: 'error', duration: dur });
    }

    function warning(message, titleOrDuration = '', duration = 6000) {
        const title = typeof titleOrDuration === 'string' ? titleOrDuration : '';
        const dur = typeof titleOrDuration === 'number' ? titleOrDuration : duration;
        return show({ message, title, type: 'warning', duration: dur });
    }

    function info(message, titleOrDuration = '', duration = 5000) {
        const title = typeof titleOrDuration === 'string' ? titleOrDuration : '';
        const dur = typeof titleOrDuration === 'number' ? titleOrDuration : duration;
        return show({ message, title, type: 'info', duration: dur });
    }

    function persistent(message, type = 'info', title = '') {
        return show({ message, title, type, duration: 0 });
    }

    return {
        notifications: globalNotifications,
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

export default useNotification;
