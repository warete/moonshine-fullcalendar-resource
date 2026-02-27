<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Tests\Feature;

use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\Date;
use PHPUnit\Framework\Attributes\Test;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;
use Warete\MoonShineFullCalendar\Tests\TestCase;

final class FullCalendarCreateConfigTest extends TestCase
{
    #[Test]
    public function it_enables_create_modal_by_default_for_calendar_resources(): void
    {
        /** @var TestCalendarResource $resource */
        $resource = $this->app->make(TestCalendarResource::class);

        self::assertTrue($resource->isCreateInModal());
    }

    #[Test]
    public function it_exposes_create_from_grid_config_with_expected_param_names(): void
    {
        $this->actingAs($this->adminUser, 'moonshine');

        /** @var TestCalendarResource $resource */
        $resource = $this->app->make(TestCalendarResource::class);

        $config = $resource->getCalendarCreateConfig();

        self::assertTrue($config['enabled']);
        self::assertSame('resource-create-modal-calendar-grid', $config['modalName']);
        self::assertSame('start', $config['startParam']);
        self::assertSame('end', $config['endParam']);
        self::assertFalse($config['openOnDoubleClick']);
        self::assertSame(60, $config['timedFallbackDurationMinutes']);
        self::assertSame(1, $config['allDayFallbackDurationDays']);
    }

    #[Test]
    public function it_can_require_double_click_for_grid_create(): void
    {
        $this->actingAs($this->adminUser, 'moonshine');

        /** @var DoubleClickCalendarResource $resource */
        $resource = $this->app->make(DoubleClickCalendarResource::class);

        $config = $resource->getCalendarCreateConfig();

        self::assertTrue($config['enabled']);
        self::assertTrue($config['openOnDoubleClick']);
    }

    #[Test]
    public function it_prefills_create_form_fields_from_request_using_configured_columns(): void
    {
        $this->app['request']->query->set('starts_at', '2026-02-27T09:15:00.000Z');
        $this->app['request']->query->set('ends_at', '2026-02-27T10:15:00.000Z');

        /** @var PrefilledCalendarResource $resource */
        $resource = $this->app->make(PrefilledCalendarResource::class);

        $fields = $resource->getFormFields()->onlyFields();

        self::assertSame(
            '2026-02-27T09:15',
            $this->findFieldByColumn($fields->toArray(), 'starts_at')?->getValue(false)
        );
        self::assertSame(
            '2026-02-27T10:15',
            $this->findFieldByColumn($fields->toArray(), 'ends_at')?->getValue(false)
        );
    }

    /**
     * @param  list<FieldContract>  $fields
     */
    private function findFieldByColumn(array $fields, string $column): ?FieldContract
    {
        foreach ($fields as $field) {
            if ($field->getColumn() === $column) {
                return $field;
            }
        }

        return null;
    }
}

final class TestCalendarResource extends FullCalendarResource
{
    protected string $model = MoonshineUser::class;

    public function fields(): iterable
    {
        return [];
    }
}

final class PrefilledCalendarResource extends FullCalendarResource
{
    protected string $model = MoonshineUser::class;

    protected string $startColumn = 'starts_at';

    protected string $endColumn = 'ends_at';

    protected function formFields(): iterable
    {
        return [
            Date::make('Start', $this->startColumn)->withTime(),
            Date::make('End', $this->endColumn)->withTime(),
        ];
    }
}

final class DoubleClickCalendarResource extends FullCalendarResource
{
    protected string $model = MoonshineUser::class;

    protected bool $createFromGridOnDoubleClick = true;

    public function fields(): iterable
    {
        return [];
    }
}
