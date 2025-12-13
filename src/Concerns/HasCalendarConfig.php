<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Concerns;

use Closure;

trait HasCalendarConfig
{
    protected array $calendarConfig = [];

    /**
     * Set calendar configuration
     */
    public function setCalendarConfig(array $config): self
    {
        $this->calendarConfig = $config;

        return $this;
    }

    /**
     * Merge calendar configuration
     */
    public function mergeCalendarConfig(array $config): self
    {
        $this->calendarConfig = array_merge_recursive($this->calendarConfig, $config);

        return $this;
    }

    /**
     * Set initial view
     */
    public function setInitialView(string $view): self
    {
        $this->calendarConfig['initialView'] = $view;

        return $this;
    }

    /**
     * Set available views
     */
    public function setViews(array $views): self
    {
        $this->calendarConfig['views'] = $views;

        return $this;
    }

    /**
     * Set header toolbar
     */
    public function setHeaderToolbar(array $toolbar): self
    {
        $this->calendarConfig['headerToolbar'] = $toolbar;

        return $this;
    }

    /**
     * Set footer toolbar
     */
    public function setFooterToolbar(array $toolbar): self
    {
        $this->calendarConfig['footerToolbar'] = $toolbar;

        return $this;
    }

    /**
     * Set locale
     */
    public function setLocale(string $locale): self
    {
        $this->calendarConfig['locale'] = $locale;

        return $this;
    }

    /**
     * Set timezone
     */
    public function setTimezone(string $timezone): self
    {
        $this->calendarConfig['timezone'] = $timezone;

        return $this;
    }

    /**
     * Set height
     */
    public function setHeight(string|int|Closure $height): self
    {
        $this->calendarConfig['height'] = $height;

        return $this;
    }

    /**
     * Set aspect ratio (alternative to height)
     */
    public function setAspectRatio(float $ratio): self
    {
        $this->calendarConfig['aspectRatio'] = $ratio;

        return $this;
    }

    /**
     * Set slot duration
     */
    public function setSlotDuration(string $duration): self
    {
        $this->calendarConfig['slotDuration'] = $duration;

        return $this;
    }

    /**
     * Set slot min time
     */
    public function setSlotMinTime(string $time): self
    {
        $this->calendarConfig['slotMinTime'] = $time;

        return $this;
    }

    /**
     * Set slot max time
     */
    public function setSlotMaxTime(string $time): self
    {
        $this->calendarConfig['slotMaxTime'] = $time;

        return $this;
    }

    /**
     * Set weekends visibility
     */
    public function setShowWeekends(bool $show = true): self
    {
        $this->calendarConfig['weekends'] = $show;

        return $this;
    }

    /**
     * Set now indicator
     */
    public function setShowNowIndicator(bool $show = true): self
    {
        $this->calendarConfig['nowIndicator'] = $show;

        return $this;
    }

    /**
     * Enable/disable event dragging
     */
    public function setEventDraggable(bool $draggable = true): self
    {
        $this->calendarConfig['editable'] = $draggable;

        return $this;
    }

    /**
     * Enable/disable event resizing
     */
    public function setEventResizable(bool $resizable = true): self
    {
        $this->calendarConfig['eventResizableFromStart'] = $resizable;

        return $this;
    }

    /**
     * Enable date selection
     */
    public function setSelectable(bool $selectable = true): self
    {
        $this->calendarConfig['selectable'] = $selectable;

        return $this;
    }

    /**
     * Set first day of week (0 = Sunday, 1 = Monday, etc.)
     */
    public function setFirstDay(int $day): self
    {
        $this->calendarConfig['firstDay'] = $day;

        return $this;
    }

    /**
     * Add custom button to header
     */
    public function addHeaderButton(string $name, array $button): self
    {
        if (! isset($this->calendarConfig['customButtons'])) {
            $this->calendarConfig['customButtons'] = [];
        }

        $this->calendarConfig['customButtons'][$name] = $button;

        return $this;
    }

    /**
     * Get complete calendar configuration
     */
    public function getCalendarConfig(): array
    {
        // Default configuration
        $defaultConfig = [
            'locale' => app()->getLocale(),
            'initialView' => 'dayGridMonth',
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            ],
            'firstDay' => 1,
            'height' => 'auto',
            'slotMinTime' => '08:00:00',
            'slotMaxTime' => '21:00:00',
            'handleWindowResize' => true,
            'windowResizeDelay' => 150,
            'weekends' => true,
            'nowIndicator' => true,
            'editable' => false,
        ];

        return array_merge($defaultConfig, $this->calendarConfig);
    }

    /**
     * Get calendar configuration as JSON
     */
    public function getCalendarConfigJson(): string
    {
        return json_encode($this->getCalendarConfig());
    }
}
