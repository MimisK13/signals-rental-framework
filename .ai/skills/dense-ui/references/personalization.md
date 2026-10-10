# Saved views, personalization, and master-detail

## Saved views

A saved view MAY contain filters, sorting, grouping, visible/orderable columns, widths, and view mode. Use it when operators repeatedly reconstruct the same operational slice or when teams share a stable queue. Do not require it for trivial datasets.

Distinguish:
- system default: Asset Number first
- user preference: hide Acquisition Date
- saved operational view: Missing Equipment — Thessaloniki

Primary identity, selection, action, and safety context remain mandatory. Personalization cannot remove the information required to identify, decide, or safely act.

## Column personalization

Support show/hide, reorder, resize, reset to default, mandatory columns, and responsive priority. Selection and action columns are structural, not ordinary user data columns. Keep reset and current view visible.

## Master-detail

Use master-detail when operators inspect many records sequentially, list context matters, and detail is bounded. The left/master pane keeps filtered list identity; the right/detail pane shows selected record, state, key metadata, history summary, and bounded actions.

Use a dedicated detail page when the workflow is complex, multi-step, deep, or high-consequence. On narrow screens use list → detail navigation when two panes are not viable.

~~~mermaid
flowchart TD
 A[Open record from list] --> B{Sequential inspection with list context?}
 B -->|Yes| C{Detail bounded and width sufficient?}
 C -->|Yes| M[Master-detail]
 C -->|No| P[Detail page]
 B -->|No| P
~~~

Master-detail states are distinct: keyboard focus may be in the master list, selected record may differ from the active/open record, and the detail pane may be loading or stale. Preserve that distinction.

