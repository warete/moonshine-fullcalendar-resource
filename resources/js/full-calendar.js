/**
 * FullCalendar Integration for MoonShine
 * Registers Alpine.js component and initializes FullCalendar
 */

import { Calendar } from "@fullcalendar/core";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";
import interactionPlugin from "@fullcalendar/interaction";
import ruLocale from "@fullcalendar/core/locales/ru";

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
function getFullCalendarLocale(localeCode = "en") {
    const normalizedCode = localeCode.toLowerCase().split("-")[0]; // Handle 'ru-RU' -> 'ru'

    // Check if locale exists in registry
    if (FULLCALENDAR_LOCALES.hasOwnProperty(normalizedCode)) {
        return FULLCALENDAR_LOCALES[normalizedCode];
    }

    // Default to English if locale not found
    return FULLCALENDAR_LOCALES.en;
}

/**
 * Get all available locale codes
 * @returns {string[]} Array of available locale codes
 */
function getAvailableLocaleCodes() {
    return Object.keys(FULLCALENDAR_LOCALES);
}

function parseMoonShineEventString(eventsValue) {
    if (!eventsValue || typeof eventsValue !== "string") {
        return [];
    }

    return eventsValue
        .split(",")
        .map((entry) => entry.trim())
        .filter(Boolean)
        .map((entry) => {
            const [eventNameRaw, attrsRaw] = entry.split("|");
            const eventName = (eventNameRaw || "").trim().toLowerCase();
            const detail = {};

            if (attrsRaw) {
                attrsRaw.split(";").forEach((pair) => {
                    const [key, value] = pair.split("~");
                    if (!key) {
                        return;
                    }

                    detail[key.trim()] =
                        value !== undefined ? value.trim() : "";
                });
            }

            return { eventName, detail };
        })
        .filter((entry) => entry.eventName !== "");
}

async function loadAsyncModalContent(url, id) {
    const containerElement = document.getElementById(id);

    if (!containerElement || !url) {
        return;
    }

    const response = await fetch(url, {
        method: "GET",
        credentials: "same-origin",
        headers: {
            "X-Requested-With": "XMLHttpRequest",
        },
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const payload = await response.text();
    let html = payload;

    try {
        const parsedPayload = JSON.parse(payload);
        html = parsedPayload?.html ?? payload;
    } catch (error) {}

    containerElement.innerHTML = html;

    const scriptElements = containerElement.querySelectorAll("script");

    Array.from(scriptElements).forEach((scriptElement) => {
        const clonedElement = document.createElement("script");

        Array.from(scriptElement.attributes).forEach((attribute) => {
            clonedElement.setAttribute(attribute.name, attribute.value);
        });

        clonedElement.text = scriptElement.text;

        scriptElement.parentNode?.replaceChild(clonedElement, scriptElement);
    });
}

/**
 * FullCalendar Alpine Component
 */
export function registerFullCalendar() {
    if (typeof Alpine === "undefined") {
        return;
    }

    const fullCalendarFactory = (props = {}) => ({
        calendar: null,
        loading: false,
        error: null,
        events: [],
        dayViewRenderRecoveryAttempted: false,
        refreshListenerHandler: null,
        refreshListenerAbortController: null,
        refreshListenerObserver: null,
        dropdownDismissAbortController: null,
        dropdownOpen: false,
        dropdownEventId: null,
        dropdownActionsHtml: "",
        dropdownActionsCount: 0,
        dropdownX: 0,
        dropdownY: 0,
        dropdownStyle: "display:none;",
        dropdownPlacement: "bottom",
        dropdownDismissPointerHandler: null,
        dropdownDismissKeyHandler: null,
        dropdownRepositionAbortController: null,
        dropdownRepositionScrollHandler: null,
        dropdownRepositionResizeHandler: null,
        dropdownAnchorEl: null,
        dropdownAnchorClickOffsetX: null,
        pendingDateMutationByEventId: {},
        lastCreateFromGridSignature: null,
        lastCreateFromGridAt: 0,
        lastCreateFromGridStartIso: null,
        lastCreateFromGridAllDay: null,
        lastCreateFromGridSource: null,

        // Props
        config: props.config || {},
        endpoint: props.endpoint || "",
        resourceUri: props.resourceUri || null,
        async: props.async !== undefined ? props.async : false,
        currentLocale: props.config?.locale || "en",

        /**
         * Initialize FullCalendar
         */
        initCalendar() {
            const calendarEl = this.$el.querySelector(".full-calendar");

            if (!calendarEl) {
                this.error = "Calendar container not found";
                return;
            }

            // Get locale object from registry
            const localeObj = getFullCalendarLocale(this.currentLocale);

            const timeZone =
                this.config.timeZone || this.config.timezone || "local";

            // Normalize incoming config from PHP (legacy `timezone` -> FullCalendar `timeZone`)
            const normalizedConfig = { ...this.config };
            if (
                Object.prototype.hasOwnProperty.call(
                    normalizedConfig,
                    "timezone"
                )
            ) {
                delete normalizedConfig.timezone;
            }

            // Build FullCalendar config
            const userEventDidMount = normalizedConfig.eventDidMount;

            const calendarConfig = {
                ...normalizedConfig,
                plugins: [
                    dayGridPlugin,
                    timeGridPlugin,
                    listPlugin,
                    interactionPlugin,
                ],
                initialView: normalizedConfig.initialView || "dayGridMonth",
                headerToolbar: normalizedConfig.headerToolbar || {
                    left: "prev,next today",
                    center: "title",
                    right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek",
                },
                editable:
                    normalizedConfig.editable !== undefined
                        ? normalizedConfig.editable
                        : false,
                selectable:
                    normalizedConfig.selectable !== undefined
                        ? normalizedConfig.selectable
                        : true,
                locale: localeObj, // Use locale object from registry
                // [FIX] Support both timeZone and timezone (PHP convention) - use 'local' if not set
                // If timezone is set, use it as IANA timezone (e.g., 'Europe/Moscow', 'UTC')
                // FullCalendar will parse event times and display in this timezone
                timeZone: timeZone,

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
                },

                eventDidMount: (info) => {
                    this.applyEventTonalStyle(info);

                    if (typeof userEventDidMount === "function") {
                        userEventDidMount(info);
                    }
                },

                // View change handler
                datesSet: () => {
                    this.closeEventActionsDropdown("datesSet");
                },
            };

            // Initialize calendar
            try {
                this.calendar = new Calendar(calendarEl, calendarConfig);
                this.calendar.render();

                this.setupDropdownDismissListeners();
                this.setupDropdownRepositionListeners();
                // Setup event listener for calendar refresh after CRUD operations
                this.setupRefreshListener();
                this.applyInitialLayoutFix("init");
            } catch (error) {
                this.error = error.message;
            }
        },

        applyEventTonalStyle(info) {
            const el = info?.el;
            const event = info?.event;

            if (!el || !event) {
                return;
            }

            const accentColor =
                event.backgroundColor ||
                event.borderColor ||
                event.extendedProps?.color ||
                event.color;
            const textColor =
                event.textColor || event.extendedProps?.textColor || null;

            if (!accentColor) {
                el.classList.remove("msfc-event-custom-tonal");
                el.style.removeProperty("--msfc-event-accent");
                el.style.removeProperty("--msfc-event-custom-text");
                return;
            }

            el.classList.add("msfc-event-custom-tonal");
            el.style.setProperty("--msfc-event-accent", accentColor);

            if (textColor) {
                el.style.setProperty("--msfc-event-custom-text", textColor);
            } else {
                el.style.removeProperty("--msfc-event-custom-text");
            }
        },

        teardownRefreshListener(reason = "unknown") {
            this.teardownDropdownDismissListeners(`refresh-teardown:${reason}`);
            this.teardownDropdownRepositionListeners(
                `refresh-teardown:${reason}`
            );
            this.closeEventActionsDropdown(`refresh-teardown:${reason}`);

            if (this.refreshListenerAbortController) {
                this.refreshListenerAbortController.abort();
                this.refreshListenerAbortController = null;
            } else if (this.refreshListenerHandler) {
                window.removeEventListener(
                    "fullcalendar:refresh",
                    this.refreshListenerHandler
                );
                document.removeEventListener(
                    "fullcalendar:refresh",
                    this.refreshListenerHandler
                );
            }

            this.refreshListenerHandler = null;

            if (this.refreshListenerObserver) {
                this.refreshListenerObserver.disconnect();
                this.refreshListenerObserver = null;
            }
        },

        setupDropdownDismissListeners() {
            if (
                this.dropdownDismissAbortController ||
                typeof document === "undefined"
            ) {
                return;
            }

            this.dropdownDismissPointerHandler ??=
                this.handleDocumentPointerDown.bind(this);
            this.dropdownDismissKeyHandler ??=
                this.handleDocumentKeydown.bind(this);

            if (typeof AbortController !== "undefined") {
                this.dropdownDismissAbortController = new AbortController();
                const signal = this.dropdownDismissAbortController.signal;

                document.addEventListener(
                    "pointerdown",
                    this.dropdownDismissPointerHandler,
                    { signal }
                );
                document.addEventListener(
                    "click",
                    this.dropdownDismissPointerHandler,
                    { signal }
                );
                document.addEventListener(
                    "keydown",
                    this.dropdownDismissKeyHandler,
                    { signal }
                );
            } else {
                document.addEventListener(
                    "pointerdown",
                    this.dropdownDismissPointerHandler
                );
                document.addEventListener(
                    "click",
                    this.dropdownDismissPointerHandler
                );
                document.addEventListener(
                    "keydown",
                    this.dropdownDismissKeyHandler
                );
            }
        },

        teardownDropdownDismissListeners(reason = "unknown") {
            if (this.dropdownDismissAbortController) {
                this.dropdownDismissAbortController.abort();
                this.dropdownDismissAbortController = null;
                return;
            }

            if (this.dropdownDismissPointerHandler) {
                document.removeEventListener(
                    "pointerdown",
                    this.dropdownDismissPointerHandler
                );
                document.removeEventListener(
                    "click",
                    this.dropdownDismissPointerHandler
                );
            }

            if (this.dropdownDismissKeyHandler) {
                document.removeEventListener(
                    "keydown",
                    this.dropdownDismissKeyHandler
                );
            }
        },

        setupDropdownRepositionListeners() {
            if (
                this.dropdownRepositionAbortController ||
                typeof document === "undefined" ||
                typeof window === "undefined"
            ) {
                return;
            }

            this.dropdownRepositionScrollHandler ??=
                this.handleDropdownRepositionEvent.bind(this);
            this.dropdownRepositionResizeHandler ??=
                this.handleDropdownRepositionEvent.bind(this);

            if (typeof AbortController !== "undefined") {
                this.dropdownRepositionAbortController = new AbortController();
                const signal = this.dropdownRepositionAbortController.signal;

                document.addEventListener(
                    "scroll",
                    this.dropdownRepositionScrollHandler,
                    { capture: true, signal }
                );
                window.addEventListener(
                    "resize",
                    this.dropdownRepositionResizeHandler,
                    { signal }
                );
            } else {
                document.addEventListener(
                    "scroll",
                    this.dropdownRepositionScrollHandler,
                    true
                );
                window.addEventListener(
                    "resize",
                    this.dropdownRepositionResizeHandler
                );
            }
        },

        teardownDropdownRepositionListeners(reason = "unknown") {
            if (this.dropdownRepositionAbortController) {
                this.dropdownRepositionAbortController.abort();
                this.dropdownRepositionAbortController = null;
                return;
            }

            if (this.dropdownRepositionScrollHandler) {
                document.removeEventListener(
                    "scroll",
                    this.dropdownRepositionScrollHandler,
                    true
                );
            }

            if (this.dropdownRepositionResizeHandler) {
                window.removeEventListener(
                    "resize",
                    this.dropdownRepositionResizeHandler
                );
            }
        },

        handleDropdownRepositionEvent(event) {
            if (!this.dropdownOpen) {
                return;
            }

            const dropdownEl = this.$refs?.eventActionsDropdown;
            const anchorEl = this.dropdownAnchorEl;

            if (!dropdownEl || !anchorEl || !anchorEl.isConnected) {
                this.closeEventActionsDropdown(
                    `reposition-missing:${event?.type || "unknown"}`
                );
                return;
            }

            this.positionEventActionsDropdown(anchorEl, dropdownEl);
        },

        handleDocumentPointerDown(event) {
            if (!this.dropdownOpen) {
                return;
            }

            const dropdownEl = this.$refs?.eventActionsDropdown;
            if (dropdownEl && dropdownEl.contains(event.target)) {
                return;
            }

            this.closeEventActionsDropdown("outside-click");
        },

        handleDocumentKeydown(event) {
            if (!this.dropdownOpen) {
                return;
            }

            if (event.key === "Escape") {
                this.closeEventActionsDropdown("escape");
            }
        },

        handleDropdownActionClick(event) {
            if (!this.dropdownOpen) {
                return;
            }

            const actionTarget = event?.target?.closest?.(
                'a, button, .btn, [role="button"]'
            );
            if (!actionTarget) {
                return;
            }

            // Close after MoonShine/Alpine handlers receive the click event.
            setTimeout(() => {
                this.closeEventActionsDropdown("action-click");
            }, 0);
        },

        dedupeTeleportedModalTemplates() {
            const templates = Array.from(
                document.querySelectorAll(
                    '.modal-template[data-teleport-target="true"]'
                )
            );
            const groups = new Map();

            for (const template of templates) {
                const keyAttr = template
                    .getAttributeNames()
                    .find((name) => name.startsWith("@modal_toggled:"));

                if (!keyAttr) {
                    continue;
                }

                if (!groups.has(keyAttr)) {
                    groups.set(keyAttr, []);
                }

                groups.get(keyAttr).push(template);
            }

            for (const [, group] of groups.entries()) {
                if (group.length <= 1) {
                    continue;
                }

                // Keep the latest teleported template (most recently initialized), remove older duplicates.
                const toRemove = group.slice(0, -1);
                toRemove.forEach((node) => {
                    node.remove();
                });
            }
        },

        getEventActionsPayload(event) {
            const payload =
                event?.extendedProps?.moonshineFullCalendar?.actions ?? null;

            return payload;
        },

        updateDropdownStyle() {
            this.dropdownStyle = this.dropdownOpen
                ? `position:absolute; left:${Math.max(
                      0,
                      this.dropdownX
                  )}px; top:${Math.max(0, this.dropdownY)}px;`
                : "display:none;";
        },

        closeEventActionsDropdown(reason = "unknown") {
            if (
                !this.dropdownOpen &&
                !this.dropdownActionsHtml &&
                !this.dropdownEventId
            ) {
                return;
            }

            this.dropdownOpen = false;
            this.dropdownEventId = null;
            this.dropdownActionsHtml = "";
            this.dropdownActionsCount = 0;
            this.dropdownAnchorEl = null;
            this.dropdownAnchorClickOffsetX = null;
            this.updateDropdownStyle();

            this.dropdownPlacement = "bottom";
        },

        positionEventActionsDropdown(anchorEl, dropdownEl = null) {
            if (!anchorEl || !this.$el) {
                return false;
            }

            const anchorRect = anchorEl.getBoundingClientRect();
            const rootRect = this.$el.getBoundingClientRect();
            const viewportWidth =
                window.innerWidth || document.documentElement.clientWidth || 0;
            const viewportHeight =
                window.innerHeight ||
                document.documentElement.clientHeight ||
                0;
            const offset = 8;
            const margin = 8;

            const measuredWidth = dropdownEl?.offsetWidth || 220;
            const measuredHeight = dropdownEl?.offsetHeight || 56;

            // Prefer X near the exact click position inside the event card (if available),
            // fallback to event center.
            const anchorClickXViewport =
                typeof this.dropdownAnchorClickOffsetX === "number"
                    ? anchorRect.left + this.dropdownAnchorClickOffsetX
                    : anchorRect.left + anchorRect.width / 2;

            const preferredLeftViewport =
                anchorClickXViewport - measuredWidth / 2;
            const clampedLeftViewport = Math.min(
                Math.max(margin, preferredLeftViewport),
                Math.max(margin, viewportWidth - measuredWidth - margin)
            );

            const spaceBelow = viewportHeight - anchorRect.bottom - margin;
            const spaceAbove = anchorRect.top - margin;
            const placeBelow =
                spaceBelow >= measuredHeight + offset ||
                spaceBelow >= spaceAbove;

            let topViewport = placeBelow
                ? anchorRect.bottom + offset
                : anchorRect.top - measuredHeight - offset;

            topViewport = Math.min(
                Math.max(margin, topViewport),
                Math.max(margin, viewportHeight - measuredHeight - margin)
            );

            this.dropdownX = clampedLeftViewport - rootRect.left;
            this.dropdownY = topViewport - rootRect.top;
            this.dropdownPlacement = placeBelow ? "bottom" : "top";

            this.updateDropdownStyle();

            return true;
        },

        initializeEventActionsDropdownContent() {
            const contentEl = this.$refs?.eventActionsDropdownContent;
            if (!contentEl) {
                return;
            }

            try {
                if (
                    window.Alpine &&
                    typeof window.Alpine.initTree === "function"
                ) {
                    const childRoots = Array.from(contentEl.children || []);

                    childRoots.forEach((child) => {
                        window.Alpine.initTree(child);
                    });

                    this.dedupeTeleportedModalTemplates();

                    return;
                }
            } catch (error) {}
        },

        openEventActionsDropdown({
            eventId,
            actionsHtml,
            actionsCount,
            anchorEl,
            clickClientX = null,
        }) {
            const hostEl = this.$refs?.eventActionsDropdown;
            const contentEl = this.$refs?.eventActionsDropdownContent;

            if (!hostEl || !contentEl) {
                return false;
            }

            this.dropdownEventId = String(eventId);
            this.dropdownActionsHtml = actionsHtml;
            this.dropdownActionsCount = actionsCount;
            this.dropdownOpen = true;
            this.dropdownAnchorEl = anchorEl;
            this.dropdownAnchorClickOffsetX = null;

            if (anchorEl && typeof clickClientX === "number") {
                const anchorRect = anchorEl.getBoundingClientRect();
                const relativeClickX = clickClientX - anchorRect.left;
                this.dropdownAnchorClickOffsetX = Math.min(
                    Math.max(0, relativeClickX),
                    Math.max(0, anchorRect.width)
                );
            }

            this.positionEventActionsDropdown(anchorEl);

            this.$nextTick(() => {
                this.initializeEventActionsDropdownContent();
                this.positionEventActionsDropdown(
                    anchorEl,
                    this.$refs?.eventActionsDropdown
                );
            });

            return true;
        },

        setupRefreshListenerAutoCleanup() {
            if (
                this.refreshListenerObserver ||
                typeof MutationObserver === "undefined"
            ) {
                return;
            }

            this.refreshListenerObserver = new MutationObserver(() => {
                if (!this.$el || this.$el.isConnected) {
                    return;
                }

                this.teardownRefreshListener("element-disconnected");
            });

            this.refreshListenerObserver.observe(document.body, {
                childList: true,
                subtree: true,
            });
        },

        /**
         * FullCalendar timeGridDay can render before layout is fully settled (x-cloak/async containers).
         * Force a delayed size recalculation to avoid "events loaded but not visible" on first render.
         */
        applyInitialLayoutFix(reason = "unknown") {
            if (!this.calendar) {
                return;
            }

            const applyFix = (phase) => {
                if (!this.calendar) {
                    return;
                }

                const viewType = this.calendar.view?.type;
                if (viewType !== "timeGridDay") {
                    return;
                }

                try {
                    this.calendar.updateSize();
                    this.calendar.render();
                } catch (error) {}

                this.ensureTimeGridDayEventsRendered(
                    `[layout:${reason}:${phase}]`
                );
            };

            requestAnimationFrame(() => {
                applyFix("raf-1");
                requestAnimationFrame(() => applyFix("raf-2"));
            });

            setTimeout(() => applyFix("timeout-50ms"), 50);
            setTimeout(() => applyFix("timeout-250ms"), 250);
        },

        /**
         * Recovery for a flaky initial timeGridDay render:
         * events are parsed but no timeGrid DOM nodes appear until manual view switch.
         */
        ensureTimeGridDayEventsRendered(reason = "unknown") {
            if (!this.calendar) {
                return;
            }

            const viewType = this.calendar.view?.type;
            if (viewType !== "timeGridDay") {
                return;
            }

            const parsedEvents = this.calendar.getEvents();
            const timeGridEvents =
                this.$el?.querySelectorAll?.(".fc-timegrid-event")?.length ?? 0;

            if (
                parsedEvents.length === 0 ||
                timeGridEvents > 0 ||
                this.dayViewRenderRecoveryAttempted
            ) {
                return;
            }

            this.dayViewRenderRecoveryAttempted = true;

            const currentDate = this.calendar.getDate();
            try {
                this.calendar.changeView("timeGridDay", currentDate);

                requestAnimationFrame(() => {
                    this.calendar?.updateSize?.();
                });
            } catch (error) {}
        },

        /**
         * Setup event listener for calendar refresh events from MoonShine
         * Listens for 'fullcalendar:refresh' custom event dispatched by modifySaveResponse
         */
        setupRefreshListener() {
            const self = this;

            // Prevent duplicate listeners on repeated init/re-render of the same component instance
            this.teardownRefreshListener("re-register");

            // Extract resource URI from endpoint URL for filtering
            const resourceUri =
                this.resourceUri || this.getResourceUriFromEndpoint();

            const handleRefreshEvent = (event) => {
                // If no resource is provided, treat as broadcast refresh for all calendars
                if (!event.detail || !event.detail.resource) {
                    if (self.calendar) {
                        self.closeEventActionsDropdown("refresh-broadcast");
                        self.calendar.refetchEvents();
                    }

                    return;
                }

                // Check if this event is for this calendar instance
                if (event.detail && event.detail.resource) {
                    const eventResource = String(event.detail.resource).trim();
                    const normalizedCalendarResource = resourceUri
                        ? String(resourceUri).trim()
                        : null;

                    if (!normalizedCalendarResource) {
                        if (self.calendar) {
                            self.closeEventActionsDropdown("refresh-fallback");
                            self.calendar.refetchEvents();
                        }

                        return;
                    }

                    // Only refresh if this event is for this calendar
                    if (eventResource === normalizedCalendarResource) {
                        // Call refetchEvents
                        if (self.calendar) {
                            self.closeEventActionsDropdown(
                                "refresh-resource-match"
                            );
                            self.calendar.refetchEvents();
                        }
                    }
                }
            };

            this.refreshListenerHandler = handleRefreshEvent;

            // MoonShine dispatches browser events via global dispatchEvent (window target).
            // Also register on document for compatibility with manual/custom dispatches.
            if (typeof AbortController !== "undefined") {
                this.refreshListenerAbortController = new AbortController();
                const signal = this.refreshListenerAbortController.signal;

                window.addEventListener(
                    "fullcalendar:refresh",
                    handleRefreshEvent,
                    { signal }
                );
                document.addEventListener(
                    "fullcalendar:refresh",
                    handleRefreshEvent,
                    { signal }
                );
            } else {
                window.addEventListener(
                    "fullcalendar:refresh",
                    handleRefreshEvent
                );
                document.addEventListener(
                    "fullcalendar:refresh",
                    handleRefreshEvent
                );
            }

            this.setupRefreshListenerAutoCleanup();
        },

        /**
         * Extract resource URI from endpoint URL
         * Endpoint format: /admin/resource/{resourceUri}/full-calendar/events
         */
        getResourceUriFromEndpoint() {
            try {
                const url = new URL(this.endpoint, window.location.origin);
                const pathParts = url.pathname.split("/");
                // Find 'resource' in path and get the next segment
                const resourceIndex = pathParts.indexOf("resource");
                if (
                    resourceIndex !== -1 &&
                    resourceIndex + 1 < pathParts.length
                ) {
                    return pathParts[resourceIndex + 1];
                }
                return null;
            } catch (error) {
                return null;
            }
        },

        getEventDateUpdateConfig() {
            const eventDateUpdate = this.config?.eventDateUpdate || {};

            return {
                method: (eventDateUpdate.method || "PATCH").toUpperCase(),
                urlTemplate: eventDateUpdate.urlTemplate || "",
                idPlaceholder:
                    eventDateUpdate.idPlaceholder || "__RESOURCE_ITEM__",
            };
        },

        buildEventDateUpdateUrl(eventId) {
            const cfg = this.getEventDateUpdateConfig();

            if (!cfg.urlTemplate) {
                throw new Error(
                    "Calendar date update endpoint template is not configured"
                );
            }

            const encodedId = encodeURIComponent(String(eventId));
            const url = cfg.urlTemplate.replaceAll(
                cfg.idPlaceholder,
                encodedId
            );

            return url;
        },

        buildAjaxHeaders() {
            const headers = {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
            };

            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content");
            if (csrfToken) {
                headers["X-CSRF-TOKEN"] = csrfToken;
            }

            return headers;
        },

        applyMoonShineJsonSideEffects(data, fallbackResource = null) {
            if (!data || typeof data !== "object") {
                return;
            }

            const message =
                typeof data.message === "string" ? data.message : null;
            const messageType =
                typeof data.messageType === "string"
                    ? data.messageType
                    : "success";
            const messageDuration = data.messageDuration ?? null;

            if (message && window.MoonShine?.ui?.toast) {
                window.MoonShine.ui.toast(
                    message,
                    messageType,
                    messageDuration
                );
            }

            const events = parseMoonShineEventString(data.events);

            if (events.length === 0 && fallbackResource) {
                window.dispatchEvent(
                    new CustomEvent("fullcalendar:refresh", {
                        detail: { resource: fallbackResource },
                    })
                );
                return;
            }

            events.forEach(({ eventName, detail }) => {
                window.dispatchEvent(
                    new CustomEvent(eventName, {
                        detail,
                        bubbles: true,
                        composed: true,
                        cancelable: true,
                    })
                );
            });
        },

        async updateEventDatesFromCalendar(info, action) {
            const eventId = String(info?.event?.id ?? "");

            if (!eventId) {
                info?.revert?.();
                return;
            }

            if (this.pendingDateMutationByEventId[eventId]) {
                info?.revert?.();
                return;
            }

            const payload = {
                start: info.event.start ? info.event.start.toISOString() : null,
                end: info.event.end ? info.event.end.toISOString() : null,
                allDay: !!info.event.allDay,
                action,
                timezone:
                    this.calendar?.getOption?.("timeZone") ||
                    this.config?.timezone ||
                    this.config?.timeZone ||
                    "local",
            };

            if (!payload.start) {
                info.revert();
                return;
            }

            let url = "";
            const method = this.getEventDateUpdateConfig().method;

            try {
                url = this.buildEventDateUpdateUrl(eventId);
                this.pendingDateMutationByEventId[eventId] = true;

                const response = await fetch(url, {
                    method,
                    headers: this.buildAjaxHeaders(),
                    body: JSON.stringify(payload),
                });

                let responseData = null;
                try {
                    responseData = await response.json();
                } catch (parseError) {}

                if (!response.ok) {
                    throw new Error(
                        responseData?.message ||
                            `HTTP ${response.status}: ${response.statusText}`
                    );
                }

                this.applyMoonShineJsonSideEffects(
                    responseData,
                    this.resourceUri
                );
            } catch (error) {
                info.revert();
                this.error = error.message;

                if (window.MoonShine?.ui?.toast) {
                    window.MoonShine.ui.toast(
                        error.message || "Update failed",
                        "error"
                    );
                }
            } finally {
                delete this.pendingDateMutationByEventId[eventId];
            }
        },

        /**
         * Fetch events from API using standard fetch with CSRF token
         * MoonShine.request is not suitable because it doesn't return data directly
         */
        fetchEvents: async function (info, successCallback, failureCallback) {
            this.loading = true;
            this.error = null;
            this.closeEventActionsDropdown("fetch-events-start");

            const start = info.start ? info.start.toISOString() : null;
            const end = info.end ? info.end.toISOString() : null;

            try {
                // Build URL with query parameters
                const url = new URL(this.endpoint, window.location.origin);
                if (start) url.searchParams.set("start", start);
                if (end) url.searchParams.set("end", end);

                // Prepare headers with CSRF token for MoonShine
                const headers = {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                };

                // Add CSRF token if available (MoonShine uses meta tag)
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content");
                if (csrfToken) {
                    headers["X-CSRF-TOKEN"] = csrfToken;
                }

                // Add X-Requested-With for AJAX detection
                headers["X-Requested-With"] = "XMLHttpRequest";

                const response = await fetch(url.toString(), {
                    method: "GET",
                    headers: headers,
                });

                if (!response.ok) {
                    throw new Error(
                        `HTTP ${response.status}: ${response.statusText}`
                    );
                }

                const data = await response.json();
                const events = Array.isArray(data) ? data : [];

                this.events = events;
                successCallback(events);

                if (this.calendar?.view?.type === "timeGridDay") {
                    this.dayViewRenderRecoveryAttempted = false;
                }

                this.applyInitialLayoutFix("events-loaded");
                setTimeout(
                    () =>
                        this.ensureTimeGridDayEventsRendered(
                            "events-loaded-post-check"
                        ),
                    10
                );
                setTimeout(
                    () =>
                        this.ensureTimeGridDayEventsRendered(
                            "events-loaded-post-check-100ms"
                        ),
                    100
                );
            } catch (error) {
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
            const payload = this.getEventActionsPayload(info.event);
            const hasActions = !!(payload && payload.hasActions);
            const actionsHtml =
                typeof payload?.html === "string" ? payload.html : "";
            const actionsCount = Number(payload?.count || 0);

            if (hasActions && actionsHtml.trim() !== "") {
                info.jsEvent?.preventDefault?.();
                info.jsEvent?.stopPropagation?.();

                if (
                    this.dropdownOpen &&
                    String(this.dropdownEventId) === String(info.event.id)
                ) {
                    this.closeEventActionsDropdown("toggle-same-event");
                    return;
                }

                this.openEventActionsDropdown({
                    eventId: info.event.id,
                    actionsHtml,
                    actionsCount,
                    anchorEl: info.el,
                    clickClientX:
                        typeof info.jsEvent?.clientX === "number"
                            ? info.jsEvent.clientX
                            : null,
                });

                return;
            }

            this.closeEventActionsDropdown("event-click-no-actions");

            if (info.event.url) {
                window.location.href = info.event.url;
            } else if (window.moonshineFullCalendarEventClick) {
                window.moonshineFullCalendarEventClick(info.event);
            }
        },

        getCreateFromGridConfig() {
            const config = this.config?.createFromGrid ?? {};

            return {
                enabled: config.enabled === true,
                modalName:
                    typeof config.modalName === "string" &&
                    config.modalName !== ""
                        ? config.modalName
                        : "resource-create-modal",
                startParam:
                    typeof config.startParam === "string" &&
                    config.startParam !== ""
                        ? config.startParam
                        : "start",
                endParam:
                    typeof config.endParam === "string" &&
                    config.endParam !== ""
                        ? config.endParam
                        : "end",
                openOnDoubleClick: config.openOnDoubleClick === true,
                timedFallbackDurationMinutes: Number(
                    config.timedFallbackDurationMinutes || 60
                ),
                allDayFallbackDurationDays: Number(
                    config.allDayFallbackDurationDays || 1
                ),
            };
        },

        getCalendarCreateTrigger() {
            const trigger =
                this.$refs?.calendarCreateTrigger ??
                this.$el.querySelector('[data-calendar-create-trigger="true"]');

            return trigger;
        },

        getCalendarCreateModalController(modalName) {
            const controller = Array.from(
                document.querySelectorAll(
                    '[data-teleport-target="true"][x-data]'
                )
            ).find(
                (el) =>
                    typeof el.innerHTML === "string" &&
                    el.innerHTML.includes(`modal_toggled:${modalName}`)
            );

            return controller || null;
        },

        patchCalendarCreateModalController(modalController, modalName) {
            const modalData = modalController?._x_dataStack?.[0] ?? null;

            if (!modalData || modalData.__calendarDynamicAsyncUrlPatched) {
                return modalData;
            }

            if (typeof modalData.toggleModal !== "function") {
                return modalData;
            }

            modalData.toggleModal = async function toggleCalendarModal() {
                this.open = !this.open;

                if (this.open && this.asyncUrl && !this.asyncLoaded) {
                    await loadAsyncModalContent(this.asyncUrl, this.id);

                    this.asyncLoaded = !this.$root.dataset.alwaysLoad;
                }

                this.dispatchEvents();
            };

            modalData.__calendarDynamicAsyncUrlPatched = true;

            return modalData;
        },

        getDefaultCreateEnd(startDate, allDay, config) {
            if (
                !(startDate instanceof Date) ||
                Number.isNaN(startDate.getTime())
            ) {
                return null;
            }

            const fallback = new Date(startDate.getTime());

            if (allDay) {
                fallback.setDate(
                    fallback.getDate() +
                        Math.max(1, config.allDayFallbackDurationDays)
                );
                return fallback;
            }

            fallback.setMinutes(
                fallback.getMinutes() +
                    Math.max(1, config.timedFallbackDurationMinutes)
            );

            return fallback;
        },

        getCreateFromGridSignature(range, resolvedEnd = null) {
            const startIso =
                range?.start instanceof Date ? range.start.toISOString() : null;
            const endIso =
                resolvedEnd instanceof Date &&
                !Number.isNaN(resolvedEnd.getTime())
                    ? resolvedEnd.toISOString()
                    : null;

            return JSON.stringify({
                start: startIso,
                end: endIso,
                allDay: Boolean(range?.allDay),
            });
        },

        buildCreateTriggerUrl(range) {
            const config = this.getCreateFromGridConfig();
            const trigger = this.getCalendarCreateTrigger();

            if (!config.enabled || !trigger) {
                return null;
            }

            const startDate = range?.start instanceof Date ? range.start : null;
            if (!startDate) {
                return null;
            }

            const baseHref =
                trigger.dataset.baseHref || trigger.getAttribute("href") || "";
            if (!baseHref) {
                return null;
            }

            if (!trigger.dataset.baseHref) {
                trigger.dataset.baseHref = baseHref;
            }

            const endDate =
                range?.end instanceof Date
                    ? range.end
                    : this.getDefaultCreateEnd(
                          startDate,
                          Boolean(range?.allDay),
                          config
                      );
            const signature = this.getCreateFromGridSignature(range, endDate);
            const now = Date.now();
            const startIso = startDate.toISOString();
            const allDay = Boolean(range?.allDay);

            if (
                this.lastCreateFromGridSignature === signature &&
                now - this.lastCreateFromGridAt < 500
            ) {
                return null;
            }

            if (
                range?.source === "dateClick" &&
                this.lastCreateFromGridSource === "select" &&
                this.lastCreateFromGridStartIso === startIso &&
                this.lastCreateFromGridAllDay === allDay &&
                now - this.lastCreateFromGridAt < 500
            ) {
                return null;
            }

            const url = new URL(baseHref, window.location.origin);
            url.searchParams.set(config.startParam, startDate.toISOString());

            if (endDate instanceof Date && !Number.isNaN(endDate.getTime())) {
                url.searchParams.set(config.endParam, endDate.toISOString());
            } else {
                url.searchParams.delete(config.endParam);
            }

            this.lastCreateFromGridSignature = signature;
            this.lastCreateFromGridAt = now;
            this.lastCreateFromGridStartIso = startIso;
            this.lastCreateFromGridAllDay = allDay;
            this.lastCreateFromGridSource = range?.source ?? "unknown";

            return {
                trigger,
                config,
                url: url.toString(),
            };
        },

        openCreateModalFromGrid(range) {
            const prepared = this.buildCreateTriggerUrl(range);

            if (!prepared) {
                return false;
            }

            try {
                prepared.trigger.setAttribute("href", prepared.url);
                const modalController = this.getCalendarCreateModalController(
                    prepared.config.modalName
                );
                const modalData = this.patchCalendarCreateModalController(
                    modalController,
                    prepared.config.modalName
                );

                if (
                    modalData &&
                    Object.prototype.hasOwnProperty.call(modalData, "asyncUrl")
                ) {
                    modalData.asyncUrl = prepared.url;

                    if (
                        Object.prototype.hasOwnProperty.call(
                            modalData,
                            "asyncLoaded"
                        )
                    ) {
                        modalData.asyncLoaded = false;
                    }
                }

                window.setTimeout(() => {
                    try {
                        window.dispatchEvent(
                            new CustomEvent(
                                `modal_toggled:${prepared.config.modalName}`
                            )
                        );
                    } catch (error) {}
                }, 0);

                return true;
            } catch (error) {
                return false;
            }
        },

        /**
         * Handle date click
         */
        handleDateClick(info) {
            const createConfig = this.getCreateFromGridConfig();

            if (
                createConfig.openOnDoubleClick &&
                (info.jsEvent?.detail ?? 0) < 2
            ) {
                if (window.moonshineFullCalendarDateClick) {
                    window.moonshineFullCalendarDateClick(info);
                }

                return;
            }

            this.openCreateModalFromGrid({
                start: info.date,
                end: null,
                allDay: info.allDay === true,
                source: "dateClick",
            });

            if (window.moonshineFullCalendarDateClick) {
                window.moonshineFullCalendarDateClick(info);
            }
        },

        /**
         * Handle date select
         */
        handleDateSelect(info) {
            const createConfig = this.getCreateFromGridConfig();

            if (!createConfig.openOnDoubleClick) {
                this.openCreateModalFromGrid({
                    start: info.start,
                    end: info.end,
                    allDay: info.allDay === true,
                    source: "select",
                });
            } else {
            }

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
            this.closeEventActionsDropdown("event-drop");
            this.updateEventDatesFromCalendar(info, "drop");

            if (window.moonshineFullCalendarEventDrop) {
                window.moonshineFullCalendarEventDrop(info);
            }
        },

        /**
         * Handle event resize
         */
        handleEventResize(info) {
            this.closeEventActionsDropdown("event-resize");
            this.updateEventDatesFromCalendar(info, "resize");

            if (window.moonshineFullCalendarEventResize) {
                window.moonshineFullCalendarEventResize(info);
            }
        },

        /**
         * Refresh calendar
         */
        refreshCalendar() {
            this.closeEventActionsDropdown("manual-refresh");

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
            if (!this.calendar) {
                return;
            }

            // Get locale object from registry
            const localeObj = getFullCalendarLocale(localeCode);

            // Update locale in FullCalendar
            this.calendar.setOption("locale", localeObj);

            // Update current locale tracking
            this.currentLocale = localeCode;
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
    });

    // Support Blade usage x-data="fullCalendar({...})" when script is loaded via MoonShine asset manager.
    window.fullCalendar = fullCalendarFactory;
    Alpine.data("fullCalendar", fullCalendarFactory);
}

/**
 * Initialize on Alpine ready
 */
document.addEventListener("alpine:init", () => {
    registerFullCalendar();
});

/**
 * Also initialize if Alpine is already loaded
 */
if (window.Alpine && window.Alpine.version) {
    registerFullCalendar();
}

/**
 * Export for external use
 */
window.fullCalendarRefresh = function (resource = null) {
    window.dispatchEvent(
        new CustomEvent("fullcalendar:refresh", {
            detail: resource ? { resource } : {},
        })
    );
};

/**
 * Export locale utilities for external use
 */
window.fullCalendarGetLocale = function (localeCode) {
    return getFullCalendarLocale(localeCode);
};

window.fullCalendarGetAvailableLocales = function () {
    return getAvailableLocaleCodes();
};
