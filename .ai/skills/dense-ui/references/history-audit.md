# History and audit

## Choose the representation

Use a dense history table when operators need comparison, filtering, sorting, timestamps, actor, event type, subject, or before/after values. Use a timeline when chronological narrative and sequence are the primary task. Use a grouped activity feed for mixed notes/events where chronology matters more than comparison.

Do not use a decorative vertical timeline by default.

## Event anatomy

Timestamp, actor, action/event, subject, change, context, and source.

Example:
09 Oct 2026 14:32 — Mimis K. — Stock Adjustment — XLR 5m — 120 → 110 — damaged cables — Thessaloniki.

Show only meaningful operator-facing events. Hide implementation noise unless it explains a decision or audit requirement.

## Before/after diff

For important edits show changed fields only:
Status: Available → Repair
Location: Audio / Consoles → Service Bench

Provide expandable detail for complex values; do not force operators to compare unchanged fields.

## History decision tree

~~~mermaid
flowchart TD
 A[History request] --> B{Need compare/filter/sort/audit fields?}
 B -->|Yes| T[Dense history table]
 B -->|No| C{Chronological narrative primary?}
 C -->|Yes| L[Timeline/activity feed]
 C -->|No| T
~~~

