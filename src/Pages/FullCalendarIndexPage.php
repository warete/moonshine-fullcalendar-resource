<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Pages;

use Illuminate\Support\Facades\Log;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use Warete\MoonShineFullCalendar\Components\FullCalendarComponent;
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
    /**
     * Use the FullCalendar component instead of default list component
     */
    protected string $component = FullCalendarComponent::class;

    /**
     * Logging level
     */
    protected string $logLevel = 'info';

    /**
     * Custom page title (optional, override in your page if needed)
     */

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

        $this->log('debug', 'FullCalendarIndexPage onLoad called', [
            'resource_class' => $this->getResource() ? get_class($this->getResource()) : 'null',
        ]);

        // Ensure async mode is enabled for calendar
        if ($this->asyncMode && method_exists($this, 'setAsync')) {
            $this->setAsync(true);

            $this->log('debug', 'Async mode enabled for calendar', [
                'async' => true,
            ]);
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

        if ($resource && !$resource instanceof FullCalendarResource) {
            $this->log('warning', 'Resource is not a FullCalendarResource', [
                'resource_class' => get_class($resource),
                'expected_class' => FullCalendarResource::class,
            ]);
        }

        return $resource;
    }

    /**
     * Get the component for rendering the calendar
     */
    public function getComponent(): string
    {
        $this->log('debug', 'Getting component', [
            'component' => $this->component,
        ]);

        return $this->component;
    }

    /**
     * Set custom title for the page
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        $this->log('debug', 'Title set', [
            'title' => $title,
        ]);

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

        $this->log('debug', 'Async mode set', [
            'async' => $async,
        ]);

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
            '[FullCalendarIndexPage.%s] %s',
            static::class,
            $message
        );

        Log::log($level, $logMessage, $context);
    }
}
