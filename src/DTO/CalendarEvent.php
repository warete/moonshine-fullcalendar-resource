<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\DTO;

use Illuminate\Database\Eloquent\Model;

final readonly class CalendarEvent
{
    private function __construct(
        public int|string $id,
        public string $title,
        public string $start,
        public string $end,
        public array $extendedProps = [],
        public array $classNames = [],
        public ?string $backgroundColor = null,
        public ?string $borderColor = null,
        public ?string $textColor = null,
        public ?string $display = null,
        public ?bool $editable = null,
        public ?bool $startEditable = null,
        public ?bool $durationEditable = null,
        public ?string $resourceId = null,
    ) {
    }

    public static function make(
        int|string $id,
        string $title,
        string $start,
        string $end,
    ): self {
        return new self(
            id: $id,
            title: $title,
            start: $start,
            end: $end,
        );
    }

    public static function fromModel(Model $model, array $mapping = []): self
    {
        $defaults = [
            'id' => $model->getKey(),
            'title' => $model->getAttributeValue($model->getKeyName()),
            'start' => now()->toIso8601String(),
            'end' => now()->addHour()->toIso8601String(),
        ];

        foreach ($mapping as $key => $field) {
            if (isset($model->{$field})) {
                $defaults[$key] = is_string($model->{$field})
                    ? $model->{$field}
                    : (string) $model->{$field};
            }
        }

        return new self(
            id: $defaults['id'],
            title: $defaults['title'],
            start: $defaults['start'],
            end: $defaults['end'],
        );
    }

    public function withExtendedProps(array $props): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            start: $this->start,
            end: $this->end,
            extendedProps: [...$this->extendedProps, ...$props],
            classNames: $this->classNames,
            backgroundColor: $this->backgroundColor,
            borderColor: $this->borderColor,
            textColor: $this->textColor,
            display: $this->display,
            editable: $this->editable,
            startEditable: $this->startEditable,
            durationEditable: $this->durationEditable,
            resourceId: $this->resourceId,
        );
    }

    public function withClassNames(array $classNames): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            start: $this->start,
            end: $this->end,
            extendedProps: $this->extendedProps,
            classNames: [...$this->classNames, ...$classNames],
            backgroundColor: $this->backgroundColor,
            borderColor: $this->borderColor,
            textColor: $this->textColor,
            display: $this->display,
            editable: $this->editable,
            startEditable: $this->startEditable,
            durationEditable: $this->durationEditable,
            resourceId: $this->resourceId,
        );
    }

    public function withColors(
        ?string $bg = null,
        ?string $text = null,
        ?string $border = null,
    ): self {
        return new self(
            id: $this->id,
            title: $this->title,
            start: $this->start,
            end: $this->end,
            extendedProps: $this->extendedProps,
            classNames: $this->classNames,
            backgroundColor: $bg ?? $this->backgroundColor,
            borderColor: $border ?? $this->borderColor,
            textColor: $text ?? $this->textColor,
            display: $this->display,
            editable: $this->editable,
            startEditable: $this->startEditable,
            durationEditable: $this->durationEditable,
            resourceId: $this->resourceId,
        );
    }

    public function withDisplay(string $display): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            start: $this->start,
            end: $this->end,
            extendedProps: $this->extendedProps,
            classNames: $this->classNames,
            backgroundColor: $this->backgroundColor,
            borderColor: $this->borderColor,
            textColor: $this->textColor,
            display: $display,
            editable: $this->editable,
            startEditable: $this->startEditable,
            durationEditable: $this->durationEditable,
            resourceId: $this->resourceId,
        );
    }

    public function withEditable(
        ?bool $editable = null,
        ?bool $startEditable = null,
        ?bool $durationEditable = null,
    ): self {
        return new self(
            id: $this->id,
            title: $this->title,
            start: $this->start,
            end: $this->end,
            extendedProps: $this->extendedProps,
            classNames: $this->classNames,
            backgroundColor: $this->backgroundColor,
            borderColor: $this->borderColor,
            textColor: $this->textColor,
            display: $this->display,
            editable: $editable ?? $this->editable,
            startEditable: $startEditable ?? $this->startEditable,
            durationEditable: $durationEditable ?? $this->durationEditable,
            resourceId: $this->resourceId,
        );
    }

    public function withResourceId(?string $resourceId): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            start: $this->start,
            end: $this->end,
            extendedProps: $this->extendedProps,
            classNames: $this->classNames,
            backgroundColor: $this->backgroundColor,
            borderColor: $this->borderColor,
            textColor: $this->textColor,
            display: $this->display,
            editable: $this->editable,
            startEditable: $this->startEditable,
            durationEditable: $this->durationEditable,
            resourceId: $resourceId,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'start' => $this->start,
            'end' => $this->end,
            'extendedProps' => $this->extendedProps === [] ? null : $this->extendedProps,
            'classNames' => $this->classNames === [] ? null : $this->classNames,
            'backgroundColor' => $this->backgroundColor,
            'borderColor' => $this->borderColor,
            'textColor' => $this->textColor,
            'display' => $this->display,
            'editable' => $this->editable,
            'startEditable' => $this->startEditable,
            'durationEditable' => $this->durationEditable,
            'resourceId' => $this->resourceId,
        ], fn ($value): bool => $value !== null);
    }
}
