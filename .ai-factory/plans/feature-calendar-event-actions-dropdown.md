# Plan: Calendar Event Actions Dropdown

- Branch: `feature/calendar-event-actions-dropdown`
- Created: `2026-02-26`
- Type: `feature`

## Settings

- Testing: `no` (explicitly skipped by user)
- Logging: `standard` (`INFO` for key events, `WARN/ERROR` for failures)
- Docs: `yes`

## Context (Refined)

- Event JSON is produced in `src/Resources/FullCalendarResource.php` via `getCalendarItems()` -> `formatEvent()`.
- Events endpoint is `src/Http/Controllers/FullCalendarEventsController.php` and currently returns array JSON consumed directly by FullCalendar (`fetchEvents` expects `Array.isArray(data)`).
- Frontend click handling exists in `resources/js/full-calendar.js` (`handleEventClick`) and currently redirects/calls global callback.
- Calendar blade wrapper is `resources/views/components/full-calendar.blade.php`; root container is already `relative`, suitable for absolute-position dropdown overlay.
- MoonShine `EditButton` / `DeleteButton` are available in vendor and support async flows; `EditButton` uses modal async when resource has `protected bool $editInModal = true`.
- Base resource currently dispatches calendar refresh only for save (`modifySaveResponse()`), not destroy (`modifyDestroyResponse()`), which is a gap for async delete from dropdown.
- Package runtime loads compiled assets from `public` (`/vendor/moonshine-fullcalendar/js/full-calendar.js`), so source changes in `resources/*` require rebuild verification.

## Tasks

### Phase 1: Backend Event Actions Contract (Resource API)

- [x] Task 1: Define stable per-event actions payload contract in `src/Resources/FullCalendarResource.php`.
  Deliverable: extend formatted event payload with a versioned/stable actions structure (prefer nested under `extendedProps`, e.g. `extendedProps.moonshineFullCalendar.actions`) containing rendered HTML and metadata (`hasActions`, `count`) without breaking existing FullCalendar keys.
  Files: `src/Resources/FullCalendarResource.php`.
  Requirements:
  - Specify exact payload keys and shape (no ambiguous “top-level or extendedProps” contract).
  - Preserve backward compatibility for consumers ignoring unknown keys.
  - Keep JSON payload valid for current `resources/js/full-calendar.js` `Array.isArray(data)` contract.
  Logging (standard):
  - `INFO` batch summary for actions payload generation (`resource`, `events_count`, `events_with_actions`).
  - `WARN` when payload assembly is skipped for a model due to unsupported action output.
  - `ERROR` on unexpected exceptions while enriching event payload.
  Dependency notes: foundation for all frontend dropdown tasks.

- [x] Task 2: Implement default + custom action composition helpers with proper item context in `src/Resources/FullCalendarResource.php`.
  Deliverable: add helper methods for default actions (`edit`, `delete`), custom per-event actions hook, and HTML rendering; bind each action to current model using MoonShine item context (`setItem(...)`, `ModelDataWrapper`, `setData(...)`) so `canSee`, URL closures, and permissions resolve correctly.
  Files: `src/Resources/FullCalendarResource.php`.
  Requirements:
  - Default actions must include resource `edit` and `delete`.
  - Add resource override hook for custom per-event action buttons/components.
  - Normalize all emitted action buttons to async (`ActionButton::async(...)` when not already async), while preserving modal behavior for edit button.
  - Document override signature and expected return types in PHPDoc.
  - Render action HTML as string safely (`render()`/`__toString()`), filtering empty/hidden buttons.
  Logging (standard):
  - `INFO` per-item composition summary (`item_id`, `default_count`, `custom_count`, `rendered_count`).
  - `WARN` when custom hook returns unsupported value type or non-renderable entry.
  - `WARN` when edit form page/action unavailable and default edit is omitted.
  Dependency notes: depends on Task 1 payload shape and unblocks frontend tasks.

- [x] Task 3: Add async destroy refresh support in `src/Resources/FullCalendarResource.php`.
  Deliverable: implement `modifyDestroyResponse()` (and align behavior with `modifySaveResponse()`) to dispatch `fullcalendar:refresh` for the current resource after async delete from dropdown.
  Files: `src/Resources/FullCalendarResource.php`.
  Requirements:
  - Reuse existing refresh event payload format (`resource` key) for compatibility with current JS listener.
  - Keep behavior safe for non-calendar contexts (no exceptions if event dispatch enrichment fails).
  - Optionally align `modifyMassDeleteResponse()` only if in scope and low-risk; do not expand scope beyond calendar event actions.
  Logging (standard):
  - `INFO` when destroy refresh event is appended (`resource`).
  - `ERROR` if refresh event injection fails.
  Dependency notes: depends on Task 2 default delete action usage; required for correct async delete UX.

### Phase 2: Frontend Dropdown Behavior (JS State + Events)

- [x] Task 4: Implement dropdown state and event-click behavior in `resources/js/full-calendar.js`.
  Deliverable: replace direct redirect-on-click with dropdown open/close flow anchored to clicked event element, reading the stable backend actions payload contract.
  Files: `resources/js/full-calendar.js`.
  Requirements:
  - Parse actions data from the exact payload key defined in Task 1.
  - Use `info.jsEvent.preventDefault()` and prevent unwanted navigation when dropdown actions exist.
  - Position dropdown via event element bounding rect + calendar container offsets (`position: absolute` inside component root).
  - Support outside click, Escape, repeated click toggle, and switching to another event.
  - Preserve fallback behavior (existing callback / URL navigation) when no actions payload exists.
  - Close/reset dropdown state on `refetchEvents`, `datesSet`, and component teardown to avoid stale anchors.
  Runtime instrumentation (frontend required):
  - `INFO` on event click (`eventId`, `hasActions`, `actionsCount`, payload keys).
  - `INFO` on dropdown open/close with coordinates and reason.
  - `WARN` if payload says actions exist but HTML is empty/missing.
  Event contract verification:
  - Log payload shape read path once per click and listener target (`eventClick`, document/window close listeners).
  Dependency notes: blocked by Task 2 (contract + render format stabilized).

- [x] Task 5: Add dropdown DOM container and Alpine-compatible state bindings in `resources/views/components/full-calendar.blade.php`.
  Deliverable: add absolute-position dropdown container inside the calendar root with x-bind/x-show hooks for JS state, safe hidden mode, and MoonShine-friendly styling.
  Files: `resources/views/components/full-calendar.blade.php`.
  Requirements:
  - Container must not interfere with calendar layout when hidden.
  - Must support keyboard focus and close interactions (`Escape`, outside click strategy from JS).
  - Render injected action HTML in a dedicated inner node (for later Alpine re-init step).
  - Styling should work in MoonShine light/dark themes with appropriate z-index over calendar events.
  Runtime instrumentation (frontend required):
  - `INFO` log when dropdown host is mounted and content target is found.
  Dependency notes: blocked by Task 2 (payload/render assumptions).

- [x] Task 6: Re-initialize Alpine/MoonShine behavior for injected ActionButton HTML in `resources/js/full-calendar.js`.
  Deliverable: after injecting `ActionButton` HTML (via `x-html` or direct DOM insertion), explicitly initialize Alpine on the dropdown subtree so async MoonShine buttons (`x-data=\"actionButton\"`, async attrs) work correctly.
  Files: `resources/js/full-calendar.js` (and `resources/views/components/full-calendar.blade.php` if needed for refs/hooks).
  Requirements:
  - Detect dropdown content node and run safe Alpine init (`Alpine.initTree(...)` or equivalent available API) after HTML update.
  - Avoid duplicate handler initialization on repeated opens (clear node / idempotent init strategy).
  - Log and degrade gracefully if Alpine re-init API is unavailable.
  Runtime instrumentation (frontend required):
  - `INFO` on HTML injection + Alpine subtree init success (`eventId`, `actionsCount`).
  - `WARN` on missing dropdown content node.
  - `ERROR` on init failure with exception context.
  Dependency notes: blocked by Tasks 4-5.

### Phase 3: Resource Modal Edit Support and Integration Hardening

- [x] Task 7: Add/clarify base resource support for async modal edit flow in `src/Resources/FullCalendarResource.php`.
  Deliverable: base package resource API/comments/docs-in-code clarifying opt-in modal edit (`protected bool $editInModal = true`) and ensuring default edit action respects modal async path when enabled.
  Files: `src/Resources/FullCalendarResource.php`.
  Requirements:
  - Do not force `editInModal = true` globally if BC risk is non-trivial.
  - Ensure default edit action generation logs whether modal mode is active.
  - Confirm compatibility with existing `modifySaveResponse()` refresh flow.
  Logging (standard):
  - `INFO` when composing edit action (`resource`, `editInModal`, `isAsync`).
  - `WARN` if edit action skipped due to missing form page/permissions.
  Dependency notes: depends on Task 2 helper architecture.

- [x] Task 8: Validate end-to-end integration and payload compatibility across backend/controller/frontend.
  Deliverable: integration pass confirming events endpoint still returns valid FullCalendar array payload, per-event actions HTML arrives, dropdown works, and refresh closes/reloads correctly after edit/delete.
  Files: `src/Http/Controllers/FullCalendarEventsController.php`, `src/Resources/FullCalendarResource.php`, `resources/js/full-calendar.js`, `resources/views/components/full-calendar.blade.php`.
  Requirements:
  - Keep existing date-range semantics `[start, end)` unchanged.
  - Verify one concrete sample event payload contains standard keys plus actions payload.
  - Verify dropdown closes on calendar refresh and does not survive stale event DOM.
  - Verify async edit/delete flows refresh the current calendar instance via `fullcalendar:refresh`.
  Runtime instrumentation / verification:
  - `INFO` fetched count vs events with actions count.
  - Manual verification checklist with one concrete day/range and click scenario.
  Dependency notes: depends on Tasks 3, 4, 5, 6, 7.

### Phase 4: Build / Publish Artifacts

- [x] Task 9: Rebuild compiled assets and verify publishable output in `public/`.
  Deliverable: run Vite build and verify generated package artifacts expected by consumer publish path (`public/js/full-calendar.js`, `public/css/full-calendar.css`, `public/manifest.json`) reflect dropdown/actions changes.
  Files: `resources/js/full-calendar.js`, `resources/views/components/full-calendar.blade.php`, `public/js/full-calendar.js`, `public/css/full-calendar.css`, `public/manifest.json`.
  Requirements:
  - Build with project command (`npm run build` / `vite build`).
  - Confirm `FullCalendarServiceProvider` publish mapping still points to correct compiled assets.
  - Note any generated file diffs that must be included in commit.
  Logging (standard):
  - No new runtime logs; capture build verification outcome in implementation notes/terminal.
  Asset verification:
  - Explicitly confirm compiled JS includes dropdown logic and Alpine re-init path.
  Dependency notes: depends on Tasks 4-6 and should run before docs finalization.

### Phase 5: Documentation Update

- [x] Task 10: Update package docs/examples for event actions dropdown and custom per-event actions.
  Deliverable: docs describing backend extension hooks, payload contract, default edit/delete behavior, async requirement, Alpine injection caveat, and enabling `editInModal` on consumer resource.
  Files: `README.md`, `docs/project-description.md` (if contract docs belong there), optionally `AGENTS.md` only if project structure changes.
  Requirements:
  - Include example override for custom per-event actions hook appending custom `ActionButton`.
  - Document exact payload location for actions HTML/meta.
  - Clarify `protected bool $editInModal = true` opt-in on consumer resource.
  - Mention compiled assets rebuild/publish expectation after frontend changes.
  Logging (standard):
  - No runtime logging changes required; mention useful logs for troubleshooting (`INFO` actions/dropdown/refresh).
  Dependency notes: depends on Task 9 (artifacts built and behavior finalized).

## Dependencies

- Task 2 blocked by Task 1
- Task 3 blocked by Task 2
- Task 4 blocked by Task 2
- Task 5 blocked by Task 2
- Task 6 blocked by Tasks 4, 5
- Task 7 blocked by Task 2
- Task 8 blocked by Tasks 3, 4, 5, 6, 7
- Task 9 blocked by Tasks 4, 5, 6
- Task 10 blocked by Task 9

## Commit Plan

1. After Tasks 1-3: `feat(calendar): add per-event action payload and async delete refresh`
2. After Tasks 4-6: `feat(calendar): add event action dropdown with alpine reinit`
3. After Tasks 7-8: `feat(calendar): harden calendar action integration and modal edit flow`
4. After Tasks 9-10: `docs(calendar): document event action dropdown contract and rebuild assets`

## Notes / Risks (Refined)

- Main risk is rendering MoonShine `ActionButton` HTML outside standard list/table context; implementation must set item context (`setItem`, `ModelDataWrapper`, `setData`) before rendering.
- Injected HTML with Alpine directives may require explicit `Alpine.initTree(...)`; otherwise async MoonShine buttons can render but not function.
- Async delete needs explicit refresh event dispatch (`modifyDestroyResponse`) or the calendar will display stale items after deletion.
- Compiled assets are consumed from published `public` artifacts, so source-only changes are insufficient for package delivery.

## Next Step

- Run `/aif-implement` on this branch to execute the refined plan with settings: `tests=no`, `logging=standard`, `docs=yes`.
