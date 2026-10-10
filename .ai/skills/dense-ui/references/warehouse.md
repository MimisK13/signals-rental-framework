# Warehouse and scanner adaptation

Rentman FACT/OBSERVATION: official warehouse documentation describes list/tile views, stock-location and date filters, configurable columns, sorting/grouping, equipment/project status distinctions, saved views, combinations, serial/scan-return workflows, and warehouse-location grouping. See `enterprise-references.md` for URLs and dates.

## Dense information, touch-friendly operation

Warehouse UI retains dense information grouping but adapts physical interaction. Use 44px or larger scan/action targets, high-contrast success/error feedback, quantity controls that work with gloves, and a persistent scan target where focus can safely return after a scan.

Show, in order: project/order/container context, current item, required/picked quantity, status/exception, next action, recent scans, and progress. Support duplicate scans, manual correction, damaged/missing exceptions, audible feedback where appropriate, visible sync/offline state, and idempotent retry. Never make a tiny modal the primary scanning surface.

Scanner input MUST coexist with native keyboard navigation. Preserve focus after successful scans, but restore it after recoverable dialogs. Do not silently accept a duplicate or lose a scan during sync failure.

+

## Scanner state model

Show operation mode (Picking, Returning, Counting), project/order/container context, persistent scan target, current result identity/state, immediate feedback, progress, and recent scans. Distinguish recognized, unrecognized, ambiguous, duplicate, wrong-context, and wrong-state identifiers. After a successful scan return focus to the scan target unless intervention is required. Preserve operation context across repeated scans and show sync/background state when work is not immediately authoritative.

