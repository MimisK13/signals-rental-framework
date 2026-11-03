# Organise related capabilities as modules inside bounded contexts

The application uses top-level `app/Domain/*` directories for bounded contexts, not for every small business capability. Closely related capabilities live as nested modules inside the context that owns their language and lifecycle, avoiding both an oversized flat domain list and artificial cross-context dependencies.

## Consequences

- `Warehouse` owns Containers, packing, dispatch, returns, repacking, manifests, and warehouse presentation.
- `Stocktakes` is a module of `Stock`.
- `Repairs` is a module of `Assets`.
- `Availability` owns Demand, Shortages, recalculation, and resolution; equipment Planner remains presentation unless it gains independent business rules.
- `Products` owns catalogue identity and configuration; Stock, Assets, Rates, Supplier Costs, Tax, Availability, and Warehouse remain separate contexts.
- `Projects` is the central coordination context. Quotations are one collaborating capability within a Project and may also begin independently; accepting a standalone Quotation guarantees a linked Project.
- The current Opportunity implementation is colocated under Projects for domain-first organization while its overloaded quotation/order lifecycle is untangled. Whether quotation acceptance immediately creates a candidate operational `Booking` or waits for an explicit planning action is intentionally deferred.
- Projects and Quotations, Stock and Assets, Invoices and Documents, Purchase Orders and Supplier Bills, Rates and Supplier Costs remain separate contexts because their business lifecycles and ownership differ.
