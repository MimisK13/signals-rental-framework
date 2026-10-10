# Normative visual language

This is the default visual system for enterprise desktop screens. It is a proposal derived from the evidence matrix in `enterprise-references.md`; it is not a claim that any vendor mandates these exact values.

## Direction

Use restrained, neutral, structured, information-first UI with a high information-to-chrome ratio. Use one shallow application surface hierarchy. A normal screen is a workspace, not a stack of floating cards.

## Surfaces, borders, and elevation

- Application background: `#f5f6f8` or the host theme equivalent.
- Primary workspace surface: `#ffffff`.
- Subtle surface: `#f0f2f5` for toolbars, grouped metadata, selected-but-not-active regions.
- Selected surface: a low-saturation accent tint, never a saturated block.
- Elevated overlay: `#ffffff` with a real shadow for modal, popover, menu, or drawer only.
- Use 1px structural borders/dividers to explain table, section, and toolbar boundaries.
- Ordinary sections SHOULD NOT receive decorative shadows. Use contrast or a divider.
- Keep hierarchy shallow: application background → workspace surface → subtle grouping; avoid card-in-card-in-field-container.

## Color behavior

Use neutral structure and semantic color with restrained saturation. Strong color is reserved for action, attention, failure, or selection. Routine statuses are quieter than exceptional statuses. Every status has text or an icon/shape in addition to color.

Roles: `background`, `surface`, `surface-subtle`, `surface-selected`, `border`, `border-strong`, `text`, `text-secondary`, `text-muted`, `accent`, `focus`, `success`, `warning`, `danger`, `information`.

## Radius and shadow

- `radius-sm: 4px` for inputs, buttons, selects, and compact controls.
- `radius-md: 6px` for bounded panels and drawers.
- `radius-pill: 999px` only for tags/badges whose shape communicates compact status, never for every control.
- Overlays MAY use `radius-md` and shadow. Tables, page regions, and ordinary sections SHOULD use square or `radius-sm` corners.
- Do not use 12–20px rounding as the default enterprise surface language.

## Typography

- Base UI/table text: 14px, line-height 20px.
- Labels: 12px, line-height 16px, medium weight where needed for scanability.
- Secondary metadata: 12px, line-height 16px, never below accessible contrast.
- Page title: 20px, line-height 28px, semibold.
- Section title: 14px, line-height 20px, semibold.
- Numeric data: 14px with tabular numerals.
- Identifiers: normal UI font by default; use monospace only when character-by-character comparison benefits.
- Do not make 10px or 11px text the density strategy.

## Controls and states

- Desktop inputs, selects, and buttons use the Dense control height in `density-tokens.md`.
- Keep visible labels. Use concise helper text beneath the control only when it changes the decision.
- Primary action: one or two visually emphasized actions per context.
- Secondary action: low-emphasis text/outlined action.
- Ghost/icon action: low-chrome, with an accessible name and tooltip/context.
- Destructive action: semantic danger styling plus confirmation when consequences are material.
- Focus: a visible 2px-equivalent focus ring with sufficient contrast; never remove it for aesthetics.
- Hover changes surface/border, not text legibility or layout.
- Selected rows/controls use a low-saturation selected surface plus a non-color indicator where ambiguity is possible.

## Whitespace

Whitespace MUST express grouping, hierarchy, or recovery space. Use micro gaps inside controls, component gaps inside groups, group gaps between related fields, and section/page gaps between distinct regions. Do not add empty space merely to make an operational page feel luxurious.
