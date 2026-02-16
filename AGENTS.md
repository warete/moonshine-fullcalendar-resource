# AGENTS.md

> Project map for AI agents. Keep this file up-to-date as the project evolves.

## Project Overview
Laravel package for MoonShine v4 that integrates FullCalendar.js as a reusable calendar component for resource index pages.

## Tech Stack
- **Language:** PHP 8.2+
- **Framework:** Laravel 11
- **Admin Panel:** MoonShine v4
- **Frontend:** Alpine.js, FullCalendar.js, Tailwind 4
- **Build:** Vite

## Project Structure
```
moonshine-fullcalendar-resource/
├── .ai-factory/              # AI factory configuration
│   └── DESCRIPTION.md        # Project specification
├── .agents/skills/           # Installed AI skills
│   ├── laravel-specialist/   # Laravel patterns
│   └── alpine-js/            # Alpine.js patterns
├── .claude/skills/           # Claude Code skills
│   ├── moonshine-patterns/   # MoonShine v4 patterns (custom)
│   └── [other skills]
├── docs/                     # Project documentation
│   └── project-description.md # Full project description
├── public/                   # Compiled assets
│   ├── css/
│   │   └── full-calendar.css
│   ├── js/
│   │   └── full-calendar.js
│   └── manifest.json
├── routes/                   # Custom routes
│   └── moonshine-fullcalendar.php
├── src/                      # Package source code
│   ├── Commands/             # Artisan commands
│   ├── Components/           # UI components (MoonShine)
│   ├── Http/Controllers/     # API controllers
│   │   └── FullCalendarEventsController.php
│   ├── InputExtensions/      # Form input extensions
│   ├── Metrics/              # Metric components
│   ├── Models/               # Eloquent models (if any)
│   ├── Pages/                # Custom pages
│   ├── Providers/            # Service providers
│   │   └── FullCalendarServiceProvider.php
│   ├── Resources/            # MoonShine resources
│   └── Testing/              # Testing utilities
│       └── TestingServiceProvider.php
├── tests/                    # Test suite
│   ├── Feature/
│   │   └── ExampleTest.php
│   └── TestCase.php
├── lang/                     # Translations
├── vendor/                   # Composer dependencies
├── composer.json             # PHP dependencies
├── composer.lock
├── package.json              # NPM dependencies
├── vite.config.js            # Vite configuration
├── phpunit.xml.dist          # PHPUnit configuration
├── rector.php                # Rector configuration
└── .php-cs-fixer.dist.php    # PHP CS Fixer configuration
```

## Key Entry Points

| File | Purpose |
|------|---------|
| `src/Providers/FullCalendarServiceProvider.php` | Main service provider, registers routes, views, components |
| `routes/moonshine-fullcalendar.php` | Custom API routes for events |
| `src/Http/Controllers/FullCalendarEventsController.php` | Events API controller |
| `src/Components/` | Calendar UI component (MoonShine component, not Field) |
| `public/js/full-calendar.js` | Alpine.js component for FullCalendar initialization |
| `public/css/full-calendar.css` | Calendar-specific styles |
| `vite.config.js` | Frontend build configuration |

## Documentation

| Document | Path | Description |
|----------|------|-------------|
| Project Description | `docs/project-description.md` | Full MVP specification, architecture, API contract |
| README | `README.md` | Package overview and installation |
| AGENTS.md | `AGENTS.md` | This file — project structure map |

## AI Context Files

| File | Purpose |
|------|---------|
| AGENTS.md | This file — project structure map |
| .ai-factory/DESCRIPTION.md | Project specification and tech stack |
| .claude/skills/moonshine-patterns/SKILL.md | MoonShine v4 patterns for this project |

## Development Notes

### Component Architecture
- The calendar is a **MoonShine UI Component**, NOT a Field
- It's designed to be placed on resource index pages
- Uses Alpine.js for reactivity
- FullCalendar.js for calendar functionality
- MoonShine.request() for async HTTP

### Key Patterns
1. **Resource Integration**: Calendar integrates with existing ModelResource
2. **Modal Editing**: Uses MoonShine's editInModal for event editing
3. **Async Loading**: Events load dynamically based on visible date range
4. **Event Refresh**: Calendar refetches after CRUD operations

### API Endpoints (to be implemented)
- `GET /resource-uri/api/events` - Fetch events by date range
- `PATCH /resource-uri/api/events/{id}/dates` - Update event dates (for drag & drop)

### Event Data Structure
```php
[
    'id' => int,
    'title' => string,
    'start' => ISO8601 datetime,
    'end' => ISO8601 datetime,
    'color' => string|null,
    'description' => string|null
]
```

### Package Naming
- Namespace: `Warete\MoonShineFullCalendar`
- Package name: `warete/moonshine-fullcalendar-resource`
- View namespace: `moonshine-fullcalendar`
