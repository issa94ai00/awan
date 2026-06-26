<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Dynamic Meta Tags -->
    <title>{{ $seo_title ?? get_setting('meta_title') ?? (get_setting('site_name') ?? 'أوان التكادوم') }}</title>
    <meta name="description" content="{{ $seo_description ?? get_setting('meta_description') ?? (get_setting('site_description') ?? 'أفضل قطع الغيار الأصلية بجودة عالية وأسعار منافسة، لجميع موديلات الهواتف الذكية') }}">
    <meta name="keywords" content="{{ $seo_keywords ?? get_setting('meta_keywords') ?? 'قطع غيار، هواتف ذكية، شاشات، بطاريات، شواحن، سورية، دمشق' }}">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta property="og:title" content="{{ $seo_title ?? get_setting('meta_title') ?? (get_setting('site_name') ?? 'أوان التكادوم') }}">
    <meta property="og:description" content="{{ $seo_description ?? get_setting('meta_description') ?? (get_setting('site_description') ?? 'أفضل قطع الغيار الأصلية بجودة عالية وأسعار منافسة') }}">
    <meta property="og:image" content="{{ $seo_image ?? (get_setting('og_image') ? asset('storage/' . get_setting('og_image')) : asset('assets/images/logo.png')) }}">
    <meta property="og:locale" content="{{ app()->getLocale() === 'en' ? 'en_US' : 'ar_SY' }}">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ request()->url() }}">
    <meta property="twitter:title" content="{{ $seo_title ?? get_setting('meta_title') ?? (get_setting('site_name') ?? 'أوان التكادوم') }}">
    <meta property="twitter:description" content="{{ $seo_description ?? get_setting('meta_description') ?? (get_setting('site_description') ?? 'أفضل قطع الغيار الأصلية بجودة عالية وأسعار منافسة') }}">
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
        "@@context": "https://schema.org",
        "@@type": "Organization",
        "name": "{{ get_setting('site_name') ?? 'أوان التكادوم' }}",
        "description": "{{ get_setting('site_description') ?? 'أفضل قطع الغيار الأصلية بجودة عالية وأسعار منافسة' }}",
        "url": "{{ request()->url() }}",
        "logo": "{{ get_setting('site_logo') ? asset('storage/' . get_setting('site_logo')) : asset('assets/images/logo.png') }}",
        "contactPoint": {
            "@@type": "ContactPoint",
            "telephone": "{{ get_setting('contact_phone') ?? '+963 900 000 000' }}",
            "email": "{{ get_setting('contact_email') ?? 'info@awan-altakaddom.com' }}",
            "address": {
                "@@type": "PostalAddress",
                "addressLocality": "دمشق",
                "addressCountry": "سورية"
            }
        },
        "sameAs": [
            "{{ get_setting('facebook') ?? '#' }}",
            "{{ get_setting('instagram') ?? '#' }}",
            "{{ get_setting('twitter') ?? '#' }}"
        ]
    }
    </script>
    @endif
    
    <style>
        :root {
            /* Default fallback values */
            --el-color-primary: #005a9c;
            --primary-dark: #121c2c;
            --mobile-primary: #005a9c;
            --info-color: #005a9c;
            --accent-blue: #005a9c;

            --el-color-primary-light-3: #0077be;
            --mobile-primary-light: #0077be;
            --accent-blue-light: #0077be;

            --primary-dark-light: #1e2d42;
            --mobile-primary-dark: #1a2634;
            --accent-blue-dark: #1a2634;

            --el-color-success: #10b981;
            --mobile-secondary: #10b981;
            --success-color: #10b981;

            --mobile-secondary-light: #34d399;

            --accent-gold: #c9a959;
            --el-color-warning: #c9a959;
            --mobile-accent: #c9a959;
            --warning-color: #c9a959;

            --mobile-accent-light: #d4c498;

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
                site_name: '{{ get_setting('site_name') ?? 'أوان التكادوم' }}',
                site_logo: '{{ get_setting('site_logo') ? asset('storage/' . get_setting('site_logo')) : asset('assets/images/logo.png') }}',
                site_description: '{{ get_setting('site_description') ?? '' }}',
                show_site_name: {{ get_setting('show_site_name') ?? 'true' }},
                contact_whatsapp: '{{ get_setting('contact_whatsapp') ?? '963900000000' }}',
                contact_facebook: '{{ get_setting('contact_facebook') ?? '#' }}',
                contact_instagram: '{{ get_setting('contact_instagram') ?? '#' }}',
                contact_phone: '{{ get_setting('contact_phone') ?? '+963 900 000 000' }}',
                contact_email: '{{ get_setting('contact_email') ?? 'info@awan-altakaddom.com' }}',
                address: '{{ get_setting('address') ?? 'سورية - دمشق' }}',
                theme_font_family: '{{ get_setting('theme_font_family') ?? 'Cairo' }}',
                theme_border_radius: '{{ get_setting('theme_border_radius') ?? '14px' }}',
                theme_hero_align: '{{ get_setting('theme_hero_align') ?? 'center' }}',
                theme_hero_overlay_opacity: '{{ get_setting('theme_hero_overlay_opacity') ?? '0.85' }}',
                theme_navbar_bg_color: '{{ get_setting('theme_navbar_bg_color') ?? '' }}',
                theme_navbar_text_color: '{{ get_setting('theme_navbar_text_color') ?? '#ffffff' }}',
                theme_navbar_scrolled_text_color: '{{ get_setting('theme_navbar_scrolled_text_color') ?? '' }}',
                theme_navbar_transparency: {{ get_setting('theme_navbar_transparency') ?? '25' }},
                theme_footer_bg_color: '{{ get_setting('theme_footer_bg_color') ?? 'linear-gradient(135deg, #1E3A0F, #2D5016)' }}',
                theme_footer_text_color: '{{ get_setting('theme_footer_text_color') ?? '#f8f9fa' }}',
                theme_footer_layout: '{{ get_setting('theme_footer_layout') ?? 'multicolumn' }}',
                theme_page_header_bg_color: '{{ get_setting('theme_page_header_bg_color') ?? 'linear-gradient(135deg, #1e3d1a, #5a6b7a)' }}',
                theme_page_header_text_color: '{{ get_setting('theme_page_header_text_color') ?? '#ffffff' }}',
                theme_hero_btn_bg_color: '{{ get_setting('theme_hero_btn_bg_color') ?? '' }}',
                theme_hero_btn_text_color: '{{ get_setting('theme_hero_btn_text_color') ?? '' }}',
                theme_hero_btn_secondary_bg_color: '{{ get_setting('theme_hero_btn_secondary_bg_color') ?? '' }}',
                theme_hero_btn_secondary_text_color: '{{ get_setting('theme_hero_btn_secondary_text_color') ?? '' }}',
                theme_cart_btn_bg_color: '{{ get_setting('theme_cart_btn_bg_color') ?? '' }}',
                theme_cart_btn_text_color: '{{ get_setting('theme_cart_btn_text_color') ?? '' }}',
                theme_custom_css: `{{ get_setting('theme_custom_css') ?? '' }}`,
                hero_bg: '{{ get_setting('hero_bg') ? asset('storage/' . get_setting('hero_bg')) : '' }}',
                about_title: '{{ get_setting('about_title') ?? 'من نحن' }}',
                about_description: '{{ get_setting('about_description') ?? '' }}',
                about_story: '{{ get_setting('about_story') ?? '' }}',
                about_values: '{{ get_setting('about_values') ?? '' }}',
                about_services: '{{ get_setting('about_services') ?? '' }}',
                about_years: {{ get_setting('about_years') ?? '10' }},
                about_projects: {{ get_setting('about_projects') ?? '500' }},
                about_customers: {{ get_setting('about_customers') ?? '1000' }},
                about_partners: {{ get_setting('about_partners') ?? '50' }},
                vision_title: '{{ get_setting('vision_title') ?? 'الهوية والرؤية' }}',
                vision_description: '{{ get_setting('vision_description') ?? '' }}',
                vision_feature_1_title: '{{ get_setting('vision_feature_1_title') ?? 'جودة عالمية' }}',
                vision_feature_1_description: '{{ get_setting('vision_feature_1_description') ?? '' }}',
                vision_feature_2_title: '{{ get_setting('vision_feature_2_title') ?? 'تصميم عصري' }}',
                vision_feature_2_description: '{{ get_setting('vision_feature_2_description') ?? '' }}',
                vision_feature_3_title: '{{ get_setting('vision_feature_3_title') ?? 'شراكة موثوقة' }}',
                vision_feature_3_description: '{{ get_setting('vision_feature_3_description') ?? '' }}',
            }
        };
    </script>
</body>
</html>
