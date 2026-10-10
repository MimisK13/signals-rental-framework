# Operational efficiency audit

## Goal-to-result model

For each workflow, write:

Goal → Required information → Decision → Action → Feedback/result

Operational Distance is the distance created by unnecessary page transitions, clicks, taps, modals, scrolling, repeated search, repeated data entry, focus changes, and context switches. Optimize the path, not a literal click count.

## Audit procedure

1. State the operator goal and success result.
2. Identify information required before action.
3. Identify the decision and the next valid action.
4. Trace feedback, persistence, audit, and recovery.
5. Count or inspect page transitions, modals, repeated input, scroll, focus changes, and context resets.
6. Separate safe friction from unnecessary friction.
7. Identify permission, domain-state, consequence, and audit constraints.
8. Test repeated operation: next item, next record, next scan, next approval.
9. Recommend changes with severity and operational impact.

## Report format

Goal: Dispatch equipment to Project 482.

Current path: 8 interactions, 3 transitions.

Required information: Project, asset, availability, store.

Unnecessary friction: detail transition, redundant confirmation, lost scanner focus.

Proposed path: row action → context check → result, with filters and focus retained.

Safety preserved: store mismatch still blocks dispatch.

Impact: HIGH.

Do not optimize away required review, confirmation, accessibility, audit, or domain validation.

## Efficiency measures

Prefer fewer context switches, preserved focus, stable list context, direct information access, one clear next action, and recoverable results. A longer path can be correct when it prevents an irreversible or high-consequence mistake.

