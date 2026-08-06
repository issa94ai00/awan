<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// `sitemap:generate` lives in App\Console\Commands\GenerateSitemap.
// Defining it here as well would register the same signature twice.

// Schedule sitemap generation
Schedule::command('sitemap:generate')->daily();
