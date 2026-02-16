/**
 * FullCalendar Integration for MoonShine
 * Registers Alpine.js component and initializes FullCalendar
 */

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';

/**
 * FullCalendar Alpine Component
 */
export function registerFullCalendar() {
    if (typeof Alpine === 'undefined') {
        console.error('[FullCalendar] Alpine.js is not loaded');
        return;
    }

    Alpine.data('fullCalendar', (props = {}) => ({
        calendar: null,
        loading: false,
        error: null,
        events: [],

        // Props
        config: props.config || {},
        endpoint: props.endpoint || '',
        async: props.async !== undefined ? props.async : false,
        debug: props.debug || false,

        /**
         * Initialize FullCalendar
         */
        initCalendar() {
            this.log('debug', 'Initializing FullCalendar', {
                endpoint: this.endpoint,
                async: this.async,
                config: this.config
            });

            const calendarEl = this.$el.querySelector('.full-calendar');

            if (!calendarEl) {
                this.log('error', 'Calendar element not found');
                this.error = 'Calendar container not found';
                return;
            }

            // Build FullCalendar config
            const calendarConfig = {
                ...this.config,
                plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
                initialView: this.config.initialView || 'dayGridMonth',
                headerToolbar: this.config.headerToolbar || {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                editable: this.config.editable !== undefined ? this.config.editable : false,
                selectable: this.config.selectable !== undefined ? this.config.selectable : true,
                locale: this.config.locale || 'en',
                timeZone: this.config.timeZone || 'local',

                // Event handlers
                events: this.fetchEvents.bind(this),
                eventClick: this.handleEventClick.bind(this),
                dateClick: this.handleDateClick.bind(this),
                select: this.handleDateSelect.bind(this),
                eventDrop: this.handleEventDrop.bind(this),
                eventResize: this.handleEventResize.bind(this),

                // Lifecycle hooks
                loading: (isLoading) => {
                    this.loading = isLoading;
                    this.log('debug', 'Calendar loading state', { isLoading });
                },

                // View change handler
                datesSet: (info) => {
                    this.log('debug', 'View changed', {
                        view: info.view.type,
                        start: info.start.toISOString(),
                        end: info.end.toISOString()
                    });
                }
            };

            // Initialize calendar
            try {
                this.calendar = new Calendar(calendarEl, calendarConfig);
                this.calendar.render();

                this.log('info', 'FullCalendar initialized successfully', {
                    view: this.calendar.view.type
                });
            } catch (error) {
                this.log('error', 'Failed to initialize FullCalendar', {
                    error: error.message,
                    stack: error.stack
                });
                this.error = error.message;
            }
        },

        /**
         * Fetch events from API using standard fetch with CSRF token
         * MoonShine.request is not suitable because it doesn't return data directly
         */
        fetchEvents: async function(info, successCallback, failureCallback) {
            this.loading = true;
            this.error = null;

            const start = info.start ? info.start.toISOString() : null;
            const end = info.end ? info.end.toISOString() : null;

            this.log('debug', 'Fetching events', {
                endpoint: this.endpoint,
                start: start,
                end: end
            });

            try {
                // Build URL with query parameters
                const url = new URL(this.endpoint, window.location.origin);
                if (start) url.searchParams.set('start', start);
                if (end) url.searchParams.set('end', end);

                this.log('debug', 'Request URL', {
                    url: url.toString()
                });

                // Prepare headers with CSRF token for MoonShine
                const headers = {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                };

                // Add CSRF token if available (MoonShine uses meta tag)
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }

                // Add X-Requested-With for AJAX detection
                headers['X-Requested-With'] = 'XMLHttpRequest';

                const response = await fetch(url.toString(), {
                    method: 'GET',
                    headers: headers
                });

                this.log('debug', 'Response received', {
                    status: response.status,
                    ok: response.ok
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                const events = Array.isArray(data) ? data : [];

                this.log('debug', 'Events fetched successfully', {
                    count: events.length,
                    sample: events.slice(0, 2)
                });

                this.events = events;
                successCallback(events);

                this.log('info', 'Events loaded successfully', {
                    count: events.length
                });
            } catch (error) {
                this.log('error', 'Failed to fetch events', {
                    error: error.message,
                    endpoint: this.endpoint,
                    stack: error.stack
                });

                this.error = error.message;
                if (failureCallback) {
                    failureCallback(error);
                }
            } finally {
                this.loading = false;
            }
        },

        /**
         * Handle event click
         */
        handleEventClick(info) {
            this.log('debug', 'Event clicked', {
                eventId: info.event.id,
                title: info.event.title
            });

            // Trigger edit modal if configured
            if (info.event.url) {
                window.location.href = info.event.url;
            } else if (window.moonshineFullCalendarEventClick) {
                window.moonshineFullCalendarEventClick(info.event);
            }
        },

        /**
         * Handle date click
         */
        handleDateClick(info) {
            this.log('debug', 'Date clicked', {
                date: info.date.toISOString()
            });

            if (window.moonshineFullCalendarDateClick) {
                window.moonshineFullCalendarDateClick(info);
            }
        },

        /**
         * Handle date select
         */
        handleDateSelect(info) {
            this.log('debug', 'Date range selected', {
                start: info.start.toISOString(),
                end: info.end.toISOString()
            });

            // Clear selection
            this.calendar.unselect();

            if (window.moonshineFullCalendarDateSelect) {
                window.moonshineFullCalendarDateSelect(info);
            }
        },

        /**
         * Handle event drop (drag and drop)
         */
        handleEventDrop(info) {
            this.log('debug', 'Event dropped', {
                eventId: info.event.id,
                newStart: info.event.start.toISOString(),
                newEnd: info.event.end ? info.event.end.toISOString() : null
            });

            if (window.moonshineFullCalendarEventDrop) {
                window.moonshineFullCalendarEventDrop(info);
            }
        },

        /**
         * Handle event resize
         */
        handleEventResize(info) {
            this.log('debug', 'Event resized', {
                eventId: info.event.id,
                newStart: info.event.start.toISOString(),
                newEnd: info.event.end ? info.event.end.toISOString() : null
            });

            if (window.moonshineFullCalendarEventResize) {
                window.moonshineFullCalendarEventResize(info);
            }
        },

        /**
         * Refresh calendar
         */
        refreshCalendar() {
            this.log('debug', 'Refreshing calendar');

            if (this.calendar) {
                this.calendar.refetchEvents();
            }
        },

        /**
         * Get calendar instance
         */
        getCalendar() {
            return this.calendar;
        },

        /**
         * Log messages to console if debug mode is enabled
         */
        log(level, message, data = {}) {
            if (!this.debug && level === 'debug') {
                return;
            }

            const prefix = `[FullCalendar]`;

            switch (level) {
                case 'debug':
                    console.debug(prefix, message, data);
                    break;
                case 'info':
                    console.info(prefix, message, data);
                    break;
                case 'warn':
                    console.warn(prefix, message, data);
                    break;
                case 'error':
                    console.error(prefix, message, data);
                    break;
            }
        }
    }));
}

/**
 * Initialize on Alpine ready
 */
document.addEventListener('alpine:init', () => {
    console.log('[FullCalendar] Alpine ready, registering component');
    registerFullCalendar();
});

/**
 * Also initialize if Alpine is already loaded
 */
if (window.Alpine && window.Alpine.version) {
    console.log('[FullCalendar] Alpine already loaded, registering component immediately');
    registerFullCalendar();
}

/**
 * Log that the script has loaded
 */
console.log('[FullCalendar] Script loaded, waiting for alpine:init');

/**
 * Initialize on Alpine ready
 */
document.addEventListener('alpine:init', () => {
    console.log('[FullCalendar] Alpine:init event fired, registering component');
    registerFullCalendar();
});

/**
 * Also initialize if Alpine is already loaded
 */
if (window.Alpine && window.Alpine.version) {
    console.log('[FullCalendar] Alpine already loaded, registering component immediately');
    registerFullCalendar();
}

/**
 * Log that the script has loaded
 */
console.log('[FullCalendar] Script loaded, waiting for alpine:init');

/**
 * Export for external use
 */
window.fullCalendarRefresh = function() {
    window.dispatchEvent(new Event('moonshineFullCalendarRefresh'));
};
