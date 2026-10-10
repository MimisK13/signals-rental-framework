# Performance

Bound data at the source with server-side filtering, sorting, pagination, and search. Debounce free-text search when appropriate and announce delayed results. Avoid rendering 10,000 DOM rows. Use virtualization only when it preserves keyboard movement, selection, row height, table semantics, and screen-reader behavior. Lazy-load detail panels and related tables. Minimize reactive updates and measure realistic data volumes on target hardware.

Performance is operational usability: a table that visually fits 2,000 records but renders them all, blocks filtering, or loses focus during updates is not a successful Dense UI.

