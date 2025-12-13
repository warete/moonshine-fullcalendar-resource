@props([
    'title' => '',
    'eventsRoute' => '',
    'eventDetailRoute' => '',
    'calendarConfig' => [],
    'createButton' => false,
    'createUrl' => null,
])

<div {{ $attributes->merge(['class' => 'full-calendar-container']) }} x-data="fullCalendar">
    <x-moonshine::layout.box :title="$title">
        <!-- Header with create button -->
        @if($createButton && $createUrl)
            <div class="flex justify-end mb-4">
                <a href="{{ $createUrl }}" class="btn btn-primary">
                    <x-moonshine::icon icon="plus" />
                    {{ __('moonshine::ui.create') }}
                </a>
            </div>
        @endif

        <!-- Calendar container -->
        <div class="card flex not-prose p-4 w-full">
            <div id="calendar" x-ref="calendar" class="w-full"></div>
        </div>
    </x-moonshine::layout.box>

    <!-- Event details modal -->
    <x-moonshine::modal wide name="event-details-modal" title="{{ __('moonshine::ui.show') }}">
        <div id="moonshine-event-details" class="w-full"></div>
    </x-moonshine::modal>

    <!-- Event actions dropdown -->
    <div
        id="event-actions-dropdown"
        x-data="{
            show: false,
            event: null,
            x: 0,
            y: 0,
            buttons: [],
            init() {
                // Hide when clicking on any button inside
                this.$el.addEventListener('click', (e) => {
                    if (e.target.closest('button, a')) {
                        this.show = false;
                    }
                });
            }
        }"
        x-show="show"
        x-transition
        :style="`position: fixed; left: ${x}px; top: ${y}px; z-index: 9999;`"
        @click.away="show = false"
        class="bg-panel dark:bg-panel rounded-lg shadow-xl border border-border dark:border-border py-1 min-w-[150px] hidden"
    >
        <template x-for="button in buttons" :key="button">
            <div @click="show = false" x-html="button"></div>
        </template>
    </div>
</div>

<script>
    document.addEventListener("alpine:init", () => {
        Alpine.data("fullCalendar", () => ({
            calendar: null,
            calendarConfig: @js($calendarConfig),
            eventsRoute: "{{ $eventsRoute }}",
            eventDetailRoute: "{{ $eventDetailRoute }}",
            createUrl: "{{ $createUrl }}",

            init() {

                // Initialize FullCalendar on the calendar element
                this.calendar = new FullCalendar.Calendar(
                    this.$refs.calendar,
                    this.buildConfig()
                );

                this.calendar.render();

                // Resize after render for correct display
                requestAnimationFrame(() => {
                    this.calendar.updateSize();
                });

                // Listen for calendar-specific refresh events
                window.addEventListener('calendar-updated', () => {
                    this.refresh();
                });
            },

            buildConfig() {
                return {
                    // Merge with config from PHP
                    ...this.calendarConfig,

                    // Critical: async event loading
                    events: {
                        url: this.eventsRoute,
                        method: 'GET',
                        failure: (error) => {
                            console.error('Failed to load events:', error);
                        },
                        loading: (isLoading) => {
                            console.log('FullCalendar loading events:', isLoading);
                        }
                    },

                    // Event handlers
                    eventClick: this.onEventClick.bind(this),
                    select: this.onSelect.bind(this),

                    // Show action buttons on hover
                    eventMouseEnter: this.onEventMouseEnter.bind(this),

                    // Responsive
                    handleWindowResize: true,
                    windowResizeDelay: 150,
                };
            },

            onEventClick(info) {
                // Check if the click was on a button
                if (info.jsEvent.target.closest('button, a')) {
                    return;
                }

                const evt = info.event;

                // Dispatch async method to get event details
                MoonShine.request(this, this.eventDetailRoute, 'post', {id: evt.id});
            },

            onSelect(selectInfo) {
                // If create is enabled, open create form with pre-filled dates
                if (this.createUrl) {
                    const params = new URLSearchParams({
                        '_data': {
                            start: selectInfo.startStr,
                            end: selectInfo.endStr
                        }
                    });
                    window.location.href = `${this.createUrl}?${params.toString()}`;
                }

                // Unselect the date range
                this.calendar.unselect();
            },

            onEventMouseEnter(info) {
                const evt = info.event;
                const eventButtons = evt.extendedProps.buttons;

                if (eventButtons && eventButtons.length > 0) {
                    const rect = info.el.getBoundingClientRect();

                    // Update dropdown
                    const dropdown = document.getElementById('event-actions-dropdown');
                    dropdown._x_dataStack[0].buttons = eventButtons;
                    dropdown._x_dataStack[0].x = rect.left;
                    dropdown._x_dataStack[0].y = rect.bottom;
                    dropdown._x_dataStack[0].show = true;
                    dropdown.classList.remove('hidden');
                }
            },

            refresh() {
                // Refresh events after create/edit/delete
                this.calendar.refetchEvents();
            },
        }));

        // Listen for table updates to also refresh calendar
        // This helps when actions are triggered from other components
        document.addEventListener('table-updated', (e) => {
            // Check if this is for our resource
            if (e.detail && e.detail.resource === window.location.pathname) {
                // Dispatch calendar refresh event
                window.dispatchEvent(new CustomEvent('calendar-updated'));
            }
        });
    });
</script>