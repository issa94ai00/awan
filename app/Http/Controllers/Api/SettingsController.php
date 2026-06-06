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
            'settings.site_tagline' => 'nullable|string|max:255',
            'settings.site_description' => 'nullable|string|max:1000',
            'settings.show_site_name' => 'sometimes|boolean',
            'settings.show_product_price' => 'sometimes|boolean',
            'settings.default_currency' => ['nullable', 'in:' . implode(',', $availableCurrencies)],
            'settings.default_language' => ['nullable', 'in:' . implode(',', $availableLanguages)],
            'settings.timezone' => ['nullable', 'in:' . implode(',', $availableTimezones)],
            'settings.contact_phone' => 'nullable|string|max:50',
            'settings.contact_whatsapp' => 'nullable|string|max:50',
            'settings.contact_email' => 'nullable|email|max:255',
            'settings.address' => 'nullable|string|max:1000',
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
