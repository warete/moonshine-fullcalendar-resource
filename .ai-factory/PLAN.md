# Implementation Plan: FullCalendar Component with Logic and JavaScript

Branch: 1.x
Created: 2025-02-16

## Overview
Implement the FullCalendar.js integration for MoonShine v4 admin panel. This plan covers the backend resource class, frontend component with Alpine.js, Blade view templates, JavaScript initialization, and asset compilation.

## Settings
- Testing: No
- Logging: Configurable via FULLCALENDAR_LOG_LEVEL env var
- Docs: Yes

## Commit Plan
**Commit 1** (after tasks 1-2): "feat: add FullCalendarResource and FullCalendarComponent classes"
**Commit 2** (after tasks 3-5): "feat: implement Blade view, JavaScript entry point, and service provider"
**Commit 3** (after tasks 6-8): "feat: compile assets, add example resource, and documentation"

## Tasks

### Phase 1: Backend Classes

- [ ] **Task 1: Create FullCalendarResource base class**
  - Extend MoonShine\Resource
  - Implement getCalendarItems(array $params): array
  - Support date range filtering (start, end)
  - Format events to FullCalendar.js spec
  - Files: `src/Resources/FullCalendarResource.php`

- [ ] **Task 2: Create FullCalendarComponent UI class** (depends on 1)
  - Render calendar UI with Alpine.js integration
  - Pass configuration to frontend
  - Generate unique component IDs
  - Files: `src/Components/FullCalendarComponent.php`
  - Commit checkpoint: tasks 1-2

### Phase 2: Frontend Implementation

- [ ] **Task 3: Create Blade view template** (depends on 2)
  - Calendar container with Alpine.js x-data
  - Initialize FullCalendar via x-init
  - Wire up event handlers
  - Async event loading via MoonShine.request
  - Files: `resources/views/components/full-calendar.blade.php`

- [ ] **Task 4: Create JavaScript entry point** (depends on 3)
  - Register FullCalendar plugins (dayGrid, timeGrid, list)
  - Create Alpine.js data component
  - API integration via MoonShine.request
  - Files: `resources/js/register.js`, `resources/js/full-calendar.js`

- [ ] **Task 5: Update service provider** (depends on 4)
  - Register component and views
  - Configure asset publishing
  - Files: `src/Providers/FullCalendarServiceProvider.php`
  - Commit checkpoint: tasks 3-5

### Phase 3: Build & Documentation

- [ ] **Task 6: Compile assets with Vite** (depends on 1,2,3,4,5)
  - Build JS/CSS with npm run build
  - Verify output files
  - Files: `public/js/full-calendar.js`, `public/css/full-calendar.css`

- [ ] **Task 7: Create example FullCalendarResource** (depends on 1)
  - Show how to extend and use FullCalendarResource
  - Sample Eloquent query implementation
  - Files: `examples/ExampleEventResource.php`

- [ ] **Task 8: Create documentation** (depends on 1,2,3,4,5,6,7)
  - Installation instructions
  - API reference
  - Usage examples
  - Troubleshooting guide
  - Files: `README.md`
  - Commit checkpoint: tasks 6-8

## Tech Stack
- **Backend:** PHP 8.2+, Laravel 11, MoonShine v4
- **Frontend:** Alpine.js, FullCalendar.js v6
- **Build:** Vite
- **Styling:** Tailwind 4 (MoonShine design tokens)

## Logging Strategy
All components implement configurable logging:
- Environment variable: `FULLCALENDAR_LOG_LEVEL` (DEBUG/INFO/WARN/ERROR)
- Format: `[ClassName.method] message {data}`
- Console logging for frontend (controlled by `FULLCALENDAR_DEBUG`)
