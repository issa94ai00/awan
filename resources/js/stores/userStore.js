// resources/js/Stores/userStore.js
import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useUserStore = defineStore('user', () => {
    const user = ref(null);
    const permissions = ref([]);
    const roles = ref([]);
    const loading = ref(false);

    /**
     * تعيين بيانات المستخدم
     * Set user data
     */
    function setUser(userData) {
        user.value = userData;
    }

    /**
     * تعيين الصلاحيات
     * Set permissions
     */
    function setPermissions(userPermissions) {
        permissions.value = userPermissions;
    }

    /**
     * تعيين الأدوار
     * Set roles
     */
    function setRoles(userRoles) {
        roles.value = userRoles;
    }

    /**
     * التحقق من صلاحية معينة
     * Check specific permission
     */
    function hasPermission(permission) {
        return permissions.value.includes(permission);
    }

    /**
     * التحقق من دور معين
     * Check specific role
     */
    function hasRole(role) {
        return roles.value.includes(role);
    }

    /**
     * التحقق من أي صلاحية من قائمة
     * Check any permission from list
     */
    function hasAnyPermission(permissionsList) {
        return permissionsList.some(p => permissions.value.includes(p));
    }

    /**
     * التحقق من جميع الصلاحيات من قائمة
     * Check all permissions from list
     */
    function hasAllPermissions(permissionsList) {
        return permissionsList.every(p => permissions.value.includes(p));
    }

    /**
     * مسح بيانات المستخدم (تسجيل الخروج)
     * Clear user data (logout)
     */
    function clearUser() {
        user.value = null;
        permissions.value = [];
        roles.value = [];
    }

    /**
     * الحصول على اسم المستخدم
     * Get user name
     */
    function getUserName() {
        return user.value?.name || 'مستخدم';
    }

    /**
     * الحصول على البريد الإلكتروني
     * Get user email
     */
    function getUserEmail() {
        return user.value?.email || '';
    }

    /**
     * الحصول على الصورة الرمزية
     * Get user avatar
     */
    function getUserAvatar() {
        return user.value?.avatar || null;
    }

    return {
        user,
        permissions,
        roles,
        loading,
        setUser,
        setPermissions,
        setRoles,
        hasPermission,
        hasRole,
        hasAnyPermission,
        hasAllPermissions,
        clearUser,
        getUserName,
        getUserEmail,
        getUserAvatar,
    };
});
