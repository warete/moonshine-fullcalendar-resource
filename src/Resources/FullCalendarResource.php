<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Crud\Buttons\DeleteButton;
use MoonShine\Crud\Buttons\EditButton;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Laravel\TypeCasts\ModelDataWrapper;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\HttpMethod;
use MoonShine\Support\Enums\ToastType;
use Symfony\Component\HttpFoundation\Response;

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
     * Calendar resources usually need async modal editing for event-click actions.
     * Consumers can opt out by overriding this property in their resource.
     */
    protected bool $editInModal = true;

    /**
     * FullCalendar view modes
     */
    final const VIEW_MONTH = 'dayGridMonth';
    final const VIEW_WEEK = 'timeGridWeek';
    final const VIEW_DAY = 'timeGridDay';
    final const VIEW_LIST = 'listWeek';

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
     * Logging level
     */
    protected string $logLevel = 'info';

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

    public function __construct(CoreContract $core)
    {
        $this->logLevel = config('fullcalendar.log_level', env('FULLCALENDAR_LOG_LEVEL', 'info'));
        $this->timezone = $this->timezone ?? config('app.timezone');

        $this->log('info', 'Resource instantiated', [
            'class' => static::class,
            'model' => $this->model ?? 'not set',
            'defaultView' => $this->defaultView,
            'timeZone' => $this->timezone,
        ]);

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
        $this->log('info', 'Getting calendar items', [
            'model' => $this->model,
            'params' => $params,
        ]);

        try {
            $start = $params['start'] ?? null;
            $end = $params['end'] ?? null;

            $this->log('debug', 'Date range parsed', [
                'start' => $start,
                'end' => $end,
                'startColumn' => $this->startColumn,
                'endColumn' => $this->endColumn,
            ]);

            $items = $this->fetchEvents($start, $end, $params);

            $this->log('info', 'Events fetched', [
                'count' => count($items),
            ]);

            $formatted = [];
            foreach ($items as $item) {
                $formattedEvent = $this->formatEvent($item);
                $formatted[] = $this->ensureCalendarEventActionsPayload($formattedEvent, $item);
            }

            $eventsWithActions = 0;
            foreach ($formatted as $event) {
                $actions = $event['extendedProps'][$this->calendarEventPayloadKey]['actions'] ?? null;
                if (is_array($actions) && (($actions['hasActions'] ?? false) === true)) {
                    $eventsWithActions++;
                }
            }

            $this->log('info', 'Events formatted with actions payload', [
                'formatted_count' => count($formatted),
                'events_with_actions' => $eventsWithActions,
                'resource' => $this->getUriKey(),
            ]);

            return $formatted;
        } catch (\Throwable $e) {
            $this->log('error', 'Error fetching calendar items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $params,
            ]);

            throw $e;
        }
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
        $this->log('debug', 'Building Eloquent query', [
            'model' => $this->model,
        ]);

        $query = $this->getQuery();

        $normalizedStart = $this->normalizeQueryDateTime($start);
        $normalizedEnd = $this->normalizeQueryDateTime($end);

        // Apply date range filtering if provided
        if ($normalizedStart && $normalizedEnd) {
            $query->where(function ($q) use ($normalizedStart, $normalizedEnd) {
                // FullCalendar end is exclusive. Select events that overlap [start, end).
                $q->where(function ($q) use ($normalizedStart, $normalizedEnd) {
                    $q->where($this->startColumn, '<', $normalizedEnd)
                        ->where($this->endColumn, '>', $normalizedStart);
                })->orWhere(function ($q) use ($normalizedStart, $normalizedEnd) {
                    // Fallback for records without end date: point-in-time events by start only
                    $q->whereNull($this->endColumn)
                        ->where($this->startColumn, '>=', $normalizedStart)
                        ->where($this->startColumn, '<', $normalizedEnd);
                });
            });

            $this->log('info', '[FIX][query-range] Date range filter applied', [
                'rawStart' => $start,
                'rawEnd' => $end,
                'normalizedStart' => $normalizedStart,
                'normalizedEnd' => $normalizedEnd,
                'timezone' => $this->timezone,
            ]);
        }

        // Apply any additional filters from params
        if (isset($params['filters']) && is_array($params['filters'])) {
            foreach ($params['filters'] as $column => $value) {
                if ($value !== null && $value !== '') {
                    $query->where($column, $value);
                    $this->log('debug', 'Filter applied', [
                        'column' => $column,
                        'value' => $value,
                    ]);
                }
            }
        }

        // Apply ordering
        $query->orderBy($this->startColumn, 'asc');

        $items = $query->get();

        $this->log('debug', 'Query executed', [
            'count' => $items->count(),
            'sql' => $query->toSql(),
        ]);

        return $items;
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
            $normalized = Carbon::parse($value)->setTimezone($timezone)->format('Y-m-d H:i:s');

            $this->log('debug', '[FIX][query-range] Query datetime normalized', [
                'input' => $value,
                'timezone' => $timezone,
                'output' => $normalized,
            ]);

            return $normalized;
        } catch (\Throwable $e) {
            $this->log('warning', '[FIX][query-range] Failed to normalize query datetime, using raw value', [
                'input' => $value,
                'timezone' => $this->timezone,
                'error' => $e->getMessage(),
            ]);

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
        $this->log('debug', 'Formatting event', [
            'model_class' => get_class($item),
            'model_id' => $item->getKey(),
        ]);

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

        // Add extended props for any additional attributes
        $extendedProps = [];
        foreach ($item->getAttributes() as $key => $value) {
            if (!in_array($key, [$item->getKeyName(), $this->getColumn(), $this->startColumn, $this->endColumn, 'color', 'background_color', 'description', 'body', 'created_at', 'updated_at'])) {
                $extendedProps[$key] = $value;
            }
        }

        $eventActionsPayload = $this->buildCalendarEventActionsPayload($item);

        $extendedProps[$this->calendarEventPayloadKey] = [
            'actions' => $eventActionsPayload,
        ];

        $event['extendedProps'] = $extendedProps;

        $this->log('debug', 'Event formatted', [
            'event_id' => $event['id'],
            'event_title' => $event['title'],
            'actions_count' => $eventActionsPayload['count'],
            'actions_has' => $eventActionsPayload['hasActions'],
        ]);

        return $event;
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
            $this->log('info', '[FIX][event-actions] Existing actions payload preserved', [
                'item_id' => $item->getKey(),
                'resource' => $this->getUriKey(),
                'has_actions' => (bool) ($existingActions['hasActions'] ?? false),
                'count' => (int) ($existingActions['count'] ?? 0),
            ]);

            return $event;
        }

        $payload = $this->buildCalendarEventActionsPayload($item);

        $extendedProps = $event['extendedProps'] ?? [];
        if (!is_array($extendedProps)) {
            $this->log('warning', '[FIX][event-actions] Invalid extendedProps type, resetting to array', [
                'item_id' => $item->getKey(),
                'resource' => $this->getUriKey(),
                'type' => get_debug_type($extendedProps),
            ]);
            $extendedProps = [];
        }

        $extendedProps[$this->calendarEventPayloadKey] = [
            'actions' => $payload,
        ];

        $event['extendedProps'] = $extendedProps;

        $this->log('info', '[FIX][event-actions] Actions payload injected after formatEvent', [
            'item_id' => $item->getKey(),
            'resource' => $this->getUriKey(),
            'has_actions' => $payload['hasActions'],
            'count' => $payload['count'],
        ]);

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

        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        if (is_string($value)) {
            try {
                $timezone = $this->timezone ?: config('app.timezone');
                $parsed = Carbon::parse($value, $timezone);

                return $parsed->format('c');
            } catch (\Throwable $e) {
                $this->log('warning', '[FIX][formatDateTime] Failed to normalize date string, returning raw value', [
                    'value' => $value,
                    'timezone' => $this->timezone,
                    'error' => $e->getMessage(),
                ]);

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

            return preg_match('/T00:00:00/', $startStr) && preg_match('/T00:00:00/', $endStr);
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
        try {
            $renderedButtons = $this->renderCalendarEventActionsHtml($item);

            $payload = [
                'version' => 1,
                'html' => implode('', $renderedButtons),
                'count' => count($renderedButtons),
                'hasActions' => count($renderedButtons) > 0,
            ];

            $this->log('info', 'Calendar event actions payload built', [
                'item_id' => $item->getKey(),
                'resource' => $this->getUriKey(),
                'actions_count' => $payload['count'],
                'has_actions' => $payload['hasActions'],
            ]);

            return $payload;
        } catch (\Throwable $e) {
            $this->log('error', 'Failed to build calendar event actions payload', [
                'item_id' => $item->getKey(),
                'resource' => $this->getUriKey(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'version' => 1,
                'html' => '',
                'count' => 0,
                'hasActions' => false,
            ];
        }
    }

    /**
     * @return list<ActionButtonContract|string>
     */
    protected function getCalendarEventActions(Model $item): array
    {
        $defaultActions = $this->getDefaultCalendarEventActions($item);
        $customActions = $this->normalizeCustomCalendarEventActions($this->getCustomCalendarEventActions($item), $item);
        $actions = [...$defaultActions, ...$customActions];

        $this->log('info', 'Calendar event actions composed', [
            'item_id' => $item->getKey(),
            'default_count' => count($defaultActions),
            'custom_count' => count($customActions),
            'total_count' => count($actions),
        ]);

        return $actions;
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

        try {
            $this->setItem($item);
        } catch (\Throwable $e) {
            $this->log('warning', 'Failed to set resource item before building actions', [
                'item_id' => $item->getKey(),
                'error' => $e->getMessage(),
            ]);
        }

        $actions = [];

        try {
            $editButton = EditButton::for($this, isAsync: true)
                ->setData($wrapped);

            $this->log('info', 'Default edit action composed', [
                'item_id' => $item->getKey(),
                'resource' => $this->getUriKey(),
                'editInModal' => $this->isEditInModal(),
                'isAsync' => method_exists($editButton, 'isAsync') ? $editButton->isAsync() : null,
            ]);

            $actions[] = $this->ensureCalendarActionIsAsync($editButton, $item, 'edit');
        } catch (\Throwable $e) {
            $this->log('warning', 'Default edit action unavailable', [
                'item_id' => $item->getKey(),
                'resource' => $this->getUriKey(),
                'editInModal' => $this->isEditInModal(),
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $deleteButton = DeleteButton::for($this, isAsync: true)
                ->setData($wrapped);

            $actions[] = $this->ensureCalendarActionIsAsync($deleteButton, $item, 'delete');
        } catch (\Throwable $e) {
            $this->log('warning', 'Default delete action unavailable', [
                'item_id' => $item->getKey(),
                'resource' => $this->getUriKey(),
                'error' => $e->getMessage(),
            ]);
        }

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
                try {
                    $action->setData(new ModelDataWrapper($item));
                } catch (\Throwable $e) {
                    $this->log('warning', 'Failed to bind item context to custom action', [
                        'item_id' => $item->getKey(),
                        'action_class' => $action::class,
                        'error' => $e->getMessage(),
                    ]);
                }

                $list[] = $this->ensureCalendarActionIsAsync($action, $item, 'custom');
                continue;
            }

            if (is_string($action)) {
                $list[] = $action;
                continue;
            }

            $this->log('warning', 'Unsupported custom calendar action type', [
                'item_id' => $item->getKey(),
                'type' => get_debug_type($action),
            ]);
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
            $this->log('info', 'Calendar action kept as modal/offcanvas flow', [
                'item_id' => $item->getKey(),
                'source' => $source,
                'action_class' => $action::class,
            ]);

            return $action;
        }

        if (method_exists($action, 'async')) {
            $action->async(HttpMethod::GET);

            $this->log('info', 'Calendar action normalized to async', [
                'item_id' => $item->getKey(),
                'source' => $source,
                'action_class' => $action::class,
            ]);
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

            if (!($action instanceof ActionButtonContract)) {
                $this->log('warning', 'Unsupported calendar action while rendering', [
                    'item_id' => $item->getKey(),
                    'type' => get_debug_type($action),
                ]);

                continue;
            }

            try {
                $html = trim((string) $action);

                if ($html === '') {
                    continue;
                }

                $rendered[] = $html;
            } catch (\Throwable $e) {
                $this->log('warning', 'Failed to render calendar action button', [
                    'item_id' => $item->getKey(),
                    'action_class' => $action::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $rendered;
    }

    /**
     * Get the API endpoint for fetching events
     */
    public function getEventsEndpoint(): string
    {
        $this->log('debug', 'Building events endpoint', [
            'resource_uri_key' => $this->getUriKey(),
        ]);

        // Build the endpoint URL manually for resource-scoped routes
        // The route is: /admin/resource/{resourceUri}/full-calendar/events
        $resourceUri = $this->getUriKey();
        $endpoint = moonshineRouter()->to('full-calendar.events.list', ['resourceUri' => $this->getUriKey()]);

        $this->log('debug', 'Events endpoint built', [
            'endpoint' => $endpoint,
            'resourceUri' => $resourceUri,
        ]);

        return $endpoint;
    }

    public function getEventDatesUpdateEndpointTemplate(): string
    {
        $template = moonshineRouter()->to('full-calendar.events.dates.update', [
            'resourceUri' => $this->getUriKey(),
            'resourceItem' => $this->calendarEventDateUpdateIdPlaceholder,
        ]);

        $this->log('debug', 'Event dates update endpoint template built', [
            'resourceUri' => $this->getUriKey(),
            'template' => $template,
            'placeholder' => $this->calendarEventDateUpdateIdPlaceholder,
        ]);

        return $template;
    }

    /**
     * Get calendar configuration for Alpine.js
     */
    public function getCalendarConfig(): array
    {
        $config = array_merge([
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
        ], $this->calendarOptions);

        $this->log('debug', 'Calendar config generated', [
            'config' => $config,
        ]);

        return $config;
    }

    /**
     * @param array{start:string,end?:?string,allDay?:bool,action:string,timezone?:?string} $payload
     */
    public function updateCalendarEventDates(string $resourceItem, array $payload, ?CrudRequestContract $request = null): Response
    {
        $this->log('info', 'Calendar event date update requested', [
            'resource' => $this->getUriKey(),
            'resourceItem' => $resourceItem,
            'payload' => $payload,
        ]);

        try {
            $item = $this->resolveCalendarEventForDateUpdate($resourceItem);

            [$start, $end] = $this->normalizeCalendarEventDateUpdatePayload($payload);

            $oldStart = $item->getAttribute($this->startColumn);
            $oldEnd = $item->getAttribute($this->endColumn);

            $this->applyCalendarEventDateUpdate($item, $start, $end, $payload, $request);

            $response = JsonResponse::make()
                ->toast(__('moonshine::ui.saved'), ToastType::SUCCESS)
                ->setStatusCode(Response::HTTP_OK);

            $this->appendCalendarRefreshEvent($response, 'updateCalendarEventDates');

            $this->log('info', 'Calendar event dates updated successfully', [
                'resource' => $this->getUriKey(),
                'resourceItem' => $resourceItem,
                'startColumn' => $this->startColumn,
                'endColumn' => $this->endColumn,
                'oldStart' => $oldStart instanceof \DateTimeInterface ? $oldStart->format('c') : $oldStart,
                'oldEnd' => $oldEnd instanceof \DateTimeInterface ? $oldEnd->format('c') : $oldEnd,
                'newStart' => $start,
                'newEnd' => $end,
                'action' => $payload['action'] ?? null,
            ]);

            return $response;
        } catch (\Throwable $e) {
            $this->log('error', 'Calendar event date update failed', [
                'resource' => $this->getUriKey(),
                'resourceItem' => $resourceItem,
                'payload' => $payload,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $response = JsonResponse::make()
                ->toast(
                    $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $e->getMessage() !== ''
                        ? $e->getMessage()
                        : __('moonshine::ui.saved_error'),
                    ToastType::ERROR
                )
                ->setStatusCode(
                    $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
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

        $this->log('debug', '[FIX][date-update] Event resolved via findItem', [
            'resource' => $this->getUriKey(),
            'resourceItem' => $resourceItem,
            'resolved_id' => $item->getKey(),
        ]);

        return $item;
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
            $this->log('warning', 'Calendar event date update payload end precedes start', [
                'start' => $start,
                'end' => $end,
                'payload' => $payload,
            ]);

            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Event end date must be after start date');
        }

        $this->log('debug', 'Calendar event date update payload normalized', [
            'start' => $start,
            'end' => $end,
            'timezone' => $payload['timezone'] ?? $this->timezone,
            'action' => $payload['action'] ?? null,
        ]);

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

        if ($this->getFormPage() !== null) {
            $this->setActivePage($this->getFormPage());
        }

        $saved = $this->save(
            $this->getCaster()->cast($item)
        );

        $this->setItem($saved->getOriginal());

        $this->log('info', '[FIX][date-update] Calendar event date update persisted via MoonShine resource save pipeline', [
            'resource' => $this->getUriKey(),
            'item_id' => $item->getKey(),
            'startColumn' => $this->startColumn,
            'endColumn' => $this->endColumn,
            'start' => $start,
            'end' => $end,
            'action' => $payload['action'] ?? null,
            'request_has_resource' => $request?->getResource() !== null,
        ]);
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
     * Log a message with level check
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $levels = ['debug', 'info', 'warning', 'error'];
        $currentLevel = strtolower($this->logLevel);

        $levelIndex = array_search(strtolower($level), $levels);
        $currentLevelIndex = array_search($currentLevel, $levels);

        if ($levelIndex === false || $currentLevelIndex === false || $levelIndex < $currentLevelIndex) {
            return;
        }

        $logMessage = sprintf(
            '[FullCalendarResource.%s] %s',
            class_basename(static::class),
            $message
        );

        Log::log($level, $logMessage, $context);
    }

    /**
     * Modify save response to dispatch calendar refresh event
     * This is called by MoonShine after successful create/update operations
     *
     * @param \MoonShine\Crud\JsonResponse $response The original response
     * @return \MoonShine\Crud\JsonResponse Modified response with refresh event
     */
    public function modifySaveResponse(\MoonShine\Crud\JsonResponse $response): \MoonShine\Crud\JsonResponse
    {
        $this->log('info', '[modifySaveResponse] Adding calendar refresh event', [
            'resource' => $this->getUriKey(),
        ]);

        try {
            $this->appendCalendarRefreshEvent($response, 'modifySaveResponse');
        } catch (\Throwable $e) {
            $this->log('error', '[modifySaveResponse] Failed to add refresh event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $response;
    }

    /**
     * Modify destroy response to dispatch calendar refresh event after async delete.
     */
    public function modifyDestroyResponse(\MoonShine\Crud\JsonResponse $response): \MoonShine\Crud\JsonResponse
    {
        $this->log('info', '[modifyDestroyResponse] Adding calendar refresh event', [
            'resource' => $this->getUriKey(),
        ]);

        try {
            $this->appendCalendarRefreshEvent($response, 'modifyDestroyResponse');
        } catch (\Throwable $e) {
            $this->log('error', '[modifyDestroyResponse] Failed to add refresh event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $response;
    }

    protected function appendCalendarRefreshEvent(\MoonShine\Crud\JsonResponse $response, string $source): void
    {
        $resourceUri = $this->getUriKey();

        $refreshEvent = AlpineJs::event('fullcalendar:refresh', null, [
            'resource' => $resourceUri,
        ]);

        $this->log('info', "[$source] Calendar refresh event added", [
            'event' => $refreshEvent,
            'resource' => $resourceUri,
        ]);

        $response->events([$refreshEvent]);
    }
}
