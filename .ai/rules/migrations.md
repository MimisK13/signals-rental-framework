---
paths:
  - 'database/migrations/**'
---

# Migrations

## Scope each migration to one table
Keep each migration file scoped to exactly one database table. Split cross-table creation, constraints, and changes into ordered migration files, each targeting one table.
