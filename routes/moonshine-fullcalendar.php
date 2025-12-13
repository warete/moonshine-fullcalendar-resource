<?php

use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;
use Illuminate\Support\Facades\Route;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Crud\JsonResponse;

Route::moonshine(static function (): void {
    Route::get('full-calendar/events', static function (CrudRequestContract $request) {
        /** @var FullCalendarResource $resource */
        $resource = $request->getResource();
        $start = $request->get('start');
        $end = $request->get('end');

        $query = $resource->getQuery();

        if ($start && $end) {
            $startDate = now()->parse($start)->format('Y-m-d');
            $endDate = now()->parse($end)->format('Y-m-d');

            $startField = $resource->startField ?? 'start';
            $endField = $resource->endField ?? 'end';

            $query->where(function ($q) use ($startField, $endField, $startDate, $endDate): void {
                // Events that start within the range
                $q->whereBetween($startField, [$startDate, $endDate])
                    // Or events that end within the range
                    ->orWhereBetween($endField, [$startDate, $endDate])
                    // Or events that span the entire range
                    ->orWhere(function ($subQ) use ($startField, $endField, $startDate, $endDate): void {
                        $subQ->where($startField, '<=', $startDate)
                            ->where($endField, '>=', $endDate);
                    });
            });
        }

        $items = $query->get();

        // Format items and add action buttons
        $events = $items->map(fn($item) => $resource->formatItem($resource->prepareItem($item)))->toArray();

        return JsonResponse::make($events);
    })->name('full-calendar.events.list');
}, withResource: true, withAuthenticate: true);
