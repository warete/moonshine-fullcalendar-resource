<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Pages;

use MoonShine\Laravel\Pages\Crud\IndexPage;
use Warete\MoonShineFullCalendar\Components\FullCalendarPageComponent;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;

/**
 * FullCalendar Index Page for MoonShine v4
 * Extends IndexPage to provide calendar view for resources
 *
 * @template TResource of FullCalendarResource
 * @extends IndexPage<TResource>
 */
class FullCalendarIndexPage extends IndexPage
{
    protected string $component = FullCalendarPageComponent::class;

    /**
     * Enable async mode for calendar (default: true)
     */
    protected bool $asyncMode = true;

    /**
     * Called when the page is loaded
     * Configure page-specific settings here
     */
    protected function onLoad(): void
    {
        parent::onLoad();

        // Ensure async mode is enabled for calendar
        if ($this->asyncMode && method_exists($this, 'setAsync')) {
            $this->setAsync(true);
        }
    }

    /**
     * Get the resource for this page
     *
     * @return FullCalendarResource|null
     */
    public function getResource(): ?FullCalendarResource
    {
        $resource = parent::getResource();

        return $resource instanceof FullCalendarResource ? $resource : null;
    }

    /**
     * Set custom title for the page
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get the page title
     */
    public function getTitle(): string
    {
        if ($this->title) {
            return $this->title;
        }

        return parent::getTitle();
    }

    /**
     * Set async mode
     */
    public function setAsyncMode(bool $async): static
    {
        $this->asyncMode = $async;

        return $this;
    }
}
