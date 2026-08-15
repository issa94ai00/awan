<?php

use App\Models\Setting;
use App\Services\CurrencyService;
use Illuminate\Support\Facades\Schema;

if (! function_exists('get_setting')) {
    function get_setting(string $key, mixed $default = null): mixed
    {
        // The books' base currency is the single source of truth; the setting
        // is a mirror kept in sync by CurrencyService.
        if ($key === 'default_currency') {
            try {
                if (Schema::hasTable('currencies')) {
                    return app(CurrencyService::class)->baseCode();
                }
            } catch (Throwable $e) {
                // Fall through to the settings row / default.
            }
        }

        try {
            $row = Setting::query()->where('key', $key)->first();
            if ($row && isset($row->value)) {
                return $row->value;
            }
        } catch (Throwable $e) {
            // ignore failures during early bootstrap (config loading)
        }

        return $default;
    }
}

if (! function_exists('base_currency_code')) {
    function base_currency_code(): string
    {
        try {
            if (Schema::hasTable('currencies')) {
                return app(CurrencyService::class)->baseCode();
            }
        } catch (Throwable $e) {
            // ignore
        }

        return (string) (get_setting('default_currency') ?: 'SYP');
    }
}

if (! function_exists('active_currencies')) {
    /**
     * @return list<array<string, mixed>>
     */
    function active_currencies(): array
    {
        try {
            if (Schema::hasTable('currencies')) {
                return app(CurrencyService::class)->active()->values()->all();
            }
        } catch (Throwable $e) {
            // ignore
        }

        return [];
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

        // Some settings (site_logo, favicon, ...) hold a path that is already
        // relative to public/, e.g. "assets/images/logo.png". Prefixing those with
        // storage/ yields a 404, which broke the logo and the JSON-LD logo field.
        foreach (['assets/', 'images/', 'img/', 'css/', 'js/', 'fonts/'] as $publicPrefix) {
            if (str_starts_with($path, $publicPrefix)) {
                return asset($path);
            }
        }

        return asset('storage/'.$path);
    }
}
