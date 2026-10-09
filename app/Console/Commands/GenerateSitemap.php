<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL as UrlGenerator;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Sitemap as SitemapTag;
use Spatie\Sitemap\Tags\Url;
use App\Models\Product;
use App\Models\Category;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate
                            {--base-url= : Absolute site root to write into every <loc>. Defaults to app.url.}
                            {--allow-local : Permit a development host, for inspecting output locally.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the sitemap.xml file';

    /**
     * Hosts a search engine can never fetch.
     *
     * A sitemap is only accepted for the site it is served from, so a file full
     * of `http://awan.test` URLs is rejected wholesale — which is exactly what
     * happened: this command run on a developer machine wrote 286 dev-host
     * entries into `public/sitemap.xml`, and that file shipped.
     */
    private const LOCAL_HOST_SUFFIXES = ['.test', '.local', '.localhost', '.example', '.invalid'];

    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];

    /**
     * URLs per product sitemap file. Google caps a sitemap at 50,000 URLs and
     * 50MB; staying far under both keeps every chunk fetchable in one request
     * and small enough to diff when something looks wrong.
     */
    private const PRODUCTS_PER_FILE = 5000;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $baseUrl = $this->resolveBaseUrl();

        if ($baseUrl === null) {
            return self::FAILURE;
        }

        // `route()` has no request to read a host from on the console, so it
        // falls back to app.url. Pinning the generator makes that explicit and
        // covers the model URLs (Category and Product both build theirs with
        // `route()`) as well as the static entries below.
        UrlGenerator::forceRootUrl($baseUrl);

        if (str_starts_with($baseUrl, 'https://')) {
            UrlGenerator::forceScheme('https');
        }

        $this->info("Generating sitemap for {$baseUrl} ...");

        // Content is split into per-type child sitemaps and a sitemap index at
        // /sitemap.xml. Crawlers read only the index; Search Console then
        // reports indexing status separately for pages, categories and products.
        //
        // Only advertise URLs that actually render: PublicPageController 404s on
        // inactive categories/products, so listing them here would create dead entries.
        $children = [
            'pages' => $this->buildPagesSitemap(),
            'categories' => $this->buildCategoriesSitemap(),
        ];

        // Products spill over several files once the catalog outgrows one: each
        // file stays small enough for a crawler to fetch in a single request,
        // and Search Console can attribute indexing problems to one chunk
        // rather than to a monolith. The first chunk keeps the historical name
        // so existing Search Console entries carry over.
        $productSitemaps = $this->buildProductSitemaps();

        foreach ($productSitemaps as $index => $sitemap) {
            $children[$index === 0 ? 'products' : 'products-'.($index + 1)] = $sitemap;
        }

        $publicDir = public_path();
        $this->removeStaleProductChunks($publicDir);
        $index = SitemapIndex::create();

        foreach ($children as $name => $sitemap) {
            $this->writeAtomically("{$publicDir}/sitemap-{$name}.xml", $sitemap->render());

            // The index's <lastmod> tells a crawler which child to refetch, so it
            // carries the newest entry in that child rather than the time of this
            // run — otherwise the daily schedule marks every file as changed.
            $tag = SitemapTag::create("{$baseUrl}/sitemap-{$name}.xml");
            $lastModified = $this->newestModification($sitemap);

            if ($lastModified !== null) {
                $tag->setLastModificationDate($lastModified);
            } else {
                // The tag defaults to now() and its property is not nullable;
                // left uninitialised, the index view omits <lastmod> entirely.
                unset($tag->lastModificationDate);
            }

            $index->add($tag);
        }

        // Written last, so it never points at a child that is not on disk yet.
        $this->writeAtomically("{$publicDir}/sitemap.xml", $index->render());

        $this->info(sprintf(
            'Sitemap generated successfully! (%d pages, %d categories, %d products in %d file(s))',
            count($children['pages']->getTags()),
            count($children['categories']->getTags()),
            collect($productSitemaps)->sum(fn ($sitemap) => count($sitemap->getTags())),
            count($productSitemaps)
        ));

        return self::SUCCESS;
    }

    /**
     * Taxonomy order, not id order: every parent category, immediately followed
     * by its own children, each group in `sort_order`. That mirrors how the
     * storefront nests the menu, so a reviewer scanning the file (or Search
     * Console's URL inspection list) sees each section as a contiguous block
     * instead of categories scattered by insertion date.
     */
    private function buildCategoriesSitemap(): Sitemap
    {
        $categories = Category::where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byParent = $categories->groupBy('parent_id');

        $ordered = $byParent->get(null, collect())
            ->flatMap(fn ($parent) => collect([$parent])->concat($byParent->get($parent->id, collect())));

        // A child whose parent was deactivated would fall out of the walk
        // above; appending it keeps the sitemap equal to the active set.
        $ordered = $ordered->concat(
            $categories->reject(fn ($category) => $ordered->contains('id', $category->id))
        );

        $sitemap = Sitemap::create();

        $ordered->each(fn ($category) => $sitemap->add($category));

        return $sitemap;
    }

    /**
     * Products grouped by category — category `sort_order`, then the product's
     * own `sort_order` — so each chunk of the split covers a run of related
     * items instead of an arbitrary id range. Streamed in chunks rather than
     * loaded at once; the sort is stable, so consecutive runs with no data
     * change write identical files.
     *
     * @return array<int, Sitemap>
     */
    private function buildProductSitemaps(): array
    {
        $sitemaps = [];
        $sitemap = Sitemap::create();
        $count = 0;

        Product::query()
            ->where('products.is_active', 1)
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->select('products.*')
            // Products with no (loaded) category sort last rather than first;
            // COALESCE keeps MySQL off filesort for the null branch.
            ->orderByRaw('COALESCE(categories.sort_order, 2147483647)')
            ->orderByRaw('COALESCE(categories.id, 2147483647)')
            ->orderBy('products.sort_order')
            ->orderBy('products.id')
            ->lazy(500)
            ->each(function ($product) use (&$sitemap, &$count, &$sitemaps) {
                $sitemap->add($product);

                if (++$count % self::PRODUCTS_PER_FILE === 0) {
                    $sitemaps[] = $sitemap;
                    $sitemap = Sitemap::create();
                }
            });

        if ($count % self::PRODUCTS_PER_FILE !== 0) {
            $sitemaps[] = $sitemap;
        }

        return $sitemaps;
    }

    /**
     * A previous run may have written more product chunks than this one needs
     * (a bulk deactivation, for example). Left on disk they still answer to a
     * direct fetch, advertising products the index no longer claims.
     */
    private function removeStaleProductChunks(string $publicDir): void
    {
        foreach (glob("{$publicDir}/sitemap-products-*.xml") ?: [] as $stale) {
            unlink($stale);
        }
    }

    private function newestModification(Sitemap $sitemap): ?Carbon
    {
        return collect($sitemap->getTags())
            ->map(fn ($tag) => $tag instanceof Url ? $tag->lastModificationDate : null)
            ->filter()
            ->max();
    }

    /**
     * The files are served straight from public/, so a crawler fetching one
     * mid-write would get truncated XML. Rename is atomic on the same filesystem.
     */
    private function writeAtomically(string $path, string $contents): void
    {
        $temporary = $path.'.tmp';

        file_put_contents($temporary, $contents);
        rename($temporary, $path);
    }

    /**
     * The handful of hand-written Blade-routed pages, unchanged from before —
     * they barely change, so they carry no <lastmod>.
     */
    private function buildPagesSitemap(): Sitemap
    {
        return Sitemap::create()
            ->add(Url::create(route('home'))
                ->setChangeFrequency('daily')
                ->setPriority(1.0))
            ->add(Url::create(route('categories.index'))
                ->setChangeFrequency('daily')
                ->setPriority(0.9))
            ->add(Url::create(route('products.index'))
                ->setChangeFrequency('daily')
                ->setPriority(0.9))
            ->add(Url::create(route('featured.products'))
                ->setChangeFrequency('daily')
                ->setPriority(0.9))
            ->add(Url::create(route('special-offers'))
                ->setChangeFrequency('daily')
                ->setPriority(0.8))
            ->add(Url::create(route('about'))
                ->setChangeFrequency('monthly')
                ->setPriority(0.6))
            ->add(Url::create(route('vision'))
                ->setChangeFrequency('monthly')
                ->setPriority(0.6))
            ->add(Url::create(route('contact'))
                ->setChangeFrequency('monthly')
                ->setPriority(0.6))
            ->add(Url::create(route('inquiry.create'))
                ->setChangeFrequency('monthly')
                ->setPriority(0.5))
            ->add(Url::create(route('purchase-request.create'))
                ->setChangeFrequency('monthly')
                ->setPriority(0.5));
    }

    /**
     * The site root every `<loc>` will carry, or null when it cannot be trusted.
     *
     * Refusing beats writing: a sitemap with the wrong host is not a partial
     * result a crawler can salvage, and the failure is silent at the point it
     * happens — it only surfaces days later in Search Console, by which time
     * the bad file is already the deployed one.
     */
    private function resolveBaseUrl(): ?string
    {
        $baseUrl = rtrim((string) ($this->option('base-url') ?: config('app.url')), '/');
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if ($baseUrl === '' || $host === null || $host === false) {
            $this->error('No usable site URL. Set APP_URL, or pass --base-url=https://example.com');

            return null;
        }

        if (! $this->option('allow-local') && $this->isLocalHost($host)) {
            $this->error("Refusing to write a sitemap for the development host \"{$host}\".");
            $this->line('');
            $this->line('Search engines reject a sitemap whose URLs point somewhere other than the');
            $this->line('site serving it, so this file would be discarded in full.');
            $this->line('');
            $this->line('  Generate on the server, where APP_URL is the public domain, or run:');
            $this->line('    php artisan sitemap:generate --base-url=https://your-domain.com');
            $this->line('');
            $this->line('  To inspect the output locally anyway, add --allow-local.');

            return null;
        }

        return $baseUrl;
    }

    private function isLocalHost(string $host): bool
    {
        $host = strtolower($host);

        if (in_array($host, self::LOCAL_HOSTS, true)) {
            return true;
        }

        foreach (self::LOCAL_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        // A bare address is a machine, not a site worth advertising to a crawler.
        return filter_var($host, FILTER_VALIDATE_IP) !== false;
    }
}
