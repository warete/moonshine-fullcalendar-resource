<?php

declare(strict_types=1);

namespace Warete\MoonShineFullCalendar\Concerns;

use Illuminate\Database\Eloquent\Model;
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
    public function getEventActionsForModel(Model $model): array
    {
        $actions = [];

        // Get standard CRUD buttons from resource
        $resource = $this->setItemID($model->getKey());
        $activeActions = $resource->getActiveActions();

        foreach ($activeActions as $action) {
            if ($resource->can($action->getAbility(), $model)) {
                $button = $resource
                    ->getActionButton($action)
                    ->setAsync()
                    ->setItem($model)
                    ->setData($this->getCaster()->cast(['id' => $model->getKey()]));

                $actions[] = $button;
            }
        }

        // Add custom actions
        foreach ($this->customEventActions as $action) {
            if ($action instanceof ActionButtonContract) {
                // Set data for the action if needed
                $action->setData(['id' => $model->getKey()]);
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * Render event actions as HTML buttons
     */
    public function renderEventActions(Model $model): array
    {
        $rendered = [];

        foreach ($this->getEventActionsForModel($model) as $action) {
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
