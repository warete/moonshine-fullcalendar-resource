[← Использование](usage.md) · [Назад в README](../../README.md) · [API Reference →](api.md)

# Конфигурация

## Опции календаря

`FullCalendarResource` предоставляет fluent-методы для настройки календаря.

| Метод | Назначение |
|-------|------------|
| `setDefaultView(string $view)` | Меняет стартовый вид FullCalendar |
| `setHeaderToolbar(array $toolbar)` | Переопределяет секции панели инструментов |
| `setEditable(bool $editable)` | Включает обработчики drag и resize |
| `setSelectable(bool $selectable)` | Включает клики по датам и выбор диапазона |
| `setLocale(string $locale)` | Задаёт код локали FullCalendar |
| `setTimezone(?string $timezone)` | Задаёт таймзону отображения |
| `setCalendarOptions(array $options)` | Добавляет дополнительные опции FullCalendar |

## Константы видов

- `FullCalendarResource::VIEW_MONTH`
- `FullCalendarResource::VIEW_WEEK`
- `FullCalendarResource::VIEW_DAY`
- `FullCalendarResource::VIEW_LIST`

## Колонки дат

Переопределите свойства, если в модели используются свои имена колонок:

```php
protected string $startColumn = 'starts_at';
protected string $endColumn = 'ends_at';
```

Эти же имена используются для фильтрации выборки, предзаполнения формы создания и сохранения обновлений дат.

## Создание из сетки календаря

Создание из календарной сетки настраивается через свойства ресурса.

| Свойство | Значение по умолчанию | Назначение |
|----------|------------------------|------------|
| `createInModal` | `true` | Включает создание в модальном окне MoonShine |
| `createFromGridOnDoubleClick` | `true` | Требует второй клик перед открытием create |
| `calendarCreateModalName` | `resource-create-modal-calendar-grid` | Идентификатор асинхронной модалки |

`getCalendarCreateConfig()` возвращает:

- `enabled`
- `modalName`
- `startParam`
- `endParam`
- `openOnDoubleClick`
- `timedFallbackDurationMinutes`
- `allDayFallbackDurationDays`

## Таймзона

- Display timezone по умолчанию берётся из `config('app.timezone')`.
- Фронтенд нормализует legacy-параметр `timezone` в `timeZone`.
- Данные drag/drop и resize нормализуются обратно в настроенную таймзону перед сохранением.

## Переопределяемые хуки

- `fetchEvents(?string $start, ?string $end, array $params): iterable`
- `formatEvent(Model $item): array`
- `getCustomCalendarEventActions(Model $item): iterable`
- `applyCalendarEventDateUpdate(Model $item, string $start, ?string $end, array $payload, ?CrudRequestContract $request = null): void`

## Удалённые debug-настройки

В release-сборке package-specific debug-флаги не поддерживаются. `FULLCALENDAR_LOG_LEVEL` и `FULLCALENDAR_DEBUG` не входят в публичную конфигурацию пакета.

## См. также

- [Использование](usage.md) - Полный пример ресурса
- [API Reference](api.md) - Контракты endpoint'ов и browser events
- [English version](../en/configuration.md) - Эта же страница на английском
