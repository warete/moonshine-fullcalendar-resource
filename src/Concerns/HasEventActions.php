<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Concerns;

use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ActionButtonContract;

trait HasEventActions
{
    /**
     * @var array<ActionButtonContract>
     */
    protected array $customEventActions = [];

    /**
     * Add a custom event action
     */
    public function addCustomEventAction(ActionButtonContract $action): self
    {
        $this->customEventActions[] = $action;

        return $this;
    }

    /**
     * Get all event actions for a model
     */
    public function getEventActionsForItem(DataWrapperContract $item): array
    {
        $actions = [];

        // Get standard CRUD buttons from resource
        $resource = $this->setItemID($item->getKey());
        $activeActions = $resource->getIndexPage()->getButtons();

        /** @var ActionButtonContract $action */
        foreach ($activeActions as $action) {
            $button = $action
                ->async()
                ->setData($item);

            $actions[] = $button;
        }

        // Add custom actions
        foreach ($this->customEventActions as $action) {
            if ($action instanceof ActionButtonContract) {
                // Set data for the action if needed
                $action->setData($item);
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * Render event actions as HTML buttons
     */
    public function renderEventActions(DataWrapperContract $item): array
    {
        $rendered = [];

        foreach ($this->getEventActionsForItem($item) as $action) {
            $rendered[] = (string) $action->render();
        }

        return $rendered;
    }

    /**
     * Get event actions (legacy method for compatibility)
     */
    public function getEventActions(): array
    {
        return $this->customEventActions;
    }
}
