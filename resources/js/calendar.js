// FullCalendar Integration Helper for MoonShine
// This file provides utility functions for calendar integration

window.FullCalendarHelper = {
    /**
     * Update calendar events from async response
     */
    updateEvents: function(calendar, events) {
        if (!calendar) return;

        // Remove all existing events
        calendar.removeAllEvents();

        // Add new events
        events.forEach(event => {
            calendar.addEvent(event);
        });
    },

    /**
     * Refresh calendar with new data
     */
    refreshCalendar: function(calendarId) {
        const calendar = this.getCalendarInstance(calendarId);
        if (calendar) {
            calendar.refetchEvents();
        }
    },

    /**
     * Get calendar instance by ID
     */
    getCalendarInstance: function(calendarId = 'calendar') {
        const calendarEl = document.getElementById(calendarId);
        if (calendarEl && calendarEl._fullCalendar) {
            return calendarEl._fullCalendar;
        }
        return null;
    },

    /**
     * Dispatch calendar update event
     */
    dispatchUpdateEvent: function(resourceUrl = null) {
        const event = new CustomEvent('calendar-updated', {
            detail: {
                resource: resourceUrl || window.location.pathname,
                timestamp: new Date().toISOString()
            }
        });
        window.dispatchEvent(event);
    },

    /**
     * Format date for FullCalendar
     */
    formatDate: function(date) {
        if (!date) return null;

        if (date instanceof Date) {
            return date.toISOString();
        }

        if (typeof date === 'string') {
            return new Date(date).toISOString();
        }

        return null;
    },

    /**
     * Parse date from FullCalendar format
     */
    parseDate: function(dateString) {
        if (!dateString) return null;
        return new Date(dateString);
    },

    /**
     * Show event actions dropdown
     */
    showEventActions: function(event, jsEvent, buttons) {
        if (!buttons || buttons.length === 0) return;

        const dropdown = document.getElementById('event-actions-dropdown');
        if (!dropdown) return;

        const rect = jsEvent.target.getBoundingClientRect();

        // Update dropdown data
        dropdown._x_dataStack[0].buttons = buttons;
        dropdown._x_dataStack[0].x = rect.left;
        dropdown._x_dataStack[0].y = rect.bottom;
        dropdown._x_dataStack[0].show = true;
        dropdown.classList.remove('hidden');
    },

    /**
     * Hide event actions dropdown
     */
    hideEventActions: function() {
        const dropdown = document.getElementById('event-actions-dropdown');
        if (dropdown) {
            dropdown._x_dataStack[0].show = false;
        }
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Add event listener to hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('event-actions-dropdown');
        if (dropdown && !dropdown.contains(e.target)) {
            window.FullCalendarHelper.hideEventActions();
        }
    });

    // Add keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.FullCalendarHelper.hideEventActions();
        }
    });
});