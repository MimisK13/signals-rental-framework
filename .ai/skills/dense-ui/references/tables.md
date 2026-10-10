# Tables, filters, columns, and row actions

## Default anatomy

Use a table when operators compare common attributes, sort/filter records, select in bulk, or act across rows. Default order: selection, primary identity, operational context, status, quantities/dates, trailing overflow actions.

| Element | Dense default |
|---|---|
| Row/header | 36px row and header; 44px touch override |
| Cell padding | 8px horizontal and vertical; preserve native table semantics |
| Identity | Left aligned, first meaningful column, never hidden on mobile |
| Numbers/quantity/currency/percent | Right aligned, tabular numerals, consistent precision and units |
| Dates | One consistent format per table; align consistently, usually left unless numeric comparison is primary |
| Status | Predictable column, text/icon plus restrained semantic color |
| Checkbox | Fixed 32px column, centered horizontally and vertically |
| Row actions | Trailing ellipsis/overflow by default; explicit action only when genuinely frequent |
| Long values | Truncate only with full accessible value on focus/detail; never truncate sole identity without access |
| Header | Sticky only when scroll context is clear; sortable state announced |
| Bulk actions | Appear with selection count and safe confirmation for destructive work |

Example checkbox cell:

```css
.checkbox-cell { width: 32px; text-align: center; vertical-align: middle; }
.checkbox-cell input[type="checkbox"] { display: inline-block; margin: 0; vertical-align: middle; }
```

Keep the `<td>` a table cell. Do not convert it to flex/grid merely to center a checkbox; native table semantics, sizing, and assistive technology behavior are more predictable.

## Column priority

- **PRIMARY:** identity, key attribute, status, next action, and information required to decide or operate. Retain on all layouts.
- **SECONDARY:** frequently needed context. Keep on wide desktop; move to pop-in/stacked detail or user-hideable columns at narrow widths.
- **TERTIARY:** optional metadata, financial/acquisition detail, or low-frequency context. Hide or personalize; never remove critical identity to preserve it.

## Filters and toolbar

Default order: `Search | Status | Location/Store | Category | More filters | Active filters | Saved view | Columns | Actions`. Common filters stay visible; advanced filters open progressively; active filters are visible as removable tokens; Reset/Clear all is explicit. Do not use a giant permanent filter card.

## Responsive behavior

Desktop uses the Dense table. Tablet may retain a table with fewer secondary columns. Mobile keeps identity + key attribute + status and moves secondary values into labelled stacked rows/pop-in or a detail flow. Horizontal scroll is acceptable only when column comparison is the task and identity/sticky context remains clear. Do not squeeze unreadable columns.

## Sorting, grouping, and performance

Show sort direction and multi-sort order. Make grouping visually subordinate to row identity. Use server-side filtering/sorting/pagination for large datasets; virtualize only when semantics, selection, keyboard movement, row height, and screen-reader behavior remain correct.

