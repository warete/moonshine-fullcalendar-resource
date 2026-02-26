<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Tests\Feature;

use Illuminate\Http\Request;
use MoonShine\Contracts\Core\CrudResourceContract;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Crud\JsonResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Warete\MoonShineFullCalendar\Http\Controllers\FullCalendarEventsController;
use Warete\MoonShineFullCalendar\Resources\FullCalendarResource;
use Warete\MoonShineFullCalendar\Tests\TestCase;

final class FullCalendarEventsControllerTest extends TestCase
{
    #[Test]
    public function it_delegates_dates_update_to_resource_and_returns_json_response(): void
    {
        $resource = $this->getMockBuilder(FullCalendarResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUriKey', 'updateCalendarEventDates'])
            ->getMockForAbstractClass();

        $resource->method('getUriKey')->willReturn('calendar-events');

        $resource->expects($this->once())
            ->method('updateCalendarEventDates')
            ->with(
                '15',
                $this->callback(static function (array $payload): bool {
                    return $payload['action'] === 'drop'
                        && $payload['allDay'] === false
                        && isset($payload['start'])
                        && array_key_exists('end', $payload);
                }),
                $this->isInstanceOf(CrudRequestContract::class)
            )
            ->willReturn(JsonResponse::make([
                'message' => 'Saved',
                'messageType' => 'success',
            ]));

        $request = TestCrudRequest::make(
            $resource,
            ['resourceItem' => '15'],
            [
                'start' => '2026-02-26 10:00:00',
                'end' => '2026-02-26 11:00:00',
                'allDay' => false,
                'action' => 'drop',
                'timezone' => 'UTC',
            ]
        );

        $response = (new FullCalendarEventsController())->updateDates($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Saved', json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)['message'] ?? null);
    }

    #[Test]
    public function it_returns_422_json_when_dates_update_payload_is_invalid(): void
    {
        $resource = $this->getMockBuilder(FullCalendarResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUriKey', 'modifyErrorResponse'])
            ->getMockForAbstractClass();

        $resource->method('getUriKey')->willReturn('calendar-events');
        $resource->method('modifyErrorResponse')->willReturnCallback(
            static fn ($response) => $response
        );

        $request = TestCrudRequest::make(
            $resource,
            ['resourceItem' => '15'],
            [
                'end' => '2026-02-26 11:00:00',
                'action' => 'drop',
            ]
        );

        $response = (new FullCalendarEventsController())->updateDates($request);
        $json = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('error', $json['messageType'] ?? null);
        self::assertArrayHasKey('errors', $json);
    }

    #[Test]
    public function it_returns_resource_error_response_when_resource_denies_dates_update(): void
    {
        $resource = $this->getMockBuilder(FullCalendarResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUriKey', 'updateCalendarEventDates', 'modifyErrorResponse'])
            ->getMockForAbstractClass();

        $resource->method('getUriKey')->willReturn('calendar-events');
        $resource->method('modifyErrorResponse')->willReturnCallback(
            static fn ($response) => $response
        );
        $resource->method('updateCalendarEventDates')
            ->willThrowException(new HttpException(403, 'Forbidden'));

        $request = TestCrudRequest::make(
            $resource,
            ['resourceItem' => '15'],
            [
                'start' => '2026-02-26 10:00:00',
                'end' => '2026-02-26 11:00:00',
                'allDay' => false,
                'action' => 'resize',
            ]
        );

        $response = (new FullCalendarEventsController())->updateDates($request);
        $json = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Forbidden', $json['message'] ?? null);
        self::assertSame('error', $json['messageType'] ?? null);
    }

    #[Test]
    public function it_returns_resource_error_response_when_calendar_event_is_not_found(): void
    {
        $resource = $this->getMockBuilder(FullCalendarResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUriKey', 'updateCalendarEventDates', 'modifyErrorResponse'])
            ->getMockForAbstractClass();

        $resource->method('getUriKey')->willReturn('calendar-events');
        $resource->method('modifyErrorResponse')->willReturnCallback(
            static fn ($response) => $response
        );
        $resource->method('updateCalendarEventDates')
            ->willThrowException(new HttpException(404, 'Calendar event not found'));

        $request = TestCrudRequest::make(
            $resource,
            ['resourceItem' => '999999'],
            [
                'start' => '2026-02-26 10:00:00',
                'end' => '2026-02-26 11:00:00',
                'allDay' => false,
                'action' => 'drop',
            ]
        );

        $response = (new FullCalendarEventsController())->updateDates($request);
        $json = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Calendar event not found', $json['message'] ?? null);
        self::assertSame('error', $json['messageType'] ?? null);
    }
}

final class TestCrudRequest extends Request implements CrudRequestContract
{
    private array $routeParams = [];
    private ?CrudResourceContract $resource = null;

    public static function make(?CrudResourceContract $resource, array $routeParams, array $payload): self
    {
        $request = self::create('/', 'PATCH', $payload);
        $request->resource = $resource;
        $request->routeParams = $routeParams;

        return $request;
    }

    public function route($param = null, $default = null): mixed
    {
        if ($param === null) {
            return null;
        }

        return $this->routeParams[$param] ?? $default;
    }

    public function getResource(): ?CrudResourceContract
    {
        return $this->resource;
    }

    public function getResourceUri(): ?string
    {
        return $this->route('resourceUri');
    }

    public function hasResource(): bool
    {
        return $this->resource !== null;
    }

    public function findPage(): ?PageContract
    {
        return null;
    }

    public function getPage(): PageContract
    {
        throw new \RuntimeException('Not implemented for test');
    }

    public function getPageUri(): ?string
    {
        return null;
    }

    public function getItemID(): int|string|null
    {
        return $this->route('resourceItem');
    }

    public function getComponentName(): string
    {
        return '';
    }

    public function getFragmentLoad(): ?string
    {
        return null;
    }

    public function isFragmentLoad(?string $name = null): bool
    {
        return false;
    }

    public function isMoonShineRequest(): bool
    {
        return true;
    }

    public function getParentResourceId(): ?string
    {
        return null;
    }

    public function getParentRelationName(): ?string
    {
        return null;
    }

    public function getParentRelationId(): int|string|null
    {
        return null;
    }
}
