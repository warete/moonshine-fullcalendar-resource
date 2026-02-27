# Plan: FullCalendar MoonShine Theme (Tailwind 4 Tokens)

- Branch: `codex/feature-fullcalendar-moonshine-theme`
- Created: `2026-02-26`
- Type: `enhancement`

## Settings

- Testing: `no` (explicitly skipped by user)
- Logging: `standard` (`INFO` for key lifecycle checks, `WARN/ERROR` for failures)
- Docs: `no` (explicitly skipped by user)

## Goal

Сделать полноценную тему FullCalendar под MoonShine v4 с использованием токенов/переменных MoonShine (`--color-*`, `--ms-*`) так, чтобы она:

- корректно работала в светлой и тёмной темах;
- автоматически подхватывала смену palette в layout (без хардкода конкретных цветов);
- покрывала не только FullCalendar UI, но и package-specific UI (`dropdown`, `loading`, `error`);
- не держала inline-стили в Blade-компоненте (вынести в отдельный подключаемый CSS-ассет, аналогично JS).

## Context (Refined)

- Сейчас в `resources/views/components/full-calendar.blade.php` есть большой inline `<style>` с базовыми стилями календаря/overlay/dropdown и частичной темизацией FullCalendar.
- Компонент уже подключает JS-ассет через `@push('scripts')`, но CSS-ассет аналогично не подключается.
- Есть compiled CSS `public/css/full-calendar.css`, но он минимальный и не покрывает большинство элементов FullCalendar.
- Сборка идёт через `vite.config.js`; CSS может публиковаться в `public/css/full-calendar.css` через import в entry (`resources/js/register.js`) или отдельный вход.
- MoonShine v4 использует Tailwind 4 + CSS tokens (`--color-primary`, `--color-base-*`, `--color-base-stroke`, `--color-*-text`, `--ms-btn-*`, `--ms-table-*`), что идеально подходит для palette-aware темы.
- FullCalendar v6.1.20 поддерживает theming через CSS custom properties `--fc-*` + точечные class overrides (`.fc-*`).
- Текущая интеграция ассетов обновлена: CSS/JS регистрируются через MoonShine component `assets()` (в PHP-компоненте), а не через Blade `@push(...)`, чтобы CSS гарантированно попадал в `<head>`.
- После первичной реализации тема технически подключается и применяется, но визуальное качество не устраивает пользователя; требуется отдельная дизайн-итерация с reference-based подходом (FlyonUI as inspiration, no direct dependency/copy).

## FullCalendar UI Elements Inventory (What Should Be Themed)

### Core / Shared

- Calendar root/container (`.fc`, `.fc-theme-standard`)
- Page/surface backgrounds (`scrollgrid`, sticky headers, popover)
- Borders/grid lines (`.fc-scrollgrid`, `td`, `th`)
- Neutral states / disabled cells / non-business hours
- Text colors (primary/secondary/muted)
- Focus / selection / highlight / selected overlay
- Today highlight and now indicator line

### Toolbar

- Toolbar wrapper spacing + title (`.fc-toolbar`, `.fc-toolbar-title`)
- Button group container seams
- Buttons: default / hover / active / disabled / focus ring
- Nav buttons + view switch buttons + today button

### DayGrid (month)

- Day headers (`Mon`, `Tue`, ...)
- Day numbers
- Current-month vs other-month cells
- `+N more` links
- “today” cell background
- Day cell hover affordance (optional)

### TimeGrid (week/day)

- Time-axis labels
- Slot lane borders
- Column headers and sticky sections
- All-day row label/background
- Current-time indicator line
- Selection highlight

### List View

- List day headers
- List row hover
- Event dot
- Empty state surface/text

### Events

- Event pill/block base colors (default event)
- Event text color
- Event hover/selected overlays
- Resize handles / drag feedback
- Event-specific custom colors (preserve payload `color`, but ensure readable borders/text fallback)

### Package-specific overlays (already present in Blade)

- Loading overlay background/spinner/text
- Error overlay card/background/border/text/icon
- Event actions dropdown shell/content/shadow/border
- Dropdown action button spacing compatibility with MoonShine buttons

## Color Mapping Proposal (MoonShine Tokens -> FullCalendar)

Ниже базовая карта, построенная на токенах MoonShine, чтобы автоматически реагировать на dark mode и palette switch.

### Recommended Variant A (Balanced, MoonShine-native)

- `--fc-page-bg-color` -> `var(--color-base)`
- `--fc-border-color` -> `var(--color-base-stroke)`
- `--fc-neutral-bg-color` -> `var(--color-base-100)` (для disabled/secondary surfaces)
- `--fc-neutral-text-color` -> `var(--color-base-text)` с пониженной opacity / color-mix
- `--fc-button-text-color` -> `var(--color-primary-text)`
- `--fc-button-bg-color` -> `var(--color-primary)`
- `--fc-button-border-color` -> `var(--color-primary)`
- `--fc-button-hover-bg-color` -> mix(`--color-primary`, `--color-base`, accent +10-15%)
- `--fc-button-hover-border-color` -> same as hover bg
- `--fc-button-active-bg-color` -> slightly stronger primary mix
- `--fc-button-active-border-color` -> same as active bg
- `--fc-event-bg-color` -> `var(--color-primary)`
- `--fc-event-border-color` -> `var(--color-primary)`
- `--fc-event-text-color` -> `var(--color-primary-text)`
- `--fc-more-link-bg-color` -> `var(--color-base-200)`
- `--fc-more-link-text-color` -> `var(--color-base-text)`
- `--fc-highlight-color` -> alpha(`--color-info`, ~18-24%)
- `--fc-today-bg-color` -> alpha(`--color-primary`, ~8-14%)
- `--fc-now-indicator-color` -> `var(--color-error)`
- `--fc-list-event-hover-bg-color` -> `var(--color-base-100)`
- `--fc-bg-event-color` -> `var(--color-info)`
- `--fc-non-business-color` -> alpha(`--color-base-text`, ~6-10%)

Почему этот вариант хороший:
- выглядит “родным” для MoonShine;
- минимум ручной логики для dark mode;
- palette changes сразу меняют toolbar/events/selection акценты.

### Variant B (Low-Accent / Table-like Admin)

- Все surface/grid/headers как в Variant A
- Toolbar primary buttons -> использовать `--ms-btn-*` (`--ms-btn-bg-color`, `--ms-btn-border-color`, hover from `--ms-btn-hover-*`)
- View active button -> `--color-primary`
- Default events -> `--color-info` / `--color-info-text` вместо primary
- Today bg -> `var(--color-base-100)` + primary border accent

Когда выбирать:
- если календарь должен визуально быть ближе к таблицам/формам MoonShine и меньше доминировать цветом.

### Variant C (High-Accent / Scheduling-first)

- Toolbar buttons + events -> `--color-primary`
- Today bg -> alpha(`--color-primary`, ~14-20%)
- Current time line -> `--color-error`
- Selection -> alpha(`--color-primary`, ~18-24%)
- List/daygrid hover -> alpha(`--color-primary`, ~4-8%)

Когда выбирать:
- если календарь является центральным экраном и нужен более выраженный визуальный фокус.

### Reference Style Constraints (FlyonUI-inspired, adapted)

Использовать как визуальные ориентиры (не прямое копирование):

- Плотность интерфейса: более компактные toolbar controls и event pills, чем в текущем варианте, но без потери читаемости.
- Радиусы: единая шкала радиусов (buttons/cards/popovers/events), ближе к современному UI kit feel.
- Бордеры: мягкий, но читаемый контур сетки и секций (не слишком “жесткий” table look).
- Поверхности: ясное разделение page/grid/header/popover слоев через тональные отличия, а не только border.
- Состояния: hover/active/focus должны быть заметны, но не доминировать над event content.
- Тени: мягкие и короткие для popover/dropdown, без тяжелого “floating glass” эффекта по умолчанию.

## UI Element -> Token Mapping (Detailed)

- Calendar surface (`.fc`, `.fc-scrollgrid`, sticky headers): `--color-base`, `--color-base-50`, `--color-base-stroke`
- Header/day labels (`.fc-col-header-cell-cushion`, time axis): `--color-base-text` (muted via opacity / mix)
- Day numbers / titles: `--color-base-text`
- Toolbar title: `--color-base-text`
- Toolbar button default/hover/active/focus: `--color-primary`, `--color-primary-text`, focus ring from `--ms-form-focus-ring-color` or `--color-primary`
- Secondary button-like seams/group dividers: `--color-base-stroke`
- Today cell/column: `--color-primary` (alpha background) + optional inset outline using `--color-primary`
- Selected range / drag selection: `--color-info` or `--color-primary` (alpha overlay)
- Non-business shading / disabled dates: `--color-base-300` or alpha(`--color-base-text`)
- “More” links/popover surfaces: `--color-base-100` + `--color-base-text` + `--color-base-stroke`
- Now indicator line: `--color-error`
- Default events: `--color-primary` / `--color-primary-text`
- Background events (availability blocks): `--color-info` or `--color-success` with alpha
- Loading overlay: surface alpha from `--color-base` + spinner `--color-primary`
- Error overlay card: `--color-error`, `--color-error-text`, plus `--color-base`-derived background when needed
- Event actions dropdown: `--color-base`, `--color-base-stroke`, shadow tuned for both themes; buttons inherit MoonShine native button styles

## Implementation Tasks

### Phase 1: Theme Architecture + Asset Wiring

- [x] Task 1: Define theme scope, CSS file placement, and asset loading strategy for FullCalendar styles.
  Deliverable: decide and document source file(s) for calendar theme (recommended `resources/css/full-calendar.css` or `resources/css/full-calendar-theme.css`) and how they are included in Vite output (`public/css/full-calendar.css`) plus Blade `@push('styles')` loading.
  Files: `vite.config.js`, `resources/js/register.js`, `resources/views/components/full-calendar.blade.php` (planning target), optionally new `resources/css/full-calendar.css`.
  Requirements:
  - Create a real source CSS entry (`resources/css/full-calendar.css`) and define how it is included in Vite build (recommended import from `resources/js/register.js`) so `public/css/full-calendar.css` is actually regenerated.
  - Preserve existing public output path `public/css/full-calendar.css` used by package publishing.
  - Ensure style asset is loaded by component similarly to JS (script + stylesheet pairing).
  - Keep styles scoped to calendar component wrapper (`.full-calendar-container` / `.ms-full-calendar`) to avoid leaking overrides.
  Logging (standard):
  - `INFO` during implementation to confirm CSS asset registration path and component style push is active.
  - `WARN` if stylesheet is unavailable/missing after build.
  Dependency notes: foundation for all UI theming tasks.

- [x] Task 2: Verify MoonShine style stack support and define fallback stylesheet injection path.
  Deliverable: confirm which Blade stack is available in target MoonShine layout for CSS (`styles`/`head`/other), and document fallback strategy if no style stack is rendered.
  Files: `resources/views/components/full-calendar.blade.php`, MoonShine layout integration points (consumer runtime behavior verification), optionally `src/Components/FullCalendarComponent.php` (if data flag/help text is needed, low priority).
  Requirements:
  - Verify the chosen stack is actually rendered in MoonShine pages used by this component.
  - Define one fallback approach if stack support is absent (e.g., direct `<link>` in component with `@once`).
  - Ensure CSS asset inclusion remains package-safe and does not break multiple calendar instances per page.
  Logging (standard):
  - `INFO` which style stack/fallback path is selected.
  - `WARN` if no stack is available and fallback is required.
  Dependency notes: depends on Task 1 (asset path known) and unblocks Blade integration extraction.

- [x] Task 3: Extract inline styles from `resources/views/components/full-calendar.blade.php` into source CSS file and reduce Blade to structure-only markup.
  Deliverable: remove inline `<style>` block from Blade (except truly component-local one-off styles if any remain justified) and migrate its content into the dedicated CSS asset with equivalent or improved selectors.
  Files: `resources/views/components/full-calendar.blade.php`, `resources/css/full-calendar.css` (new or existing source).
  Requirements:
  - Preserve behavior for min-height, dropdown positioning classes, loading/error overlays, and event hover cursor.
  - Eliminate hardcoded `bg-white/dark:bg-gray-900` dependence where token-based CSS should be used.
  - CSS and JS assets must each be injected only once (`@once` + chosen stack/fallback), even if multiple calendars are rendered.
  - Keep Blade readable and focused on markup/data only.
  Logging (standard):
  - `INFO` verification log (implementation note / JS optional) that inline styles were removed and external stylesheet loaded.
  - `WARN` if visual regressions occur due to style load order.
  Dependency notes: blocked by Tasks 1-2.

### Phase 2: FullCalendar Token Theme (CSS Variables + Shared Overrides)

- [x] Task 4: Define and apply FullCalendar override selector strategy (order/specificity-safe).
  Deliverable: a documented strategy inside the stylesheet/comments and implementation approach that prioritizes `--fc-*` variables and uses scoped class overrides with minimal necessary specificity for styles that variables cannot cover.
  Files: `resources/css/full-calendar.css`.
  Requirements:
  - Prefer `--fc-*` overrides first; use class selectors only for unsupported aspects (layout/typography/specific states).
  - Keep selectors scoped to package wrapper to avoid global collisions.
  - Account for FullCalendar runtime-injected base styles and CSS load order.
  Logging (standard):
  - `INFO` note listing which visual concerns are handled by `--fc-*` vs class overrides.
  - `WARN` if a selector requires unusually high specificity / `!important` (justify and minimize).
  Dependency notes: depends on Task 3 (stylesheet extraction baseline).

- [x] Task 5: Implement scoped FullCalendar CSS variable theme using MoonShine tokens (`--fc-*` overrides).
  Deliverable: comprehensive `--fc-*` mapping in the new stylesheet for shared FullCalendar core variables (page, border, buttons, events, highlights, today, now indicator, list hover).
  Files: `resources/css/full-calendar.css`.
  Requirements:
  - Use MoonShine tokens (`--color-*`, `--ms-*`) instead of hardcoded hex values.
  - Author package CSS as plain CSS (no Tailwind 4 DSL like `@theme`, `@variant`, `--alpha()`), because this package build pipeline currently uses Tailwind 3 and should not depend on MoonShine source preprocessing.
  - Prefer token/mix/alpha-based values so palette switching updates automatically.
  - Maintain compatibility with both light and dark themes without `prefers-color-scheme` fallback hacks if MoonShine tokens already cover it.
  - Do not cache computed theme colors in JS or inline styles; rely on CSS variables so palette switches propagate automatically.
  - Include safe fallbacks only where necessary.
  Logging (standard):
  - `INFO` (optional JS diagnostics) logging computed key CSS variables once on init when debug enabled (`--color-primary`, `--color-base`, `--fc-button-bg-color`).
  - `WARN` if computed token values are empty or invalid at runtime.
  Dependency notes: blocked by Task 4 (override strategy defined).

- [x] Task 6: Add shared class-level overrides for typography, spacing, focus, borders, and sticky surfaces to match MoonShine visual language.
  Deliverable: class-based overrides beyond `--fc-*` for toolbar title sizing/weight, button radius/focus ring, header cell typography, scrollgrid background layering, popover surfaces, and interaction states.
  Files: `resources/css/full-calendar.css`.
  Requirements:
  - Align button feel with MoonShine (`radius`, border, font-weight, hover/focus states).
  - Use MoonShine z-index tokens where relevant (`--z-dropdown`, etc.) or safe scoped values.
  - Ensure accessibility-visible focus states in both themes.
  Logging (standard):
  - `INFO` visual verification checklist item for focus ring visibility and sticky header backgrounds.
  - `WARN` if overriding FullCalendar classes causes layout shifts in any view.
  Dependency notes: depends on Tasks 4-5.

### Phase 3: View-Specific Styling (DayGrid / TimeGrid / List)

- [x] Task 7: Theme DayGrid and TimeGrid view-specific elements with MoonShine tokens.
  Deliverable: styling for day headers, day numbers, today cells/columns, slot labels, lane separators, non-current month days, selection overlays, more-link chips/popovers, and current-time indicator.
  Files: `resources/css/full-calendar.css`.
  Requirements:
  - Validate both month and week/day layouts.
  - Verify `timeGrid` sticky headers, slot label column, and all-day row remain readable and visually separated in dark mode.
  - Keep contrast acceptable when palette primary is very light or very dark.
  - Preserve drag/resize affordances (do not hide resizers/selection feedback).
  Logging (standard):
  - `INFO` manual verification notes for `dayGridMonth`, `timeGridWeek`, `timeGridDay`.
  - `WARN` if `today`/selection styles become indistinguishable under custom palettes.
  Dependency notes: depends on Tasks 5-6.

- [x] Task 8: Theme List view and empty states consistently with MoonShine tokens.
  Deliverable: list day headers, row hover, event dots, empty-state surface/text, and list table borders using MoonShine base/primary/info tokens.
  Files: `resources/css/full-calendar.css`.
  Requirements:
  - Ensure hover/focus states are visible but subtle in both themes.
  - Keep sticky list day headers readable over scroll.
  - Harmonize list surfaces with MoonShine table/card feel.
  Logging (standard):
  - `INFO` manual verification notes for `listWeek` and empty-range state.
  - `WARN` if sticky header or hover background clashes with current palette.
  Dependency notes: depends on Tasks 5-6.

### Phase 4: Package-Specific UI Theme (Overlays / Dropdown)

- [x] Task 9: Theme package overlays and event actions dropdown with MoonShine tokens, replacing hardcoded light/dark utility colors.
  Deliverable: token-driven styles for loading overlay, spinner/text, error overlay card, and dropdown shell/content/shadow that adapt to light/dark/palette changes automatically.
  Files: `resources/css/full-calendar.css`, `resources/views/components/full-calendar.blade.php` (class cleanup if needed).
  Requirements:
  - Remove direct hardcoded color assumptions from Blade classes where CSS theme should own the visuals.
  - Keep dropdown contrast and shadow legibility in dark themes.
  - Preserve existing interaction behavior and z-index layering over calendar events.
  Logging (standard):
  - `INFO` verification notes for dropdown/open states in both themes.
  - `WARN` if dropdown becomes clipped or low-contrast after style changes.
  Dependency notes: depends on Tasks 3, 5, 6, 7, 8.

### Phase 5: Palette/Theme Switch Resilience + Build Verification

- [ ] Task 10: Validate runtime behavior under light/dark mode and palette switching in MoonShine layout.
  Deliverable: manual verification pass confirming no page reload hacks are required and calendar colors update correctly when theme/palette changes (or define minimal hook if runtime recalc is necessary).
  Files: `resources/css/full-calendar.css`, `resources/js/full-calendar.js` (only if runtime hook/observer is actually needed).
  Requirements:
  - Verify at least one light palette and one dark palette.
  - Verify toolbar, grid, events, dropdown, loading/error overlays after palette switch.
  - Verify visual hierarchy quality after redesign: toolbar active state readability, event title readability, grid contrast balance, popover/dropdown consistency.
  - If a runtime issue is found (e.g., cached inline style values), implement minimal observer and keep logs at `INFO/WARN`.
  Runtime instrumentation (frontend required if JS touched):
  - `INFO` on theme/palette change detection and re-application action (if implemented).
  - `WARN` if observer cannot detect layout palette changes reliably.
  Dependency notes: depends on Tasks 3, 5, 6, 7, 8, 9.

- [x] Task 11: Rebuild assets and verify publishable outputs for package consumers.
  Deliverable: run Vite build and confirm `public/css/full-calendar.css`, `public/js/full-calendar.js` (if changed), and `public/manifest.json` are updated and publish mapping remains valid.
  Files: `resources/css/full-calendar.css`, `resources/js/register.js`, `resources/views/components/full-calendar.blade.php`, `public/css/full-calendar.css`, `public/js/full-calendar.js`, `public/manifest.json`.
  Requirements:
  - Confirm compiled CSS contains extracted inline styles and FullCalendar token overrides.
  - Confirm compiled CSS contains the latest redesign selectors/markers introduced in the FlyonUI-inspired pass (not the earlier rejected visual draft only).
  - Confirm component loads compiled stylesheet from published vendor path.
  - Include generated artifact diffs in commit if package expects committed build assets.
  Asset verification:
  - Explicitly inspect compiled CSS for key selectors (`--fc-button-bg-color`, dropdown classes).
  Logging (standard):
  - No new runtime logs; capture build/verification results in terminal notes.
  Dependency notes: depends on final accepted visual theme state (see Phase 6 tasks) in addition to earlier asset pipeline tasks.

### Phase 6: FlyonUI-Inspired Visual Redesign (Reference-Only)

- [x] Task 12: Audit FlyonUI FullCalendar theme as a visual reference and extract reusable patterns.
  Deliverable: inspect FlyonUI FullCalendar styling (reference-only) and document which patterns to emulate in this package (toolbar density, border/surface layering, event chip proportions, popovers, list states) without copying library code or introducing dependency.
  Files: `resources/css/full-calendar.css` (comments/implementation target), implementation notes in task progress, optional code comments in redesigned sections.
  Requirements:
  - Treat FlyonUI as inspiration only; no direct vendor import and no wholesale copy of CSS blocks.
  - Extract design language decisions separately from color system (MoonShine tokens remain source of truth).
  - Identify which current theme areas look visually weak and map them to reference improvements.
  Logging (standard):
  - `INFO` summary of extracted patterns and where they will be applied.
  - `WARN` if any desired FlyonUI effect depends on unavailable markup/classes and needs adaptation.
  Dependency notes: blocks Tasks 13-14.

- [x] Task 13: Redesign FullCalendar core/views visual system using FlyonUI-inspired patterns + MoonShine tokens.
  Deliverable: replace the current visual styling pass for toolbar, shared surfaces, dayGrid/timeGrid/list states, events, popovers, and focus/hover states with a more polished, cohesive theme inspired by FlyonUI structure/spacing.
  Files: `resources/css/full-calendar.css`.
  Requirements:
  - Preserve MoonShine token-based theming and palette-switch behavior.
  - Improve toolbar/button hierarchy, grid contrast, event density, and overall rhythm.
  - Validate at least `dayGridMonth`, `timeGridDay`, `timeGridWeek`, `listWeek` visually (iterative pass may continue in Task 15).
  - Avoid hardcoded brand colors from FlyonUI; map to MoonShine tokens only.
  Logging (standard):
  - `INFO` note for each redesigned area (toolbar/grid/events/list/popover) when implementation reaches stable pass.
  - `WARN` if a reference pattern conflicts with FullCalendar markup constraints.
  Dependency notes: blocked by Task 12 and supersedes the earlier visual draft from Tasks 5-8.

- [x] Task 14: Redesign package overlays/dropdown to match the new calendar visual language.
  Deliverable: align loading overlay, error card, and event-actions dropdown visuals with the FlyonUI-inspired redesign (surface layering, radius, border, shadow, spacing) while preserving MoonShine button behavior/content.
  Files: `resources/css/full-calendar.css`, `resources/views/components/full-calendar.blade.php` (class cleanup only if necessary).
  Requirements:
  - Match visual language from Task 13 (same radius/shadow/spacing cadence).
  - Preserve accessibility and contrast in light/dark themes.
  - Keep dropdown interaction behavior unchanged (styling only unless bug is found).
  Logging (standard):
  - `INFO` verification notes for loading/error/dropdown visual alignment.
  - `WARN` if dropdown clipping/stacking issues appear after redesign.
  Dependency notes: blocked by Task 12 and should follow Task 13 for consistency.

- [x] Task 15: Run iterative visual QA across views/themes/palettes and tune the redesign.
  Deliverable: iterative visual refinement pass on the real MoonShine page with concrete checks and CSS adjustments until the theme is visually acceptable.
  Files: `resources/css/full-calendar.css` (primary), `resources/js/full-calendar.js` only if runtime palette/theme sync issue is discovered.
  Requirements:
  - Verify `dayGridMonth`, `timeGridWeek`, `timeGridDay`, `listWeek`.
  - Verify light mode, dark mode, and at least one palette switch.
  - Verify toolbar active states, event readability (short/long titles), popover/dropdown legibility, and loading/error overlays.
  - Record specific regressions/fixes during iteration (avoid vague “looks better” completion).
  Runtime instrumentation (frontend optional):
  - `INFO` only if JS is touched for palette/theme re-application.
  - `WARN` if a visual issue is caused by runtime layout constraints outside CSS scope.
  Dependency notes: blocked by Tasks 13-14 and should complete before final build verification.

- [x] Task 16: Rebuild assets and verify/publish the final redesigned theme output.
  Deliverable: rebuild compiled assets after the FlyonUI-inspired redesign and verify the published vendor assets reflect the final accepted theme, then re-run runtime checks that CSS/JS load via MoonShine `assets()`.
  Files: `resources/css/full-calendar.css`, `src/Components/FullCalendarComponent.php`, `public/css/full-calendar.css`, `public/js/full-calendar.js`, `public/manifest.json`.
  Requirements:
  - Confirm final compiled CSS includes redesigned selectors and overrides (not stale previous pass).
  - Confirm runtime page loads `/vendor/moonshine-fullcalendar/css/full-calendar.css` and `/vendor/moonshine-fullcalendar/js/full-calendar.js` via MoonShine assets manager.
  - Confirm no Alpine init regression (`fullCalendar is not defined`) after final publish.
  Logging (standard):
  - No new runtime logs required unless JS changes; capture verification outcomes in terminal/devtools checks.
  Dependency notes: blocked by Tasks 13-15 and becomes the new final build checkpoint.

## Dependencies

- Task 2 blocked by Task 1
- Task 3 blocked by Tasks 1, 2
- Task 4 blocked by Task 3
- Task 5 blocked by Task 4
- Task 6 blocked by Tasks 4, 5
- Task 7 blocked by Tasks 5, 6
- Task 8 blocked by Tasks 5, 6
- Task 9 blocked by Tasks 3, 5, 6, 7, 8
- Task 10 blocked by Tasks 3, 5, 6, 7, 8, 9
- Task 11 blocked by Tasks 1-10
- Task 12 blocked by Task 10 completion not required (can start immediately after current baseline is stable)
- Task 13 blocked by Task 12
- Task 14 blocked by Task 12
- Task 15 blocked by Tasks 13, 14
- Task 16 blocked by Tasks 13, 14, 15

## Commit Plan

1. After Tasks 1-3: `refactor(calendar): extract component styles into external css asset`
2. After Tasks 4-6: `feat(calendar): add moonshine token-based fullcalendar core theme`
3. After Tasks 7-9: `feat(calendar): theme fullcalendar views and overlays for moonshine`
4. After Tasks 10-11: `build(calendar): verify palette-aware theme assets and publish output`
5. After Tasks 12-14: `refactor(calendar): redesign theme using flyonui-inspired patterns`
6. After Tasks 15-16: `style(calendar): finalize and verify redesigned moonshine calendar theme`

## Risks / Notes

- FullCalendar class names are stable enough for targeted overrides, but excessive selector specificity may break future upgrades; prefer `--fc-*` first, class overrides second.
- Some visual states (hover/active/focus) may need `color-mix` / alpha helpers; ensure output is acceptable in the current browser support target.
- If MoonShine palettes can produce very low-contrast primary colors, event text contrast may require fallback logic (e.g., preserving event-provided text color or forcing borders).
- Package consumers rely on published compiled assets, so source-only CSS changes are insufficient.
- Reference risk: overfitting to FlyonUI visuals can reduce MoonShine palette compatibility. Mitigation: copy layout/spacing/state ideas, keep all colors on MoonShine tokens.

## Next Step

- Run `/aif-implement` on this branch to execute the FlyonUI-inspired redesign phase with settings: `tests=no`, `logging=standard`, `docs=no`.
