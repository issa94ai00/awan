<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    @php
        $locale = app()->getLocale();
        $defaultSiteName = $locale === 'en' ? (get_setting('site_name_en') ?: 'Awaan Altakadom') : (get_setting('site_name') ?: 'أوان التقدم');
        $defaultTagline = $locale === 'en' ? (get_setting('site_tagline_en') ?: 'Building a Better Tomorrow') : (get_setting('site_tagline') ?: 'نبني معاً غد سورية الأجمل');
        $defaultDesc = $locale === 'en' 
            ? (get_setting('meta_description_en') ?: (get_setting('site_description_en') ?: 'At Awan Al Taqaddam, we offer building supplies that combine global quality with modern design.')) 
            : (get_setting('meta_description') ?: (get_setting('site_description') ?: 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم.'));
        $defaultKeywords = $locale === 'en' 
            ? (get_setting('meta_keywords_en') ?: 'building materials, Syria, Damascus') 
            : (get_setting('meta_keywords') ?: 'أفضل تاجر مواد بناء, أوان التقدم, سوريا, دمشق');
        $defaultTitle = $locale === 'en' ? (get_setting('meta_title_en') ?: ($defaultSiteName . ' - ' . $defaultTagline)) : (get_setting('meta_title') ?: ($defaultSiteName . ' - ' . $defaultTagline));
    @endphp

    <!-- Dynamic Meta Tags -->
    <title>{{ $seo_title ?? $defaultTitle }}</title>
    <meta name="description" content="{{ $seo_description ?? $defaultDesc }}">
    <meta name="keywords" content="{{ $seo_keywords ?? $defaultKeywords }}">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta property="og:title" content="{{ $seo_title ?? $defaultTitle }}">
    <meta property="og:description" content="{{ $seo_description ?? $defaultDesc }}">
    <meta property="og:image" content="{{ $seo_image ?? (get_setting('og_image') ? asset('storage/' . get_setting('og_image')) : asset('assets/images/logo.png')) }}">
    <meta property="og:locale" content="{{ app()->getLocale() === 'en' ? 'en_US' : 'ar_SY' }}">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ request()->url() }}">
    <meta property="twitter:title" content="{{ $seo_title ?? $defaultTitle }}">
    <meta property="twitter:description" content="{{ $seo_description ?? $defaultDesc }}">
    <meta property="twitter:image" content="{{ $seo_image ?? (get_setting('og_image') ? asset('storage/' . get_setting('og_image')) : asset('assets/images/logo.png')) }}">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="{{ request()->url() }}">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ get_setting('favicon') ? asset('storage/' . get_setting('favicon')) : asset('assets/images/favicon.ico') }}">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Cairo:wght@300;400;500;600;700;800&family=El+Messiri:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700;800&family=Readex+Pro:wght@300;400;500;600;700&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Font Awesome 6 (Free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- JSON-LD Structured Data -->
    @if(!empty($seo_json_ld))
        {!! $seo_json_ld !!}
    @else
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "{{ $defaultSiteName }}",
        "description": "{{ $defaultDesc }}",
        "url": "{{ url('/') }}",
        "logo": "{{ get_setting('site_logo') ? asset('storage/' . get_setting('site_logo')) : asset('assets/images/logo.png') }}",
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "{{ get_setting('contact_phone') ?? '00963962889577' }}",
            "email": "{{ get_setting('contact_email') ?? 'awaanaltakadom@gmail.com' }}",
            "address": {
                "@type": "PostalAddress",
                "addressLocality": "{{ $locale === 'en' ? 'Damascus' : 'دمشق' }}",
                "addressCountry": "{{ $locale === 'en' ? 'Syria' : 'سورية' }}"
            }
        },
        "sameAs": array_values(array_filter([
            "{{ get_setting('facebook') ?? '' }}",
            "{{ get_setting('instagram') ?? '' }}",
            "{{ get_setting('twitter') ?? '' }}"
        ]))
    }
    </script>
    @endif
    
    <style>
        :root {
            /* Default fallback values (matching abc.sql) */
            --el-color-primary: #1e3a8a;
            --primary-dark: #1e3a8a;
            --mobile-primary: #1e3a8a;
            --info-color: #1e3a8a;
            --accent-blue: #1e3a8a;

            --el-color-primary-light-3: #3b82f6;
            --mobile-primary-light: #3b82f6;
            --accent-blue-light: #3b82f6;

            --primary-dark-light: #1e1b4b;
            --mobile-primary-dark: #1e1b4b;
            --accent-blue-dark: #1e1b4b;

            --el-color-success: #06b6d4;
            --mobile-secondary: #06b6d4;
            --success-color: #06b6d4;

            --mobile-secondary-light: #67e8f9;

            --accent-gold: #f59e0b;
            --el-color-warning: #f59e0b;
            --mobile-accent: #f59e0b;
            --warning-color: #f59e0b;

            --mobile-accent-light: #fbbf24;

            /* Dynamic settings values */
            @if(get_setting('theme_primary_color'))
                --el-color-primary: {{ get_setting('theme_primary_color') }} !important;
                --primary-dark: {{ get_setting('theme_primary_color') }} !important;
                --mobile-primary: {{ get_setting('theme_primary_color') }};
                --info-color: {{ get_setting('theme_primary_color') }};
                --accent-blue: {{ get_setting('theme_primary_color') }};
            @endif
            @if(get_setting('theme_primary_light_color'))
                --el-color-primary-light-3: {{ get_setting('theme_primary_light_color') }} !important;
                --mobile-primary-light: {{ get_setting('theme_primary_light_color') }};
                --accent-blue-light: {{ get_setting('theme_primary_light_color') }};
            @endif
            @if(get_setting('theme_primary_dark_color'))
                --primary-dark-light: {{ get_setting('theme_primary_dark_color') }} !important;
                --mobile-primary-dark: {{ get_setting('theme_primary_dark_color') }};
                --accent-blue-dark: {{ get_setting('theme_primary_dark_color') }};
            @endif
            @if(get_setting('theme_secondary_color'))
                --el-color-success: {{ get_setting('theme_secondary_color') }} !important;
                --mobile-secondary: {{ get_setting('theme_secondary_color') }};
                --success-color: {{ get_setting('theme_secondary_color') }};
            @endif
            @if(get_setting('theme_secondary_light_color'))
                --mobile-secondary-light: {{ get_setting('theme_secondary_light_color') }};
            @endif
            @if(get_setting('theme_accent_color'))
                --accent-gold: {{ get_setting('theme_accent_color') }} !important;
                --el-color-warning: {{ get_setting('theme_accent_color') }} !important;
                --mobile-accent: {{ get_setting('theme_accent_color') }};
                --warning-color: {{ get_setting('theme_accent_color') }};
            @endif
            @if(get_setting('theme_accent_light_color'))
                --mobile-accent-light: {{ get_setting('theme_accent_light_color') }};
            @endif
        }
        @if(get_setting('hero_bg'))
        .hero {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.85), rgba(96, 96, 96, 0.9)), url('{{ asset('storage/' . get_setting('hero_bg')) }}') center/cover no-repeat !important;
        }
        @endif
    </style>
</head>
<body>
    <div id="app"></div>
    <script>
        console.log('Vue template loaded');
        
        // Pass system data to Vue app
        window.systemData = {
            locale: '{{ app()->getLocale() }}',
            direction: '{{ config('app.direction', 'rtl') }}',
            settings: {
                site_name: '{{ get_setting('site_name') ?? 'أوان التقدم' }}',
                site_name_en: '{{ get_setting('site_name_en') ?? 'Awaan Altakadom' }}',
                site_tagline: '{{ get_setting('site_tagline') ?? 'نبني معاً غد سورية الأجمل' }}',
                site_tagline_en: '{{ get_setting('site_tagline_en') ?? 'Building a better tomorrow' }}',
                site_logo: '{{ get_setting('site_logo') ? asset('storage/' . get_setting('site_logo')) : asset('assets/images/logo.png') }}',
                site_description: '{{ get_setting('site_description') ?? 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.' }}',
                site_description_en: '{{ get_setting('site_description_en') ?? 'At Awan Al Taqaddam, we offer building supplies that combine global quality with modern design, to be your ideal partner in your construction projects.' }}',
                show_site_name: {{ get_setting('show_site_name') ?? '1' }},
                show_product_price: {{ get_setting('show_product_price') ?? '0' }},
                default_currency: '{{ get_setting('default_currency') ?? 'SAR' }}',
                default_language: '{{ get_setting('default_language') ?? 'ar' }}',
                timezone: '{{ get_setting('timezone') ?? 'Asia/Riyadh' }}',
                contact_phone: '{{ get_setting('contact_phone') ?? '00963962889577' }}',
                contact_whatsapp: '{{ get_setting('contact_whatsapp') ?? '00963962889577' }}',
                contact_email: '{{ get_setting('contact_email') ?? 'awaanaltakadom@gmail.com' }}',
                contact_address: '{{ get_setting('contact_address') ?? 'المملكة العربية السعوديه - الرياض\r\nالفرع2: سوريا - دمشق' }}',
                address: '{{ get_setting('address') ?? 'المملكة العربية السعوديه - الرياض\r\nالفرع2: سوريا - دمشق' }}',
                address_en: '{{ get_setting('address_en') ?? 'Kingdom of Saudi Arabia - Riyadh\r\nSyria - Damascus' }}',
                working_hours: '{{ get_setting('working_hours') ?? 'السبت الى الخميس 08:00-22:00' }}',
                facebook: '{{ get_setting('facebook') ?? 'https://www.facebook.com/share/18AcYpks2o/' }}',
                contact_facebook: '{{ get_setting('contact_facebook') ?? 'https://www.facebook.com/share/18AcYpks2o/' }}',
                instagram: '{{ get_setting('instagram') ?? 'https://www.instagram.com/awaan_altakadm.co?igsh=MjQydTZmZTkweXg3' }}',
                contact_instagram: '{{ get_setting('contact_instagram') ?? 'https://www.instagram.com/awaan_altakadm.co?igsh=MjQydTZmZTkweXg3' }}',
                twitter: '{{ get_setting('twitter') ?? 'https://x.com/awaan' }}',
                contact_twitter: '{{ get_setting('contact_twitter') ?? 'https://x.com/awaan' }}',
                youtube: '{{ get_setting('youtube') ?? 'https://youtube.com/awaan' }}',
                contact_youtube: '{{ get_setting('contact_youtube') ?? 'https://youtube.com/awaan' }}',
                linkedin: '{{ get_setting('linkedin') ?? 'https://linkedin.com/awaan' }}',
                contact_linkedin: '{{ get_setting('contact_linkedin') ?? 'https://linkedin.com/awaan' }}',
                meta_title: '{{ get_setting('meta_title') ?? '' }}',
                meta_keywords: '{{ get_setting('meta_keywords') ?? 'مواد بناء, مضخات مياه, خلاطات حمامات, أكسسوارات صحية, كلادينج, قواطع جبسية, أدوات, مشابك, علاقات معدنية, أنظمة تثبيت ورفع' }}',
                theme_primary_color: '{{ get_setting('theme_primary_color') ?? '#1e3a8a' }}',
                theme_primary_light_color: '{{ get_setting('theme_primary_light_color') ?? '#3b82f6' }}',
                theme_primary_dark_color: '{{ get_setting('theme_primary_dark_color') ?? '#1e1b4b' }}',
                theme_secondary_color: '{{ get_setting('theme_secondary_color') ?? '#06b6d4' }}',
                theme_secondary_light_color: '{{ get_setting('theme_secondary_light_color') ?? '#67e8f9' }}',
                theme_accent_color: '{{ get_setting('theme_accent_color') ?? '#f59e0b' }}',
                theme_accent_light_color: '{{ get_setting('theme_accent_light_color') ?? '#fbbf24' }}',
                theme_font_family: '{{ get_setting('theme_font_family') ?? 'Cairo' }}',
                theme_border_radius: '{{ get_setting('theme_border_radius') ?? '14px' }}',
                theme_hero_align: '{{ get_setting('theme_hero_align') ?? 'center' }}',
                theme_hero_overlay_opacity: '{{ get_setting('theme_hero_overlay_opacity') ?? '0.5' }}',
                theme_navbar_bg_color: '{{ get_setting('theme_navbar_bg_color') ?? '#1e3a8a' }}',
                theme_navbar_text_color: '{{ get_setting('theme_navbar_text_color') ?? '#ffffff' }}',
                theme_navbar_scrolled_bg_color: '{{ get_setting('theme_navbar_scrolled_bg_color') ?? '#1e3a8a' }}',
                theme_navbar_scrolled_text_color: '{{ get_setting('theme_navbar_scrolled_text_color') ?? '#ffffff' }}',
                theme_navbar_transparency: {{ get_setting('theme_navbar_transparency') ?? '25' }},
                theme_footer_bg_color: '{{ get_setting('theme_footer_bg_color') ?? '#1e1b4b' }}',
                theme_footer_text_color: '{{ get_setting('theme_footer_text_color') ?? '#e2e8f0' }}',
                theme_footer_layout: '{{ get_setting('theme_footer_layout') ?? 'multicolumn' }}',
                theme_page_header_bg_color: '{{ get_setting('theme_page_header_bg_color') ?? 'linear-gradient(135deg, #1e3a8a, #3b82f6)' }}',
                theme_page_header_text_color: '{{ get_setting('theme_page_header_text_color') ?? '#ffffff' }}',
                theme_hero_btn_bg_color: '{{ get_setting('theme_hero_btn_bg_color') ?? 'linear-gradient(135deg, #1e3a8a, #3b82f6)' }}',
                theme_hero_btn_text_color: '{{ get_setting('theme_hero_btn_text_color') ?? '#ffffff' }}',
                theme_hero_btn_secondary_bg_color: '{{ get_setting('theme_hero_btn_secondary_bg_color') ?? 'rgba(255, 255, 255, 0.1)' }}',
                theme_hero_btn_secondary_text_color: '{{ get_setting('theme_hero_btn_secondary_text_color') ?? '#1e3a8a' }}',
                theme_cart_btn_bg_color: '{{ get_setting('theme_cart_btn_bg_color') ?? '#1e3a8a' }}',
                theme_cart_btn_text_color: '{{ get_setting('theme_cart_btn_text_color') ?? '#ffffff' }}',
                theme_custom_css: `{{ get_setting('theme_custom_css') ?? '' }}`,
                hero_bg: '{{ get_setting('hero_bg') ? asset('storage/' . get_setting('hero_bg')) : '' }}',
                favicon: '{{ get_setting('favicon') ? asset('storage/' . get_setting('favicon')) : asset('assets/images/favicon.ico') }}',
                og_image: '{{ get_setting('og_image') ? asset('storage/' . get_setting('og_image')) : '' }}',
                google_analytics: '{{ get_setting('google_analytics') ?? '' }}',
                email_notifications: {{ get_setting('email_notifications') ?? '1' }},
                sms_notifications: {{ get_setting('sms_notifications') ?? '0' }},
                push_notifications: {{ get_setting('push_notifications') ?? '0' }},
                system_notifications: {{ get_setting('system_notifications') ?? '1' }},
                about_title: '{{ get_setting('about_title') ?? 'من نحن' }}',
                about_title_en: '{{ get_setting('about_title_en') ?? 'About Us' }}',
                about_description: '{{ get_setting('about_description') ?? 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.' }}',
                about_description_en: '{{ get_setting('about_description_en') ?? 'We at Awaan Al-Takadom provide building materials that combine global quality with modern design, making us your ideal partner for your construction projects.' }}',
                about_story: '{{ get_setting('about_story') ?? 'في عالم متسارع يتطلب البناء فيه الجمع بين القوة والجمال، ولدت أوان التقدم.لم نكن نريد مجرد توريد مواد بناء، بل أردنا تغيير الطريقة التي تُبنى بها المشاريع. انطلقنا برؤية واضحة: سد الفجوة بين الجودة العالمية الصارمة والتصاميم العصرية الحديثة.كل حجر، وكل قطعة، وكل مادة نوفرها اليوم هي نتاج بحث دقيق وشراكات عالمية. نحن لا نبيع مستلزمات إنشائية فحسب، بل نقدم لعملائنا ومطورينا الطمأنينة والأمان لتبديل المخططات الورقية إلى واقع ملموس يدوم لأجيال.اليوم، نفخر بأننا لسنا مجرد موردين، بل الشريك الأمثل لقصص نجاح معمارية تزيّن حاضرنا وتبني مستقبلك.' }}',
                about_story_en: '{{ get_setting('about_story_en') ?? "In a fast-paced world where construction requires a combination of strength and beauty, Awan Progress was born. We didn\'t just want to supply building materials, we wanted to change the way projects are built. We set out with a clear vision: to bridge the gap between stringent international quality and modern, contemporary designs. Every stone, every piece, and every material we offer today is the result of meticulous research and global partnerships. We do not just sell construction supplies, but we also provide our customers and developers with the reassurance and security to turn paper plans into a tangible reality that will last for generations. Today, we are proud to be not just suppliers, but rather the ideal partner for architectural success stories that decorate our present and build your future." }}',
                about_values: '{{ get_setting('about_values') ?? 'نختار منتجاتنا بعناية من أفضل الموردين العالميين.\\nنبحث عن أحدث التقنيات والحلول العصرية.\\nنبني علاقات طويلة الأمد مبنية على الشفافية.\\nفريق متخصص جاهز لتقديم الدعم في كل خطوة.' }}',
                about_values_en: '{{ get_setting('about_values_en') ?? "We carefully select our products from the best global suppliers.\\nWe seek the latest technologies and modern solutions.\\nWe build long-term relationships based on transparency.\\nA specialized team ready to provide support at every step." }}',
                about_services: '{{ get_setting('about_services') ?? 'أدوات صحية وعصرية: تشكيلة راقية من أطقم الحمامات والخلاطات التي تدمج بين كفاءة استهلاك المياه وجمال التصميم الحديث.\r\nأنظمة إضاءة ذكية: حلول إنارة داخلية وخارجية متطورة توفر الطاقة وتمنح المباني لمسة معمارية ساحرة.\r\nسيراميك وبورسلان فاخر: أرضيات وجدران بألوان ونقشات عصرية تحاكي أحدث صيحات الديكور العالمي وتتحمل الاستخدام الشاق.\r\nمواد عزل وحماية: مستلزمات عزل مائي وحراري عالية الكفاءة لحماية المنشآت من التغيرات المناخية وضمان استدامتها.\r\nاجهات زجاجية وكلادينج: حلول متكاملة لكسوة المباني الخارجية تمنح المشاريع التجارية والسكنية مظهراً مستقبلياً فخماً.\r\nاستشارات وتوريد للمشاريع: خدمة دعم فني متخصصة لمساعدة المقاولين في اختيار المواد الأنسب وتوريدها بدقة وفي الموعد.' }}',
                about_services_en: '{{ get_setting('about_services_en') ?? "Modern Sanitary Ware: A refined selection of bathroom fixtures and mixers that blend water efficiency with modern design aesthetics.\r\nSmart Lighting Systems: Advanced indoor and outdoor lighting solutions that save energy and give buildings a stunning architectural touch.\r\nPremium Ceramics & Porcelain: Floors and walls with modern colors and patterns that mimic the latest global decor trends and withstand heavy use.\r\nInsulation & Protection Materials: High-efficiency waterproofing and thermal insulation materials to protect structures from climate changes and ensure sustainability.\r\nGlass Facades & Cladding: Integrated building exterior cladding solutions that give commercial and residential projects a luxurious futuristic look.\r\nProject Consulting & Supply: Specialized technical support service to help contractors select the most suitable materials and supply them accurately and on time." }}',
                about_years: {{ get_setting('about_years') ?? '15' }},
                about_projects: {{ get_setting('about_projects') ?? '1000' }},
                about_customers: {{ get_setting('about_customers') ?? '5000' }},
                about_partners: {{ get_setting('about_partners') ?? '200' }},
                vision_title: '{{ get_setting('vision_title') ?? 'هويتنا ورؤيتنا' }}',
                vision_title_en: '{{ get_setting('vision_title_en') ?? 'Our Identity & Vision' }}',
                vision_description: '{{ get_setting('vision_description') ?? 'نسعى لأن نكون الخيار الأول في سوق مستلزمات البناء في سورية والمنطقة، من خلال تقديم منتجات عالمية بمعايير جودة عالية وخدمة لا مثيل لها.' }}',
                vision_description_en: '{{ get_setting('vision_description_en') ?? 'We aspire to be the first choice in the building materials market in Syria and the region, by providing global products with high quality standards and unparalleled service.' }}',
                vision_feature_1_title: '{{ get_setting('vision_feature_1_title') ?? 'جودة عالمية' }}',
                vision_feature_1_title_en: '{{ get_setting('vision_feature_1_title_en') ?? 'Global Quality' }}',
                vision_feature_1_description: '{{ get_setting('vision_feature_1_description') ?? 'نعمل مع أكبر الموردين العالميين لتقديم مستلزمات بناء تلبي أعلى معايير الجودة الدولية. كل منتج نقدمه يخضع لعمليات فحص ورقابة صارمة لضمان التميز.' }}',
                vision_feature_1_description_en: '{{ get_setting('vision_feature_1_description_en') ?? "We work with the world\'s largest suppliers to provide building materials that meet the highest international quality standards. Every product we offer undergoes strict inspection and quality control processes." }}',
                vision_feature_2_title: '{{ get_setting('vision_feature_2_title') ?? 'تصميم عصري' }}',
                vision_feature_2_title_en: '{{ get_setting('vision_feature_2_title_en') ?? 'Modern Design' }}',
                vision_feature_2_description: '{{ get_setting('vision_feature_2_description') ?? 'نواكب أحدث صرحات التصميم المعماري والديكور الداخلي لنقدم لكم منتجات تجمع بين الجمال والوظيفية. نؤمن بأن التصميم الجيد يبدأ باختيار المواد المناسبة.' }}',
                vision_feature_2_description_en: '{{ get_setting('vision_feature_2_description_en') ?? "We keep pace with the latest architectural and interior design trends to offer you products that combine beauty and functionality. We believe that good design starts with choosing the right materials." }}',
                vision_feature_3_title: '{{ get_setting('vision_feature_3_title') ?? 'شراكة موثوقة' }}',
                vision_feature_3_title_en: '{{ get_setting('vision_feature_3_title_en') ?? 'Trusted Partnership' }}',
                vision_feature_3_description: '{{ get_setting('vision_feature_3_description') ?? 'نبني مع شركائنا علاقات استراتيجية طويلة الأمد ترتكز على الثقة والشفافية والمنفعة المشتركة. نرى أنفسنا شريكاً حقيقياً في نجاح مشاريعكم الإنشائية.' }}',
                vision_feature_3_description_en: '{{ get_setting('vision_feature_3_description_en') ?? "We build long-term strategic relationships with our partners based on trust, transparency, and mutual benefit. We see ourselves as a true partner in the success of your construction projects." }}',
            }
        };
    </script>
</body>
</html>
