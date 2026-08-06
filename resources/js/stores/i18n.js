import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useI18nStore = defineStore('i18n', () => {
    // Get initial locale from localStorage or default to 'ar'
    const locale = ref(localStorage.getItem('locale') || 'ar');
    
    // Translation dictionary
    const translations = {
        ar: {
            // Navbar & General Public
            'nav.home': 'الرئيسية',
            'nav.categories': 'الفئات',
            'nav.products': 'المنتجات',
            'nav.orders': 'الطلبات',
            'nav.vision': 'الهوية والرؤية',
            'nav.about': 'من نحن',
            'nav.contact': 'اتصل بنا',
            'nav.search_placeholder': 'ابحث...',
            'nav.search_product': 'ابحث عن منتج...',
            'nav.login': 'تسجيل دخول',
            'nav.subscribe': 'اشترك معنا',
            'nav.light_mode': 'الوضع الفاتح',
            'nav.dark_mode': 'الوضع الداكن',
            'nav.welcome': 'مرحباً',
            'nav.profile': 'ملفي الشخصي',
            'nav.logout': 'تسجيل خروج',
            
            // Public Pages Common
            'common.view_details': 'عرض التفاصيل',
            'common.add_to_cart': 'أضف إلى السلة',
            'common.no_results': 'لا توجد نتائج مطابقة',
            'common.related_products': 'منتجات ذات صلة',
            'common.in_stock': 'متوفر',
            'common.out_of_stock': 'غير متوفر',
            
            // Footer
            'footer.about': 'عن',
            'footer.quick_links': 'روابط سريعة',
            'footer.rights_reserved': 'جميع الحقوق محفوظة',
            
            // Admin Sidebar
            'admin.dashboard': 'لوحة التحكم',
            'admin.content_management': 'إدارة المحتوى',
            'admin.categories': 'الفئات',
            'admin.products': 'المنتجات',
            'admin.special_offers': 'العروض المميزة',
            'admin.sales': 'المبيعات',
            'admin.overview': 'نظرة عامة',
            'admin.invoices': 'الفواتير',
            'admin.customers': 'العملاء',
            'admin.quotes': 'عروض الأسعار',
            'admin.sales_orders': 'طلبات البيع',
            'admin.payments': 'المدفوعات',
            'admin.purchases': 'المشتريات',
            'admin.suppliers': 'الموردين',
            'admin.purchase_orders': 'طلبات الشراء',
            'admin.purchase_receipts': 'إيصالات الاستلام',
            'admin.accounting': 'المحاسبة',
            'admin.journal': 'دفتر اليومية',
            'admin.ledger': 'دفتر الأستاذ',
            'admin.trial_balance': 'ميزان المراجعة',
            'admin.inventory': 'المخزون',
            'admin.stock_movements': 'حركات المخزون',
            'admin.hr': 'الموارد البشرية',
            'admin.employees': 'الموظفين',
            'admin.attendance': 'الحضور والانصراف',
            'admin.leaves': 'الإجازات',
            'admin.payrolls': 'الرواتب',
            'admin.crm': 'إدارة علاقات العملاء',
            'admin.tickets': 'التذاكر',
            'admin.production': 'الإنتاج',
            'admin.reports': 'التقارير',
            'admin.sales_report': 'تقرير المبيعات',
            'admin.inventory_report': 'تقرير المخزون',
            'admin.financial_report': 'التقرير المالي',
            'admin.payroll_report': 'تقرير الرواتب',
            'admin.pos': 'نقطة البيع',
            'admin.inquiries': 'الاستفسارات',
            'admin.visitors': 'الزوار',
            'admin.system': 'النظام',
            'admin.roles': 'الأدوار',
            'admin.permissions': 'الصلاحيات',
            'admin.settings': 'الإعدادات',
            
            // Settings Fallbacks (Translated on the fly)
            'settings.about_us': 'من نحن',
            'settings.identity_vision': 'الهوية والرؤية',
            'settings.world_quality': 'جودة عالمية',
            'settings.modern_design': 'تصميم عصري',
            'settings.trusted_partnership': 'شراكة موثوقة'
        },
        en: {
            // Navbar & General Public
            'nav.home': 'Home',
            'nav.categories': 'Categories',
            'nav.products': 'Products',
            'nav.orders': 'Orders',
            'nav.vision': 'Identity & Vision',
            'nav.about': 'About Us',
            'nav.contact': 'Contact Us',
            'nav.search_placeholder': 'Search...',
            'nav.search_product': 'Search for a product...',
            'nav.login': 'Login',
            'nav.subscribe': 'Subscribe',
            'nav.light_mode': 'Light Mode',
            'nav.dark_mode': 'Dark Mode',
            'nav.welcome': 'Welcome',
            'nav.profile': 'My Profile',
            'nav.logout': 'Logout',
            
            // Public Pages Common
            'common.view_details': 'View Details',
            'common.add_to_cart': 'Add to Cart',
            'common.no_results': 'No matching results found',
            'common.related_products': 'Related Products',
            'common.in_stock': 'In Stock',
            'common.out_of_stock': 'Out of Stock',
            
            // Footer
            'footer.about': 'About',
            'footer.quick_links': 'Quick Links',
            'footer.rights_reserved': 'All Rights Reserved',
            
            // Admin Sidebar
            'admin.dashboard': 'Dashboard',
            'admin.content_management': 'Content Management',
            'admin.categories': 'Categories',
            'admin.products': 'Products',
            'admin.special_offers': 'Special Offers',
            'admin.sales': 'Sales',
            'admin.overview': 'Overview',
            'admin.invoices': 'Invoices',
            'admin.customers': 'Customers',
            'admin.quotes': 'Quotes',
            'admin.sales_orders': 'Sales Orders',
            'admin.payments': 'Payments',
            'admin.purchases': 'Purchases',
            'admin.suppliers': 'Suppliers',
            'admin.purchase_orders': 'Purchase Orders',
            'admin.purchase_receipts': 'Purchase Receipts',
            'admin.accounting': 'Accounting',
            'admin.journal': 'Journal',
            'admin.ledger': 'Ledger',
            'admin.trial_balance': 'Trial Balance',
            'admin.inventory': 'Inventory',
            'admin.stock_movements': 'Stock Movements',
            'admin.hr': 'Human Resources',
            'admin.employees': 'Employees',
            'admin.attendance': 'Attendance',
            'admin.leaves': 'Leaves',
            'admin.payrolls': 'Payrolls',
            'admin.crm': 'CRM',
            'admin.tickets': 'Tickets',
            'admin.production': 'Production',
            'admin.reports': 'Reports',
            'admin.sales_report': 'Sales Report',
            'admin.inventory_report': 'Inventory Report',
            'admin.financial_report': 'Financial Report',
            'admin.payroll_report': 'Payroll Report',
            'admin.pos': 'POS',
            'admin.inquiries': 'Inquiries',
            'admin.visitors': 'Visitors',
            'admin.system': 'System',
            'admin.roles': 'Roles',
            'admin.permissions': 'Permissions',
            'admin.settings': 'Settings',
            
            // Settings Fallbacks (Translated on the fly)
            'settings.about_us': 'About Us',
            'settings.identity_vision': 'Identity & Vision',
            'settings.world_quality': 'World Class Quality',
            'settings.modern_design': 'Modern Design',
            'settings.trusted_partnership': 'Trusted Partnership'
        }
    };

    function t(key, defaultValue = '') {
        const keys = key.split('.');
        let translationObj = translations[locale.value];
        for (const k of keys) {
            if (translationObj && translationObj[k] !== undefined) {
                translationObj = translationObj[k];
            } else {
                // Try fallback to Arabic
                let fallbackObj = translations['ar'];
                for (const fk of keys) {
                    if (fallbackObj && fallbackObj[fk] !== undefined) {
                        fallbackObj = fallbackObj[fk];
                    } else {
                        fallbackObj = null;
                        break;
                    }
                }
                return fallbackObj || defaultValue || key;
            }
        }
        return translationObj || defaultValue || key;
    }

    function translateSetting(value) {
        if (!value) return '';
        if (locale.value === 'ar') return value;
        
        // Reverse lookup for exact Arabic matches to English
        const arDict = translations['ar'];
        const enDict = translations['en'];
        
        for (const key in arDict) {
            if (arDict[key] === value) {
                return enDict[key] || value;
            }
        }
        return value;
    }

    function setLocale(newLocale) {
        if (newLocale === 'ar' || newLocale === 'en') {
            locale.value = newLocale;
            localStorage.setItem('locale', newLocale);
            updatePageDirection();
        }
    }

    function updatePageDirection() {
        const isRtl = locale.value === 'ar';
        document.documentElement.setAttribute('lang', locale.value);
        document.documentElement.setAttribute('dir', isRtl ? 'rtl' : 'ltr');
    }

    return {
        locale,
        t,
        setLocale,
        updatePageDirection,
        translateSetting
    };
});
