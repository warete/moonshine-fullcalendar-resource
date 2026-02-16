<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use MoonShine\Laravel\Pages\Crud\FormPage;

/**
 * Example Form Page for Event Resource
 *
 * This page handles creating and editing events
 */
class EventFormPage extends FormPage
{
    /**
     * Get the page title
     */
    public function getTitle(): string
    {
        return $this->getResource()->getItem()
            ? 'Edit Event'
            : 'Create Event';
    }

    /**
     * Configure the form page
     */
    protected function onBoot(): void
    {
        parent::onBoot();

        // Add any custom page configuration here
    }
}
