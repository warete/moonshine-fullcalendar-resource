<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Providers;

use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use Warete\MoonShineFullCalendar\Components\FullCalendarPageComponent;

final class FullCalendarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FullCalendarPageComponent::class, fn($app): FullCalendarPageComponent => new FullCalendarPageComponent($app->make(CoreContract::class)));
    }

    public function boot(): void
    {
        // Load translations
        $this->loadTranslationsFrom(__DIR__ . '/../../lang', 'moonshine-fullcalendar');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'moonshine-fullcalendar');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/moonshine-fullcalendar.php');

        // Publish compiled assets
        $this->publishes([
            __DIR__ . '/../../public' => public_path('vendor/moonshine-fullcalendar'),
        ], ['moonshine-fullcalendar-assets', 'laravel-assets']);

        // Publish translations
        $this->publishes([
            __DIR__ . '/../../lang' => $this->app->langPath('vendor/moonshine-fullcalendar'),
        ], 'moonshine-fullcalendar-lang');
    }
}
