<?php

use App\Models\Setting;

if (! function_exists('get_setting')) {
    function get_setting(string $key, mixed $default = null): mixed
    {
        try {
            $row = Setting::query()->where('key', $key)->first();
            if ($row && isset($row->value)) {
                return $row->value;
            }
        } catch (\Throwable $e) {
            // ignore failures during early bootstrap (config loading)
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
    function image_url(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Already absolute URL
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Clean leading slash
        $path = ltrim($path, '/');

        // If it already starts with storage/
        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/' . $path);
    }
}
