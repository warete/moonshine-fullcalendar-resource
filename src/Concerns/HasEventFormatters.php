<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\TypeCasts\ModelDataWrapper;
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
     * Format data for FullCalendar - main entry point
     */
    public function formatCalendarItem(DataWrapperContract $item): array
    {
        $item->getOriginal();

        return $this->applyEventFormatters($item);

    }

    /**
     * Apply formatters to a model
     */
    protected function applyEventFormatters(DataWrapperContract $item): array
    {
        $eventData = [];

        if ($item instanceof ModelDataWrapper) {
            $eventData = $this->formatEventFromModel($item->getOriginal());
        }

        // Apply each formatter
        foreach ($this->eventFormatters as $formatter) {
            $formatted = $formatter($item, $eventData);

            // If formatter returns an array, merge it
            if (is_array($formatted)) {
                $eventData = array_merge($eventData, $formatted);
            }

            // If formatter returns a CalendarEvent, convert to array
            if ($formatted instanceof CalendarEvent) {
                $eventData = array_merge($eventData, $formatted->toArray());
            }
        }

        return $eventData;
    }



    /**
     * Format model to calendar event array
     */
    public function formatEventFromModel(Model $model): array
    {
        $event = [
            'id' => $model->getKey(),
            'title' => $this->getEventTitle($model),
            'start' => $this->getEventStart($model),
            'end' => $this->getEventEnd($model),
        ];

        // Add description if available
        if ($description = $this->getEventDescription($model)) {
            $event['extendedProps']['description'] = $description;
        }

        // Add CSS classes
        $event['classNames'] = $this->getEventClassNames($model);

        // Add colors
        $event['backgroundColor'] = $this->getEventBackgroundColor($model);
        $event['textColor'] = $this->getEventTextColor($model);
        $event['borderColor'] = $this->getEventBorderColor($model);

        return $event;
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
