<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\Action;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;
use Warete\MoonShineFullCalendar\Tests\TestCase;

final class FullCalendarSecurityGuardsTest extends TestCase
{
    #[Test]
    public function it_rejects_date_updates_when_update_ability_is_denied(): void
    {
        /** @var GuardedCalendarResource $resource */
        $resource = $this->app->make(GuardedCalendarResource::class);
        $resource->setEditable(true);
        $resource->allowUpdateAbility = false;

        $response = $resource->updateCalendarEventDates('15', [
            'start' => '2026-02-27T10:00:00Z',
            'end' => '2026-02-27T11:00:00Z',
            'action' => 'drop',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertFalse($resource->didApplyDateUpdate);
    }

    #[Test]
    public function it_does_not_expose_model_attributes_in_extended_props_by_default(): void
    {
        /** @var DefaultExtendedPropsResource $resource */
        $resource = $this->app->make(DefaultExtendedPropsResource::class);

        $event = $resource->exposeFormattedEvent(new SecurityTestModel([
            'id' => 7,
            'title' => 'Board meeting',
            'starts_at' => '2026-02-27 10:00:00',
            'ends_at' => '2026-02-27 11:00:00',
            'email' => 'private@example.com',
            'internal_notes' => 'secret',
        ]));

        self::assertArrayHasKey('extendedProps', $event);
        self::assertArrayNotHasKey('email', $event['extendedProps']);
        self::assertArrayNotHasKey('internal_notes', $event['extendedProps']);
        self::assertSame(
            [
                'version' => 1,
                'html' => '',
                'count' => 0,
                'hasActions' => false,
            ],
            $event['extendedProps']['moonshineFullCalendar']['actions'] ?? null
        );
    }

    #[Test]
    public function it_allows_resources_to_opt_in_specific_extended_props(): void
    {
        /** @var OptInExtendedPropsResource $resource */
        $resource = $this->app->make(OptInExtendedPropsResource::class);

        $event = $resource->exposeFormattedEvent(new SecurityTestModel([
            'id' => 9,
            'title' => 'Review',
            'starts_at' => '2026-02-27 12:00:00',
            'ends_at' => '2026-02-27 13:00:00',
            'status' => 'visible',
            'email' => 'private@example.com',
        ]));

        self::assertSame('visible', $event['extendedProps']['publicStatus'] ?? null);
        self::assertArrayNotHasKey('email', $event['extendedProps']);
        self::assertArrayHasKey('moonshineFullCalendar', $event['extendedProps']);
    }
}

final class SecurityTestModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

abstract class SecurityBaseCalendarResource extends FullCalendarResource
{
    protected string $model = SecurityTestModel::class;

    protected string $column = 'title';

    protected string $startColumn = 'starts_at';

    protected string $endColumn = 'ends_at';

    public function fields(): iterable
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function exposeFormattedEvent(Model $item): array
    {
        return $this->formatEvent($item);
    }

    /**
     * @return array{version:int,html:string,count:int,hasActions:bool}
     */
    protected function buildCalendarEventActionsPayload(Model $item): array
    {
        unset($item);

        return [
            'version' => 1,
            'html' => '',
            'count' => 0,
            'hasActions' => false,
        ];
    }
}

final class DefaultExtendedPropsResource extends SecurityBaseCalendarResource
{
}

final class OptInExtendedPropsResource extends SecurityBaseCalendarResource
{
    /**
     * @return array<string, mixed>
     */
    protected function getCalendarEventExtendedProps(Model $item): array
    {
        return [
            'publicStatus' => $item->getAttribute('status'),
        ];
    }
}

final class GuardedCalendarResource extends SecurityBaseCalendarResource
{
    public bool $allowUpdateAbility = true;

    public bool $allowUpdateAction = true;

    public bool $didApplyDateUpdate = false;

    public function can(string|Ability $ability): bool
    {
        if (($ability instanceof Ability ? $ability : Ability::from($ability)) === Ability::UPDATE) {
            return $this->allowUpdateAbility;
        }

        return parent::can($ability);
    }

    public function hasAction(Action ...$actions): bool
    {
        foreach ($actions as $action) {
            if ($action === Action::UPDATE && ! $this->allowUpdateAction) {
                return false;
            }
        }

        return parent::hasAction(...$actions);
    }

    protected function resolveCalendarEventForDateUpdate(string $resourceItem): Model
    {
        $item = new SecurityTestModel([
            'id' => (int) $resourceItem,
            'title' => 'Guarded',
            'starts_at' => '2026-02-27 10:00:00',
            'ends_at' => '2026-02-27 11:00:00',
        ]);

        $this->setItem($item);

        return $item;
    }

    protected function applyCalendarEventDateUpdate(
        Model $item,
        string $start,
        ?string $end,
        array $payload,
        ?\MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract $request = null
    ): void {
        unset($item, $start, $end, $payload, $request);

        $this->didApplyDateUpdate = true;
    }
}
