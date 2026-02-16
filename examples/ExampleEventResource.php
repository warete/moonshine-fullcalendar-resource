<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\Event;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\DateTime;
use MoonShine\UI\Fields\Color;
use MoonShine\Laravel\Pages\Crud\FormPage;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;
use Warete\MoonShineFullCalendar\Pages\FullCalendarIndexPage;

/**
 * Example Event Resource with FullCalendar Integration
 *
 * This example demonstrates how to create a FullCalendar-enabled resource
 * that extends FullCalendarResource (which extends ModelResource).
 *
 * Features:
 * - Calendar view with month/week/day/list modes
 * - Eloquent model integration with automatic date filtering
 * - Modal-based CRUD operations
 * - Custom calendar configuration
 *
 * @extends FullCalendarResource<Event>
 */
class EventResource extends FullCalendarResource
{
    /**
     * The Eloquent model class
     * This is required for ModelResource to work
     */
    protected string $model = Event::class;

    /**
     * Resource title displayed in UI
     */
    protected string $title = 'Events';

    /**
     * Column used for displaying the resource in relationships
     */
    protected string $column = 'title';

    /**
     * Relationships to eager load
     */
    protected array $with = [];

    /**
     * Enable/create in modal
     */
    protected bool $createInModal = true;

    /**
     * Enable edit in modal
     */
    protected bool $editInModal = true;

    /**
     * Redirect after save
     */
    protected ?string $redirectAfterSave = null;

    /**
     * Date column names for calendar filtering
     * Override if your model uses different column names
     */
    protected string $startColumn = 'start';
    protected string $endColumn = 'end';

    /**
     * Register pages for this resource
     * FullCalendarIndexPage provides the calendar view
     */
    protected function pages(): array
    {
        return [
            FullCalendarIndexPage::class,
            EventFormPage::class,
        ];
    }

    /**
     * Define fields for the resource
     * These fields are used in forms and for display
     */
    public function fields(): array
    {
        return [
            ID::make()
                ->sortable()
                ->useOnImport(),

            Text::make('Title', 'title')
                ->required()
                ->sortable(),

            DateTime::make('Start', 'start')
                ->required()
                ->sortable()
                ->format('Y-m-d H:i'),

            DateTime::make('End', 'end')
                ->required()
                ->sortable()
                ->format('Y-m-d H:i'),

            Color::make('Color', 'color')
                ->nullable(),

            Textarea::make('Description', 'description')
                ->nullable(),
        ];
    }

    /**
     * Configure calendar view options
     * Called automatically when the calendar is initialized
     */
    public function __construct()
    {
        parent::__construct();

        // Customize calendar appearance
        $this->setDefaultView(self::VIEW_MONTH)
            ->setHeaderToolbar([
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            ])
            ->setEditable(true)  // Enable drag and drop
            ->setSelectable(true)  // Enable date selection
            ->setLocale('en')
            ->setTimezone(config('app.timezone'));
    }

    /**
     * Custom event formatting (optional)
     * Override this method to customize how events are formatted for FullCalendar
     *
     * @param \Illuminate\Database\Eloquent\Model $item
     * @return array
     */
    protected function formatEvent(\Illuminate\Database\Eloquent\Model $item): array
    {
        $event = parent::formatEvent($item);

        // Add custom styling or extended props
        $event['backgroundColor'] = $item->color ?? '#3b82f6';
        $event['borderColor'] = $item->color ?? '#2563eb';

        // Add custom extended props
        $event['extendedProps'] = [
            'description' => $item->description,
            'location' => $item->location ?? null,
        ];

        return $event;
    }

    /**
     * Custom filters for the index page (optional)
     * Add filters that can be applied when fetching events
     */
    public function filters(): array
    {
        return [
            // Add filters here if needed
            // Text::make('Search', 'title')->customFilter(),
        ];
    }

    /**
     * Actions for the resource (optional)
     * Define custom actions that appear on the index page
     */
    public function actions(): array
    {
        return [
            // Add custom actions here
        ];
    }
}
