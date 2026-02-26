# Implementation Plan: Calendar Auto-Refresh After Modal Save

Branch: feature/calendar-refresh
Created: 2025-02-16

## Settings
- Testing: no
- Logging: standard
- Docs: yes

## Overview

Add automatic calendar refresh after creating/updating events through MoonShine modal forms. The calendar will listen for a custom event dispatched from PHP after successful CRUD operations.

## Tasks

### Phase 1: PHP Event Dispatch
- [x] Task 1: Add modifySaveResponse to FullCalendarResource
  - Override modifySaveResponse to dispatch `fullcalendar:refresh` event
  - Use MoonShine event format: `eventName|key~value`
  - Include resource URI for filtering

### Phase 2: JavaScript Event Listener
- [x] Task 2: Add event listener in Alpine component
  - Listen for `fullcalendar:refresh` custom event
  - Filter by resource URI
  - Call calendar.refetchEvents() on match

### Phase 3: Build & Deploy
- [x] Task 3: Build JavaScript assets
- [x] Task 4: Update README documentation

## Technical Details

**Event Flow:**
1. User creates/updates event in modal
2. MoonShine calls `modifySaveResponse`
3. PHP adds `fullcalendar:refresh|resource~events` to response
4. MoonShine JS dispatches the event
5. Calendar component catches event and refetches events

**MoonShine Event Format:**
```
fullcalendar:refresh|resource~events
```

**Files to Modify:**
- `src/Resources/FullCalendarResource.php` - Add modifySaveResponse
- `resources/js/full-calendar.js` - Add event listener
- `README.md` - Documentation
