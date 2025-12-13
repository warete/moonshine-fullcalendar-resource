<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Warete\MoonShineFullCalendar\DTO\CalendarEvent;

trait HasEventFormatters
{
    /**
     * @var array<Closure>
     */
    protected array $eventFormatters = [];

    /**
     * Add an event formatter
     */
    public function addEventFormatter(Closure $formatter): self
    {
        $this->eventFormatters[] = $formatter;

        return $this;
    }

    /**
     * Set multiple event formatters
     */
    public function setEventFormatters(array $formatters): self
    {
        $this->eventFormatters = $formatters;

        return $this;
    }

    /**
     * Apply formatters to a model
     */
    protected function applyEventFormatters(Model $model): array
    {
        $eventData = [];

        // Apply each formatter
        foreach ($this->eventFormatters as $formatter) {
            $formatted = $formatter($model);

            // If formatter returns an array, merge it
            if (is_array($formatted)) {
                $eventData = array_merge($eventData, $formatted);
            }

            // If formatter returns a CalendarEvent, convert to array
            if ($formatted instanceof CalendarEvent) {
                $eventData = array_merge($eventData, $formatted->toArray());
            }
        }

        // If no custom formatters, use the default one
        if (empty($this->eventFormatters)) {
            $eventData = $this->formatEventFromModel($model);
        }

        return $eventData;
    }

    /**
     * Format a collection of models to calendar events
     */
    public function formatEvents(Collection $models): Collection
    {
        return $models->map(fn ($model) => $this->applyEventFormatters($model));
    }

    /**
     * Basic formatter that creates a CalendarEvent from model
     */
    protected function createCalendarEventFromModel(Model $model): CalendarEvent
    {
        $id = $model->getKey();
        $title = $this->getEventTitle($model);
        $start = $this->getEventStart($model);
        $end = $this->getEventEnd($model);

        $event = CalendarEvent::make($id, $title, $start, $end);

        // Add description if available
        if ($description = $this->getEventDescription($model)) {
            $event = $event->withExtendedProps(['description' => $description]);
        }

        // Add CSS classes
        if (! empty($this->getEventClassNames($model))) {
            $event = $event->withClassNames($this->getEventClassNames($model));
        }

        // Set colors
        $event = $event->withColors(
            bg: $this->getEventBackgroundColor($model),
            text: $this->getEventTextColor($model),
            border: $this->getEventBorderColor($model)
        );

        // Set editability
        $event = $event->withEditable($this->can('update', $model));

        return $event;
    }
}
