# ERP UI foundation

Signals now contains a small, package-safe foundation for dense enterprise
interfaces. It is intentionally opt-in and does not replace Flux, Tailwind or
the existing Signals components.

## Density contexts

Apply density to a subtree with `data-density`:

```html
<div data-density="compact">
    <!-- Signals tables/toolbars and future ERP primitives inherit the values. -->
</div>
```

Supported modes:

- `comfortable`: touch-friendly default for an opted-in scope;
- `compact`: normal desktop ERP work;
- `condensed`: high-density tables and metadata only.

Nested scopes can override an ancestor because the tokens inherit through the
DOM:

```html
<div data-density="compact">
    <div data-density="comfortable">
        Scanner controls remain comfortable.
    </div>
</div>
```

## Dimensional scale

Structural dimensions use a deliberate 5px progression from 20px through
60px. Semantic tokens, rather than raw scale names, are consumed by UI rules:

```css
height: var(--erp-control-height);
padding-inline: var(--erp-cell-padding-x);
```

The initial semantic vocabulary covers control, row, action, toolbar and
header dimensions, cell/panel/section spacing, and inline/control gaps.
Typography, borders and optical corrections are intentionally separate.

## Consumption modes

The token layer is plain CSS and has no Flux or Signals domain dependency.
Plain CSS can consume the tokens directly or use the small generic helpers:

```html
<button class="erp-action-target">Action</button>
```

Tailwind can consume the same source of truth with arbitrary-value utilities:

```html
<div class="h-[var(--erp-toolbar-height)] gap-[var(--erp-control-gap)]">
    ...
</div>
```

Flux remains an optional adapter. Flux controls should use their public
`size="sm"` or default APIs explicitly; the core token layer does not depend
on Flux internals or undocumented selectors.

## Signals relationship

Existing `.s-*` defaults are preserved. The first proof integrations are:

- `.s-table` cell/header padding inside a density scope;
- `.s-toolbar` height, padding and gap inside a density scope.

Existing quotation, warehouse and legacy screens do not change until they
explicitly opt in. Domain semantics remain in Signals; this layer contains no
equipment, quotation, reservation or warehouse concepts.

## C2 guidance

C2 may use `data-density="compact"` for desktop warehouse workspaces and
`data-density="comfortable"` around scanner/touch-primary controls. Do not use
`condensed` for scanner inputs, destructive confirmations or primary touch
actions.

The foundation is deliberately not a complete component library and is not
yet extracted as a standalone package.
