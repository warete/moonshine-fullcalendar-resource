<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Components;

use Closure;
use MoonShine\AssetManager\Js;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\Action;
use MoonShine\UI\Components\MoonShineComponent;

/**
 * @method static static make(ModelResource $resource, ?Closure $formatter = null)
 */
final class FullCalendar extends MoonShineComponent
{
    protected string $view = 'moonshine-fullcalendar::components.full-calendar';

    public function __construct(
        protected ModelResource $resource,
        protected ?Closure $formatter = null,
    ) {
        parent::__construct();

        // Add FullCalendar assets
        $this->addAssets([
            // FullCalendar core
            Js::make('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.js'),

            // Locales (optional)
            Js::make('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/locales-all.global.min.js'),
        ]);
    }

    /**
     * Get the resource instance
     */
    protected function getResource(): ModelResource
    {
        return $this->resource;
    }

    /**
     * Get the index page instance
     */
    protected function getIndexPage(): IndexPage
    {
        return $this->resource->getIndexPage();
    }

    /**
     * Check if create button should be shown
     */
    protected function shouldShowCreateButton(): bool
    {
        $resource = $this->getResource();

        // Check if resource has create ability and active action
        if (! $resource->can(Ability::CREATE)) {
            return false;
        }

        return $resource->hasAction(Action::CREATE);
    }

    /**
     * Prepare data for the view
     */
    protected function viewData(): array
    {
        $resource = $this->getResource();
        $page = $this->getIndexPage();

        return [
            'title' => $resource->getTitle(),
            'eventDetailRoute' => $resource->getAsyncMethodUrl('detailEvent', page: $page),
            'eventsRoute' => $resource->getRoute('full-calendar.events.list'),
            'calendarConfig' => $resource->getCalendarConfig(),
            'createButton' => $this->shouldShowCreateButton(),
            'createUrl' => $this->shouldShowCreateButton()
                ? $resource->getFormPageUrl() // URL for create form
                : null,
        ];
    }

    /**
     * Prepare component before rendering
     */
    protected function prepareBeforeRender(): void
    {
        parent::prepareBeforeRender();

        // Add CSS classes
        $this->customAttributes([
            'class' => 'full-calendar-wrapper',
        ]);
    }
}
