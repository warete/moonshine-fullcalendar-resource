/**
 * FullCalendar Integration for MoonShine
 * Registers Alpine.js component and initializes FullCalendar
 */

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import ruLocale from '@fullcalendar/core/locales/ru';

/**
 * Locale registry for FullCalendar
 * Maps locale codes to imported locale objects
 */
const FULLCALENDAR_LOCALES = {
    en: null, // English is built-in, no import needed
    ru: ruLocale,
    // Extensible: add more locales here as needed
    // es: esLocale,
    // de: deLocale,
    // fr: frLocale,
};

/**
 * Get locale object by code
 * @param {string} localeCode - Locale code (e.g., 'en', 'ru')
 * @returns {object|null} Locale object or null for built-in English
 */
function getFullCalendarLocale(localeCode = 'en') {
    const normalizedCode = localeCode.toLowerCase().split('-')[0]; // Handle 'ru-RU' -> 'ru'
    console.log('[FullCalendar] getFullCalendarLocale', {
        requested: localeCode,
        normalized: normalizedCode,
        available: Object.keys(FULLCALENDAR_LOCALES)
    });

    // Check if locale exists in registry
    if (FULLCALENDAR_LOCALES.hasOwnProperty(normalizedCode)) {
        console.log('[FullCalendar] Locale found in registry', { locale: normalizedCode });
        return FULLCALENDAR_LOCALES[normalizedCode];
    }

    // Default to English if locale not found
    console.warn('[FullCalendar] Locale not found, defaulting to English', {
        requested: localeCode,
        normalized: normalizedCode
    });
    return FULLCALENDAR_LOCALES.en;
}

/**
 * Get all available locale codes
 * @returns {string[]} Array of available locale codes
 */
function getAvailableLocaleCodes() {
    return Object.keys(FULLCALENDAR_LOCALES);
}

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
        dayViewRenderRecoveryAttempted: false,
        refreshListenerHandler: null,
        refreshListenerAbortController: null,
        refreshListenerObserver: null,

        // Props
        config: props.config || {},
        endpoint: props.endpoint || '',
        resourceUri: props.resourceUri || null,
        async: props.async !== undefined ? props.async : false,
        debug: props.debug || false,
        currentLocale: props.config?.locale || 'en',

        /**
         * Initialize FullCalendar
         */
        initCalendar() {
            this.log('debug', 'Initializing FullCalendar', {
                endpoint: this.endpoint,
                async: this.async,
                config: this.config,
                locale: this.currentLocale
            });

            const calendarEl = this.$el.querySelector('.full-calendar');

            if (!calendarEl) {
                this.log('error', 'Calendar element not found');
                this.error = 'Calendar container not found';
                return;
            }

            // Get locale object from registry
            const localeObj = getFullCalendarLocale(this.currentLocale);

            this.log('debug', 'Locale configuration', {
                localeCode: this.currentLocale,
                localeObject: localeObj,
                availableLocales: getAvailableLocaleCodes()
            });

            // [FIX] Log timezone configuration for debugging
            const timeZone = this.config.timeZone || this.config.timezone || 'local';
            this.log('debug', '[FIX] Timezone configuration', {
                configTimeZone: this.config.timeZone,
                configTimezone: this.config.timezone,
                finalTimeZone: timeZone,
                'local': Intl.DateTimeFormat().resolvedOptions().timeZone
            });

            // Normalize incoming config from PHP (legacy `timezone` -> FullCalendar `timeZone`)
            const normalizedConfig = { ...this.config };
            if (Object.prototype.hasOwnProperty.call(normalizedConfig, 'timezone')) {
                this.log('info', '[FIX] Removing unsupported FullCalendar option `timezone`', {
                    timezone: normalizedConfig.timezone
                });
                delete normalizedConfig.timezone;
            }

            // Build FullCalendar config
            const calendarConfig = {
                ...normalizedConfig,
                plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
                initialView: normalizedConfig.initialView || 'dayGridMonth',
                headerToolbar: normalizedConfig.headerToolbar || {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                editable: normalizedConfig.editable !== undefined ? normalizedConfig.editable : false,
                selectable: normalizedConfig.selectable !== undefined ? normalizedConfig.selectable : true,
                locale: localeObj, // Use locale object from registry
                // [FIX] Support both timeZone and timezone (PHP convention) - use 'local' if not set
                // If timezone is set, use it as IANA timezone (e.g., 'Europe/Moscow', 'UTC')
                // FullCalendar will parse event times and display in this timezone
                timeZone: normalizedConfig.timeZone || this.config.timezone || 'local',

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
                    view: this.calendar.view.type,
                    locale: this.currentLocale
                });

                // Setup event listener for calendar refresh after CRUD operations
                this.setupRefreshListener();
                this.applyInitialLayoutFix('init');
            } catch (error) {
                this.log('error', 'Failed to initialize FullCalendar', {
                    error: error.message,
                    stack: error.stack
                });
                this.error = error.message;
            }
        },

        teardownRefreshListener(reason = 'unknown') {
            if (this.refreshListenerAbortController) {
                this.log('info', '[FIX][refresh] Tearing down refresh listeners via AbortController', { reason });
                this.refreshListenerAbortController.abort();
                this.refreshListenerAbortController = null;
            } else if (this.refreshListenerHandler) {
                this.log('info', '[FIX][refresh] Tearing down refresh listeners (fallback removeEventListener)', { reason });
                window.removeEventListener('fullcalendar:refresh', this.refreshListenerHandler);
                document.removeEventListener('fullcalendar:refresh', this.refreshListenerHandler);
            }

            this.refreshListenerHandler = null;

            if (this.refreshListenerObserver) {
                this.refreshListenerObserver.disconnect();
                this.refreshListenerObserver = null;
            }
        },

        setupRefreshListenerAutoCleanup() {
            if (this.refreshListenerObserver || typeof MutationObserver === 'undefined') {
                return;
            }

            this.refreshListenerObserver = new MutationObserver(() => {
                if (!this.$el || this.$el.isConnected) {
                    return;
                }

                this.teardownRefreshListener('element-disconnected');
            });

            this.refreshListenerObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        /**
         * FullCalendar timeGridDay can render before layout is fully settled (x-cloak/async containers).
         * Force a delayed size recalculation to avoid "events loaded but not visible" on first render.
         */
        applyInitialLayoutFix(reason = 'unknown') {
            if (!this.calendar) {
                return;
            }

            const applyFix = (phase) => {
                if (!this.calendar) {
                    return;
                }

                const viewType = this.calendar.view?.type;
                if (viewType !== 'timeGridDay') {
                    this.log('debug', '[FIX][layout] Skip layout fix for non-timeGridDay view', {
                        reason,
                        phase,
                        viewType
                    });
                    return;
                }

                this.log('info', '[FIX][layout] Applying timeGridDay layout refresh', {
                    reason,
                    phase,
                    viewType,
                    eventCount: this.calendar.getEvents().length
                });

                try {
                    this.calendar.updateSize();
                    this.calendar.render();
                } catch (error) {
                    this.log('error', '[FIX][layout] Failed to apply layout refresh', {
                        reason,
                        phase,
                        error: error.message,
                        stack: error.stack
                    });
                }

                this.ensureTimeGridDayEventsRendered(`[layout:${reason}:${phase}]`);
            };

            requestAnimationFrame(() => {
                applyFix('raf-1');
                requestAnimationFrame(() => applyFix('raf-2'));
            });

            setTimeout(() => applyFix('timeout-50ms'), 50);
            setTimeout(() => applyFix('timeout-250ms'), 250);
        },

        /**
         * Recovery for a flaky initial timeGridDay render:
         * events are parsed but no timeGrid DOM nodes appear until manual view switch.
         */
        ensureTimeGridDayEventsRendered(reason = 'unknown') {
            if (!this.calendar) {
                return;
            }

            const viewType = this.calendar.view?.type;
            if (viewType !== 'timeGridDay') {
                return;
            }

            const parsedEvents = this.calendar.getEvents();
            const timeGridEvents = this.$el?.querySelectorAll?.('.fc-timegrid-event')?.length ?? 0;
            const allDayEvents = this.$el?.querySelectorAll?.('.fc-daygrid-event, .fc-timegrid-allday .fc-event')?.length ?? 0;

            this.log('info', '[FIX][day-view] Render probe', {
                reason,
                parsedCount: parsedEvents.length,
                timeGridDomCount: timeGridEvents,
                allDayDomCount: allDayEvents,
                recoveryAttempted: this.dayViewRenderRecoveryAttempted
            });

            if (parsedEvents.length === 0 || timeGridEvents > 0 || this.dayViewRenderRecoveryAttempted) {
                return;
            }

            this.dayViewRenderRecoveryAttempted = true;

            const currentDate = this.calendar.getDate();
            const parsedSample = parsedEvents.slice(0, 3).map((event) => ({
                id: event.id,
                title: event.title,
                start: event.start ? event.start.toISOString() : null,
                end: event.end ? event.end.toISOString() : null,
                allDay: event.allDay
            }));

            this.log('warn', '[FIX][day-view] Parsed events exist but no timeGrid DOM events rendered; forcing view reapply', {
                reason,
                currentDate: currentDate?.toISOString?.() || null,
                parsedSample
            });

            try {
                this.calendar.changeView('timeGridDay', currentDate);

                requestAnimationFrame(() => {
                    this.calendar?.updateSize?.();
                    this.log('info', '[FIX][day-view] View reapply complete', {
                        reason,
                        timeGridDomCountAfter: this.$el?.querySelectorAll?.('.fc-timegrid-event')?.length ?? 0
                    });
                });
            } catch (error) {
                this.log('error', '[FIX][day-view] View reapply failed', {
                    reason,
                    error: error.message,
                    stack: error.stack
                });
            }
        },

        /**
         * Setup event listener for calendar refresh events from MoonShine
         * Listens for 'fullcalendar:refresh' custom event dispatched by modifySaveResponse
         */
        setupRefreshListener() {
            const self = this;

            // Prevent duplicate listeners on repeated init/re-render of the same component instance
            this.teardownRefreshListener('re-register');

            // Extract resource URI from endpoint URL for filtering
            const resourceUri = this.resourceUri || this.getResourceUriFromEndpoint();

            this.log('info', '[refresh] Setting up calendar refresh listener', {
                resourceUri: resourceUri,
                endpoint: this.endpoint,
                resourceUriSource: this.resourceUri ? 'props.resourceUri' : 'endpoint'
            });

            const handleRefreshEvent = (event) => {
                self.log('info', '[refresh] Refresh event received', {
                    eventType: event.type,
                    detail: event.detail
                });

                // If no resource is provided, treat as broadcast refresh for all calendars
                if (!event.detail || !event.detail.resource) {
                    self.log('info', '[FIX][refresh] Broadcast refresh event received', {
                        detail: event.detail || null
                    });

                    if (self.calendar) {
                        self.calendar.refetchEvents();
                    } else {
                        self.log('warn', '[FIX][refresh] Cannot refresh on broadcast: calendar not initialized');
                    }

                    return;
                }

                // Check if this event is for this calendar instance
                if (event.detail && event.detail.resource) {
                    const eventResource = String(event.detail.resource).trim();
                    const normalizedCalendarResource = resourceUri ? String(resourceUri).trim() : null;

                    self.log('info', '[refresh] Checking resource match', {
                        eventResource: eventResource,
                        calendarResource: normalizedCalendarResource,
                        matches: eventResource === normalizedCalendarResource
                    });

                    if (!normalizedCalendarResource) {
                        self.log('warn', '[FIX][refresh] Calendar resource URI is unknown, applying fallback refresh', {
                            eventResource: eventResource,
                            endpoint: self.endpoint
                        });

                        if (self.calendar) {
                            self.calendar.refetchEvents();
                        }

                        return;
                    }

                    // Only refresh if this event is for this calendar
                    if (eventResource === normalizedCalendarResource) {
                        self.log('info', '[refresh] Resource match detected, refreshing calendar');

                        // Call refetchEvents
                        if (self.calendar) {
                            const beforeCount = self.calendar.getEvents().length;
                            self.log('info', '[refresh] Before refetch', { eventCount: beforeCount });

                            self.calendar.refetchEvents();

                            // Log after refetch (async, so use timeout)
                            setTimeout(() => {
                                const afterCount = self.calendar.getEvents().length;
                                self.log('info', '[refresh] After refetch', { eventCount: afterCount });
                            }, 500);
                        } else {
                            self.log('warn', '[refresh] Cannot refresh: calendar not initialized');
                        }
                    } else {
                        self.log('debug', '[FIX][refresh] Skipping refresh - resource mismatch', {
                            event: eventResource,
                            current: normalizedCalendarResource
                        });
                    }
                }
            };

            this.refreshListenerHandler = handleRefreshEvent;

            // MoonShine dispatches browser events via global dispatchEvent (window target).
            // Also register on document for compatibility with manual/custom dispatches.
            if (typeof AbortController !== 'undefined') {
                this.refreshListenerAbortController = new AbortController();
                const signal = this.refreshListenerAbortController.signal;

                window.addEventListener('fullcalendar:refresh', handleRefreshEvent, { signal });
                document.addEventListener('fullcalendar:refresh', handleRefreshEvent, { signal });
            } else {
                window.addEventListener('fullcalendar:refresh', handleRefreshEvent);
                document.addEventListener('fullcalendar:refresh', handleRefreshEvent);
            }

            this.setupRefreshListenerAutoCleanup();

            this.log('info', '[FIX][refresh] Calendar refresh listener registered', {
                targets: ['window', 'document']
            });
        },

        /**
         * Extract resource URI from endpoint URL
         * Endpoint format: /admin/resource/{resourceUri}/full-calendar/events
         */
        getResourceUriFromEndpoint() {
            try {
                const url = new URL(this.endpoint, window.location.origin);
                const pathParts = url.pathname.split('/');
                // Find 'resource' in path and get the next segment
                const resourceIndex = pathParts.indexOf('resource');
                if (resourceIndex !== -1 && resourceIndex + 1 < pathParts.length) {
                    return pathParts[resourceIndex + 1];
                }
                return null;
            } catch (error) {
                this.log('warn', '[refresh] Failed to extract resource URI from endpoint', {
                    endpoint: this.endpoint,
                    error: error.message
                });
                return null;
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

                if (this.calendar?.view?.type === 'timeGridDay') {
                    const activeStart = this.calendar.view.activeStart?.toISOString?.() || null;
                    const activeEnd = this.calendar.view.activeEnd?.toISOString?.() || null;
                    const parsedSample = this.calendar.getEvents().slice(0, 3).map((event) => ({
                        id: event.id,
                        title: event.title,
                        start: event.start ? event.start.toISOString() : null,
                        end: event.end ? event.end.toISOString() : null,
                        allDay: event.allDay,
                        display: event.display,
                        overlapCurrentDay: (() => {
                            try {
                                const eventStart = event.start ? event.start.getTime() : null;
                                const eventEnd = event.end ? event.end.getTime() : eventStart;
                                const rangeStart = activeStart ? new Date(activeStart).getTime() : null;
                                const rangeEnd = activeEnd ? new Date(activeEnd).getTime() : null;

                                if (eventStart === null || rangeStart === null || rangeEnd === null) {
                                    return null;
                                }

                                return eventStart < rangeEnd && (eventEnd ?? eventStart) > rangeStart;
                            } catch (e) {
                                return null;
                            }
                        })()
                    }));
                    const rawSample = events.slice(0, 3).map((event) => ({
                        id: event.id ?? null,
                        title: event.title ?? null,
                        start: event.start ?? null,
                        end: event.end ?? null,
                        allDay: event.allDay ?? null,
                        display: event.display ?? null,
                    }));

                    this.log('info', '[FIX][day-view] Events loaded in timeGridDay', {
                        activeStart,
                        activeEnd,
                        fetchedCount: events.length,
                        parsedCount: this.calendar.getEvents().length,
                        parsedSample,
                        rawSample,
                        parsedSampleJson: JSON.stringify(parsedSample),
                        rawSampleJson: JSON.stringify(rawSample)
                    });
                }

                this.applyInitialLayoutFix('events-loaded');
                setTimeout(() => this.ensureTimeGridDayEventsRendered('events-loaded-post-check'), 10);
                setTimeout(() => this.ensureTimeGridDayEventsRendered('events-loaded-post-check-100ms'), 100);

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
         * Set calendar locale dynamically
         * @param {string} localeCode - New locale code (e.g., 'en', 'ru')
         */
        setLocale(localeCode) {
            this.log('debug', 'Setting locale', {
                currentLocale: this.currentLocale,
                newLocale: localeCode
            });

            if (!this.calendar) {
                this.log('warn', 'Cannot set locale: calendar not initialized');
                return;
            }

            // Get locale object from registry
            const localeObj = getFullCalendarLocale(localeCode);

            // Update locale in FullCalendar
            this.calendar.setOption('locale', localeObj);

            // Update current locale tracking
            this.currentLocale = localeCode;

            this.log('info', 'Locale updated successfully', {
                locale: localeCode,
                localeObject: localeObj
            });
        },

        /**
         * Get current locale code
         * @returns {string} Current locale code
         */
        getLocale() {
            return this.currentLocale;
        },

        /**
         * Get available locale codes
         * @returns {string[]} Array of available locale codes
         */
        getAvailableLocales() {
            return getAvailableLocaleCodes();
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
 * Export for external use
 */
window.fullCalendarRefresh = function(resource = null) {
    console.info('[FIX] fullCalendarRefresh dispatch', {
        event: 'fullcalendar:refresh',
        resource: resource
    });

    window.dispatchEvent(new CustomEvent('fullcalendar:refresh', {
        detail: resource ? { resource } : {}
    }));
};

/**
 * Export locale utilities for external use
 */
window.fullCalendarGetLocale = function(localeCode) {
    return getFullCalendarLocale(localeCode);
};

window.fullCalendarGetAvailableLocales = function() {
    return getAvailableLocaleCodes();
};
