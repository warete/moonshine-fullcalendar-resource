[← Configuration](configuration.md) · [Back to README](../../README.md)

# API Reference

## Resource-Scoped Routes

Routes are registered with `Route::moonshine(..., withResource: true, withAuthenticate: true)`, so the final URLs are scoped by the active MoonShine resource.

| Method | Path | Route Name | Purpose |
|--------|------|------------|---------|
| `GET` | `/admin/resource/{resourceUri}/full-calendar/events` | `full-calendar.events.list` | Fetch events for the visible range |
| `PATCH` | `/admin/resource/{resourceUri}/full-calendar/events/{resourceItem}/dates` | `full-calendar.events.dates.update` | Persist drag/drop or resize changes |

The exact admin prefix is controlled by MoonShine, but the resource-scoped suffix remains the same.

## Event Fetch Request

| Parameter | Type | Notes |
|-----------|------|-------|
| `start` | ISO8601 string | Range start |
| `end` | ISO8601 string | Range end (exclusive) |
| `filters` | array | Optional resource-specific filters |

## Event Fetch Response

```php
[
    'id' => 15,
    'title' => 'Planning',
    'start' => '2026-02-27T09:00:00+03:00',
    'end' => '2026-02-27T10:00:00+03:00',
    'color' => '#3b82f6',
    'description' => 'Weekly planning',
    'extendedProps' => [
        'moonshineFullCalendar' => [
            'actions' => [
                'version' => 1,
                'html' => '<button ...>Edit</button>',
                'count' => 2,
                'hasActions' => true,
            ],
        ],
    ],
]
```

## Date Update Request

```json
{
  "start": "2026-02-27T09:00:00.000Z",
  "end": "2026-02-27T10:00:00.000Z",
  "allDay": false,
  "action": "drop",
  "timezone": "UTC"
}
```

Validation rules:

- `start`: required date
- `end`: nullable date
- `allDay`: nullable boolean
- `action`: required, one of `drop` or `resize`
- `timezone`: nullable string

## Date Update Response

- Success: `200 OK` with a MoonShine JSON response and a success toast
- Validation error: `422 Unprocessable Entity` with `message`, `messageType`, and `errors`
- Resource denial or not found: resource-controlled error status and message

Successful date updates also append the `fullcalendar:refresh` browser event for the current resource.

## Browser Event Contract

```js
window.dispatchEvent(new CustomEvent('fullcalendar:refresh', {
    detail: { resource: 'calendar-events' }
}));
```

- If `resource` is omitted, all mounted calendars refetch.
- If `resource` is present, only the matching calendar refetches.

## See Also

- [Configuration](configuration.md) - Configure date updates and calendar options
- [Usage](usage.md) - See how the resource hooks feed these payloads
- [Русская версия](../ru/api.md) - Read the same guide in Russian
