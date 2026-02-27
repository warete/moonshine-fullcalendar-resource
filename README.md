# MoonShine FullCalendar Resource

<!-- screenshots:
![Calendar index](./docs/images/calendar-index.png)
![Event actions](./docs/images/event-actions.png)
-->

<p align="center">
<a href="https://packagist.org/packages/warete/moonshine-fullcalendar-resource"><img src="https://img.shields.io/packagist/dt/warete/moonshine-fullcalendar-resource" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/warete/moonshine-fullcalendar-resource"><img src="https://img.shields.io/packagist/v/warete/moonshine-fullcalendar-resource" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/warete/moonshine-fullcalendar-resource"><img src="https://img.shields.io/packagist/l/warete/moonshine-fullcalendar-resource" alt="License"></a>
</p>

> FullCalendar-powered MoonShine resource pages with async loading, modal CRUD flows, and resource-scoped refresh.

`warete/moonshine-fullcalendar-resource` adds a reusable calendar index page for MoonShine v4 resources. It keeps the standard MoonShine CRUD flow, loads events asynchronously, supports event-specific action dropdowns, and refreshes the active calendar after create, update, delete, and date mutations.

**Languages:** [English](README.md) | [Русский](docs/ru/usage.md)

## Compatibility

| MoonShine | FullCalendar Resource | Currently supported |
|-----------|------------------------|---------------------|
| `4.x` | `1.x` | `yes` |

## Installation

```bash
composer require warete/moonshine-fullcalendar-resource
php artisan vendor:publish --tag=moonshine-fullcalendar-assets
```

The package already ships compiled assets in `public/`, so for package consumers publishing vendor assets is enough. `npm install` and `npm run build` are only needed when developing this package itself.

## Key Features

- Async event loading for the visible FullCalendar range
- MoonShine modal create/edit/delete flows with automatic calendar refresh
- Resource-level hooks for event formatting and per-event actions
- Support for custom `ActionButton` items per calendar event
- Configurable views, toolbar, locale, timezone, and grid-create behavior

## Example

```php
<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\CalendarEvent;
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

    protected string $title = 'Calendar Events';

    protected string $startColumn = 'start';

    protected string $endColumn = 'end';

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
            Text::make('Title', 'title')->required(),
            Date::make('Start', $this->startColumn)->withTime()->required(),
            Date::make('End', $this->endColumn)->withTime()->required(),
            Color::make('Color', 'color')->nullable(),
            Textarea::make('Description', 'description')->nullable(),
        ];
    }
}
```

## Event Actions

Every calendar event can expose backend-rendered MoonShine actions in the event dropdown. To append your own actions, override `getCustomCalendarEventActions(Model $item): iterable` and return `ActionButton` instances or raw HTML strings.

```php
use Illuminate\Database\Eloquent\Model;
use MoonShine\UI\Components\ActionButton;

protected function getCustomCalendarEventActions(Model $item): iterable
{
    return [
        ActionButton::make('Duplicate', route('events.duplicate', $item))
            ->primary()
            ->async(),
    ];
}
```

## Documentation

- [English documentation](docs/en/usage.md)
- [Русская документация](docs/ru/usage.md)

## Support

- Source: [github.com/warete/moonshine-fullcalendar-resource](https://github.com/warete/moonshine-fullcalendar-resource)
- Issues: [github.com/warete/moonshine-fullcalendar-resource/issues](https://github.com/warete/moonshine-fullcalendar-resource/issues)
- Package: [packagist.org/packages/warete/moonshine-fullcalendar-resource](https://packagist.org/packages/warete/moonshine-fullcalendar-resource)

## License

MIT
