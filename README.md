# MoonShine FullCalendar Resource

<p align="center">
<a href="https://github.com/warete/moonshine-fullcalendar-resource/actions"><img src="https://github.com/warete/moonshine-fullcalendar-resource/workflows/tests/badge.svg" alt="Tests status"></a>
<a href="https://github.com/warete/moonshine-fullcalendar-resource/actions"><img src="https://github.com/warete/moonshine-fullcalendar-resource/workflows/phpstan/badge.svg" alt="Static analysis status"></a>
<a href="https://github.com/warete/moonshine-fullcalendar-resource/actions"><img src="https://github.com/warete/moonshine-fullcalendar-resource/workflows/mutate-tests/badge.svg" alt="Mutate tests status"></a>
<a href="https://packagist.org/packages/warete/moonshine-fullcalendar-resource"><img src="https://img.shields.io/packagist/dt/warete/moonshine-fullcalendar-resource" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/warete/moonshine-fullcalendar-resource"><img src="https://img.shields.io/packagist/v/warete/moonshine-fullcalendar-resource" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/warete/moonshine-fullcalendar-resource"><img src="https://img.shields.io/packagist/l/warete/moonshine-fullcalendar-resource" alt="License"></a>
</p>

A Laravel package that integrates [FullCalendar.js](https://fullcalendar.io/) into [MoonShine v4](https://moonshine.laravel.com/) admin panel. Provides a calendar UI component for resource index pages with async event loading, modal editing, and FullCalendar.js integration.

## Features

- **Calendar UI component** for MoonShine resources with month/week/day/list views
- **Eloquent model integration** via ModelResource with automatic date filtering
- **Async event loading** by date range using MoonShine.request
- **Modal-based CRUD operations** with editInModal integration
- **Alpine.js component** for reactive calendar interactions
- **Customizable** view modes, toolbar, locale, and timezone
- **MoonShine v4 architecture** with proper pages and components

## Requirements

- PHP 8.2+
- Laravel 11
- MoonShine v4
- Node.js 18+ (for asset compilation)

## Installation

### 1. Install via Composer

```bash
composer require warete/moonshine-fullcalendar-resource
```

### 2. Publish Assets

```bash
php artisan vendor:publish --tag=moonshine-fullcalendar-assets
```

### 3. Compile Frontend Assets

```bash
npm install
npm run build
```

### 4. Register Service Provider

The package will auto-register its service provider via Laravel's package discovery.

## Quick Start

### 1. Create Your Model

First, create an Eloquent model with `start` and `end` datetime columns:

```php
// app/Models/Event.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title',
        'start',
        'end',
        'description',
        'color',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
    ];
}
```

### 2. Create the Resource

Create a resource extending `FullCalendarResource`:

```php
// app/MoonShine/Resources/EventResource.php
namespace App\MoonShine\Resources;

use App\Models\Event;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\DateTime;
use MoonShine\UI\Fields\Color;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;
use Warete\MoonShineFullCalendar\Pages\FullCalendarIndexPage;

class EventResource extends FullCalendarResource
{
    protected string $model = Event::class;
    protected string $title = 'Events';
    protected string $column = 'title';

    protected bool $createInModal = true;
    protected bool $editInModal = true;

    // Date column names (override if different)
    protected string $startColumn = 'start';
    protected string $endColumn = 'end';

    protected function pages(): array
    {
        return [
            FullCalendarIndexPage::class,
            // Add your FormPage::class here
        ];
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Text::make('Title', 'title')->required(),
            DateTime::make('Start', 'start')->required(),
            DateTime::make('End', 'end')->required(),
            Color::make('Color', 'color')->nullable(),
            Textarea::make('Description', 'description')->nullable(),
        ];
    }
}
```

### 3. Register in MoonShine Provider

```php
// app/Providers/MoonShineServiceProvider.php
use App\MoonShine\Resources\EventResource;

public function resources(): array
{
    return [
        EventResource::class,
        // ... other resources
    ];
}
```

## Configuration

### Calendar Options

Customize calendar appearance and behavior in your resource's constructor:

```php
public function __construct()
{
    parent::__construct();

    $this->setDefaultView(FullCalendarResource::VIEW_MONTH)
        ->setHeaderToolbar([
            'left' => 'prev,next today',
            'center' => 'title',
            'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        ])
        ->setEditable(true)      // Enable drag & drop
        ->setSelectable(true)    // Enable date selection
        ->setLocale('en')
        ->setTimezone('UTC');
}
```

### Available View Modes

- `FullCalendarResource::VIEW_MONTH` - Month view (default)
- `FullCalendarResource::VIEW_WEEK` - Week view
- `FullCalendarResource::VIEW_DAY` - Day view
- `FullCalendarResource::VIEW_LIST` - List view

### Custom Date Columns

If your model uses different column names:

```php
protected string $startColumn = 'start_date';
protected string $endColumn = 'end_date';
```

### Logging

Enable debug logging:

```env
# .env
FULLCALENDAR_LOG_LEVEL=debug
FULLCALENDAR_DEBUG=true
```

## Auto-Refresh After CRUD Operations

The calendar automatically refreshes after creating and updating events through MoonShine modal forms. This is handled through a custom event system.

### How It Works

1. **PHP Side:** When a calendar event is saved via modal, the `modifySaveResponse()` method adds a `fullcalendar:refresh` event to the response
2. **JavaScript Side:** The calendar component listens for this event and refetches events automatically
3. **Resource Filtering:** Events are filtered by resource URI, so only the relevant calendar refreshes

### Event Format

```php
// MoonShine event format: eventName|key1~value1
fullcalendar:refresh|resource~events
```

### Customization

#### Disable Auto-Refresh

Override `modifySaveResponse` in your resource to return the response without events:

```php
public function modifySaveResponse(\MoonShine\Crud\JsonResponse $response): \MoonShine\Crud\JsonResponse
{
    // Return response without adding refresh event
    return $response;
}
```

#### Custom Refresh Logic

Override `modifySaveResponse` to add custom behavior:

```php
public function modifySaveResponse(\MoonShine\Crud\JsonResponse $response): \MoonShine\Crud\JsonResponse
{
    $response = parent::modifySaveResponse($response);

    // Add your custom logic here
    // For example: send notifications, update related data, etc.

    return $response;
}
```

#### Manual Refresh

Trigger a calendar refresh manually from JavaScript:

```javascript
// Refresh all calendar instances
window.fullCalendarRefresh();

// Or dispatch the event directly with parameters
document.dispatchEvent(new CustomEvent('fullcalendar:refresh', {
    detail: { resource: 'events' }
}));
```

### Debugging

Enable debug logging to see refresh event flow:

```env
FULLCALENDAR_LOG_LEVEL=debug
FULLCALENDAR_DEBUG=true
```

Console logs will show:
- `[refresh] Setting up calendar refresh listener`
- `[refresh] Refresh event received`
- `[refresh] Resource match detected, refreshing calendar`

## API Reference

### FullCalendarResource

Extends `MoonShine\Laravel\Resources\ModelResource` with calendar-specific functionality.

#### Methods

- `getCalendarItems(array $params): array` - Fetch events formatted for FullCalendar
- `getCalendarConfig(): array` - Get calendar configuration for frontend
- `getEventsEndpoint(): string` - Get API endpoint for event fetching
- `setDefaultView(string $view): self` - Set default calendar view
- `setHeaderToolbar(array $toolbar): self` - Customize toolbar
- `setEditable(bool $editable): self` - Enable drag & drop
- `setSelectable(bool $selectable): self` - Enable date selection
- `setLocale(string $locale): self` - Set locale
- `setTimezone(?string $timezone): self` - Set timezone

#### Overridable Methods

- `fetchEvents(?string $start, ?string $end, array $params): iterable` - Custom event fetching logic
- `formatEvent(Model $item): array` - Custom event formatting

### FullCalendarIndexPage

Extends `MoonShine\Laravel\Pages\Crud\IndexPage` to provide calendar view.

```php
class EventIndexPage extends FullCalendarIndexPage
{
    // Customize page behavior
}
```

## Examples

### Custom Event Formatting

```php
protected function formatEvent(Model $item): array
{
    $event = parent::formatEvent($item);

    // Add custom styling
    $event['backgroundColor'] = $item->category->color ?? '#3b82f6';

    // Add extended props
    $event['extendedProps'] = [
        'location' => $item->location,
        'attendees' => $item->attendees,
    ];

    return $event;
}
```

### Custom Event Fetching with Filters

```php
protected function fetchEvents(?string $start, ?string $end, array $params): iterable
{
    $query = $this->query();

    // Apply date range (automatically handled by parent)
    if ($start && $end) {
        $query->whereBetween($this->startColumn, [$start, $end]);
    }

    // Custom filters
    if (isset($params['category_id'])) {
        $query->where('category_id', $params['category_id']);
    }

    return $query->with(['category', 'attendees'])->get();
}
```

### Event Handlers (JavaScript)

Define handlers in your views for calendar interactions:

```javascript
window.moonshineFullCalendarEventClick = function(info) {
    // Handle event click
    window.location.href = '/admin/events/' + info.event.id + '/edit';
};

window.moonshineFullCalendarDateSelect = function(info) {
    // Handle date selection for new event
    window.location.href = '/admin/events/create?start=' + info.start.toISOString();
};
```

## Troubleshooting

### Calendar not displaying

1. Ensure assets are published: `php artisan vendor:publish --tag=moonshine-fullcalendar-assets`
2. Check browser console for JavaScript errors
3. Verify `FULLCALENDAR_DEBUG=true` for detailed logs

### Events not loading

1. Check the events endpoint is accessible
2. Verify `start` and `end` columns match your database schema
3. Enable `FULLCALENDAR_LOG_LEVEL=debug` to see query logs

### Styling issues

1. Ensure MoonShine CSS is loaded
2. Check Tailwind CSS configuration
3. Verify FullCalendar CSS is included in compiled assets

## Roadmap (Post-MVP)

- Custom Tailwind theme for FullCalendar
- Event ownership and ACL
- Recurring events (RRULE)
- Conflict detection
- Advanced filters and categories
- Drag & drop UI for event movement
- Export to iCal/Google Calendar
- Resource bookings and availability

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-source software licensed under the [MIT license](LICENSE.md).
