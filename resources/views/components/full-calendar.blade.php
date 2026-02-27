@props([
    'calendarConfig' => [],
    'calendarCreateTrigger' => null,
    'async' => false,
    'endpoint' => '',
    'resource' => null,
])

<div
    x-data="fullCalendar({
        config: @js($calendarConfig),
        endpoint: '{{ $endpoint }}',
        resourceUri: '{{ $resource?->getUriKey() ?? '' }}',
        async: {{ $async ? 'true' : 'false' }},
        debug: {{ config('fullcalendar.debug', env('FULLCALENDAR_DEBUG', false)) ? 'true' : 'false' }}
    })"
    x-init="initCalendar"
    class="full-calendar-container ms-full-calendar relative"
    x-cloak
>
    <!-- Calendar toolbar (optional - can be handled by FullCalendar) -->
    @if($calendarConfig['headerToolbar'] ?? false)
        <div class="full-calendar-header flex justify-between items-center mb-4">
            <div class="full-calendar-title"></div>
            <div class="full-calendar-toolbar"></div>
        </div>
    @endif

    <!-- FullCalendar container - always visible for proper rendering -->
    <div id="full-calendar-{{ $resource?->getUriKey() ?? 'default' }}" class="full-calendar"></div>

    <!-- Loading state - overlay on top of calendar -->
    <div x-show="loading" x-transition.opacity
         class="full-calendar-loading absolute inset-0 flex items-center justify-center z-10"
         style="display: none;">
        <div class="full-calendar-loading__content flex items-center">
            <div class="full-calendar-loading__spinner animate-spin rounded-full h-8 w-8 border-b-2"></div>
            <span class="full-calendar-loading__text ml-2">{{ __('Loading events...') }}</span>
        </div>
    </div>

    <!-- Error state - overlay on top of calendar -->
    <div x-show="error" x-cloak x-transition.opacity
         class="full-calendar-error absolute inset-0 flex items-center justify-center z-10 hidden">
        <div class="full-calendar-error__card rounded-md p-4 max-w-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="full-calendar-error__icon h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="full-calendar-error__title text-sm font-medium">
                        {{ __('Error loading calendar events') }}
                    </h3>
                    <div class="full-calendar-error__message mt-2 text-sm" x-text="error"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Event actions dropdown (rendered from backend ActionButton HTML) -->
    <div
        x-ref="eventActionsDropdown"
        x-show="dropdownOpen"
        x-cloak
        x-transition.opacity.duration.100ms
        x-on:click.outside="closeEventActionsDropdown('alpine-click-outside')"
        x-on:keydown.escape.window="closeEventActionsDropdown('alpine-escape-window')"
        class="full-calendar-event-actions-dropdown"
        :style="dropdownStyle"
        style="display:none; position:absolute;"
        role="menu"
        tabindex="-1"
        aria-label="{{ __('Event actions') }}"
    >
        <div
            x-ref="eventActionsDropdownContent"
            class="full-calendar-event-actions-dropdown__content"
            x-on:click="handleDropdownActionClick($event)"
            x-html="dropdownActionsHtml"
        ></div>
    </div>

    <!-- Hidden event trigger for MoonShine actions -->
    <div x-data="{ refreshTrigger: $watch('window.moonshineFullCalendarRefresh', value => {
        if (value) {
            refreshCalendar();
            window.moonshineFullCalendarRefresh = false;
        }
    })}"></div>

    @if($calendarCreateTrigger)
        <div class="hidden" aria-hidden="true">
            {!! $calendarCreateTrigger !!}
        </div>
    @endif
</div>
