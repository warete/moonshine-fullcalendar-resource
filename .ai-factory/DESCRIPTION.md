# MoonShine FullCalendar Resource Package

## Overview
A Laravel package that integrates FullCalendar.js into MoonShine v4 admin panel. Provides a reusable calendar UI component for resource index pages with async event loading, modal editing, and FullCalendar.js integration.

## Core Features
- Calendar UI component for MoonShine resources
- Async event loading by date range
- Modal-based event editing (editInModal integration)
- Support for all FullCalendar view modes (month/week/day/list)
- Custom index page with calendar component
- Resource-scoped custom routes for event fetching
- Drag & drop endpoint preparation (UI in post-MVP)

## Tech Stack
- **Language:** PHP 8.2+
- **Framework:** Laravel 11
- **Admin Panel:** MoonShine v4
- **Frontend:** Alpine.js (built-in MoonShine), FullCalendar.js (npm)
- **Build:** Vite
- **Styling:** Tailwind 4 (MoonShine design tokens)

## Architecture Notes
- Package namespace: `Warete\MoonShineFullCalendar`
- Service Provider: `FullCalendarServiceProvider`
- Component-based architecture (not a Field, used only on index pages)
- Alpine.js data-component for FullCalendar initialization
- HTTP requests via MoonShine.request helper
- Timezone handling via app.timezone config

## Event Model Schema
- `title` — string, required
- `start` — datetime with timezone, required
- `end` — datetime with timezone, required
- `color` — string, nullable
- `description` — text, nullable

## Extension Points
- Date formatting hooks (normalization on save, formatting on output)
- Custom event source
- Filters and color rules
- Drag & drop enablement

## MVP Limitations (post-MVP features)
- Custom Tailwind theme for FullCalendar
- Event ownership and ACL
- Recurring events (RRULE)
- Conflict detection
- Filters and categories
- Drag & drop UI

## Package Structure
```
src/
├── Components/         # UI components
├── Http/Controllers/   # API controllers
├── Providers/          # Service providers
├── Resources/          # Resource classes
└── ...
routes/                 # Custom routes
public/                 # Compiled assets (css/js)
```

## Non-Functional Requirements
- Async event loading on calendar view change
- Modal-based CRUD operations via MoonShine editInModal
- Calendar refresh after create/update/delete operations
- ISO8601 date format for API
- Extensible hooks without package modification
