<?php

use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;
use Illuminate\Support\Facades\Route;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Crud\JsonResponse;

Route::moonshine(static function (): void {
    Route::get('full-calendar/events', static function (CrudRequestContract $request) {
        /** @var FullCalendarResource $resource */
        $resource = $request->getResource();

        return JsonResponse::make($resource->getCalendarItems($request));
    })->name('full-calendar.events.list');
}, withResource: true, withAuthenticate: true);
