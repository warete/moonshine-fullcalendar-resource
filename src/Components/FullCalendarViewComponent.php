<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Components;

use Throwable;
use MoonShine\Contracts\AssetManager\AssetElementContract;
use MoonShine\AssetManager\Css;
use MoonShine\AssetManager\Js;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Core\Traits\WithCore;
use MoonShine\Crud\Contracts\Page\IndexPageContract;
use MoonShine\UI\Components\MoonShineComponent;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;

final class FullCalendarViewComponent extends MoonShineComponent
{
    use WithCore;

    protected string $view = 'moonshine-fullcalendar::components.full-calendar';

    public function __construct(
        protected string $name,
        private readonly FullCalendarResource $resource,
        private readonly IndexPageContract $page,
        CoreContract $core,
    ) {
        $this->setCore($core);

        parent::__construct($name);
    }

    /**
     * @return list<AssetElementContract>
     */
    protected function assets(): array
    {
        return [
            Css::make('/vendor/moonshine-fullcalendar/css/full-calendar.css')->defer(),
            Js::make('/vendor/moonshine-fullcalendar/js/full-calendar.js'),
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return array_merge(parent::data(), [
            'calendarConfig' => $this->resource->getCalendarConfig(),
            'calendarCreateTrigger' => $this->renderCalendarCreateTrigger(),
            'resource' => $this->resource,
            'async' => $this->page->isAsync(),
            'endpoint' => $this->resource->getEventsEndpoint(),
        ]);
    }

    protected function renderCalendarCreateTrigger(): ?string
    {
        $createConfig = $this->resource->getCalendarCreateConfig();

        if (($createConfig['enabled'] ?? false) !== true) {
            return null;
        }

        try {
            $button = $this->resource->getCreateButton(
                componentName: $this->name,
                isAsync: true,
                modalName: $this->resource->getCalendarCreateModalName(),
            )->customAttributes([
                'x-ref' => 'calendarCreateTrigger',
                'data-calendar-create-trigger' => 'true',
                'aria-hidden' => 'true',
                'tabindex' => '-1',
            ])->class('hidden');

            $html = trim((string) $button);

            return $html === '' ? null : $html;
        } catch (Throwable) {
            return null;
        }
    }
}
