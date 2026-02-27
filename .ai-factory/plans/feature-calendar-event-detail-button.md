# Plan: Calendar Event Detail Button

- Branch: `feature/calendar-event-detail-button`
- Created: `2026-02-27`
- Type: `feature`

## Settings

- Testing: `no` (explicitly skipped by user)
- Logging: `none` (explicitly do not add logs)
- Docs: `conditional` (update existing docs only if implementation creates a mismatch)

## Context

- Per-event action payload is assembled in `src/Resources/FullCalendarResource.php`, primarily via `buildCalendarEventActionsPayload()` and `getDefaultCalendarEventActions()`.
- Current implementation wraps default button generation in multiple `try/catch` blocks, including a silent fallback in `buildCalendarEventActionsPayload()` and silent suppression around edit/delete button creation.
- Local MoonShine source confirms `ModelResource` gets `getCreateButton()`, `getEditButton()`, `getDetailButton()`, and `getDeleteButton()` from `vendor/moonshine/moonshine/src/Crud/src/Traits/Resource/ResourceWithButtons.php`.
- For event-level actions, the usable replacements are `getEditButton()`, `getDetailButton()`, and `getDeleteButton()`. `getCreateButton()` exists, but it is not item-scoped and is already used separately for grid/date creation flows, so it should not be folded into per-event actions.
- Context7 returns generic MoonShine docs only; the local vendor source is the authoritative reference for signatures and expected usage in this repo.
- The worktree was already dirty before planning: `src/Resources/FullCalendarResource.php` has local modifications and must be edited carefully without discarding user changes.

## Tasks

### Phase 1: Refine Default Event Actions API

- [x] Task 1: Rework default calendar event action composition in `src/Resources/FullCalendarResource.php`.
  Deliverable: update `getDefaultCalendarEventActions(Model $item)` so the default action set becomes `detail`, `edit`, `delete`, and the implementation uses resource button helpers instead of direct `EditButton::for(...)` / `DeleteButton::for(...)` calls.
  Files: `src/Resources/FullCalendarResource.php`.
  Requirements:
  - Replace direct factory calls with `$this->getDetailButton()`, `$this->getEditButton(...)`, and `$this->getDeleteButton(...)`.
  - Preserve item binding by continuing to wrap the model with `ModelDataWrapper` and applying `setData(...)` to each generated button before normalization/rendering.
  - Keep async/modal behavior aligned with current resource defaults (`editInModal`, current delete async flow).
  - Do not introduce `getCreateButton()` into per-event actions; leave create flow where it already belongs.
  Dependency notes: foundational task for all behavior updates.

### Phase 2: Remove Silent Exception Suppression

- [x] Task 2: Remove silent `try/catch` handling around event action payload generation in `src/Resources/FullCalendarResource.php`.
  Deliverable: eliminate the empty/suppressing `catch` blocks in the default actions path and in `buildCalendarEventActionsPayload()` so button-generation errors surface instead of being silently converted to empty payloads.
  Files: `src/Resources/FullCalendarResource.php`.
  Requirements:
  - Remove the outer fallback in `buildCalendarEventActionsPayload()` that currently returns an empty payload on any `Throwable`.
  - Remove silent `try/catch` blocks around `setItem(...)` and default action button creation.
  - Let real integration/configuration issues fail loudly, while preserving normal success-path payload shape.
  - Keep the resulting code straightforward; avoid replacing silent catches with new silent conditionals that hide the same failures.
  Dependency notes: depends on Task 1 because the new button helper usage should land before exception-flow cleanup is finalized.

### Phase 3: Documentation Alignment And Verification

- [x] Task 3: Align existing documentation only if implementation changes observable behavior or documented defaults.
  Deliverable: review current package docs and update only the existing documents that become inaccurate after adding `DetailButton` or changing fail-fast behavior.
  Files: `README.md`, `docs/en/*.md`, `docs/ru/*.md` as needed.
  Requirements:
  - Do not create new documentation sections unless an existing statement becomes wrong.
  - If documentation already matches the new behavior well enough, make no docs changes.
  - If one language tree needs a correction, mirror that correction in the paired language tree when the same statement exists there.
  Dependency notes: depends on Tasks 1-2.

- [x] Task 4: Verify package-level compatibility for the updated event actions contract without adding tests.
  Deliverable: run a focused code-level verification pass confirming the PHP-side event payload still matches the current contract while now including `DetailButton` and surfacing failures.
  Files: `src/Resources/FullCalendarResource.php`, `README.md`, `docs/en/*.md`, `docs/ru/*.md` as applicable.
  Requirements:
  - Confirm `extendedProps.<calendarEventPayloadKey>.actions` still retains the current shape (`version`, `html`, `count`, `hasActions`).
  - Confirm one concrete sample event payload yields a non-empty HTML action block and an increased count after `detail` is added.
  - Confirm no frontend rebuild is required because the change is limited to PHP button composition and payload generation.
  - Verify documentation was touched only if the implementation made some existing statement inaccurate.
  - Do not add automated tests as part of this task.
  Dependency notes: depends on Task 3.

## Dependencies

- Task 2 blocked by Task 1
- Task 3 blocked by Tasks 1, 2
- Task 4 blocked by Task 3

## Notes / Risks

- The existing file is already modified in the working tree, so implementation must merge carefully with current in-progress edits rather than overwrite them.
- `DetailButton` may have different modal/navigation behavior than `EditButton`; verification should focus on the backend payload contract and item binding, not assume a specific frontend modal flow unless explicitly required.
- Using resource helper methods should reduce duplication and better preserve MoonShine defaults (`query`, modal names, translator labels), but the buttons still need item data attached explicitly for per-record actions.

## Next Step

- Run `/aif-implement` on `feature/calendar-event-detail-button` to execute the plan.
