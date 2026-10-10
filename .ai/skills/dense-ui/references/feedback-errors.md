# Feedback, errors, concurrency, and long-running work

## Feedback hierarchy

- INLINE: local validation or local result close to the affected field/row.
- TOAST: brief, non-semantic, low-risk success when the operator can continue immediately.
- BANNER/CALLOUT: page/workflow-wide condition that must persist.
- DIALOG: interruption, decision, confirmation, or critical information requiring acknowledgement.
- SCANNER FEEDBACK: immediate visible success/error; optional sound/haptic according to platform and user needs.

Do not use toast for critical errors, long messages, or content the operator must copy/revisit. Avoid notification spam.

## Interaction states

Keep these distinct:
- DEFAULT: available, resting state.
- HOVER: pointer affordance only; not keyboard focus.
- FOCUS-VISIBLE: current keyboard/assistive target; must have visible focus.
- ACTIVE: pressed/being activated or current interaction.
- SELECTED: part of a selection set.
- DISABLED: control exists but cannot be used now.
- READ-ONLY: value is inspectable but not editable.
- LOADING: operation is in progress; prevent duplicate submission where needed.
- SUCCESS/WARNING/ERROR: result or condition, with text/icon and appropriate persistence.

Focus is not selection. In a table, focus is where keyboard input applies, selection is the batch set, and active/open record is the detail context.

## Error recovery

State what failed, affected scope, reason, and next step. Offer retry, review conflict, refresh, cancel, or continue with remaining records where safe. Keep field validation near fields and server/domain errors near the operation context.

## Stale data and conflicts

Never silently overwrite authoritative changes. Show current server value and attempted value, identify conflicting fields, and offer Review, Reload, or safe Reapply.

Example:
“Stock quantity changed while you were editing. Your value: 12. Current value: 10. [Review] [Reload]”

## Long-running operations

Classify immediate, short-running, long-running/background. Use immediate state changes for fast actions, progress for meaningful work, and background status for imports/exports/reconciliation/bulk updates. Do not keep a blocking modal open while the server works. Provide completion state, failure access, and safe navigation away.

## Dirty forms and validation

Track untouched, changed, saving, saved, and save-failed states. Warn before destructive navigation when significant unsaved work would be lost. Autosave fits low-risk isolated edits/preferences; explicit Save fits complex, financial, dependent, or review-required forms.

Use immediate validation only when the value can be judged without interrupting valid entry; use blur/submit/server validation for dependent or domain rules.

## Feedback decision tree

~~~mermaid
flowchart TD
 A[Result or condition] --> B{Must interrupt or require decision?}
 B -->|Yes| D[Dialog]
 B -->|No| C{Must persist or affects whole workflow?}
 C -->|Yes| I[Inline/banner/callout]
 C -->|No| E{Short low-risk success?}
 E -->|Yes| T[Toast/status announcement]
 E -->|No| I
~~~

