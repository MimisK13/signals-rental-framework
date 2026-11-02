# Organise related capabilities as modules inside bounded contexts

The application uses top-level `app/Domain/*` directories for bounded contexts, not for every small business capability. Closely related capabilities live as nested modules inside the context that owns their language and lifecycle, avoiding both an oversized flat domain list and artificial cross-context dependencies.

## Consequences

- `Warehouse` owns Containers, packing, dispatch, returns, repacking, manifests, and warehouse presentation.
- `Stocktakes` will become a module of `Stock`.
- `Repairs` will become a module of `Assets`.
- `Availability` will own Demand, Shortages, recalculation, and resolution; equipment Planner remains presentation unless it gains independent business rules.
- Projects and Quotations, Stock and Assets, Invoices and Documents, Purchase Orders and Supplier Bills, Rates and Supplier Costs remain separate contexts because their business lifecycles and ownership differ.
