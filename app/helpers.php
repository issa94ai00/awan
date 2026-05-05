<?php

use App\Models\Setting;

if (! function_exists('get_setting')) {
    function get_setting(string $key, mixed $default = null): mixed
    {
        $settings = session('setting');

        if ($settings instanceof \Illuminate\Support\Collection) {
            $row = $settings->firstWhere('key', $key);
            if ($row && isset($row->value)) {
                return $row->value;
            }
        }
        
        try {
            $row = Setting::query()->where('key', $key)->first();
            if ($row && isset($row->value)) {
                return $row->value;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $default;
    }
}

if (! function_exists('site_url')) {
    function site_url(?string $path = null): string
    {
        return $path === null ? url('/') : url($path);
    }
}

if (! function_exists('asset_url')) {
    function asset_url(?string $path = null): string
    {
        return $path === null ? asset('') : asset($path);
    }
}

if (! function_exists('image_url')) {
    function image_url(string $path): string
    {
        return asset('storage/' . ltrim($path, '/'));
    }
}
