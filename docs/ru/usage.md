[Назад в README](../../README.md) · [Конфигурация →](configuration.md)

# Использование

## Базовая настройка ресурса

Используйте `FullCalendarResource` как базовый класс ресурса и оставьте `FullCalendarIndexPage` в списке страниц ресурса.

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

## Поведение календаря

- Индексная страница рендерит FullCalendar вместо стандартного списка.
- События загружаются асинхронно для текущего видимого диапазона.
- По клику на событие открывается отрендеренный на бэкенде выпадающий список действий.
- Асинхронные создание, редактирование, удаление и обновления дат диспатчат `fullcalendar:refresh`.
- Текущий экземпляр календаря обновляется только при совпадении `resource` в payload.

## Расширенный пример

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

    protected function getCalendarEventExtendedProps(Model $item): array
    {
        return [
            'description' => $item->description,
            'location' => $item->location ?? null,
        ];
    }
}
```

## Действия для событий

Переопределите `getCustomCalendarEventActions(Model $item): iterable`, чтобы добавить свои `ActionButton` в выпадающий список действий события.

## Дополнительный payload события

`extendedProps` теперь работает по opt-in. По умолчанию пакет добавляет только `extendedProps.moonshineFullCalendar.actions`. Переопределите `getCalendarEventExtendedProps(Model $item): array`, если хотите явно отдать дополнительные значения в браузер.

## Кастомизация страницы

Если вы заменяете стандартный компонент списка на своей производной странице, используйте `Warete\MoonShineFullCalendar\Components\FullCalendarPageComponent`.

## См. также

- [Конфигурация](configuration.md) - Настройка views, locale и create-from-grid
- [API Reference](api.md) - Контракты запросов и событий
- [English version](../en/usage.md) - Эта же страница на английском
