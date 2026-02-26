<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Crud\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;

final class FullCalendarEventsController
{
    public function index(CrudRequestContract $request): JsonResponse
    {
        $resource = $request->getResource();

        abort_unless($resource instanceof FullCalendarResource, 404, 'FullCalendar resource not found');

        $events = $resource->getCalendarItems($request->all());

        return JsonResponse::make($events);
    }

    public function updateDates(CrudRequestContract $request): Response
    {
        $resource = $request->getResource();

        abort_unless($resource instanceof FullCalendarResource, 404, 'FullCalendar resource not found');

        $payload = $request->all();
        $resourceItem = $request->route('resourceItem');

        Log::debug('[FullCalendarEventsController] Update dates request received', [
            'resource' => $resource->getUriKey(),
            'resourceItem' => $resourceItem,
            'payload_keys' => array_keys($payload),
        ]);

        try {
            $validated = Validator::make($payload, [
                'start' => ['required', 'date'],
                'end' => ['nullable', 'date'],
                'allDay' => ['nullable', 'boolean'],
                'action' => ['required', 'string', 'in:drop,resize'],
                'timezone' => ['nullable', 'string'],
            ])->validate();

            Log::info('[FullCalendarEventsController] Update dates request validated', [
                'resource' => $resource->getUriKey(),
                'resourceItem' => $resourceItem,
                'action' => $validated['action'] ?? null,
                'timezone' => $validated['timezone'] ?? null,
            ]);

            return $resource->updateCalendarEventDates((string) $resourceItem, $validated, $request);
        } catch (ValidationException $e) {
            Log::warning('[FullCalendarEventsController] Update dates validation failed', [
                'resource' => $resource->getUriKey(),
                'resourceItem' => $resourceItem,
                'errors' => $e->errors(),
            ]);

            $message = $e->validator->errors()->first() ?: __('moonshine::ui.saved_error');
            $response = JsonResponse::make([
                'message' => $message,
                'messageType' => 'error',
                'errors' => $e->errors(),
            ])->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);

            return $resource->modifyErrorResponse($response, $e);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            Log::warning('[FullCalendarEventsController] Update dates http exception', [
                'resource' => $resource->getUriKey(),
                'resourceItem' => $resourceItem,
                'status' => $e->getStatusCode(),
                'message' => $e->getMessage(),
            ]);

            $response = JsonResponse::make([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : __('moonshine::ui.saved_error'),
                'messageType' => 'error',
            ])->setStatusCode($e->getStatusCode());

            return $resource->modifyErrorResponse($response, $e);
        } catch (\Throwable $e) {
            Log::error('[FullCalendarEventsController] Update dates failed', [
                'resource' => $resource->getUriKey(),
                'resourceItem' => $resourceItem,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $response = JsonResponse::make([
                'message' => __('moonshine::ui.saved_error'),
                'messageType' => 'error',
            ])->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);

            return $resource->modifyErrorResponse($response, $e);
        }
    }
}
