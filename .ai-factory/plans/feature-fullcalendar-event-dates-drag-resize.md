# Plan: FullCalendar Drag&Drop / Resize Event Dates

Created: 2026-02-26
Mode: fast

## Settings

- Testing: Yes (backend contract + resource delegation unit/feature coverage)
- Logging: Verbose (`debug`/`info` around drag/resize request lifecycle)
- Docs: No (skip for this plan unless requested)

## Goal

Добавить стандартное FullCalendar поведение `eventDrop` и `eventResize` в UI календаря так, чтобы после завершения действия:

- отправлялся async-запрос в backend;
- обновлялись даты события в БД;
- возвращался стандартный MoonShine toast (`Saved`);
- календарь обновлялся (через существующий механизм `fullcalendar:refresh` events в `JsonResponse`);
- пакет **не содержал свою бизнес-логику сохранения модели**, а вызывал метод ресурса (`FullCalendarResource` / пользовательский ресурс).

## Proposed Design (for implementation)

- Frontend (`resources/js/full-calendar.js`) обрабатывает `eventDrop`/`eventResize`, формирует payload (`id`, `start`, `end`, `allDay`, `timezone`, `action`), отправляет `PATCH` на новый resource-scoped endpoint.
- Backend дополнительно отдаёт frontend-совместимый endpoint/template для date-update (не собирать URL на клиенте через string hacks из `events` endpoint).
- Новый controller action принимает `CrudRequestContract`, получает `FullCalendarResource` из request и **делегирует** обновление дат в метод ресурса (например `updateCalendarEventDates(...)` / `handleCalendarEventDatesUpdate(...)`).
- `FullCalendarResource` предоставляет расширяемый метод обновления дат:
  - дефолтный путь: вызвать ресурсный save/update pipeline MoonShine (не прямой `Model::save()` в контроллере пакета);
  - потребитель пакета может override-нуть метод под свою модель/валидацию/колонки и собственный save flow.
- Response: `MoonShine\Crud\JsonResponse`
  - `message: __('moonshine::ui.saved')` (стандартный toast MoonShine)
  - `events([...fullcalendar:refresh...])` через существующий helper/hook в ресурсе
  - при ошибке: `modifyErrorResponse(...)` / корректный error JSON с revert на frontend
- Frontend при ошибке вызывает `info.revert()` и логирует payload/response.

## Tasks

### Phase 1: Backend API contract and resource delegation

1. Add resource-scoped PATCH endpoint for calendar date updates
   - Files: `routes/moonshine-fullcalendar.php`, `src/Providers/FullCalendarServiceProvider.php` (only if route registration/signature changes)
   - Deliverable: new route like `PATCH full-calendar/events/{resourceItem}/dates` with MoonShine resource/auth middleware and stable route name; explicitly fix route param names used by controller/resource (`resourceUri`, `resourceItem`).
   - Logging (verbose): log route registration context and request route params (`resourceUri`, `resourceItem`) at `debug`.
   - Dependency notes: none (foundation for frontend + controller).

2. Add backend-provided date-update endpoint metadata/template to calendar config payload
   - Files: `src/Resources/FullCalendarResource.php`
   - Deliverable:
     - expose a stable date-update endpoint/template in `getCalendarConfig()` (for example `eventDateUpdateUrlTemplate` or nested `eventDateUpdate` config with route template + method);
     - avoid client-side URL reconstruction from `events` endpoint path parsing;
     - document placeholder replacement contract in code comments/logs (e.g. `__ID__` token).
   - Logging (verbose): `debug` when generating config keys and route template, include `resourceUri` and route name.
   - Dependency notes: depends on Task 1 route.

3. Add resource extension method for calendar date mutations via MoonShine resource pipeline
   - Files: `src/Resources/FullCalendarResource.php`
   - Deliverable:
     - new public/protected resource API for date updates (e.g. `updateCalendarEventDates(...)`);
     - explicit hook contract for incoming payload normalization (`start`, `end`, `allDay`, `action`, `timezone`) and column mapping;
     - default implementation that resolves item and updates start/end through resource-level save/update flow when safe, or a resource-owned fallback path that remains override-friendly (still no controller-owned DB logic);
     - overridable hooks for extra attributes / all-day normalization / timezone handling.
   - Acceptance notes:
    - Preserve extensibility for consumers that override `startColumn` / `endColumn`.
    - Package default path does not enforce authorization checks; access control stays in consumer app/resource customization if needed.
     - Return `MoonShine\Crud\JsonResponse` with standard `__('moonshine::ui.saved')` toast + `fullcalendar:refresh` event using existing `appendCalendarRefreshEvent(...)`.
     - Error path should integrate with resource error response conventions (or equivalent consistent JSON error contract).
   - Logging (verbose): `debug` before/after normalization, `info` on save success with item id + old/new range, `warning` on invalid end/null semantics, `error` on exception.
   - Dependency notes: depends on Tasks 1-2.

4. Split/extend controller to handle date-update requests and delegate to resource
   - Files: `src/Http/Controllers/FullCalendarEventsController.php` (refactor `__invoke` list action + add dedicated update action/controller), optionally new controller file under `src/Http/Controllers/`
   - Deliverable:
     - backend action that reads request payload (`start`, `end`, `allDay`, `action`, optional timezone), validates minimal contract (`start` required ISO8601, `end` nullable ISO8601, `allDay` boolean, `action` in `drop|resize`);
    - resolves `FullCalendarResource` + target item route param (authorization intentionally not enforced in package controller path);
     - delegates to resource method instead of directly saving model in controller;
     - returns `422` for invalid payload, `403/404` for access/not-found, with JSON error contract compatible with frontend revert flow.
  - Logging (verbose): `info` for lifecycle (`received`, `validated`, `delegated`, `success`), `debug` for normalized payload/timezone and route params, `error` with request identifiers and exception details.
   - Dependency notes: depends on Tasks 1-3.

### Phase 2: Frontend drag/resize async workflow

5. Implement async `eventDrop` / `eventResize` handlers with revert-on-failure
   - Files: `resources/js/full-calendar.js`
   - Deliverable:
     - replace current no-op handlers with shared async update function (`drag` / `resize`);
     - send `PATCH` request to backend endpoint using backend-provided route template/metadata from calendar config (not endpoint string parsing);
     - include CSRF + AJAX headers consistently with existing `fetchEvents()` implementation;
     - on success, apply MoonShine JSON side effects (`message`, `messageType`, `messageDuration`, `events`) through a dedicated adapter that reuses MoonShine runtime when available, with fallback path (`MoonShine.ui.toast` + event dispatch) if not;
     - on failure, call `info.revert()` and expose error state/logs.
   - Runtime instrumentation requirements:
     - log outgoing payload (`eventId`, `action`, `start`, `end`, `allDay`, timezone, endpoint`);
     - log response parse result (`status`, `hasMessage`, `hasEvents`);
     - log revert reason and duplicate-request prevention state if implemented.
   - Event contract verification:
     - verify FullCalendar callback payload differences between `eventDropInfo` and `eventResizeInfo`;
     - ensure endpoint target resolves per resource via config template and item id extraction is stable (`event.id` as string/int).
   - Date/time acceptance criteria:
     - explicit `[start, end)` semantics documented in code comments/logs for resize/drop payload;
     - preserve `null` end when event has no end;
     - include a concrete sample verification in manual checks (e.g. move `2026-02-26T10:00:00` -> `2026-02-26T12:00:00`).
   - Dependency notes: depends on Tasks 1-4.

### Phase 3: Response integration and regression checks

6. Ensure MoonShine JSON response triggers standard toast and calendar refresh after drag/resize
   - Files: `src/Resources/FullCalendarResource.php`, `resources/js/full-calendar.js`
   - Deliverable:
     - backend response shape matches MoonShine async JSON (`message`, `messageType`, optional `events`);
     - frontend request path executes/propagates returned `events` and toast handling via the chosen adapter path (MoonShine runtime reuse vs fallback);
     - no duplicate refresh events or double-refetch after success.
   - Logging (verbose): `info` when refresh event appended from drag/resize flow, `debug` when frontend receives and applies response side effects.
   - Regression-cycle checks:
     - repeated drag -> drag -> resize sequences on same event without stale UI state;
     - ensure dropdown/actions behavior is not broken after drag/resize interactions;
     - confirm no duplicate `fullcalendar:refresh` dispatch/listener side effects (`window` + `document` listener compatibility remains intact).
   - Dependency notes: depends on Tasks 3-5.

7. Add automated tests for backend contract and resource delegation (no browser DnD)
   - Files: `tests/Feature/*` (new feature test), optionally `tests/TestCase.php`, package testing helpers under `src/Testing/*` if required
   - Deliverable:
     - test PATCH endpoint success returns MoonShine-style JSON with standard saved message;
     - test controller delegates to resource method (not direct model save in controller path), preferably via test resource subclass override/hook spy;
     - test invalid payload/error path returns error JSON/status and does not silently mutate data;
     - test not-found path for mismatched `resourceItem`.
   - Logging (verbose during test debug only): assert logs optional, but include deterministic payload fixtures with explicit timestamps/timezone.
   - Date/time acceptance criteria:
     - one test with concrete timestamps validating start/end persistence and end-null handling;
     - timezone assumption stated in test setup (`config('app.timezone')`).
   - Dependency notes: depends on Tasks 1-6.

8. Verify frontend asset build/publish path for shipped calendar JS after drag/resize implementation
   - Files: `resources/js/full-calendar.js`, `public/js/full-calendar.js` (generated artifact if committed), `public/manifest.json` (if build output changes), `vite.config.js` (only if build config fix is needed)
   - Deliverable:
     - build/package step executed for updated frontend asset;
     - verify Blade-loaded asset (`vendor/moonshine-fullcalendar/js/full-calendar.js` publish target) includes drag/resize changes in local package workflow;
     - document/confirm local verification path used during implementation (e.g. compiled `public/js/full-calendar.js` checksum/timestamp changed).
   - Asset verification/publish step:
     - explicitly check that changes in `resources/js/full-calendar.js` are reflected in the built asset consumed by the package UI;
     - avoid shipping backend-only changes with stale frontend bundle.
   - Logging (verbose): note build command and artifact paths checked; log any mismatch between source and compiled asset.
   - Dependency notes: depends on Tasks 5-6.

## Commit Plan

1. `feat(calendar): add backend endpoint and resource delegation for event date updates`
   - Includes Tasks 1-4
2. `feat(calendar): implement drag and resize async updates in fullcalendar ui`
   - Includes Tasks 5-6
3. `test(calendar): cover event date update endpoint and error handling`
   - Includes Task 7
4. `build(calendar): publish updated fullcalendar frontend bundle`
   - Includes Task 8

## Risks / Open Questions (to confirm before implementation)

- Какой именно ресурсный метод MoonShine лучше дергать для date-only update:
  - напрямую `save(...)` через `DataWrapper`/caster (более “нативно”, но требует аккуратной подготовки полей),
  - или отдельный overridable hook в `FullCalendarResource`, который по умолчанию использует `save(...)`.
  План предполагает второй вариант как более безопасный и расширяемый для библиотеки.
- Нужно ли поддерживать all-day преобразования (`00:00:00` normalization) в дефолтной реализации, или оставить это целиком на override в пользовательском ресурсе.
- Если `MoonShine.request()` не исполняет `events`/toast для произвольных PATCH JSON ответов в этом контексте, в реализации потребуется fallback через direct `fetch` + ручное применение MoonShine response handler.
- Нужен выбор дефолтной стратегии применения MoonShine JSON side-effects в `fetch`-ответе:
  - reuse vendor runtime helper (предпочтительно, если доступен из глобального API),
  - fallback adapter в пакете (`toast + events dispatch`) без дублирования всего `Request/Core`.

## Next Steps

- Run `/aif-implement` to execute this plan.
- If you want, I can regenerate this as a `full` plan with a feature branch and your preferences (tests/logging/docs).
