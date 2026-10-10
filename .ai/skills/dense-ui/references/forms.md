# Dense forms

## Defaults

- Use visible labels above controls for long, responsive, or high-consequence forms.
- Use 32px desktop controls and 44px touch controls from `density-tokens.md`.
- Use 8px between label/control and 16px between field groups; use 24px only between major sections.
- Group related fields with section headings and dividers, not a card per field.
- Mark required fields consistently; do not use placeholders as labels.
- Keep helper text concise and adjacent to the field.
- Show field errors locally, summarize at the top, link to fields, preserve entered values, and do not steal focus unexpectedly.
- Preserve native tab order. Enter submits only where safe; Escape cancels only when reversible; focus returns to the invoking control after overlays close.
- Put Save/Continue as the primary action, Cancel as secondary, and destructive actions separated with confirmation.

## One vs two columns

Use one column when fields are long, dependent, sequential, high-risk, or need substantial help/validation. Use two columns on wide desktop only for short independent fields with obvious pairing (for example Store + Location, Currency + Payment terms). Collapse to one column at the narrow breakpoint. Do not add columns merely to reduce page height.

## Form examples

- Product: identity → classification → stock/reorder → supplier; long descriptions and pricing remain one-column where needed.
- Asset: asset number/product/serial → store/location → ownership/acquisition → maintenance; serial and identity are primary.
- Contact: name/company/contact channels → address → permissions/notes; keep privacy-sensitive fields grouped.
- Purchase order: supplier and delivery context → line-item table → totals/tax → approval/actions.
- Container: container identity/type → contents/handling → location/availability; scanning actions stay outside the ordinary edit form.

