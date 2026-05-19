<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use App\Models\Product;
use App\Models\Category;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sitemap:generate', function () {
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
})->purpose('Generate the sitemap.xml file');

// Schedule sitemap generation
Schedule::command('sitemap:generate')->daily();
