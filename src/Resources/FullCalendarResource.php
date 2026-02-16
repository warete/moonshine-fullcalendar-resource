<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Resources\ModelResource;

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

    public function __construct(CoreContract $core)
    {
        $this->logLevel = config('fullcalendar.log_level', env('FULLCALENDAR_LOG_LEVEL', 'info'));
        $this->timezone = $this->timezone ?? config('app.timezone');

        $this->log('info', 'Resource instantiated', [
            'class' => static::class,
            'model' => $this->model ?? 'not set',
            'defaultView' => $this->defaultView,
            'timezone' => $this->timezone,
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
                $formatted[] = $this->formatEvent($item);
            }

            $this->log('debug', 'Events formatted', [
                'formatted_count' => count($formatted),
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

        // Apply date range filtering if provided
        if ($start && $end) {
            $query->where(function ($q) use ($start, $end) {
                // Events that overlap with the range
                $q->whereBetween($this->startColumn, [$start, $end])
                    ->orWhereBetween($this->endColumn, [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where($this->startColumn, '<=', $start)
                            ->where($this->endColumn, '>=', $end);
                    });
            });

            $this->log('debug', 'Date range filter applied', [
                'start' => $start,
                'end' => $end,
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

        if (!empty($extendedProps)) {
            $event['extendedProps'] = $extendedProps;
        }

        $this->log('debug', 'Event formatted', [
            'event_id' => $event['id'],
            'event_title' => $event['title'],
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
            return $value;
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
        ], $this->calendarOptions);

        $this->log('debug', 'Calendar config generated', [
            'config' => $config,
        ]);

        return $config;
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
}
