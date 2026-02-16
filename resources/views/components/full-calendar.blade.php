@props([
    'calendarConfig' => [],
    'async' => false,
    'endpoint' => '',
    'resource' => null,
])

@once
@push('scripts')
    <script src="{{ asset('vendor/moonshine-fullcalendar/js/full-calendar.js') }}"></script>
@endpush
@endonce

<div
    x-data="fullCalendar({
        config: @js($calendarConfig),
        endpoint: '{{ $endpoint }}',
        async: {{ $async ? 'true' : 'false' }},
        debug: {{ config('fullcalendar.debug', env('FULLCALENDAR_DEBUG', false)) ? 'true' : 'false' }}
    })"
    x-init="initCalendar"
    class="full-calendar-container relative"
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
         class="full-calendar-loading absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-gray-900/80 z-10"
         style="display: none;">
        <div class="flex items-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
            <span class="ml-2 text-text-secondary">{{ __('Loading events...') }}</span>
        </div>
    </div>

    <!-- Error state - overlay on top of calendar -->
    <div x-show="error" x-cloak x-transition.opacity
         class="full-calendar-error absolute inset-0 flex items-center justify-center z-10 hidden">
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-4 max-w-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                        {{ __('Error loading calendar events') }}
                    </h3>
                    <div class="mt-2 text-sm text-red-700 dark:text-red-300" x-text="error"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden event trigger for MoonShine actions -->
    <div x-data="{ refreshTrigger: $watch('window.moonshineFullCalendarRefresh', value => {
        if (value) {
            refreshCalendar();
            window.moonshineFullCalendarRefresh = false;
        }
    })}"></div>
</div>

<style>
    .full-calendar {
        min-height: 600px;
    }

    .full-calendar .fc {
        font-family: inherit;
    }

    .fc-event {
        cursor: pointer;
    }

    .fc-event:hover {
        opacity: 0.8;
    }

    /* MoonShine theme integration */
    .fc-theme-standard .fc-scrollgrid,
    .fc-theme-standard td,
    .fc-theme-standard th {
        border-color: var(--color-border, #e5e7eb);
    }

    .fc-theme-standard .fc-col-header-cell-cushion {
        color: var(--color-text-secondary, #6b7280);
        font-weight: 600;
    }

    .fc-theme-standard .fc-daygrid-day-number {
        color: var(--color-text-primary, #111827);
    }

    .fc-theme-standard .fc-button-primary {
        background-color: var(--color-primary, #3b82f6);
        border-color: var(--color-primary, #3b82f6);
    }

    .fc-theme-standard .fc-button-primary:hover {
        background-color: var(--color-primary-hover, #2563eb);
        border-color: var(--color-primary-hover, #2563eb);
    }

    .fc-theme-standard .fc-button-primary:disabled {
        background-color: var(--color-primary-disabled, #93c5fd);
        border-color: var(--color-primary-disabled, #93c5fd);
    }

    /* Dark mode support */
    @media (prefers-color-scheme: dark) {
        .fc-theme-standard .fc-scrollgrid,
        .fc-theme-standard td,
        .fc-theme-standard th {
            border-color: var(--color-border, #374151);
        }

        .fc-theme-standard .fc-col-header-cell-cushion {
            color: var(--color-text-secondary, #9ca3af);
        }

        .fc-theme-standard .fc-daygrid-day-number {
            color: var(--color-text-primary, #f9fafb);
        }

        .fc-theme-standard a:not([href]).fc-nav-button:disabled {
            color: var(--color-text-disabled, #6b7280);
        }
    }
</style>

<script>
    // Alpine.js component will be registered by resources/js/full-calendar.js
    document.addEventListener('alpine:init', () => {
        window.fullCalendarInitialized = true;
    });
</script>
