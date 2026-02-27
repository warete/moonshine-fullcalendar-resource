# Implementation Plan: FullCalendar Create Modal Prefill From Grid Click

Branch: codex/feature/calendar-create-modal-prefill
Created: 2026-02-27

## Settings
- Testing: yes
- Logging: verbose
- Docs: no

## Goal

Добавить в пакет поведение, при котором клик по свободной ячейке календарного грида (и, при включенной selection, выбор диапазона) открывает стандартную async create-модалку MoonShine для ресурса с уже предзаполненными значениями `start` и `end`.

Ключевые условия:
- использовать стандартный MoonShine flow `createInModal`, без собственной кастомной формы;
- для `FullCalendarResource` дефолтный `createInModal` должен быть `true`, но пользовательский ресурс может это переопределить;
- предзаполнение должно проходить через стандартный request-based механизм MoonShine, а не через отдельный JS store.

## Confirmed MoonShine Pattern

По коду MoonShine в `vendor/moonshine/moonshine` уже есть нужная механика:
- `CreateButton::for()` строит async URL формы с `_component_name` и `_async_form`.
- `ActionButton` в async-режиме берет URL из `href` на момент клика, значит URL можно подготовить/изменить перед программным `.click()`.
- Поля формы читают значения из request по именам полей через `FormElement::getRequestValue()`.
- При сохранении `ModelResource::fieldApply()` применяет эти request values в модель.

Следствие для реализации:
- достаточно открыть обычную create-модалку по URL, в который добавлены query-параметры, совпадающие с именами колонок ресурса (`startColumn`, `endColumn`);
- safest path: отрендерить скрытую стандартную create-кнопку внутри компонента календаря, перед кликом подменять ей `href` на URL с `start`/`end`, затем вызывать `.click()`.

## Proposed Design

- В `FullCalendarResource`:
  - включить `createInModal` по умолчанию;
  - добавить backend-хелпер, который отдает календарю базовый async create URL и метаданные для открытия create-модалки;
  - добавить хук/нормализацию для предзаполнения дат, чтобы фронтенд не захардкодил названия параметров и семантику диапазона.

- В UI-компоненте календаря:
  - добавить скрытый create trigger, построенный стандартной create-кнопкой MoonShine;
  - передавать во view/backend config флаг доступности create flow.

- Во frontend (`resources/js/full-calendar.js`):
  - на `dateClick` и `select` собирать `start`/`end`;
  - формировать create URL с query params;
  - программно нажимать скрытую async create-кнопку;
  - если create flow выключен (`createInModal=false` или нет прав), ничего не открывать и логировать причину.

## Tasks

### Phase 1: Backend contract for create-from-calendar

1. Enable create modal by default in the calendar resource and expose create-flow metadata
   - Files: `src/Resources/FullCalendarResource.php`
   - Deliverable:
     - set `protected bool $createInModal = true;` in `FullCalendarResource`;
     - add a dedicated calendar create config payload (for example `createFromGrid`) returned from `getCalendarConfig()` with:
       - `enabled` flag based on create ability + `isCreateInModal()`,
       - base async create form URL,
       - modal name (`resource-create-modal` unless customized),
       - request param keys for `start` and `end` derived from `startColumn` / `endColumn`;
     - keep this override-friendly for consumer resources.
   - Logging requirements:
     - `debug` when building create config payload;
     - `info` when create-from-grid is enabled/disabled with reason (`createInModal`, missing form page, permission);
     - `error` only if URL generation fails.
   - Dependency notes: foundation for frontend and Blade wiring.

2. Add resource-side normalization for clicked/selected calendar ranges before sending them into query params
   - Files: `src/Resources/FullCalendarResource.php`
   - Deliverable:
     - add a small resource API (for example `normalizeCalendarCreateRangeForForm(array $payload): array`) that documents and normalizes the frontend contract;
     - explicitly define range semantics as `[start, end)` (exclusive `end`), matching FullCalendar selection behavior;
     - ensure single-cell/date click still provides a sensible default `end` (for example use FullCalendar-provided selection end when available, otherwise derive it predictably and document it);
     - keep timezone assumptions explicit: UI sends/displays calendar timezone, DB save still uses normal form submit pipeline.
   - Logging requirements:
     - `debug` for raw incoming range payload and normalized query values;
     - `info` for final emitted `start`/`end` defaults;
     - `warning` for invalid or incomplete payload that disables create.
   - Date/time acceptance criteria:
     - include one concrete sample in comments/logs, e.g. `2026-02-27T10:00:00` to `2026-02-27T11:00:00`;
     - document that `end` remains exclusive from calendar selection.
   - Dependency notes: depends on Task 1.

### Phase 2: Component and Blade integration

3. Render a hidden standard MoonShine async create button inside the calendar component
   - Files: `src/Components/FullCalendarComponent.php`, `resources/views/components/full-calendar.blade.php`
   - Deliverable:
     - build and pass a hidden create trigger/button using the resource’s standard create button flow, not a custom modal implementation;
     - ensure the teleported modal template for `resource-create-modal` is present in DOM when create-from-grid is enabled;
     - expose the trigger in the Blade component via a stable `x-ref` so frontend code can update `href` and call `.click()`;
     - do not duplicate visible UI controls if the page already renders the normal create button elsewhere.
   - Logging requirements:
     - `debug` when the component decides whether to render the hidden trigger;
     - `info` with trigger enabled state and modal name;
     - `warning` if create flow is expected but the trigger cannot be rendered.
   - Runtime instrumentation requirements:
     - verify the modal template exists only once per render cycle;
     - avoid duplicate teleported modal templates across repeated refreshes.
   - Dependency notes: depends on Tasks 1-2.

### Phase 3: Frontend create-on-grid interaction

4. Implement date-click and date-range-select handlers that open the hidden async create trigger with prefilled query params
   - Files: `resources/js/full-calendar.js`
   - Deliverable:
     - replace current `window.moonshineFullCalendarDateClick` / `DateSelect` no-op hook behavior with built-in create-from-grid flow;
     - on `dateClick`, build a normalized create payload and update the hidden trigger URL before clicking it;
     - on `select`, do the same using the exact selected `[start, end)` range, then call `calendar.unselect()`;
     - preserve existing global callbacks as extension hooks after or around the built-in flow (document the order explicitly).
   - Logging requirements:
     - `debug` for raw FullCalendar callback payloads;
     - `info` when create modal launch is attempted, including `start`, `end`, `allDay`, `resourceUri`, `finalUrl`;
     - `warning` when flow is skipped because create config/trigger is unavailable;
     - `error` if URL build or trigger click fails.
   - Event contract verification:
     - verify `dateClick` payload shape vs `select` payload shape;
     - ensure the hidden trigger uses the latest `href` before `.click()` so MoonShine async request reads the updated URL.
   - Date/time acceptance criteria:
     - preserve exact ISO timestamps sent by FullCalendar;
     - for selection, keep exclusive `end` intact;
     - for single click, use the normalized fallback from Task 2 and log which strategy was used.
   - Dependency notes: depends on Tasks 1-3.

5. Add regression guards for repeated modal opens and disabled-create scenarios
   - Files: `resources/js/full-calendar.js`, `resources/views/components/full-calendar.blade.php`
   - Deliverable:
     - ensure repeated click -> close -> click cycles reuse the same hidden trigger/modal without stale URLs or duplicated modal templates;
     - ensure behavior is a no-op when `createInModal` is overridden to `false`;
     - ensure the event actions dropdown and existing event-click flows are not affected by the new date-click logic.
   - Logging requirements:
     - `info` for each create-open attempt sequence and modal reuse path;
     - `debug` for DOM checks on hidden trigger/template presence;
     - `warning` when duplicate modal templates are detected and cleaned up (if cleanup is needed).
   - Regression-cycle checks:
     - repeated open/close/open on the same day cell;
     - open by `dateClick`, then by `select`, then by `dateClick` again;
     - verify no interaction regression for event dropdown and `fullcalendar:refresh`.
   - Dependency notes: depends on Task 4.

### Phase 4: Verification and shipped assets

6. Add automated coverage for backend config/render contract and rebuild shipped frontend asset
   - Files: `tests/Feature/*` (new tests), optionally `tests/TestCase.php`, `resources/js/full-calendar.js`, `public/js/full-calendar.js`, `public/manifest.json`
   - Deliverable:
     - add PHP-level tests that verify:
       - `FullCalendarResource` exposes create-from-grid config with expected defaults;
       - `createInModal` defaults to enabled in the package resource base;
       - rendered component includes the hidden create trigger only when create-from-grid is enabled;
     - rebuild the frontend bundle so the committed `public/js/full-calendar.js` matches `resources/js/full-calendar.js`;
     - verify the package-consumed asset path stays in sync.
   - Logging requirements:
     - use deterministic fixtures and explicit assertions rather than log assertions;
     - note build step and artifact paths in implementation logs/comments if build tooling surfaces them.
   - Asset verification/publish step:
     - confirm the new handler logic is present in the built asset;
     - avoid shipping backend changes with stale `public/js/full-calendar.js`.
   - Dependency notes: depends on Tasks 1-5.

## Commit Plan

- **Commit 1** (after tasks 1-3): `feat(calendar): add create-from-grid modal contract`
- **Commit 2** (after tasks 4-6): `feat(calendar): open create modal from grid click with prefilled dates`

## Risks / Open Questions

- Нужно подтвердить желаемый fallback для `dateClick`, когда FullCalendar не дает `end`:
  - использовать точку-времени (`end = start`),
  - или вычислять минимальный интервал на основе текущего view/slot.
  План закладывает централизованную нормализацию в ресурсе, чтобы это не зашивать в JS.

- Если пользовательский ресурс переопределяет `createInModal = false`, новое поведение должно тихо отключаться, а не пытаться открыть обычную create-страницу.

- Если потребитель уже использует `window.moonshineFullCalendarDateClick` / `DateSelect`, нужно сохранить расширяемость без ломающего изменения порядка вызовов.

## Next Steps

- Run `/aif-implement` to execute this plan on `codex/feature/calendar-create-modal-prefill`.
