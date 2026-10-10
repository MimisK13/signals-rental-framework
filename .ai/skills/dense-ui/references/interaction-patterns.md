# Interaction patterns and component anatomy

This is structural guidance, not a component library.

## Data Table

Required: result context, semantic header, identity-first rows, native selection semantics where selection exists, loading/empty/error handling, and keyboard/focus behavior.

Optional: search/filter toolbar, active filters, bulk bar, saved view, column controls, pagination, sticky header, expandable rows.

Responsibility: compare, select, sort/filter, open identity, and perform bounded row actions.

Common mistakes: action wall, focus/selection collapse, hidden identity, unbounded DOM, silent partial failure.

## Filter Toolbar

Required when filtering matters: local search or identifier lookup, common filters, active filter summary, and reset/clear.

Optional: advanced filter panel, saved views, columns, grouping, result count, contextual actions.

Keep common filters visible and advanced controls progressive. Do not make the toolbar a second page header.

## Record Header

Required: primary identity, secondary identity/context, current state, primary action, secondary/overflow actions.

Responsibility: answer what this is, where it belongs, what state it is in, and what can happen next.

## Detail Workspace

Required: compact header, key summary, stable local navigation when needed, sections or related tables.

Optional: history, drawer-adjacent context, inline actions, activity.

Avoid card-per-field, giant hero headers, and nested panel soup.

## Form

Required: visible labels, grouped fields, validation, keyboard order, dirty/saving/saved/error behavior, and finalizing action.

Optional: helper text, inline field action, autosave, step sequence.

Complex forms belong on pages; small bounded edits may use drawer/modal/inline.

## Action Menu

Required: stable labels, grouped context, keyboard access, and focus return.

Responsibility: expose secondary/rare actions without hiding frequent/core operations.

## Bulk Action Bar

Appears when selection exists unless a workflow explicitly requires persistent batch controls. Show selection count, scope, available actions, progress/result, and partial failures.

## Status Indicator

Required: text label; icon/shape may reinforce; semantic color is secondary. Emphasis follows actionability.

## Key-Value Group

Use stable labels, clear values, readable missing state, and responsive stacking. Use for read-only metadata rather than separate cards.

## Master-Detail Workspace

Required: master list context, active/open record distinction, detail pane, selection/focus behavior, and route/full-page escape.

Use when sequential inspection benefits from preserved list context and detail is bounded. Do not use for complex deep workflows or insufficient widths.

## Scanner Workspace

Required: operation, project/order/container context, persistent scan target, current result, feedback, progress, and recent scans. Touch targets are adapted from V2 desktop dimensions.

## History/Audit View

Required: timestamp, actor, action/event, subject, change/context. Choose table for comparison/filtering and timeline for narrative chronology.

## Notification/Feedback

Choose inline, toast, banner/callout, or dialog by persistence, semantic importance, interruptiveness, and action requirement. Success should be quiet; errors require recovery.

## Confirmation Dialog

Use only for consequential, destructive, irreversible, broad, external, or ambiguous actions. Title and final action name the consequence and scope. Do not use a generic “Are you sure?”.

