<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General Settings (abc.sql IDs 1-5, 14-28, 112-142)
            ['key' => 'site_name', 'value' => 'أوان التقدم', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_tagline', 'value' => 'نبني معاً غد سورية الأجمل', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_description', 'value' => 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.', 'type' => 'textarea', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_logo', 'value' => 'assets/images/logo.png', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'show_site_name', 'value' => '1', 'type' => 'boolean', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'address', 'value' => 'المركز الرئيسي - المملكة العربية السعوديه - الرياض' . "\r\n" . 'الفرع - دمشق', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'working_hours', 'value' => 'السبت الى الخميس 08:00-22:00', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'facebook', 'value' => 'https://www.facebook.com/share/18AcYpks2o/', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'instagram', 'value' => 'https://www.instagram.com/awaan_altakadm.co?igsh=MjQydTZmZTkweXg3', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'twitter', 'value' => 'https://x.com/awaan', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'youtube', 'value' => 'https://youtube.com/awaan', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'linkedin', 'value' => 'https://linkedin.com/awaan', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'meta_title', 'value' => null, 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'google_analytics', 'value' => null, 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'show_product_price', 'value' => '0', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'logo', 'value' => 'assets/images/logo.png', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'favicon', 'value' => null, 'type' => 'file', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'default_currency', 'value' => 'SAR', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'default_language', 'value' => 'ar', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'timezone', 'value' => 'Asia/Riyadh', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],

            // English General Settings (abc.sql IDs 112-114)
            ['key' => 'site_name_en', 'value' => 'Awaan Altakadom', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_tagline_en', 'value' => 'Building a better tomorrow', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_description_en', 'value' => 'At Awan Al Taqaddam, we offer building supplies that combine global quality with modern design, to be your ideal partner in your construction projects.', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],

            // Theme Settings (abc.sql IDs 115-142)
            ['key' => 'theme_primary_color', 'value' => '#1e3a8a', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_primary_light_color', 'value' => '#3b82f6', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_primary_dark_color', 'value' => '#1e1b4b', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_secondary_color', 'value' => '#06b6d4', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_secondary_light_color', 'value' => '#67e8f9', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_accent_color', 'value' => '#f59e0b', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_accent_light_color', 'value' => '#fbbf24', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_font_family', 'value' => 'Cairo', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_border_radius', 'value' => '14px', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_hero_align', 'value' => 'center', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_hero_overlay_opacity', 'value' => '0.5', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_footer_layout', 'value' => 'multicolumn', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_custom_css', 'value' => null, 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_navbar_bg_color', 'value' => '#1e3a8a', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_navbar_text_color', 'value' => '#ffffff', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_navbar_scrolled_bg_color', 'value' => '#1e3a8a', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_navbar_scrolled_text_color', 'value' => '#ffffff', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_navbar_transparency', 'value' => '25', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_hero_btn_bg_color', 'value' => 'linear-gradient(135deg, #1e3a8a, #3b82f6)', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_hero_btn_text_color', 'value' => '#ffffff', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_hero_btn_secondary_bg_color', 'value' => 'rgba(255, 255, 255, 0.1)', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_hero_btn_secondary_text_color', 'value' => '#1e3a8a', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_cart_btn_bg_color', 'value' => '#1e3a8a', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_cart_btn_text_color', 'value' => '#ffffff', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_footer_bg_color', 'value' => '#1e1b4b', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_footer_text_color', 'value' => '#e2e8f0', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_page_header_bg_color', 'value' => 'linear-gradient(135deg, #1e3a8a, #3b82f6)', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_page_header_text_color', 'value' => '#ffffff', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],

            // SEO Settings (abc.sql IDs 6-8, 29)
            ['key' => 'meta_description', 'value' => 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.', 'type' => 'textarea', 'group' => 'seo', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'meta_keywords', 'value' => 'مواد بناء, مضخات مياه, خلاطات حمامات, أكسسوارات صحية, كلادينج, قواطع جبسية, أدوات, مشابك, علاقات معدنية, أنظمة تثبيت ورفع', 'type' => 'text', 'group' => 'seo', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'og_description', 'value' => 'مستلزمات البناء بأعلى جودة وتصاميم عصرية لمشاريعكم في سورية', 'type' => 'text', 'group' => 'seo', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'og_image', 'value' => null, 'type' => 'file', 'group' => 'seo', 'created_at' => now(), 'updated_at' => now()],

            // Contact Settings (abc.sql IDs 9-13, 30-33)
            ['key' => 'contact_phone', 'value' => '00963962889577', 'type' => 'text', 'group' => 'contact', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_email', 'value' => 'awaanaltakadom@gmail.com', 'type' => 'text', 'group' => 'contact', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_whatsapp', 'value' => '00963962889577', 'type' => 'text', 'group' => 'contact', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_facebook', 'value' => 'https://www.facebook.com/share/18AcYpks2o/', 'type' => 'text', 'group' => 'contact', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_address', 'value' => 'المركز الرئيسي - المملكة العربية السعوديه - الرياض' . "\r\n" . 'الفرع - دمشق', 'type' => 'text', 'group' => 'contact', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_instagram', 'value' => 'https://www.instagram.com/awaan_altakadm.co?igsh=MjQydTZmZTkweXg3', 'type' => 'text', 'group' => 'contact', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_twitter', 'value' => 'https://x.com/awaan', 'type' => 'text', 'group' => 'contact', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_youtube', 'value' => 'https://youtube.com/awaan', 'type' => 'text', 'group' => 'contact', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_linkedin', 'value' => 'https://linkedin.com/awaan', 'type' => 'text', 'group' => 'contact', 'created_at' => now(), 'updated_at' => now()],

            // Notification Settings (abc.sql IDs 34-37)
            ['key' => 'email_notifications', 'value' => '1', 'type' => 'boolean', 'group' => 'notifications', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'sms_notifications', 'value' => '0', 'type' => 'boolean', 'group' => 'notifications', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'push_notifications', 'value' => '0', 'type' => 'boolean', 'group' => 'notifications', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'system_notifications', 'value' => '1', 'type' => 'boolean', 'group' => 'notifications', 'created_at' => now(), 'updated_at' => now()],

            // About Settings Arabic (abc.sql IDs 38-46)
            ['key' => 'about_title', 'value' => 'من نحن', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_description', 'value' => 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.', 'type' => 'textarea', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_story', 'value' => 'نحن في <strong>أوان التقدم</strong> نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية. نحرص على توفير منتجات تلبي أعلى معايير الجودة والمتانة لتناسب احتياجاتكم المتنوعة.', 'type' => 'textarea', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_values', 'value' => "نختار منتجاتنا بعناية من أفضل الموردين العالميين.\nنبحث عن أحدث التقنيات والحلول العصرية.\nنبني علاقات طويلة الأمد مبنية على الشفافية.\nفريق متخصص جاهز لتقديم الدعم في كل خطوة.", 'type' => 'textarea', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_services', 'value' => "أدوات صحية وعصرية: تشكيلة راقية من أطقم الحمامات والخلاطات التي تدمج بين كفاءة استهلاك المياه وجمال التصميم الحديث.\r\nأنظمة إضاءة ذكية: حلول إنارة داخلية وخارجية متطورة توفر الطاقة وتمنح المباني لمسة معمارية ساحرة.\r\nسيراميك وبورسلان فاخر: أرضيات وجدران بألوان ونقشات عصرية تحاكي أحدث صيحات الديكور العالمي وتتحمل الاستخدام الشاق.\r\nمواد عزل وحماية: مستلزمات عزل مائي وحراري عالية الكفاءة لحماية المنشآت من التغيرات المناخية وضمان استدامتها.\r\nاجهات زجاجية وكلادينج: حلول متكاملة لكسوة المباني الخارجية تمنح المشاريع التجارية والسكنية مظهراً مستقبلياً فخماً.\r\nاستشارات وتوريد للمشاريع: خدمة دعم فني متخصصة لمساعدة المقاولين في اختيار المواد الأنسب وتوريدها بدقة وفي الموعد.", 'type' => 'textarea', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_years', 'value' => '15', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_projects', 'value' => '1000', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_customers', 'value' => '5000', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_partners', 'value' => '200', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],

            // About Settings English (abc.sql IDs 47-51)
            ['key' => 'about_title_en', 'value' => 'About Us', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_description_en', 'value' => 'We at Awaan Al-Takadom provide building materials that combine global quality with modern design, making us your ideal partner for your construction projects.', 'type' => 'textarea', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_story_en', 'value' => 'We at <strong>Awaan Al-Takadom</strong> provide building materials that combine global quality with modern design, making us your ideal partner for your construction projects. We are committed to providing products that meet the highest standards of quality and durability to suit your diverse needs.', 'type' => 'textarea', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_values_en', 'value' => "We carefully select our products from the best global suppliers.\nWe seek the latest technologies and modern solutions.\nWe build long-term relationships based on transparency.\nA specialized team ready to provide support at every step.", 'type' => 'textarea', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_services_en', 'value' => "Modern Sanitary Ware: A refined selection of bathroom fixtures and mixers that blend water efficiency with modern design aesthetics.\r\nSmart Lighting Systems: Advanced indoor and outdoor lighting solutions that save energy and give buildings a stunning architectural touch.\r\nPremium Ceramics & Porcelain: Floors and walls with modern colors and patterns that mimic the latest global decor trends and withstand heavy use.\r\nInsulation & Protection Materials: High-efficiency waterproofing and thermal insulation materials to protect structures from climate changes and ensure sustainability.\r\nGlass Facades & Cladding: Integrated building exterior cladding solutions that give commercial and residential projects a luxurious futuristic look.\r\nProject Consulting & Supply: Specialized technical support service to help contractors select the most suitable materials and supply them accurately and on time.", 'type' => 'textarea', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],

            // About Values 1-5 (abc.sql IDs 52-71)
            ['key' => 'about_value_1_title', 'value' => 'الجودة العالمية', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_1_title_en', 'value' => 'Global Quality', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_1_desc', 'value' => 'نلتزم بأعلى معايير الكفاءة والمتانة في كل منتج نقدّمه لضمان استدامة مشاريعكم.', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_1_desc_en', 'value' => 'We adhere to the highest standards of efficiency and durability in every product we offer to ensure the sustainability of your projects.', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],

            ['key' => 'about_value_2_title', 'value' => 'الابتكار والعصرية', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_2_title_en', 'value' => 'Innovation & Modernity', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_2_desc', 'value' => 'نواكب أحدث توجهات التصميم المعماري لنمنح مشاريعكم لمسة جمالية متجددة.', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_2_desc_en', 'value' => 'We keep pace with the latest architectural design trends to give your projects a renewed aesthetic touch.', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],

            ['key' => 'about_value_3_title', 'value' => 'الشراكة الحقيقية', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_3_title_en', 'value' => 'True Partnership', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_3_desc', 'value' => 'لا نرى عملاءنا كمشترين، بل كشركاء نجاح نرافقهم خطوة بخطوة حتى اكتمال البناء.', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_3_desc_en', 'value' => 'We don\'t see our customers as buyers, but as success partners we accompany step by step until construction is complete.', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],

            ['key' => 'about_value_4_title', 'value' => 'النزاهة والشفافية', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_4_title_en', 'value' => 'Integrity & Transparency', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_4_desc', 'value' => 'نلتزم بالصدق والأمانة في التعامل، والوضوح التام في مواصفات المنتجات ومواعيد التسليم.', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_4_desc_en', 'value' => 'We are committed to honesty and integrity in dealings, and complete clarity in product specifications and delivery dates.', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],

            ['key' => 'about_value_5_title', 'value' => 'الاستدامة والمسؤولية', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_5_title_en', 'value' => 'Sustainability & Responsibility', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_5_desc', 'value' => 'نحرص على توفير مستلزمات بناء صديقة للبيئة تساهم في إعمار مستقبل آمن وصحي.', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_value_5_desc_en', 'value' => 'We are keen to provide environmentally friendly building materials that contribute to building a safe and healthy future.', 'type' => 'text', 'group' => 'about', 'created_at' => now(), 'updated_at' => now()],

            // Vision Settings Arabic (abc.sql IDs 72-79)
            ['key' => 'vision_title', 'value' => 'هويتنا ورؤيتنا', 'type' => 'text', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_description', 'value' => 'نسعى لأن نكون الخيار الأول في سوق مستلزمات البناء في سورية والمنطقة، من خلال تقديم منتجات عالمية بمعايير جودة عالية وخدمة لا مثيل لها.', 'type' => 'textarea', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_feature_1_title', 'value' => 'جودة عالمية', 'type' => 'text', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_feature_1_description', 'value' => 'نعمل مع أكبر الموردين العالميين لتقديم مستلزمات بناء تلبي أعلى معايير الجودة الدولية. كل منتج نقدمه يخضع لعمليات فحص ورقابة صارمة لضمان التميز.', 'type' => 'textarea', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_feature_2_title', 'value' => 'تصميم عصري', 'type' => 'text', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_feature_2_description', 'value' => 'نواكب أحدث صرحتات التصميم المعماري والديكور الداخلي لنقدم لكم منتجات تجمع بين الجمال والوظيفية. نؤمن بأن التصميم الجيد يبدأ باختيار المواد المناسبة.', 'type' => 'textarea', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_feature_3_title', 'value' => 'شراكة موثوقة', 'type' => 'text', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_feature_3_description', 'value' => 'نبني مع شركائنا علاقات استراتيجية طويلة الأمد ترتكز على الثقة والشفافية والمنفعة المشتركة. نعتبر أنفسنا شريكاً حقيقياً في نجاح مشاريعكم الإنشائية.', 'type' => 'textarea', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],

            // Vision Settings English (abc.sql IDs 80-87)
            ['key' => 'vision_title_en', 'value' => 'Our Identity & Vision', 'type' => 'text', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_description_en', 'value' => 'We aspire to be the first choice in the building materials market in Syria and the region, by providing global products with high quality standards and unparalleled service.', 'type' => 'textarea', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_feature_1_title_en', 'value' => 'Global Quality', 'type' => 'text', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_feature_1_description_en', 'value' => 'We work with the world\'s largest suppliers to provide building materials that meet the highest international quality standards. Every product we offer undergoes strict inspection and quality control processes.', 'type' => 'textarea', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_feature_2_title_en', 'value' => 'Modern Design', 'type' => 'text', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_feature_2_description_en', 'value' => 'We keep pace with the latest architectural and interior design trends to offer you products that combine beauty and functionality. We believe that good design starts with choosing the right materials.', 'type' => 'textarea', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_feature_3_title_en', 'value' => 'Trusted Partnership', 'type' => 'text', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vision_feature_3_description_en', 'value' => 'We build long-term strategic relationships with our partners based on trust, transparency, and mutual benefit. We see ourselves as a true partner in the success of your construction projects.', 'type' => 'textarea', 'group' => 'vision', 'created_at' => now(), 'updated_at' => now()],
        ];

        DB::table('settings')->insertOrIgnore($settings);
    }
}
