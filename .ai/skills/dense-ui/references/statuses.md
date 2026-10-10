# Statuses, numbers, loading, and errors

## Status hierarchy

- **Neutral:** routine informational states such as Draft, Reserved, Prepared. Use quiet text/icon and low-saturation surface.
- **Positive:** Available, Completed, Returned. Use restrained success signal; do not make ordinary success louder than exceptions.
- **Informational:** Dispatched, On location, In transit. Use cool semantic cue plus text/icon.
- **Warning:** Repair, Delayed, Pending review. Use stronger border/icon and clear next action.
- **Critical:** Missing, Lost, Cancelled, failed scan, conflict. Use danger emphasis, explicit text, and recovery/action path.

Status must be understandable without color. Do not give every badge equal saturation (Badge Rainbow). Status labels should be stable and placed predictably.

## Numbers

Right-align quantities, currency, percentages, and totals; use tabular numerals, units, locale-aware separators, and consistent precision. Distinguish zero from unavailable (`—`). Emphasize totals with weight or a divider rather than oversized typography.

## Loading and empty states

Use row skeletons or inline loading that preserves layout. Button loading keeps its label/context and prevents duplicate submission. Avoid replacing an operational workspace with a giant spinner. Empty states are concise and actionable: “No assets found. Try changing your filters.”

## Errors

Differentiate field validation, operation failure, permission failure, conflict, system failure, and scanner failure. State what happened, what is affected, and what the operator can do next. Scanner errors need immediate visible feedback, duplicate-scan handling, and a safe retry path.

