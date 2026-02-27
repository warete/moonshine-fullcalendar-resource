<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Components;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Log;
use MoonShine\AssetManager\Css;
use MoonShine\AssetManager\Js;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Core\Traits\WithCore;
use MoonShine\Crud\Contracts\PageComponents\DefaultListComponentContract;
use MoonShine\Crud\Contracts\Page\IndexPageContract;
use MoonShine\UI\Components\MoonShineComponent;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;

/**
 * FullCalendar component for MoonShine v4
 * Implements DefaultListComponentContract to provide calendar view for resources
 */
final class FullCalendarComponent implements DefaultListComponentContract
{
    use WithCore;

    /**
     * Logging level
     */
    protected string $logLevel = 'info';

    public function __construct(CoreContract $core)
    {
        $this->setCore($core);
        $this->logLevel = config('fullcalendar.log_level', env('FULLCALENDAR_LOG_LEVEL', 'info'));

        $this->log('info', 'FullCalendarComponent instantiated', [
            'core_class' => get_class($core),
        ]);
    }

    /**
     * Invoke the component to render the calendar
     *
     * @param IndexPageContract $page The index page
     * @param iterable $items The items (events) to display
     * @param FieldsContract $fields The fields for the resource
     * @return ComponentContract The calendar UI component
     */
    public function __invoke(
        IndexPageContract $page,
        iterable $items,
        FieldsContract $fields
    ): ComponentContract {
        $resource = $page->getResource();

        $this->log('info', 'FullCalendarComponent __invoke called', [
            'resource_class' => get_class($resource),
            'items_count' => is_countable($items) ? count($items) : 'unknown',
            'page_class' => get_class($page),
        ]);

        // Ensure the resource is a FullCalendarResource
        if (!$resource instanceof FullCalendarResource) {
            $this->log('error', 'Resource is not a FullCalendarResource', [
                'resource_class' => get_class($resource),
                'expected_class' => FullCalendarResource::class,
            ]);

            throw new \InvalidArgumentException(
                sprintf(
                    'Resource must extend %s, %s given',
                    FullCalendarResource::class,
                    get_class($resource)
                )
            );
        }

        // Generate component name
        $componentName = $page->getListComponentName();

        $this->log('debug', 'Component configuration prepared', [
            'component_name' => $componentName,
            'is_async' => $page->isAsync(),
        ]);

        // Create and return the calendar UI component
        return new class($componentName, $resource, $page, $this->getCore(), $this->logLevel) extends MoonShineComponent
        {
            protected string $view = 'moonshine-fullcalendar::components.full-calendar';

            public function __construct(
                protected string $name,
                private readonly FullCalendarResource $resource,
                private readonly IndexPageContract $page,
                CoreContract $core,
                private readonly string $logLevel = 'info'
            ) {
                $this->setCore($core);
                parent::__construct($name);

                $this->log('debug', 'Calendar UI component created', [
                    'name' => $name,
                    'resource' => get_class($resource),
                ]);
            }

            /**
             * Register package assets via MoonShine asset manager so CSS is injected in layout head.
             *
             * @return list<\MoonShine\Contracts\AssetManager\AssetElementContract>
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
             * Get data for the view
             *
             * @return array<string, mixed>
             */
            public function data(): array
            {
                $data = parent::data();

                $calendarConfig = $this->resource->getCalendarConfig();

                $this->log('debug', 'Calendar view data prepared', [
                    'config_keys' => array_keys($calendarConfig),
                    'async' => $this->page->isAsync(),
                ]);

                $endpoint = $this->resource->getEventsEndpoint();

                $this->log('debug', 'Endpoint for calendar', [
                    'endpoint' => $endpoint,
                ]);

                return array_merge($data, [
                    'calendarConfig' => $calendarConfig,
                    'resource' => $this->resource,
                    'async' => $this->page->isAsync(),
                    'endpoint' => $endpoint,
                ]);
            }

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
                    '[FullCalendarComponent.%s] %s',
                    class_basename(static::class),
                    $message
                );

                Log::log($level, $logMessage, $context);
            }
        };
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
            '[FullCalendarComponent] %s',
            $message
        );

        Log::log($level, $logMessage, $context);
    }
}
