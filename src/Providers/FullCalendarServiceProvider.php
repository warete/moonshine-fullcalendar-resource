<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Providers;

use Illuminate\Support\ServiceProvider;

final class FullCalendarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../../lang', 'moonshine-fullcalendar');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'moonshine-fullcalendar');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/moonshine-fullcalendar.php');

        $this->publishes([
            __DIR__ . '/../../public' => public_path('vendor/moonshine-fullcalendar'),
        ], ['moonshine-fullcalendar-assets', 'laravel-assets']);

        $this->publishes([
            __DIR__ . '/../../lang' => $this->app->langPath('vendor/moonshine-fullcalendar'),
        ]);

        $this->commands([]);
    }
}
