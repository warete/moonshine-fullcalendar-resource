<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Resources;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ModalContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Laravel\TypeCasts\ModelDataWrapper;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\HttpMethod;
use MoonShine\Support\Enums\ToastType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * @template TData of Model
 * @template-covariant TIndexPage of \MoonShine\Crud\Contracts\Page\IndexPageContract = null
 * @template-covariant TFormPage of \MoonShine\Crud\Contracts\Page\FormPageContract = null
 * @template-covariant TDetailPage of \MoonShine\Crud\Contracts\Page\DetailPageContract = null
 *
 * @extends ModelResource<TData, TIndexPage, TFormPage, TDetailPage>
 */
abstract class FullCalendarResource extends ModelResource
{
    /**
     * Calendar resources usually create records from date clicks, so modal create is on by default.
     * Consumers can opt out by overriding this property in their resource.
     */
    protected bool $createInModal = true;

    /**
     * Calendar resources usually need async modal editing for event-click actions.
     * Consumers can opt out by overriding this property in their resource.
     */
    protected bool $editInModal = true;

    /**
     * Calendar event details are also expected to open in a modal by default.
     * Consumers can opt out by overriding this property in their resource.
     */
    protected bool $detailInModal = true;

    /**
     * FullCalendar view modes
     */
    final public const VIEW_MONTH = 'dayGridMonth';
    final public const VIEW_WEEK = 'timeGridWeek';
    final public const VIEW_DAY = 'timeGridDay';
    final public const VIEW_LIST = 'listWeek';

    /**
     * Default calendar view
     */
    protected string $defaultView = self::VIEW_MONTH;

    /**
     * Calendar header toolbar configuration
     */
    protected array $headerToolbar = [
        'left' => 'prev,next today',
        'center' => 'title',
        'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
    ];

    /**
     * FullCalendar configuration options
     */
    protected array $calendarOptions = [];

    /**
     * Enable editable events (drag & drop)
     */
    protected bool $editable = false;

    /**
     * Enable selectable dates
     */
    protected bool $selectable = true;

    /**
     * Locale for FullCalendar
     */
    protected string $locale = 'en';

    /**
     * Timezone for calendar display
     */
    protected ?string $timezone = null;

    /**
     * Date column name for start datetime (override in your resource)
     */
    protected string $startColumn = 'start';

    /**
     * Date column name for end datetime (override in your resource)
     */
    protected string $endColumn = 'end';

    /**
     * Top-level namespace for custom FullCalendar payload metadata inside extendedProps.
     */
    protected string $calendarEventPayloadKey = 'moonshineFullCalendar';

    protected string $calendarEventDateUpdateIdPlaceholder = '__RESOURCE_ITEM__';

    protected string $calendarCreateModalName = 'resource-create-modal-calendar-grid';

    /**
     * When enabled, create-from-grid opens only on the second click on a date cell/slot.
     */
    protected bool $createFromGridOnDoubleClick = true;

    public function __construct(CoreContract $core)
    {
        $this->timezone ??= config('app.timezone');

        parent::__construct($core);
    }

    /**
     * Get calendar items for FullCalendar
     * Uses Eloquent query builder with date range filtering
     *
     * @param array $params Query parameters (start, end, filters)
     * @return array Formatted events for FullCalendar
     */
    public function getCalendarItems(array $params = []): array
    {
        $start = $params['start'] ?? null;
        $end = $params['end'] ?? null;
        $items = $this->fetchEvents($start, $end, $params);

        $formatted = [];
        foreach ($items as $item) {
            $formattedEvent = $this->formatEvent($item);
            $formatted[] = $this->ensureCalendarEventActionsPayload($formattedEvent, $item);
        }

        return $formatted;
    }

    /**
     * Fetch events from Eloquent model using query builder
     * Override this method for custom fetching logic
     *
     * @param string|null $start Start date (ISO8601)
     * @param string|null $end End date (ISO8601)
     * @param array $params Additional query parameters
     * @return iterable Raw events from Eloquent model
     */
    protected function fetchEvents(?string $start, ?string $end, array $params): iterable
    {

        $query = $this->getQuery();

        $normalizedStart = $this->normalizeQueryDateTime($start);
        $normalizedEnd = $this->normalizeQueryDateTime($end);

        // Apply date range filtering if provided
        if ($normalizedStart && $normalizedEnd) {
            $query->where(function ($q) use ($normalizedStart, $normalizedEnd): void {
                // FullCalendar end is exclusive. Select events that overlap [start, end).
                $q->where(function ($q) use ($normalizedStart, $normalizedEnd): void {
                    $q->where($this->startColumn, '<', $normalizedEnd)
                        ->where($this->endColumn, '>', $normalizedStart);
                })->orWhere(function ($q) use ($normalizedStart, $normalizedEnd): void {
                    // Fallback for records without end date: point-in-time events by start only
                    $q->whereNull($this->endColumn)
                        ->where($this->startColumn, '>=', $normalizedStart)
                        ->where($this->startColumn, '<', $normalizedEnd);
                });
            });

        }

        // Apply any additional filters from params
        if (isset($params['filters']) && is_array($params['filters'])) {
            foreach ($params['filters'] as $column => $value) {
                if ($value !== null && $value !== '') {
                    $query->where($column, $value);
                }
            }
        }

        // Apply ordering
        $query->orderBy($this->startColumn, 'asc');

        return $query->get();
    }

    /**
     * Normalize FullCalendar ISO8601 query boundary to DB-friendly datetime string.
     */
    protected function normalizeQueryDateTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $timezone = $this->timezone ?: config('app.timezone');

            return Carbon::parse($value)->setTimezone($timezone)->format('Y-m-d H:i:s');
        } catch (Throwable) {

            return $value;
        }
    }

    /**
     * Format a single Eloquent model event for FullCalendar
     * Override this method for custom formatting
     *
     * @param Model $item Eloquent model instance
     * @return array Formatted event with keys: id, title, start, end, color, description, etc.
     */
    protected function formatEvent(Model $item): array
    {

        $event = [
            'id' => $item->getKey(),
            'title' => $item->getAttribute('title') ?: 'Untitled',
            'start' => $this->formatDateTime($item->getAttribute($this->startColumn)),
            'end' => $this->formatDateTime($item->getAttribute($this->endColumn)),
            'editable' => $this->editable,
            'allDay' => $this->isAllDay($item),
        ];

        // Add optional color if model has color/background_color attribute
        if ($item->hasAttribute('color')) {
            $event['color'] = $item->getAttribute('color');
        } elseif ($item->hasAttribute('background_color')) {
            $event['color'] = $item->getAttribute('background_color');
        }

        // Add optional description if model has description/body attribute
        if ($item->hasAttribute('description')) {
            $event['description'] = $item->getAttribute('description');
        } elseif ($item->hasAttribute('body')) {
            $event['description'] = $item->getAttribute('body');
        }

        // Additional event payload is opt-in to avoid leaking internal model attributes.
        $extendedProps = $this->getCalendarEventExtendedProps($item);

        $eventActionsPayload = $this->buildCalendarEventActionsPayload($item);

        $extendedProps[$this->calendarEventPayloadKey] = [
            'actions' => $eventActionsPayload,
        ];

        $event['extendedProps'] = $extendedProps;

        return $event;
    }

    /**
     * Override to expose extra event metadata to the browser.
     *
     * Default is intentionally empty so packages do not leak model attributes.
     *
     * @return array<string, mixed>
     */
    protected function getCalendarEventExtendedProps(Model $item): array
    {
        unset($item);

        return [];
    }

    /**
     * Ensure actions payload exists even when a consumer resource overrides formatEvent()
     * and does not call parent::formatEvent().
     *
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    protected function ensureCalendarEventActionsPayload(array $event, Model $item): array
    {
        $existingActions = $event['extendedProps'][$this->calendarEventPayloadKey]['actions'] ?? null;

        if (is_array($existingActions)) {

            return $event;
        }

        $payload = $this->buildCalendarEventActionsPayload($item);

        $extendedProps = $event['extendedProps'] ?? [];
        if (! is_array($extendedProps)) {
            $extendedProps = [];
        }

        $extendedProps[$this->calendarEventPayloadKey] = [
            'actions' => $payload,
        ];

        $event['extendedProps'] = $extendedProps;

        return $event;
    }

    /**
     * Format datetime for FullCalendar (ISO8601)
     */
    protected function formatDateTime($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('c');
        }

        if (is_string($value)) {
            try {
                $timezone = $this->timezone ?: config('app.timezone');
                $parsed = Carbon::parse($value, $timezone);

                return $parsed->format('c');
            } catch (Throwable) {

                return $value;
            }
        }

        return null;
    }

    /**
     * Determine if event is all-day
     */
    protected function isAllDay(Model $item): bool
    {
        if ($item->hasAttribute('all_day')) {
            return (bool) $item->getAttribute('all_day');
        }

        if ($item->hasAttribute('allDay')) {
            return (bool) $item->getAttribute('allDay');
        }

        // Default: check if time component is 00:00:00 for both start and end
        $start = $item->getAttribute($this->startColumn);
        $end = $item->getAttribute($this->endColumn);

        if ($start && $end) {
            $startStr = $this->formatDateTime($start);
            $endStr = $this->formatDateTime($end);

            return preg_match('/T00:00:00/', (string) $startStr) && preg_match('/T00:00:00/', (string) $endStr);
        }

        return false;
    }

    /**
     * Build a stable actions payload for a single calendar event.
     *
     * Contract (under `extendedProps.<calendarEventPayloadKey>.actions`):
     * - `version` (int)
     * - `html` (string)
     * - `count` (int)
     * - `hasActions` (bool)
     *
     * @return array{version:int,html:string,count:int,hasActions:bool}
     */
    protected function buildCalendarEventActionsPayload(Model $item): array
    {
        $renderedButtons = $this->renderCalendarEventActionsHtml($item);

        return [
            'version' => 1,
            'html' => implode('', $renderedButtons),
            'count' => count($renderedButtons),
            'hasActions' => $renderedButtons !== [],
        ];
    }

    /**
     * @return list<ActionButtonContract|string>
     */
    protected function getCalendarEventActions(Model $item): array
    {
        $defaultActions = $this->getDefaultCalendarEventActions($item);
        $customActions = $this->normalizeCustomCalendarEventActions($this->getCustomCalendarEventActions($item), $item);

        return [...$defaultActions, ...$customActions];
    }

    /**
     * Override to append custom per-event action buttons.
     *
     * Supported values:
     * - `ActionButtonContract`
     * - raw HTML `string`
     * - iterable of the above
     *
     * @return iterable<ActionButtonContract|string>|ActionButtonContract|string|null
     */
    protected function getCustomCalendarEventActions(Model $item): iterable|ActionButtonContract|string|null
    {
        return [];
    }

    /**
     * @return list<ActionButtonContract>
     */
    protected function getDefaultCalendarEventActions(Model $item): array
    {
        $wrapped = new ModelDataWrapper($item);
        $this->setItem($item);

        $actions = [
            $this->ensureCalendarActionIsAsync(
                $this->getDetailButton()
                    ->setData($wrapped),
                $item,
                'detail'
            ),
            $this->ensureCalendarActionIsAsync(
                $this->getEditButton(isAsync: true)
                    ->setData($wrapped),
                $item,
                'edit'
            ),
            $this->ensureCalendarActionIsAsync(
                $this->getDeleteButton(isAsync: true)
                    ->setData($wrapped),
                $item,
                'delete'
            ),
        ];

        return array_values(array_filter($actions, static fn ($action): bool => $action instanceof ActionButtonContract));
    }

    /**
     * @param iterable<ActionButtonContract|string>|ActionButtonContract|string|null $actions
     * @return list<ActionButtonContract|string>
     */
    protected function normalizeCustomCalendarEventActions(iterable|ActionButtonContract|string|null $actions, Model $item): array
    {
        if ($actions === null) {
            return [];
        }

        $list = [];
        $source = $actions;

        if ($actions instanceof ActionButtonContract || is_string($actions)) {
            $source = [$actions];
        }

        foreach ($source as $action) {
            if ($action instanceof ActionButtonContract) {
                $action->setData(new ModelDataWrapper($item));

                $list[] = $this->ensureCalendarActionIsAsync($action, $item, 'custom');

                continue;
            }

            if (is_string($action)) {
                $list[] = $action;

                continue;
            }

        }

        return $list;
    }

    protected function ensureCalendarActionIsAsync(ActionButtonContract $action, Model $item, string $source): ActionButtonContract
    {
        if (method_exists($action, 'isAsync') && $action->isAsync()) {
            return $action;
        }

        // Buttons with embedded modal/offcanvas components (Edit/Delete defaults) can still
        // execute async inside the modal flow; avoid clobbering their behavior.
        if (method_exists($action, 'hasComponent') && $action->hasComponent()) {

            return $action;
        }

        if (method_exists($action, 'async')) {
            $action->async(HttpMethod::GET);

        }

        return $action;
    }

    /**
     * @return list<string>
     */
    protected function renderCalendarEventActionsHtml(Model $item): array
    {
        $rendered = [];

        foreach ($this->getCalendarEventActions($item) as $action) {
            if (is_string($action)) {
                if (trim($action) !== '') {
                    $rendered[] = $action;
                }

                continue;
            }

            if (! ($action instanceof ActionButtonContract)) {

                continue;
            }

            $html = trim((string) $action);

            if ($html === '') {
                continue;
            }

            $rendered[] = $html;
        }

        return $rendered;
    }

    /**
     * Get the API endpoint for fetching events
     */
    public function getEventsEndpoint(): string
    {

        // Build the endpoint URL manually for resource-scoped routes
        // The route is: /admin/resource/{resourceUri}/full-calendar/events
        $this->getUriKey();

        return moonshineRouter()->to('full-calendar.events.list', ['resourceUri' => $this->getUriKey()]);
    }

    public function getEventDatesUpdateEndpointTemplate(): string
    {
        return moonshineRouter()->to('full-calendar.events.dates.update', [
            'resourceUri' => $this->getUriKey(),
            'resourceItem' => $this->calendarEventDateUpdateIdPlaceholder,
        ]);
    }

    /**
     * Get calendar configuration for Alpine.js
     */
    public function getCalendarConfig(): array
    {
        return array_merge([
            'initialView' => $this->defaultView,
            'headerToolbar' => $this->headerToolbar,
            'editable' => $this->editable,
            'selectable' => $this->selectable,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'events' => [
                'url' => $this->getEventsEndpoint(),
                'method' => 'GET',
            ],
            'eventDateUpdate' => [
                'urlTemplate' => $this->getEventDatesUpdateEndpointTemplate(),
                'method' => 'PATCH',
                'idPlaceholder' => $this->calendarEventDateUpdateIdPlaceholder,
            ],
            'createFromGrid' => $this->getCalendarCreateConfig(),
        ], $this->calendarOptions);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     modalName: string,
     *     startParam: string,
     *     endParam: string,
     *     openOnDoubleClick: bool,
     *     timedFallbackDurationMinutes: int,
     *     allDayFallbackDurationDays: int
     * }
     */
    public function getCalendarCreateConfig(): array
    {
        $enabled = false;
        $reason = 'available';

        if (! $this->getFormPage() instanceof PageContract) {
            $reason = 'missing-form-page';
        } elseif (! $this->isCreateInModal()) {
            $reason = 'create-in-modal-disabled';
        } elseif (! $this->hasAction(Action::CREATE)) {
            $reason = 'action-disabled';
        } elseif (! $this->can(Ability::CREATE)) {
            $reason = 'ability-denied';
        } else {
            $enabled = true;
        }

        return [
            'enabled' => $enabled,
            'modalName' => $this->calendarCreateModalName,
            'startParam' => $this->startColumn,
            'endParam' => $this->endColumn,
            'openOnDoubleClick' => $this->createFromGridOnDoubleClick,
            'timedFallbackDurationMinutes' => 60,
            'allDayFallbackDurationDays' => 1,
        ];
    }

    public function getCalendarCreateModalName(): string
    {
        return $this->calendarCreateModalName;
    }

    public function getFormFields(bool $withOutside = false): FieldsContract
    {
        $fields = parent::getFormFields($withOutside);

        if ($this->isItemExists()) {
            return $fields;
        }

        $prefill = array_filter([
            $this->startColumn => $this->getCore()->getRequest()->getScalar($this->startColumn),
            $this->endColumn => $this->getCore()->getRequest()->getScalar($this->endColumn),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($prefill === []) {
            return $fields;
        }

        $fields->onlyFields()->each(
            static function (FieldContract $field) use ($prefill): void {
                $column = $field->getColumn();

                if (array_key_exists($column, $prefill)) {
                    $field->fill($prefill[$column]);
                }
            }
        );

        return $fields;
    }

    public function resolveCreateModal(ModalContract $modal): ModalContract
    {
        return parent::resolveCreateModal($modal)->alwaysLoad();
    }

    /**
     * @param array{start:string,end?:?string,allDay?:bool,action:string,timezone?:?string} $payload
     */
    public function updateCalendarEventDates(string $resourceItem, array $payload, ?CrudRequestContract $request = null): Response
    {

        try {
            $item = $this->resolveCalendarEventForDateUpdate($resourceItem);
            $this->authorizeCalendarEventDateUpdate($item);

            [$start, $end] = $this->normalizeCalendarEventDateUpdatePayload($payload);

            $this->applyCalendarEventDateUpdate($item, $start, $end, $payload, $request);

            $response = JsonResponse::make()
                ->toast(__('moonshine::ui.saved'), ToastType::SUCCESS)
                ->setStatusCode(Response::HTTP_OK);

            $this->appendCalendarRefreshEvent($response);

            return $response;
        } catch (Throwable $e) {

            $response = JsonResponse::make()
                ->toast(
                    $e instanceof HttpExceptionInterface && $e->getMessage() !== ''
                        ? $e->getMessage()
                        : __('moonshine::ui.saved_error'),
                    ToastType::ERROR
                )
                ->setStatusCode(
                    $e instanceof HttpExceptionInterface
                        ? $e->getStatusCode()
                        : Response::HTTP_INTERNAL_SERVER_ERROR
                );

            return $this->modifyErrorResponse($response, $e);
        }
    }

    protected function resolveCalendarEventForDateUpdate(string $resourceItem): Model
    {
        $previousItem = $this->getItem();
        $previousItemId = $this->getItemID();

        $this->setItem(null);
        $this->setItemID($resourceItem);
        $wrapped = $this->findItem(orFail: true);

        $item = $wrapped?->getOriginal();

        if (! $item instanceof Model) {
            $this->setItem($previousItem);
            $this->setItemID($previousItemId);
            abort(Response::HTTP_NOT_FOUND, 'Calendar event not found');
        }

        $this->setItem($item);

        return $item;
    }

    protected function authorizeCalendarEventDateUpdate(Model $item): void
    {
        unset($item);

        if (! $this->editable || ! $this->hasAction(Action::UPDATE) || ! $this->can(Ability::UPDATE)) {
            abort(Response::HTTP_FORBIDDEN, 'Forbidden');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0:string,1:?string}
     */
    protected function normalizeCalendarEventDateUpdatePayload(array $payload): array
    {
        $start = $this->normalizeIncomingCalendarMutationDateTime((string) $payload['start']);
        $end = isset($payload['end']) && $payload['end'] !== null && $payload['end'] !== ''
            ? $this->normalizeIncomingCalendarMutationDateTime((string) $payload['end'])
            : null;

        if ($end !== null && $end < $start) {

            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Event end date must be after start date');
        }

        return [$start, $end];
    }

    protected function normalizeIncomingCalendarMutationDateTime(string $value): string
    {
        $timezone = $this->timezone ?: config('app.timezone');

        return Carbon::parse($value)->setTimezone($timezone)->format('Y-m-d H:i:s');
    }

    /**
     * Default save implementation lives in the resource layer so consumers can override
     * this method and reuse their own domain/resource persistence flow.
     *
     * @param array<string, mixed> $payload
     */
    protected function applyCalendarEventDateUpdate(
        Model $item,
        string $start,
        ?string $end,
        array $payload,
        ?CrudRequestContract $request = null
    ): void {
        $item->setAttribute($this->startColumn, $start);
        $item->setAttribute($this->endColumn, $end);

        $this->setItemID($item->getKey());
        $this->setItem($item);

        if ($this->getFormPage() instanceof PageContract) {
            $this->setActivePage($this->getFormPage());
        }

        $saved = $this->save(
            $this->getCaster()->cast($item)
        );

        $this->setItem($saved->getOriginal());

    }

    /**
     * Set calendar options
     */
    public function setCalendarOptions(array $options): static
    {
        $this->calendarOptions = array_merge($this->calendarOptions, $options);

        return $this;
    }

    /**
     * Set default view
     */
    public function setDefaultView(string $view): static
    {
        $this->defaultView = $view;

        return $this;
    }

    /**
     * Set header toolbar configuration
     */
    public function setHeaderToolbar(array $toolbar): static
    {
        $this->headerToolbar = $toolbar;

        return $this;
    }

    /**
     * Enable or disable editable events
     */
    public function setEditable(bool $editable): static
    {
        $this->editable = $editable;

        return $this;
    }

    /**
     * Enable or disable selectable dates
     */
    public function setSelectable(bool $selectable): static
    {
        $this->selectable = $selectable;

        return $this;
    }

    /**
     * Set locale
     */
    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Set timezone
     */
    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Set start column name
     */
    public function setStartColumn(string $column): static
    {
        $this->startColumn = $column;

        return $this;
    }

    /**
     * Set end column name
     */
    public function setEndColumn(string $column): static
    {
        $this->endColumn = $column;

        return $this;
    }

    /**
     * Modify save response to dispatch calendar refresh event
     * This is called by MoonShine after successful create/update operations
     *
     * @param JsonResponse $response The original response
     * @return JsonResponse Modified response with refresh event
     */
    public function modifySaveResponse(JsonResponse $response): JsonResponse
    {
        try {
            $this->appendCalendarRefreshEvent($response);
        } catch (Throwable) {
        }

        return $response;
    }

    /**
     * Modify destroy response to dispatch calendar refresh event after async delete.
     */
    public function modifyDestroyResponse(JsonResponse $response): JsonResponse
    {
        try {
            $this->appendCalendarRefreshEvent($response);
        } catch (Throwable) {
        }

        return $response;
    }

    protected function appendCalendarRefreshEvent(JsonResponse $response): void
    {
        $resourceUri = $this->getUriKey();

        $refreshEvent = AlpineJs::event('fullcalendar:refresh', null, [
            'resource' => $resourceUri,
        ]);

        $response->events([$refreshEvent]);
    }
}
