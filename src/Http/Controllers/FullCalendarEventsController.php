<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Crud\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
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

        try {
            $validated = Validator::make($payload, [
                'start' => ['required', 'date'],
                'end' => ['nullable', 'date'],
                'allDay' => ['nullable', 'boolean'],
                'action' => ['required', 'string', 'in:drop,resize'],
                'timezone' => ['nullable', 'string'],
            ])->validate();

            return $resource->updateCalendarEventDates((string) $resourceItem, $validated, $request);
        } catch (ValidationException $e) {

            $message = $e->validator->errors()->first() ?: __('moonshine::ui.saved_error');
            $response = JsonResponse::make([
                'message' => $message,
                'messageType' => 'error',
                'errors' => $e->errors(),
            ])->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);

            return $resource->modifyErrorResponse($response, $e);
        } catch (HttpExceptionInterface $e) {

            $response = JsonResponse::make([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : __('moonshine::ui.saved_error'),
                'messageType' => 'error',
            ])->setStatusCode($e->getStatusCode());

            return $resource->modifyErrorResponse($response, $e);
        } catch (Throwable $e) {

            $response = JsonResponse::make([
                'message' => __('moonshine::ui.saved_error'),
                'messageType' => 'error',
            ])->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);

            return $resource->modifyErrorResponse($response, $e);
        }
    }
}
