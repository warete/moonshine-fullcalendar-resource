<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Warete\MoonShineFullCalendar\Http\Controllers\FullCalendarEventsController;

Route::moonshine(static function (): void {
    Route::get('full-calendar/events', [FullCalendarEventsController::class, 'index'])
        ->name('full-calendar.events.list');

    Route::patch('full-calendar/events/{resourceItem}/dates', [FullCalendarEventsController::class, 'updateDates'])
        ->name('full-calendar.events.dates.update');
}, withResource: true, withAuthenticate: true);
