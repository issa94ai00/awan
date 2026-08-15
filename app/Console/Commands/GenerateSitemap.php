<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL as UrlGenerator;
use Spatie\Sitemap\Sitemap;
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

        $sitemap = Sitemap::create()
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

        // Only advertise URLs that actually render: PublicPageController 404s on
        // inactive categories/products, so listing them here would create dead entries.
        $categories = Category::where('is_active', 1)->get();
        $products = Product::where('is_active', 1)->get();

        $sitemap->add($categories)
            ->add($products)
            ->writeToFile(public_path('sitemap.xml'));

        $this->info(sprintf(
            'Sitemap generated successfully! (%d categories, %d products)',
            $categories->count(),
            $products->count()
        ));

        return self::SUCCESS;
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
