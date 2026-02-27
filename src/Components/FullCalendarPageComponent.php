<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Components;

use InvalidArgumentException;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Core\Traits\WithCore;
use MoonShine\Crud\Contracts\Page\IndexPageContract;
use MoonShine\Crud\Contracts\PageComponents\DefaultListComponentContract;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;

final class FullCalendarPageComponent implements DefaultListComponentContract
{
    use WithCore;

    public function __construct(CoreContract $core)
    {
        $this->setCore($core);
    }

    public function __invoke(
        IndexPageContract $page,
        iterable $items,
        FieldsContract $fields
    ): ComponentContract {
        unset($items, $fields);

        $resource = $page->getResource();

        if (! $resource instanceof FullCalendarResource) {
            throw new InvalidArgumentException(
                sprintf(
                    'Resource must extend %s, %s given',
                    FullCalendarResource::class,
                    get_debug_type($resource)
                )
            );
        }

        return new FullCalendarViewComponent(
            $page->getListComponentName(),
            $resource,
            $page,
            $this->getCore(),
        );
    }
}
