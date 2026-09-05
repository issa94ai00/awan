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

        // Matches CurrencyService::FALLBACK — the two are read as the same answer
        // by the boot payload and the API, so they must not name different money.
        return (string) (get_setting('default_currency') ?: 'USD');
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
        foreach (['assets/', 'images/', 'images_items/', 'img/', 'css/', 'js/', 'fonts/'] as $publicPrefix) {
            if (str_starts_with($path, $publicPrefix)) {
                return asset($path);
            }
        }

        return asset('storage/'.$path);
    }
}

if (! function_exists('image_path')) {
    /**
     * The inverse of image_url(): turn whatever an admin form posts back —
     * an absolute URL, a "/storage/..." path, or a bare relative path — into
     * the relative form that belongs in the database.
     *
     * The forms round-trip images: the API hands them image_url()'s absolute
     * URL and they save it straight back. Without this the host name gets
     * baked into the row, so every image 404s the moment the domain or the
     * scheme changes — and paths that live in public/ rather than storage/
     * (images_items/..., assets/...) never matched the front end's
     * "/storage/" stripping at all.
     */
    function image_path(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '//')) {
            $host = parse_url($url, PHP_URL_HOST);

            $ownHosts = array_values(array_filter([
                parse_url((string) config('app.url'), PHP_URL_HOST),
                request()?->getHost(),
            ]));

            // An image genuinely hosted elsewhere (a CDN, a supplier's
            // catalogue) has no local path — it keeps its absolute URL.
            if ($host && ! in_array($host, $ownHosts, true)) {
                return $url;
            }

            $url = (string) parse_url($url, PHP_URL_PATH);
        }

        // Drop any cache-busting query string the front end appended.
        $url = explode('?', $url)[0];
        $url = ltrim(rawurldecode($url), '/');

        // An installation served from a sub-directory carries that prefix in
        // every asset() URL; it is not part of the stored path.
        $basePath = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        if ($basePath !== '' && str_starts_with($url, $basePath.'/')) {
            $url = substr($url, strlen($basePath) + 1);
        }

        // image_url() prefixes storage/ for anything that is not already a
        // public/ path, so take it back off to reach the stored form.
        if (str_starts_with($url, 'storage/')) {
            $url = substr($url, strlen('storage/'));
        }

        // A path that climbs out of public/ is not an image path.
        if ($url === '' || str_contains($url, '..')) {
            return null;
        }

        return $url;
    }
}
