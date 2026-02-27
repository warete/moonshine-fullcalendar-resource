[← Конфигурация](configuration.md) · [Назад в README](../../README.md)

# API Reference

## Маршруты с привязкой к ресурсу

Маршруты регистрируются через `Route::moonshine(..., withResource: true, withAuthenticate: true)`, поэтому итоговые URL привязаны к активному MoonShine-ресурсу.

| Метод | Путь | Route Name | Назначение |
|-------|------|------------|------------|
| `GET` | `/admin/resource/{resourceUri}/full-calendar/events` | `full-calendar.events.list` | Получение событий для видимого диапазона |
| `PATCH` | `/admin/resource/{resourceUri}/full-calendar/events/{resourceItem}/dates` | `full-calendar.events.dates.update` | Сохранение drag/drop и resize |

Точный admin prefix управляется MoonShine, но суффикс с привязкой к ресурсу остаётся тем же.

## Запрос на получение событий

| Параметр | Тип | Описание |
|----------|-----|----------|
| `start` | ISO8601 string | Начало диапазона |
| `end` | ISO8601 string | Конец диапазона (exclusive) |
| `filters` | array | Опциональные фильтры ресурса |

## Ответ со списком событий

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

По умолчанию гарантирован только `extendedProps.moonshineFullCalendar.actions`. Любые другие ключи в `extendedProps` появляются только если ресурс явно возвращает их из `getCalendarEventExtendedProps(Model $item): array`.

## Запрос на обновление дат

```json
{
  "start": "2026-02-27T09:00:00.000Z",
  "end": "2026-02-27T10:00:00.000Z",
  "allDay": false,
  "action": "drop",
  "timezone": "UTC"
}
```

Правила валидации:

- `start`: обязательная дата
- `end`: nullable date
- `allDay`: nullable boolean
- `action`: обязательный, `drop` или `resize`
- `timezone`: nullable string

## Ответ на обновление дат

- Успех: `200 OK` с MoonShine JSON response и сообщением об успешном сохранении
- Ошибка валидации: `422 Unprocessable Entity` с `message`, `messageType` и `errors`
- Запрет: `403 Forbidden`, если ресурс не editable или текущему пользователю недоступно действие `UPDATE`
- Запрет или отсутствие ресурса: код и сообщение определяются ресурсом

При успешном обновлении дат также добавляется браузерное событие `fullcalendar:refresh` для текущего ресурса.

## Контракт браузерного события

```js
window.dispatchEvent(new CustomEvent('fullcalendar:refresh', {
    detail: { resource: 'calendar-events' }
}));
```

- Если `resource` не передан, обновляются все смонтированные календари.
- Если `resource` передан, обновляется только совпадающий календарь.

## См. также

- [Конфигурация](configuration.md) - Настройка дат и опций календаря
- [Использование](usage.md) - Как resource hooks формируют эти payload
- [English version](../en/api.md) - Эта же страница на английском
