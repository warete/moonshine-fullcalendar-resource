[← Usage](usage.md) · [Back to README](../../README.md) · [API Reference →](api.md)

# Configuration

## Calendar Options

`FullCalendarResource` exposes fluent setters for runtime calendar configuration.

| Method | Purpose |
|--------|---------|
| `setDefaultView(string $view)` | Change the initial FullCalendar view |
| `setHeaderToolbar(array $toolbar)` | Override toolbar sections |
| `setEditable(bool $editable)` | Enable drag and resize callbacks |
| `setSelectable(bool $selectable)` | Enable date click and range selection |
| `setLocale(string $locale)` | Set the FullCalendar locale code |
| `setTimezone(?string $timezone)` | Set the display timezone |
| `setCalendarOptions(array $options)` | Merge additional FullCalendar options |

## Supported View Constants

- `FullCalendarResource::VIEW_MONTH`
- `FullCalendarResource::VIEW_WEEK`
- `FullCalendarResource::VIEW_DAY`
- `FullCalendarResource::VIEW_LIST`

## Date Columns

Override these properties when your model uses custom column names:

```php
protected string $startColumn = 'starts_at';
protected string $endColumn = 'ends_at';
```

The package uses these same names for event fetch filtering, create form prefilling, and date mutation persistence.

## Create From Grid

Calendar-to-modal creation is configured through resource properties.

| Property | Default | Purpose |
|----------|---------|---------|
| `createInModal` | `true` | Enables MoonShine modal create flow |
| `createFromGridOnDoubleClick` | `true` | Requires a second click before opening create |
| `calendarCreateModalName` | `resource-create-modal-calendar-grid` | Modal identifier for async create |

`getCalendarCreateConfig()` exposes:

- `enabled`
- `modalName`
- `startParam`
- `endParam`
- `openOnDoubleClick`
- `timedFallbackDurationMinutes`
- `allDayFallbackDurationDays`

## Timezone Notes

- Display timezone defaults to `config('app.timezone')`.
- The frontend normalizes legacy `timezone` config into FullCalendar's `timeZone`.
- Incoming drag/drop and resize payloads are normalized back into the configured timezone before save.
- Date updates are accepted only when the resource is editable and the current user can perform the `UPDATE` action.

## Extended Props Policy

- `extendedProps` are empty by default, except for `moonshineFullCalendar.actions`.
- Override `getCalendarEventExtendedProps(Model $item): array` to expose an explicit allowlist of extra values.

## Hooks You Can Override

- `fetchEvents(?string $start, ?string $end, array $params): iterable`
- `formatEvent(Model $item): array`
- `getCalendarEventExtendedProps(Model $item): array`
- `getCustomCalendarEventActions(Model $item): iterable`
- `applyCalendarEventDateUpdate(Model $item, string $start, ?string $end, array $payload, ?CrudRequestContract $request = null): void`

## Removed Development Toggles

Release builds do not expose package-specific debug flags. `FULLCALENDAR_LOG_LEVEL` and `FULLCALENDAR_DEBUG` are not part of the supported configuration surface.

## See Also

- [Usage](usage.md) - See a complete resource example
- [API Reference](api.md) - Review endpoint and browser event payloads
- [Русская версия](../ru/configuration.md) - Read the same guide in Russian
