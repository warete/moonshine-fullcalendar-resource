<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Warete\MoonShineFullCalendar\Http\Controllers\FullCalendarEventsController;

Route::moonshine(static function (): void {
    Route::get('full-calendar/events', FullCalendarEventsController::class)
        ->name('full-calendar.events.list');
}, withResource: true, withAuthenticate: true);
