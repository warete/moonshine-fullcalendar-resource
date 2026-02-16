<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Http\Controllers;

use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Crud\JsonResponse;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;

final class FullCalendarEventsController
{
    public function __invoke(CrudRequestContract $request): JsonResponse
    {
        $resource = $request->getResource();

        abort_unless($resource instanceof FullCalendarResource, 404, 'FullCalendar resource not found');

        $events = $resource->getCalendarItems($request->all());

        return JsonResponse::make($events);
    }
}
