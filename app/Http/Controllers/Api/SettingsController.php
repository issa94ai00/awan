<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $settings,
            ]
        ]);
    }

    public function update(Request $request)
    {
        $availableCurrencies = ['USD', 'EUR', 'SAR', 'AED', 'EGP'];
        $availableLanguages = ['ar', 'en', 'fr'];
        $availableTimezones = ['Asia/Riyadh', 'Asia/Dubai', 'Asia/Amman', 'Africa/Cairo', 'Europe/Istanbul', 'Europe/Paris', 'UTC'];

        $validated = $request->validate([
            'settings.site_name' => 'nullable|string|max:255',
            'settings.site_name_en' => 'nullable|string|max:255',
            'settings.site_tagline' => 'nullable|string|max:255',
            'settings.site_tagline_en' => 'nullable|string|max:255',
            'settings.site_description' => 'nullable|string|max:1000',
            'settings.site_description_en' => 'nullable|string|max:1000',
            'settings.show_site_name' => 'sometimes|boolean',
            'settings.show_product_price' => 'sometimes|boolean',
            'settings.default_currency' => ['nullable', 'in:' . implode(',', $availableCurrencies)],
            'settings.default_language' => ['nullable', 'in:' . implode(',', $availableLanguages)],
            'settings.timezone' => ['nullable', 'in:' . implode(',', $availableTimezones)],
            'settings.contact_phone' => 'nullable|string|max:50',
            'settings.contact_whatsapp' => 'nullable|string|max:50',
            'settings.contact_email' => 'nullable|email|max:255',
            'settings.address' => 'nullable|string|max:1000',
            'settings.address_en' => 'nullable|string|max:1000',
            'settings.working_hours' => 'nullable|string|max:255',
            'settings.facebook' => 'nullable|url|max:255',
            'settings.instagram' => 'nullable|url|max:255',
            'settings.twitter' => 'nullable|url|max:255',
            'settings.youtube' => 'nullable|url|max:255',
            'settings.linkedin' => 'nullable|url|max:255',
            'settings.meta_title' => 'nullable|string|max:255',
            'settings.meta_description' => 'nullable|string|max:1000',
            'settings.meta_keywords' => 'nullable|string|max:500',
            'settings.google_analytics' => 'nullable|string|max:50',
            'settings.email_notifications' => 'sometimes|boolean',
            'settings.sms_notifications' => 'sometimes|boolean',
            'settings.push_notifications' => 'sometimes|boolean',
            'settings.system_notifications' => 'sometimes|boolean',
            'settings.about_title' => 'nullable|string|max:255',
            'settings.about_title_en' => 'nullable|string|max:255',
            'settings.about_description' => 'nullable|string|max:1000',
            'settings.about_description_en' => 'nullable|string|max:1000',
            'settings.about_story' => 'nullable|string',
            'settings.about_story_en' => 'nullable|string',
            'settings.about_values' => 'nullable|string',
            'settings.about_values_en' => 'nullable|string',
            'settings.about_value_1_title' => 'nullable|string|max:255',
            'settings.about_value_1_title_en' => 'nullable|string|max:255',
            'settings.about_value_1_desc' => 'nullable|string',
            'settings.about_value_1_desc_en' => 'nullable|string',
            'settings.about_value_2_title' => 'nullable|string|max:255',
            'settings.about_value_2_title_en' => 'nullable|string|max:255',
            'settings.about_value_2_desc' => 'nullable|string',
            'settings.about_value_2_desc_en' => 'nullable|string',
            'settings.about_value_3_title' => 'nullable|string|max:255',
            'settings.about_value_3_title_en' => 'nullable|string|max:255',
            'settings.about_value_3_desc' => 'nullable|string',
            'settings.about_value_3_desc_en' => 'nullable|string',
            'settings.about_value_4_title' => 'nullable|string|max:255',
            'settings.about_value_4_title_en' => 'nullable|string|max:255',
            'settings.about_value_4_desc' => 'nullable|string',
            'settings.about_value_4_desc_en' => 'nullable|string',
            'settings.about_value_5_title' => 'nullable|string|max:255',
            'settings.about_value_5_title_en' => 'nullable|string|max:255',
            'settings.about_value_5_desc' => 'nullable|string',
            'settings.about_value_5_desc_en' => 'nullable|string',
            'settings.about_services' => 'nullable|string',
            'settings.about_services_en' => 'nullable|string',
            'settings.about_years' => 'nullable|integer|min:0',
            'settings.about_projects' => 'nullable|integer|min:0',
            'settings.about_customers' => 'nullable|integer|min:0',
            'settings.about_partners' => 'nullable|integer|min:0',
            'settings.vision_title' => 'nullable|string|max:255',
            'settings.vision_title_en' => 'nullable|string|max:255',
            'settings.vision_description' => 'nullable|string|max:1000',
            'settings.vision_description_en' => 'nullable|string|max:1000',
            'settings.vision_feature_1_title' => 'nullable|string|max:255',
            'settings.vision_feature_1_title_en' => 'nullable|string|max:255',
            'settings.vision_feature_1_description' => 'nullable|string',
            'settings.vision_feature_1_description_en' => 'nullable|string',
            'settings.vision_feature_2_title' => 'nullable|string|max:255',
            'settings.vision_feature_2_title_en' => 'nullable|string|max:255',
            'settings.vision_feature_2_description' => 'nullable|string',
            'settings.vision_feature_2_description_en' => 'nullable|string',
            'settings.vision_feature_3_title' => 'nullable|string|max:255',
            'settings.vision_feature_3_title_en' => 'nullable|string|max:255',
            'settings.vision_feature_3_description' => 'nullable|string',
            'settings.vision_feature_3_description_en' => 'nullable|string',
            'settings.theme_primary_color' => 'nullable|string|max:50',
            'settings.theme_primary_light_color' => 'nullable|string|max:50',
            'settings.theme_primary_dark_color' => 'nullable|string|max:50',
            'settings.theme_secondary_color' => 'nullable|string|max:50',
            'settings.theme_secondary_light_color' => 'nullable|string|max:50',
            'settings.theme_accent_color' => 'nullable|string|max:50',
            'settings.theme_accent_light_color' => 'nullable|string|max:50',
            'settings.theme_font_family' => 'nullable|string|max:50',
            'settings.theme_border_radius' => 'nullable|string|max:50',
            'settings.theme_hero_align' => 'nullable|string|max:50',
            'settings.theme_hero_overlay_opacity' => 'nullable|string|max:50',
            'settings.theme_footer_layout' => 'nullable|string|max:50',
            'settings.theme_custom_css' => 'nullable|string|max:5000',
            'settings.theme_navbar_bg_color' => 'nullable|string|max:200',
            'settings.theme_navbar_text_color' => 'nullable|string|max:50',
            'settings.theme_navbar_scrolled_bg_color' => 'nullable|string|max:200',
            'settings.theme_navbar_scrolled_text_color' => 'nullable|string|max:50',
            'settings.theme_navbar_transparency' => 'nullable|string|max:10',
            'settings.theme_hero_btn_bg_color' => 'nullable|string|max:200',
            'settings.theme_hero_btn_text_color' => 'nullable|string|max:50',
            'settings.theme_hero_btn_secondary_bg_color' => 'nullable|string|max:200',
            'settings.theme_hero_btn_secondary_text_color' => 'nullable|string|max:50',
            'settings.theme_cart_btn_bg_color' => 'nullable|string|max:200',
            'settings.theme_cart_btn_text_color' => 'nullable|string|max:50',
            'settings.theme_footer_bg_color' => 'nullable|string|max:200',
            'settings.theme_footer_text_color' => 'nullable|string|max:50',
            'settings.theme_page_header_bg_color' => 'nullable|string|max:200',
            'settings.theme_page_header_text_color' => 'nullable|string|max:50',
        ]);

        $data = $request->input('settings', []);

        $booleanFields = ['show_product_price', 'show_site_name', 'email_notifications', 'sms_notifications', 'push_notifications', 'system_notifications'];
        foreach ($booleanFields as $field) {
            if (!isset($data[$field])) {
                $data[$field] = '0';
            }
        }

        foreach ($data as $key => $value) {
            $this->updateSettingWithAliases($key, $value);
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('settings', 'public');
            $this->updateSettingWithAliases('logo', $path);
        }

        if ($request->hasFile('og_image')) {
            $path = $request->file('og_image')->store('settings', 'public');
            $this->updateSettingWithAliases('og_image', $path);
        }

        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('settings', 'public');
            $this->updateSettingWithAliases('favicon', $path);
        }

        if ($request->hasFile('hero_bg')) {
            $path = $request->file('hero_bg')->store('settings', 'public');
            $this->updateSettingWithAliases('hero_bg', $path);
        }

        $settings = Setting::all()->pluck('value', 'key')->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Settings updated',
            'data' => ['settings' => $settings]
        ]);
    }

    private function updateSettingWithAliases(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);

        $aliasMap = [
            'logo' => ['site_logo'],
            'site_logo' => ['logo'],
            'address' => ['contact_address'],
            'contact_address' => ['address'],
            'address_en' => ['contact_address_en'],
            'contact_address_en' => ['address_en'],
            'facebook' => ['contact_facebook'],
            'contact_facebook' => ['facebook'],
            'instagram' => ['contact_instagram'],
            'contact_instagram' => ['instagram'],
            'twitter' => ['contact_twitter'],
            'contact_twitter' => ['twitter'],
            'youtube' => ['contact_youtube'],
            'contact_youtube' => ['youtube'],
            'linkedin' => ['contact_linkedin'],
            'contact_linkedin' => ['linkedin'],
        ];

        foreach ($aliasMap[$key] ?? [] as $aliasKey) {
            Setting::updateOrCreate(['key' => $aliasKey], ['value' => $value]);
        }
    }
}
