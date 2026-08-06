<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
    protected $signature = 'sitemap:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the sitemap.xml file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating sitemap...');

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
    }
}
