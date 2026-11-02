# CustomViews

Manages saved table views and the user preferences that select them.

## Owns

- Personal, shared, and system view definitions.
- Columns, filters, sorting, pagination, and defaults.
- User view preferences, resolution, and API presentation.

## Boundary

Generic DataTable queries, Ransack filtering, column registries, and entity-specific schemas remain shared consumers.
