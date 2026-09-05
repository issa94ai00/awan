import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';

// ---- module-level shared state ----------------------------------------------
// The public layout owns the "route defaults" side of the <head>; individual
// pages push data-driven overrides (product/category names, breadcrumbs, schema)
// once their data arrives. Keeping both on module state means one flush() always
// reflects the *latest* truth instead of two components racing over the DOM.
const currentRoute = ref(null);   // { name, path } of the active public route
const currentOverride = ref(null); // page-level seo data (cleared on navigation)

/**
 * Single entry point for SEO <head> management across the public SPA pages.
 *
 * - PublicLayout calls `setRoute()` on every navigation (route defaults).
 * - Data-driven pages call `setOverride()` once their content is known.
 * - `refresh()` re-applies the same page while settings/locale settle.
 *
 * Server-rendered meta tags in vue.blade.php are the crawlable baseline and stay
 * untouched here; every call below simply rewrites the same <head> nodes so
 * client-side navigation stays in sync with the search-engine-facing tags.
 */
export function useSeo() {
    const { locale } = useI18n();
    const settingsStore = useSettingsStore();

    // systemData (injected by the blade layout) covers the first paint before the
    // settings API round-trips; the store wins once it resolves.
    const settings = computed(() => ({
        ...(window.systemData?.settings || {}),
        ...(settingsStore.data || {}),
    }));

    const localeVal = () => locale.value;
    const suffix = () => (locale.value === 'en' ? '_en' : '');

    const setRoute = (info) => {
        currentRoute.value = info || null;
        currentOverride.value = null;
        flush();
    };

    const setOverride = (override) => {
        if (!currentRoute.value || !override) return;
        currentOverride.value = { ...override };
        flush();
    };

    const clearOverride = () => {
        currentOverride.value = null;
        flush();
    };

    const refresh = () => flush();

    const flush = () => {
        if (!currentRoute.value || typeof document === 'undefined') return;
        writeHead(resolveConfig(currentRoute.value.name));
    };

    // ---- site-level resolved values -----------------------------------------
    const siteName = () =>
        settings.value[`meta_title${suffix()}`] ||
        settings.value[`site_name_${localeVal()}`] ||
        settings.value.site_name ||
        (localeVal() === 'en' ? 'Awaan Altakadom' : 'أوان التقدم');

    const siteDescription = () =>
        settings.value[`meta_description${suffix()}`] ||
        settings.value[`site_description_${localeVal()}`] ||
        settings.value.site_description ||
        (localeVal() === 'en'
            ? 'At Awan Al Taqaddam, we offer building supplies that combine global quality with modern design.'
            : 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم.');

    const siteKeywords = () =>
        settings.value[`meta_keywords${suffix()}`] ||
        settings.value.meta_keywords ||
        (localeVal() === 'en' ? 'building materials, Syria, Damascus' : 'مواد بناء, سوريا, دمشق');

    const defaultOgImage = () => {
        const og = settings.value.og_image;
        return og ? resolveImageUrl(og) : '/assets/images/logo.png';
    };

    // ---- route-level defaults (mirror PublicPageController) ------------------
    const buildRouteDefaults = (routeName) => {
        const en = localeVal() === 'en';
        // Static informational pages: localized copy matches the server so a fresh
        // SPA visit and a direct crawl agree on what the page is about.
        const LITERALS = {
            about: {
                title: settings.value[`about_title${suffix()}`] || (en ? 'About Us' : 'من نحن'),
                description: settings.value[`about_description${suffix()}`] || siteDescription(),
            },
            vision: {
                title: settings.value[`vision_title${suffix()}`] || (en ? 'Identity & Vision' : 'الهوية والرؤية'),
                description: settings.value[`vision_description${suffix()}`] || siteDescription(),
            },
            contact: {
                title: en ? 'Contact Us' : 'اتصل بنا',
                description: en
                    ? 'Contact Awaan Al-Takadom for consultations and supply of premium building materials.'
                    : 'تواصل معنا في أوان التقدم للحصول على استشارات وتوريد مستلزمات البناء الفاخرة.',
            },
            inquiry: {
                title: en ? 'Send Inquiry' : 'إرسال استفسار',
                description: en
                    ? 'Send an inquiry about our building materials and construction solutions.'
                    : 'أرسل استفساراً حول منتجاتنا ومستلزمات البناء التي نقدمها.',
            },
            'purchase-request': {
                title: en ? 'Purchase Request' : 'طلب شراء',
                description: en
                    ? 'Submit a purchase request for high-quality building and installation materials.'
                    : 'قدم طلب شراء لمستلزمات ومواد البناء والتركيب عالية الجودة.',
            },
            categories: {
                title: en ? 'Categories' : 'الفئات',
                description: en
                    ? 'Browse our main construction product categories.'
                    : 'تصفح الفئات الرئيسية لمواد البناء ومستلزمات التثبيت.',
            },
            products: {
                title: en ? 'All Products' : 'جميع المنتجات',
                description: en
                    ? 'Browse the full catalogue of building materials, sanitary ware, cladding and installation systems.'
                    : 'تصفح الكتالوج الكامل لمواد البناء والأدوات الصحية والكلادينج وأنظمة التثبيت.',
            },
            'special.offers': {
                title: en ? 'Special Offers' : 'العروض المميزة',
                description: en
                    ? 'Discover current discounts and limited-time offers on premium building materials.'
                    : 'اكتشف الخصومات الحالية والعروض المحدودة على مستلزمات البناء الفاخرة.',
            },
            'featured.products': {
                title: en ? 'Featured Products' : 'المنتجات المميزة',
                description: en
                    ? 'Explore featured products and construction supplies.'
                    : 'اكتشف المنتجات المميزة ومستلزمات البناء الإنشائية.',
            },
            cart: {
                title: en ? 'Shopping Cart' : 'سلة التسوق',
                description: en ? 'Review the items in your shopping cart.' : 'راجع المنتجات الموجودة في سلة التسوق الخاصة بك.',
                noindex: true,
            },
            'customer.orders': {
                title: en ? 'Orders & Invoices' : 'الطلبات والفواتير',
                description: en ? 'Track your customer orders and view invoices.' : 'تتبع طلباتك واستعرض فواتير الشراء الخاصة بك.',
                noindex: true,
            },
            'not-found': {
                title: en ? 'Page Not Found' : 'الصفحة غير موجودة',
                description: en
                    ? 'The page you are looking for could not be found.'
                    : 'الصفحة التي تبحث عنها غير موجودة.',
                noindex: true,
            },
        };

        if (LITERALS[routeName]) {
            return { ...LITERALS[routeName], ogType: 'website', jsonLd: [] };
        }

        // Data-driven routes: the generic fallback shows until the page fetches
        // its record and pushes a setOverride() with the real copy + schema.
        if (routeName === 'product.detail') {
            return { title: en ? 'Product Details' : 'تفاصيل المنتج', description: '', ogType: 'product' };
        }
        if (routeName === 'category.detail') {
            return { title: en ? 'Category Details' : 'تفاصيل الفئة', description: '' };
        }

        // Home — the only route whose title is the bare site name + tagline, and
        // the only one that owns the Organization schema for the brand.
        return { title: '', description: '', ogType: 'website', jsonLd: [organizationSchema()] };
    };

    const resolveConfig = (routeName) => {
        if (currentOverride.value) {
            return {
                title: currentOverride.value.title ?? '',
                description: currentOverride.value.description ?? '',
                keywords: currentOverride.value.keywords ?? '',
                image: currentOverride.value.image ?? '',
                noindex: currentOverride.value.noindex ?? false,
                ogType: currentOverride.value.ogType ?? 'website',
                jsonLd: currentOverride.value.jsonLd ?? [],
            };
        }
        return { keywords: '', image: '', noindex: false, ...buildRouteDefaults(routeName) };
    };

    // ---- schema builders ------------------------------------------------------
    const absoluteUrl = (path) => {
        if (!path) return '';
        if (/^(https?:)?\/\//i.test(path)) return path;
        return window.location.origin + (path.startsWith('/') ? path : `/${path}`);
    };

    const breadcrumbSchema = (items) => ({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: (items || []).map((item, index) => ({
            '@type': 'ListItem',
            position: index + 1,
            name: item.name,
            item: item.url ? absoluteUrl(item.url) : undefined,
        })),
    });

    const organizationSchema = () => ({
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: siteName(),
        description: siteDescription(),
        url: window.location.origin,
        logo: defaultOgImage(),
        contactPoint: [{
            '@type': 'ContactPoint',
            telephone: settings.value.contact_phone || '00963962889577',
            email: settings.value.contact_email || 'awaanaltakadom@gmail.com',
            contactType: 'customer service',
            areaServed: ['SY', 'SA'],
            availableLanguage: ['Arabic', 'English'],
        }],
        sameAs: [settings.value.facebook, settings.value.instagram, settings.value.twitter, settings.value.linkedin, settings.value.youtube]
            .filter(Boolean),
    });

    const productSchema = (product) => {
        const currency = window.systemData?.currencies?.base;
        const image = product.image_main ? resolveImageUrl(product.image_main) : defaultOgImage();
        const name = localeVal() === 'en'
            ? (product.name_en || product.name_ar || product.name)
            : (product.name_ar || product.name);
        const description = localeVal() === 'en'
            ? (product.short_description_en || product.description_en || '')
            : (product.short_description_ar || product.description_ar || product.description || '');

        const schema = {
            '@context': 'https://schema.org',
            '@type': 'Product',
            name,
            image: [image],
            description: cleanText(description, 1000),
            sku: product.sku || `PROD-${product.id}`,
            mpn: product.barcode || `MPN-${product.id}`,
            brand: { '@type': 'Brand', name: product.brand || siteName() },
        };

        // Only advertise an Offer for a real, publicly visible price; emitting
        // "0.00" for a price-hidden catalogue reads as "free" to Google.
        const pricesArePublic = String(settings.value.show_product_price ?? '0') === '1';
        const price = parseFloat(product.price || 0);
        if (pricesArePublic && price > 0 && product.show_price) {
            schema.offers = {
                '@type': 'Offer',
                url: absoluteUrl(window.location.pathname),
                priceCurrency: currency || 'SAR',
                price: price.toFixed(2),
                availability: product.in_stock
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                itemCondition: 'https://schema.org/NewCondition',
            };
        }
        return schema;
    };

    // ---- head writing ---------------------------------------------------------
    function writeHead(cfg) {
        const pageTitle = (cfg.title || '').trim();

        let finalTitle;
        if (pageTitle) {
            finalTitle = `${pageTitle} - ${siteName()}`;
        } else {
            const metaTitle = settings.value[`meta_title${suffix()}`];
            const tagline = settings.value[`site_tagline${suffix()}`] || settings.value.site_tagline;
            finalTitle = metaTitle || (tagline ? `${siteName()} - ${tagline}` : siteName());
        }

        const cleaned = cfg.description ? cleanText(cfg.description) : siteDescription();
        const finalDesc = cleanText(cleaned);

        const finalKeywords = cfg.keywords ? `${cfg.keywords}, ${siteKeywords()}` : siteKeywords();
        const finalImage = cfg.image ? resolveImageUrl(cfg.image) : defaultOgImage();

        // Canonical must ignore the query string (/featured?new / ?best are the
        // same document) — and stay aligned across og:url and hreflang.
        const canonicalUrl = window.location.origin + window.location.pathname;

        const robots = cfg.noindex
            ? 'noindex, follow'
            : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

        document.title = finalTitle;
        setMeta('meta[name="robots"]', 'content', robots);
        setMeta('meta[name="description"]', 'content', finalDesc);
        setMeta('meta[name="keywords"]', 'content', finalKeywords);
        setMeta('meta[property="og:type"]', 'content', cfg.ogType || 'website');
        setMeta('meta[property="og:title"]', 'content', finalTitle);
        setMeta('meta[property="og:description"]', 'content', finalDesc);
        setMeta('meta[property="og:image"]', 'content', finalImage);
        setMeta('meta[property="og:url"]', 'content', canonicalUrl);
        setMeta('meta[name="twitter:card"]', 'content', 'summary_large_image');
        setMeta('meta[name="twitter:title"]', 'content', finalTitle);
        setMeta('meta[name="twitter:description"]', 'content', finalDesc);
        setMeta('meta[name="twitter:image"]', 'content', finalImage);
        setMeta('meta[name="twitter:url"]', 'content', canonicalUrl);

        const canonical = document.querySelector('link[rel="canonical"]');
        if (canonical) canonical.setAttribute('href', canonicalUrl);
        document.querySelectorAll('link[rel="alternate"][hreflang]').forEach((el) => {
            el.setAttribute('href', canonicalUrl);
        });

        manageJsonLd(cfg.jsonLd || []);
    }

    return {
        setRoute,
        setOverride,
        clearOverride,
        refresh,
        breadcrumbSchema,
        organizationSchema,
        productSchema,
    };
}

function cleanText(value, limit = 160) {
    if (!value) return '';
    let text = String(value).replace(/<[^>]*>/g, ' ');
    text = text.replace(/\s+/g, ' ').trim();
    if (text.length > limit) return `${text.slice(0, limit - 3)}...`;
    return text;
}

function resolveImageUrl(value) {
    if (!value) return '';
    if (/^(https?:)?\/\//i.test(value) || value.startsWith('data:')) return value;
    if (value.startsWith('/')) return value;
    return `/storage/${value}`;
}

function setMeta(query, attr, value) {
    if (value === undefined || value === null || value === false) return;
    const el = document.querySelector(query);
    if (el) {
        el.setAttribute(attr, value);
        return;
    }
    const meta = document.createElement('meta');
    const propMatch = String(query).match(/property="([^"]+)"/);
    const nameMatch = String(query).match(/name="([^"]+)"/);
    if (propMatch) meta.setAttribute('property', propMatch[1]);
    else if (nameMatch) meta.setAttribute('name', nameMatch[1]);
    meta.setAttribute(attr, value);
    document.head.appendChild(meta);
}

/**
 * JSON-LD lifecycle: the server ships static schema (WebSite search-action block
 * tagged data-schema="site", plus anonymous page-level Organization/Product and
 * app-managed scripts such as Home's ItemList, which carry their own id). On SPA
 * navigation we drop every anonymous schema script we don't explicitly
 * reconstitute so a stray Product block never bleeds into unrelated pages, then
 * inject the current page's scripts fresh. Tagged/id'd scripts are their owners'
 * responsibility and are left untouched.
 */
function manageJsonLd(scripts) {
    document.querySelectorAll('script[type="application/ld+json"]').forEach((el) => {
        if (el.dataset.schema === 'site') return;
        if (el.id) return;
        el.remove();
    });
    (scripts || []).forEach((item) => {
        const el = document.createElement('script');
        el.type = 'application/ld+json';
        el.dataset.dynamic = 'seo';
        el.text = typeof item === 'string' ? item : JSON.stringify(item);
        document.head.appendChild(el);
    });
}