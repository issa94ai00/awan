<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // General Settings
            [
                'key' => 'site_name',
                'value' => 'أوان التقدم',
                'type' => 'text',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'site_tagline',
                'value' => 'نبني معاً غد سورية الأجمل',
                'type' => 'text',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'site_description',
                'value' => 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.',
                'type' => 'textarea',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'site_logo',
                'value' => 'assets/images/logo.png',
                'type' => 'text',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'show_site_name',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'show_product_price',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // SEO Settings
            [
                'key' => 'meta_title',
                'value' => 'أوان التقدم - مستلزمات البناء والتطوير',
                'type' => 'text',
                'group' => 'seo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'meta_description',
                'value' => 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.',
                'type' => 'textarea',
                'group' => 'seo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'meta_keywords',
                'value' => 'مواد بناء, مضخات مياه, خلاطات حمامات, أكسسوارات صحية, كلادينج, قواطع جبسية, أدوات, مشابك, علاقات معدنية, أنظمة تثبيت ورفع',
                'type' => 'text',
                'group' => 'seo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'og_image',
                'value' => null,
                'type' => 'file',
                'group' => 'seo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'google_analytics',
                'value' => null,
                'type' => 'text',
                'group' => 'seo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Contact Settings
            [
                'key' => 'contact_phone',
                'value' => '+963 900 000 000',
                'type' => 'text',
                'group' => 'contact',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'contact_email',
                'value' => 'info@awan-altakaddom.com',
                'type' => 'text',
                'group' => 'contact',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'contact_whatsapp',
                'value' => '963900000000',
                'type' => 'text',
                'group' => 'contact',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'contact_facebook',
                'value' => '#',
                'type' => 'text',
                'group' => 'contact',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'contact_address',
                'value' => 'سورية، دمشق',
                'type' => 'text',
                'group' => 'contact',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('settings')->insertOrIgnore($settings);
    }
}
