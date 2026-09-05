<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The lifetime of an uploaded picture, in one place.
 *
 * Uploads land on disk the moment the admin picks a file, long before the form
 * is saved — so replacing a product's main image, dropping a photo from its
 * gallery, or deleting the product outright left the old file behind for good.
 * Only SpecialOfferController ever cleaned up after itself, and it did so by
 * handing whatever string sat in the column straight to Storage::delete().
 *
 * Two rules make that safe to do everywhere:
 *
 *  1. Only files this application wrote may be deleted (see MANAGED_PREFIXES).
 *     Most of the catalogue's pictures are shipped assets under public/ —
 *     images_items/, assets/, img/ — shared by many products and not ours to
 *     remove; suppliers' absolute URLs are not on our disk at all.
 *
 *  2. A file is only deleted once nothing points at it any more. The same
 *     upload legitimately appears twice: "set as main image" copies a gallery
 *     path into image_main, and an order line keeps a snapshot of the picture
 *     the customer actually bought. So callers save first and prune second,
 *     and this class asks the database what is still in use.
 */
class ImageStore
{
    /**
     * Directories on the public disk that this application writes to, and
     * therefore the only ones it may delete from.
     */
    public const MANAGED_PREFIXES = ['uploads/', 'special-offers/', 'settings/'];

    /**
     * Where a stored image path can still be referenced from.
     *
     * Read with the query builder rather than the models on purpose: a
     * soft-deleted product is still a reference — restoring it should not
     * bring back a row whose pictures were swept in the meantime.
     *
     * @var array<string, array{exact: list<string>, json: list<string>}>
     */
    private const REFERENCES = [
        'products' => ['exact' => ['image_main'], 'json' => ['image_gallery']],
        'categories' => ['exact' => ['image'], 'json' => []],
        'special_offers' => ['exact' => ['image'], 'json' => []],
        'order_items' => ['exact' => ['product_image'], 'json' => []],
        'reviews' => ['exact' => [], 'json' => ['images']],
        'settings' => ['exact' => ['value'], 'json' => []],
    ];

    /**
     * Every stored image path a value holds.
     *
     * Accepts what the columns and the forms actually carry: a single path, an
     * absolute URL, the JSON string the gallery column keeps, a plain array, or
     * el-upload's `{url, name, size}` file objects.
     *
     * @return list<string>
     */
    public static function paths(mixed $value): array
    {
        return collect(self::flatten($value))
            ->map(fn (string $candidate) => image_path($candidate))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Is this a file we put on the public disk ourselves?
     */
    public static function isManaged(?string $path): bool
    {
        $path = image_path($path);

        // image_path() leaves a genuinely external URL (a CDN, a supplier's
        // catalogue) intact and returns null for anything climbing out of
        // public/; neither is a file on our disk.
        return $path !== null
            && ! Str::startsWith($path, ['http://', 'https://', '//'])
            && Str::startsWith($path, self::MANAGED_PREFIXES);
    }

    /**
     * Does any row still show this picture?
     */
    public static function isReferenced(string $path): bool
    {
        return self::unreferenced([$path]) === [];
    }

    /**
     * Of the given paths, the ones nothing points at any more.
     *
     * Takes a batch because answering costs one pass over the JSON columns
     * (see below) however many paths are asked about.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    public static function unreferenced(array $paths): array
    {
        $paths = array_values(array_unique($paths));

        if ($paths === []) {
            return [];
        }

        $inUse = self::jsonPaths();

        return array_values(array_filter(
            $paths,
            fn (string $path) => ! isset($inUse[$path]) && ! self::hasPlainReference($path)
        ));
    }

    /**
     * Columns that hold the path verbatim can be asked about directly, and are
     * indexed, so they are queried per path rather than read whole.
     */
    private static function hasPlainReference(string $path): bool
    {
        foreach (self::REFERENCES as $table => $columns) {
            if ($columns['exact'] === [] || ! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table);
            $matched = false;

            foreach ($columns['exact'] as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $query->orWhere($column, $path);
                $matched = true;
            }

            if ($matched && $query->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every path currently held by a JSON column, as a set.
     *
     * These columns cannot be matched with LIKE: json_encode()'s defaults
     * escape the slashes and every non-ASCII character, so a gallery holds
     * "uploads\/\u0645….jpg" for a file whose path is "uploads/م….jpg".
     * Matching that text would mean building the escaped form and then
     * escaping *that* for LIKE — and a backslash in a LIKE pattern means
     * different things to MySQL and SQLite, so the same code would answer one
     * way in production and another under test. A reference that fails to
     * match deletes a picture that is still on a product, so the columns are
     * decoded and compared exactly instead. Reading them costs a single pass
     * (about 10ms over this catalogue), and is deliberately not cached: a
     * stale answer here is a deleted file.
     *
     * @return array<string, true>
     */
    private static function jsonPaths(): array
    {
        $paths = [];

        foreach (self::REFERENCES as $table => $columns) {
            foreach ($columns['json'] as $column) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->select('id', $column)
                    ->whereNotNull($column)
                    ->orderBy('id')
                    ->chunk(500, function ($rows) use (&$paths, $column) {
                        foreach ($rows as $row) {
                            foreach (self::paths($row->{$column}) as $path) {
                                $paths[$path] = true;
                            }
                        }
                    });
            }
        }

        return $paths;
    }

    /**
     * Delete the given files, skipping anything that is not ours or is still
     * in use. Call it *after* the row has been saved or deleted.
     *
     * @return int number of files actually removed
     */
    public static function forget(mixed $paths): int
    {
        $candidates = array_values(array_filter(
            self::paths($paths),
            fn (string $path) => self::isManaged($path)
        ));

        $removed = 0;

        foreach (self::unreferenced($candidates) as $path) {
            try {
                $disk = Storage::disk('public');

                if ($disk->exists($path) && $disk->delete($path)) {
                    $removed++;
                }
            } catch (Throwable $e) {
                // A file that will not go away must never fail the request
                // that replaced it: the row is already saved and correct, and
                // an orphan on disk is a housekeeping problem (images:prune),
                // not an error the admin can act on.
                Log::warning('ImageStore: failed to delete image', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $removed;
    }

    /**
     * Delete whatever the new value dropped. The usual shape of an update:
     * read the old paths, save the row, then hand both states to this.
     */
    public static function forgetReplaced(mixed $before, mixed $after): int
    {
        return self::forget(array_diff(self::paths($before), self::paths($after)));
    }

    /**
     * @return list<string>
     */
    private static function flatten(mixed $value): array
    {
        if (is_array($value)) {
            // el-upload posts its picker entries as objects, not strings.
            if (isset($value['url']) || isset($value['path'])) {
                return self::flatten($value['url'] ?? $value['path']);
            }

            return collect($value)->flatMap(fn ($item) => self::flatten($item))->all();
        }

        if (! is_string($value)) {
            return [];
        }

        $value = trim($value);

        if ($value === '') {
            return [];
        }

        // The gallery column keeps its list as a JSON string.
        if (Str::startsWith($value, ['[', '{'])) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return self::flatten($decoded);
            }
        }

        return [$value];
    }
}
