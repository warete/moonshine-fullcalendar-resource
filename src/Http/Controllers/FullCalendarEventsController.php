<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;

final class FullCalendarEventsController
{
    public function __invoke(CrudRequestContract $request, Request $httpRequest): JsonResponse
    {
        $resource = $request->getResource();

        abort_unless($resource instanceof FullCalendarResource, 404, 'FullCalendar resource not found');

        return response()->json($resource->getCalendarItems($httpRequest->query()));
    }
}
