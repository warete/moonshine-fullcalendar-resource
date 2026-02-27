[Back to README](../../README.md) · [Configuration →](configuration.md)

# Usage

## Basic Resource Setup

Use `FullCalendarResource` as the base resource class and keep `FullCalendarIndexPage` in the resource page list.

```php
<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\Event;
use MoonShine\Crud\Contracts\Page\FormPageContract;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use Warete\MoonShineFullCalendar\Pages\FullCalendarIndexPage;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;

final class EventResource extends FullCalendarResource
{
    protected string $model = Event::class;

    protected function pages(): array
    {
        return [
            FullCalendarIndexPage::class,
            FormPageContract::class,
        ];
    }

    protected function formFields(): array
    {
        return [
            ID::make(),
            Text::make('Title', 'title')->required(),
            Date::make('Start', 'start')->withTime()->required(),
            Date::make('End', 'end')->withTime()->required(),
        ];
    }
}
```

## Calendar Runtime Behavior

- The index page renders a FullCalendar instance instead of the default list component.
- Events are fetched asynchronously for the current visible range.
- Clicking an event opens the backend-rendered action dropdown.
- Async create, edit, delete, and date updates dispatch `fullcalendar:refresh`.
- The current calendar instance refetches only when the `resource` payload matches.

## Advanced Example

```php
<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\CalendarEvents;

use App\Models\CalendarEvent;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Crud\Contracts\Page\DetailPageContract;
use MoonShine\Crud\Contracts\Page\FormPageContract;
use MoonShine\UI\Fields\Color;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use Warete\MoonShineFullCalendar\Pages\FullCalendarIndexPage;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;

final class CalendarEventsResource extends FullCalendarResource
{
    protected string $model = CalendarEvent::class;

    protected string $title = 'CalendarEvents';

    protected string $startColumn = 'start';

    protected string $endColumn = 'end';

    protected bool $createFromGridOnDoubleClick = true;

    protected function pages(): array
    {
        return [
            FullCalendarIndexPage::class,
            FormPageContract::class,
            DetailPageContract::class,
        ];
    }

    protected function formFields(): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Title', 'title')->required()->sortable(),
            Date::make('Start', $this->startColumn)->required()->sortable()->withTime()->format('Y-m-d H:i'),
            Date::make('End', $this->endColumn)->required()->sortable()->withTime()->format('Y-m-d H:i'),
            Color::make('Color', 'color')->nullable(),
            Textarea::make('Description', 'description')->nullable(),
        ];
    }

    public function __construct(CoreContract $core)
    {
        parent::__construct($core);

        $this->setDefaultView(self::VIEW_DAY)
            ->setHeaderToolbar([
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            ])
            ->setEditable(true)
            ->setSelectable(true)
            ->setLocale('ru')
            ->setTimezone(config('app.timezone'));
    }

    protected function formatEvent(Model $item): array
    {
        $event = parent::formatEvent($item);

        $event['backgroundColor'] = $item->color ?? '#3b82f6';
        $event['borderColor'] = $item->color ?? '#2563eb';
        $event['extendedProps'] = [
            'description' => $item->description,
            'location' => $item->location ?? null,
        ];

        return $event;
    }
}
```

## Per-Event Actions

Override `getCustomCalendarEventActions(Model $item): iterable` to append additional MoonShine action buttons to the event dropdown.

## Advanced Page Customization

If you replace the default list component on a derived page, use `Warete\MoonShineFullCalendar\Components\FullCalendarPageComponent`.

## See Also

- [Configuration](configuration.md) - Configure views, locale, and create-from-grid
- [API Reference](api.md) - Review request and event contracts
- [Русская версия](../ru/usage.md) - Read the same guide in Russian
