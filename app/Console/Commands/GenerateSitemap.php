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

        Sitemap::create()
            ->add(Url::create(route('home'))
                ->setChangeFrequency('daily')
                ->setPriority(1.0))
            ->add(Url::create(route('categories.index'))
                ->setChangeFrequency('daily')
                ->setPriority(0.9))
            ->add(Url::create(route('featured.products'))
                ->setChangeFrequency('daily')
                ->setPriority(0.9))
            ->add(Url::create(route('about'))
                ->setChangeFrequency('monthly')
                ->setPriority(0.6))
            ->add(Url::create(route('vision'))
                ->setChangeFrequency('monthly')
                ->setPriority(0.6))
            ->add(Url::create(route('contact'))
                ->setChangeFrequency('monthly')
                ->setPriority(0.6))
            ->add(Category::all())
            ->add(Product::all())
            ->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap generated successfully!');
    }
}
