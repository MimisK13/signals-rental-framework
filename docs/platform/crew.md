---
title: Crew
description: Crew registry, project staffing requests, assignments, shifts, availability, timesheets, compensation, expenses, and coverage planning for AV rental operations.
---

# Crew

## Status

The operational Crew domain is implemented under `app/Domain/Crew` and wired by
`CrewServiceProvider`. Crew presentation belongs to `app/Domain/Crew/Presentation`: its
Livewire components are named PHP classes, and its Blade views contain presentation markup.
Crew does not use Volt. The detailed sections below remain the design specification;
proposed or later-phase items are not evidence of delivered functionality.

The Presentation migration is verified by 45 native Livewire/architecture tests
(173 assertions) and 18 API/integration tests (125 assertions). Component PHP classes
and Blade templates are separate, and an architecture test forbids Volt dependencies.

Implemented scope:

- All 19 domain migrations, models, factories, enums, policies, settings and permissions.
- Registry, roles, skills, qualification verification, effective-dated rates and requirements.
- Project requests/role lines, invitation/confirmation/cancellation/substitution, shifts,
  UTC/DST validation, cross-store conflicts, availability and explicit schedule realignment.
- Integer compensation with agreed policy snapshots; actual time entry editing,
  chronological weekly overtime safeguards, submission/approval/locking and Finance reference handoff.
- Cancellation notice guarantees are frozen in a separate assignment ledger and forecast
  currency total. They do not create worked hours; eligible completed work remains claimable.
- Expense claim lifecycle and frozen FX amounts (Finance still owns payment).
- Candidate matching with bounded 500-person scoring pages and explicit continuation metadata;
  coverage, shortages, utilisation, history, cost forecast and qualification queries.
- Livewire registry/configuration/rates, request staffing, assignment/shifts,
  availability, timesheets, coverage planner and weekly calendar, including substitution
  and review of changed project schedules.
- Domain-local `/crew` web and `/api/v1/crew` API routes, token abilities, privacy,
  atomic audit, commit-time webhook dispatch and notification registry integration.
- Scheduled invitation expiry, shortage/qualification alerts and archived-contact retention.

Enable `modules.crew` and grant the required `crew.*` permissions. Normal deployment
must run `php artisan migrate`, `php artisan db:seed --class=PermissionSeeder` and
`php artisan db:seed --class=NotificationTypeSeeder`
so domain tables and notification types are present. This implementation does not
migrate the developer's application database automatically. The scheduler and queue
workers use the application's existing setup.

Business defaults in section N were approved on 1 November 2026. Confirmed assignment
terms include their compensation policy; later settings/rate-card edits do not rewrite
agreements. Timesheets must be processed chronologically within the member's pay week;
backdating into later submitted/frozen pay or interleaved unresolved entries is rejected
with a validation message. Forecasts keep currencies separate and identify assignments
that cannot yet be estimated.

Explicit realignment can expand Crew request/line windows to the current project schedule.
Unrelated line dates and sibling shifts remain unchanged; a shared line with unresolved
active sibling bookings must be resolved before its inherited period can expand.

Remaining extensions: graphical drag/drop and month-calendar interfaces, personalised
DataTable column registries, a self-service crew portal, dedicated expense/report screens,
automatic holiday-calendar ingestion, and call-sheet/contract/payroll adapters. Expense
receipts and qualification documents use the existing attachment relationship; a dedicated
Crew upload interface is not provided. Shift completion does not create a timesheet under
shift-management permission; creating a timesheet explicitly seeds its eligible shifts.
Shift-break windows retain their Phase 2 schema; MVP pay uses `break_minutes`.
Individual shift cancellation changes the schedule; terminating an assignment or
request records its contractual cancellation entitlement. Finance/payment adapters
consume that entitlement separately from approved worked time.

Run `php artisan test --compact tests/Feature/Crew` for the operational suites and
`php artisan test --compact tests/Pgsql/CrewConcurrencyTest.php` for the dedicated
PostgreSQL concurrency lane.

Verification: the combined Crew/custom-field run passed 259 tests (898 assertions),
related notification/registry checks passed 77 tests, and PostgreSQL concurrency
passed three two-process tests. Subsequent next-day realignment and cancellation
regressions passed their focused suites. Scoped application/test PHPStan and Pint
checks completed without errors.

## How to read this document

Every non-trivial statement is tagged so that confirmed repository behaviour is
never confused with a business decision that still needs sign-off.

| Tag | Meaning |
| --- | --- |
| **CONFIRMED** | Verified in this repository. The file path is cited. |
| **ASSUMPTION** | A reasonable reading of the brief that must be confirmed by the product owner. |
| **PROPOSAL** | A recommended policy, default, or algorithm. Not yet a business rule. |

Repository facts verified for this specification:

| Fact | Evidence |
| --- | --- |
| Laravel `^13.0`, PHP `^8.4`, Livewire `^4.0`, Volt `^1.6`, Pest `^4.3` | `composer.json` |
| Default DB connection is `pgsql`; connections configured are `sqlite` and `pgsql` only | `config/database.php` |
| No database `ENUM` columns; statuses are `string(…)` columns cast to native PHP enums | `database/migrations/2026_10_10_000001_create_projects_table.php`, `app/Enums/ProjectStatus.php` |
| Actions are invokable classes under `app/Actions/{Domain}` that authorise with `Gate` and return DTOs | `app/Actions/Projects/CreateProject.php` |
| DTOs are `spatie/laravel-data` classes under `app/Data/{Domain}` with `fromModel()` | `app/Data/Projects/ProjectData.php` |
| Money is stored as integer minor units and formatted with `brick/money` | `app/Models/Traits/FormatsMoney.php` |
| Timestamps are stored in UTC and rendered through `App\Support\Timezone` | `app/Support/Timezone.php` |
| Store visibility uses a global `StoreScope`; models opt in with `HasStoreScoping` | `app/Models/Scopes/StoreScope.php`, `app/Models/Traits/HasStoreScoping.php` |
| Audit is written by `AuditableEvent` → `ActionLog`; webhooks ride the same audit stream | `app/Events/AuditableEvent.php`, `app/Listeners/DispatchWebhookForAuditableEvent.php`, `app/Models/ActionLog.php` |
| Permissions are declared in `PermissionSeeder::permissions()` and served through `PermissionRegistry` | `database/seeders/PermissionSeeder.php`, `app/Providers/AppServiceProvider.php` |
| API v1 uses the `ResourceActions`/`FiltersQueries` traits plus token abilities | `app/Http/Controllers/Api/V1/ProjectController.php`, `app/Services/Api/CoreAbilityDefinitions.php` |
| Custom fields are opt-in via `HasCustomFields` and registered in `CustomFieldModuleRegistry` | `app/Models/Traits/HasCustomFields.php`, `app/Services/CustomFieldModuleRegistry.php` |
| File attachments are a polymorphic `Attachment` relation via `HasAttachments` | `app/Models/Traits/HasAttachments.php` |
| Crew uses class-based Livewire under `Presentation/Livewire` with namespaced Blade views under `Presentation/Views` | `app/Domain/Crew/Presentation`, `CrewServiceProvider` |
| Settings groups extend `SettingsDefinition` | `app/Settings/SchedulingSettings.php`, `app/Settings/SettingsDefinition.php` |
| Feature profiles already gate a `crew` module flag | `app/Enums/FeatureProfile.php` |
| The application header already contains a placeholder Crew link | `resources/views/components/layouts/app/header.blade.php` |

> **Database note (CONFIRMED):** the brief allows MySQL or PostgreSQL. This
> repository currently configures `pgsql` (default) and `sqlite` only. The schema
> below is written to be portable, but a MySQL connection entry and a MySQL test
> lane would be new work. Do not assume MySQL is supported today.

---

## A. Domain purpose, terminology, and boundaries

### A.1 Purpose

Crew owns **who works, when, where, in what role, under what agreed
compensation, and what was actually worked**. It turns a project's staffing
demand into confirmed assignments, resolved shifts, approved time, and payable
labour cost.

Crew is deliberately **not**:

- the person master (Contacts/Companies own identity);
- the project master (Projects owns lifecycle and schedule);
- payroll or accounts payable (Finance owns payables and payments);
- transport planning (Logistics owns vehicles and routes);
- the user/permission system (IdentityAccess owns users and roles).

### A.2 Terminology (canonical, implementation-ready)

| Term | Definition | Distinct from |
| --- | --- | --- |
| **Crew member** | A person on the staffing roster, internal or external. Optionally linked to a `Member` identity and/or a `User` login. | `Member`, `User` |
| **Engagement type** | The commercial relationship: employee, freelancer, contractor, external crew. | Role, rate type |
| **Crew role** | A job function (e.g. Sound Engineer, Rigger, Stage Manager). | Skill, qualification |
| **Skill** | A capability with a proficiency level (e.g. GrandMA3, line-array rigging). | Role, qualification |
| **Qualification** | A verifiable certificate/licence with an issuer and expiry (e.g. IPAF 3a/3b). | Skill |
| **Role requirement** | A mandatory or desirable skill/qualification attached to a role or a request line. | Crew member's own record |
| **Crew request** | Project-level staffing demand: one or more role lines, quantities, periods, and requirements. | Assignment |
| **Crew request line** | One role+quantity+period requirement inside a request. | Assignment |
| **Crew assignment** | The decision that a specific crew member fills a request line, with agreed compensation snapshot. | Request, shift |
| **Crew shift** | A time block belonging to an assignment (setup, show, strike, travel, standby). One assignment may have many shifts. | Assignment, time entry |
| **Time entry** | Actual work/travel/standby recorded by or for a crew member. Belongs to a timesheet, optionally references a shift. | Shift |
| **Timesheet** | A submitted period of time entries for one assignment, with its own approval lifecycle. | Payment |
| **Availability** | Declared leave/unavailability/training window for a crew member. | Assignment/shift |
| **Hard conflict** | A scheduling or compliance violation that must block the operation. | Warning |
| **Warning** | A policy risk that may be acknowledged and overridden by an authorised user. | Hard conflict |
| **Coverage** | Filled quantity vs requested quantity per request line. | Utilisation |
| **Utilisation** | Booked/worked time as a proportion of available capacity over a period. | Coverage |
| **Call** | The working engagement on a date, bounded by call time and wrap time. | Shift |
| **Rate card** | Effective-dated compensation defaults for a member, role, or engagement type. | Assignment snapshot |
| **Agreed rate snapshot** | The immutable compensation terms copied onto an assignment when it is confirmed. | Rate card |

### A.3 Boundary contract

```text
┌──────────────────────┐        identifiers + read contracts        ┌──────────────────────┐
│ Projects             │ ─────────────────────────────────────────▶ │ Crew                 │
│ project identity     │                                            │ requests             │
│ lifecycle, schedule  │ ◀───────── CrewCostReader (read) ───────── │ assignments          │
└──────────────────────┘                                            │ shifts               │
                                                                    │ timesheets           │
┌──────────────────────┐        member_id / org_id references        │ compensation         │
│ Contacts / Companies │ ◀───────────────────────────────────────── │ expenses             │
│ person & org master  │                                            └──────────┬───────────┘
└──────────────────────┘                                                       │
                                                                               │ events
┌──────────────────────┐   user_id (optional) + permissions                    │
│ IdentityAccess       │ ◀─────────────────────────────────────────────────────┤
└──────────────────────┘                                                       │
                                                                               │
┌──────────────────────┐   approved cost + payable hand-off                   │
│ Finance              │ ◀─────────────────────────────────────────────────────┤
│ payables & payments  │                                                       │
└──────────────────────┘                                                       │
                                                                               │
┌──────────────────────┐   crew events → templates & channels                   │
│ Notifications        │ ◀─────────────────────────────────────────────────────┤
└──────────────────────┘                                                       │
                                                                               │
┌──────────────────────┐   call sheets, contracts, crew packs                  │
│ Documents            │ ◀─────────────────────────────────────────────────────┘
└──────────────────────┘
```

Boundary rules (all **PROPOSAL**, aligned with the brief):

1. **No cross-domain writes.** Crew never writes `projects`, `members`, `users`,
   `invoices`, `supplier_bills`, or `stock_*`.
2. **Identifiers, not object graphs.** Crew stores `project_id`, `member_id`,
   `user_id`, `store_id`, `opportunity_id`, and optional `venue_member_id`.
3. **Explicit contracts for reads.** Where Crew needs project dates or member
   names, it reads through a small read contract or Eloquent query, never by
   mutating the other domain.
4. **Projects announces schedule changes.** Projects must publish a schedule
   event; Crew reacts with an impact review. Crew never silently edits confirmed
   shifts because a project moved.
5. **Finance owns payment state.** A timesheet can be `approved` and `locked`
   without being paid. Payment lives in Finance, referenced by
   `finance_reference`.
6. **IdentityAccess owns authentication.** A crew member without a login is a
   first-class record; `user_id` is nullable and never required by any Crew
   operation.

### A.4 Relationship to the existing `Member` model

**CONFIRMED:** `Member` is the CRM master for users, contacts, organisations, and
venues (`app/Enums/MembershipType.php`). Crew must not duplicate addresses,
emails, phones, or tax data.

**PROPOSAL:** `crew_members.member_id` is a nullable FK to `members`:

- internal employee → `member_id` points at a `MembershipType::Contact` (or
  `User`) member;
- freelancer/contractor → `member_id` points at the person's `Contact`;
- external crew → `member_id` points at the person (if known) and
  `supplier_member_id` points at the supplying `Organisation`.

A crew member with no CRM record is allowed (walk-in freelancer) and keeps
denormalised `name`, `email`, `phone` fallbacks until merged.

---

## B. Capabilities grouped by MVP and later phases

### B.1 MVP (must ship together)

| # | Capability | Delivered by |
| --- | --- | --- |
| 1 | Crew registry with engagement types and optional identity/login links | `crew_members` |
| 2 | Roles, skills, qualifications, role requirements | `crew_roles`, `crew_skills`, `crew_qualifications`, `crew_role_requirements` |
| 3 | Member skills, roles, and qualifications with expiry tracking | `crew_member_*` pivots, `crew_member_qualifications` |
| 4 | Effective-dated rate cards | `crew_rate_cards` |
| 5 | Project crew requests with role lines and quantities | `crew_requests`, `crew_request_lines`, `crew_request_line_requirements` |
| 6 | Assignments with proposed → invited → confirmed lifecycle | `crew_assignments` |
| 7 | Multiple shifts per assignment, including overnight | `crew_shifts` |
| 8 | Availability declarations and hard-conflict detection | `crew_availabilities`, conflict query |
| 9 | Timesheets from actual time entries, submitted → approved → locked | `crew_timesheets`, `crew_time_entries` |
| 10 | Compensation snapshot and pay calculation | assignment snapshot + calculator |
| 11 | Crew planning calendar and coverage board | Queries + Livewire pages |
| 12 | Candidate matching and shortage detection | Matching + shortage queries |
| 13 | Notifications, audit, permissions, privacy | Registries, policies, `AuditableEvent` |
| 14 | Core REST API for registry, requests, assignments, shifts, timesheets | `Api\V1\Crew*Controller` |

### B.2 Phase 2 (post-MVP)

| # | Capability | Why deferred |
| --- | --- | --- |
| 15 | Crew expenses and reimbursement hand-off | Needs Finance payable contract |
| 16 | Utilisation, work-history, and cost-forecast reports | Needs stable MVP data |
| 17 | Self-service crew portal (login required) | Needs auth/privacy review |
| 18 | Shift break windows with paid/unpaid rules | `break_minutes` suffices for MVP |
| 19 | Document generation (call sheets, crew packs, contracts) | Needs Documents template work |
| 20 | Bulk planning, drag-and-drop reassignment | Needs calendar UX hardening |
| 21 | External crew supplier portal and compliance document collection | Needs supplier/CRM flow |
| 22 | Automated qualification-expiry campaigns | Needs notification tuning |
| 23 | Payroll export adapters | Needs Finance contract |
| 24 | Multi-day travel and per-diem policy engine | Needs policy sign-off |

### B.3 Explicitly out of scope

- Payroll tax, pension, and statutory deductions.
- Bank details and payment execution.
- Vehicle/fleet scheduling (Logistics).
- Inventory, equipment allocation, and transport manifests.
- Customer-facing crew scheduling.
- Time-clock hardware integration beyond an import seam.

---

## C. Eloquent model inventory, relationships, and ownership

### C.1 Model inventory

| Model | Table | Aggregate root | Store-scoped | Soft deletes | Custom fields | Attachments |
| --- | --- | --- | --- | --- | --- | --- |
| `CrewRole` | `crew_roles` | yes (config) | no | no | no | no |
| `CrewSkill` | `crew_skills` | yes (config) | no | no | no | no |
| `CrewQualification` | `crew_qualifications` | yes (config) | no | no | no | no |
| `CrewRoleRequirement` | `crew_role_requirements` | no | no | no | no | no |
| `CrewMember` | `crew_members` | yes | no | yes | yes | yes |
| `CrewMemberRole` | `crew_member_roles` | no | no | no | no | no |
| `CrewMemberSkill` | `crew_member_skills` | no | no | no | no | no |
| `CrewMemberQualification` | `crew_member_qualifications` | no | no | no | no | yes |
| `CrewRateCard` | `crew_rate_cards` | yes | optional | yes | no | no |
| `CrewRequest` | `crew_requests` | yes | yes | yes | yes | yes |
| `CrewRequestLine` | `crew_request_lines` | no | via request | no | no | no |
| `CrewRequestLineRequirement` | `crew_request_line_requirements` | no | via request | no | no | no |
| `CrewAssignment` | `crew_assignments` | yes | yes | yes | no | yes |
| `CrewShift` | `crew_shifts` | yes | yes | yes | no | no |
| `CrewShiftBreak` | `crew_shift_breaks` | no | via shift | no | no | no |
| `CrewAvailability` | `crew_availabilities` | yes | no | no | no | yes |
| `CrewTimesheet` | `crew_timesheets` | yes | yes | yes | no | yes |
| `CrewTimeEntry` | `crew_time_entries` | no | via timesheet | no | no | no |
| `CrewExpense` | `crew_expenses` | yes | yes | yes | no | yes |

> `CrewShiftBreak` is Phase 2. It is listed so migrations and relations are
> designed without a future breaking change.

### C.2 Ownership decisions

| Decision | Rationale | Tag |
| --- | --- | --- |
| `CrewMember` is **not** store-scoped | A person works across stores; store scoping would duplicate people and break conflict detection. | **PROPOSAL** |
| `CrewRateCard` is global when `store_id` is `null`, store-specific otherwise | Allows company-wide rates with per-store overrides. | **PROPOSAL** |
| `CrewRequest`, `CrewAssignment`, `CrewShift`, `CrewTimesheet`, `CrewExpense` are store-scoped | Labour cost must roll up to a store. | **PROPOSAL** |
| `CrewAvailability` is global per member | Leave follows the person, not a store. | **PROPOSAL** |
| Request store defaults from the linked Opportunity's store; otherwise explicit | Projects are cross-store, Opportunities are not. | **PROPOSAL** |
| Registry/config models (`CrewRole`, `CrewSkill`, `CrewQualification`) are global | Shared vocabulary across stores. | **PROPOSAL** |
| No `Repository`, `Data Mapper`, or CQRS layer | Matches the brief and existing codebase. | **CONFIRMED (brief)** |

### C.3 Relationship map

```text
CrewMember
├── user_id ─────────────▶ User (nullable, IdentityAccess)
├── member_id ───────────▶ Member (nullable, CRM identity)
├── supplier_member_id ──▶ Member (nullable Organisation, external crew supplier)
├── roles[] ─────────────▶ CrewRole            (crew_member_roles)
├── skills[] ────────────▶ CrewSkill           (crew_member_skills)
├── qualifications[] ────▶ CrewMemberQualification ──▶ CrewQualification
├── rateCards[] ─────────▶ CrewRateCard
├── availabilities[] ────▶ CrewAvailability
├── assignments[] ───────▶ CrewAssignment
├── timesheets[] ────────▶ CrewTimesheet
└── expenses[] ──────────▶ CrewExpense

CrewRole
└── requirements[] ──────▶ CrewRoleRequirement ──▶ CrewSkill | CrewQualification

Project (Projects domain)
└── crewRequests[] ──────▶ CrewRequest
    ├── opportunity_id ──▶ Opportunity (nullable, operational job)
    ├── venue_member_id ─▶ Member (nullable Venue)
    └── lines[] ─────────▶ CrewRequestLine ──▶ CrewRole
        ├── requirements[] ▶ CrewRequestLineRequirement
        └── assignments[] ─▶ CrewAssignment ──▶ CrewMember
            ├── shifts[] ──▶ CrewShift
            ├── timesheets[] ▶ CrewTimesheet ──▶ CrewTimeEntry
            └── expenses[] ─▶ CrewExpense
```

### C.4 Key model conventions (match existing repo style)

**CONFIRMED** conventions to inherit:

- `final` is not used on models; models are plain classes extending `Model`.
- `protected $fillable` is an explicit list, typed with `/** @var list<string> */`.
- Casts live in `protected function casts(): array`.
- Relations use explicit generic PHPDoc (`/** @return BelongsTo<Member, $this> */`).
- Enums are cast directly (`'status' => CrewAssignmentStatus::class`).
- Money columns are integers; formatting goes through `FormatsMoney`.
- Status enum shape mirrors `App\Enums\ProjectStatus` with `label()` and
  `canTransitionTo()`.
- Models that implement `HasSchema` also implement `defineSchema(SchemaBuilder $builder)`
  so the API schema discovery and list builder work.

**PROPOSAL:** every Crew model implements `HasSchema` and registers searchable,
filterable, sortable, and groupable columns, following `Activity::defineSchema()`.

---

## D. Proposed database schema, constraints, indexes, and snapshots

### D.1 Conventions

- Primary keys: `$table->id()`.
- Public identifiers: keep integer IDs in MVP (repo convention). Add
  `reference` string columns for operator-facing numbers, not UUIDs.
- Timestamps: `$table->timestamps()`; terminal/immutable records also use
  `softDeletes()`.
- Instants: `dateTime` stored in **UTC**. Calendar dates: `date`.
- Status: `string(24)` with a native PHP enum cast. **No DB `ENUM`.**
- Money: `bigint` minor units named `*_minor`.
- Multipliers: `decimal(4,2)`.
- JSON: `json` (Postgres `jsonb` is fine through Laravel's `json` column).
- Booleans: `boolean` with explicit defaults.
- Every FK states its delete behaviour. Default: `restrictOnDelete` for
  operational records, `nullOnDelete` for optional references, `cascadeOnDelete`
  only for owned child rows.
- Every status and every range column gets a composite index matching its query.
- Portable CHECK constraints only where both PostgreSQL and MySQL 8 enforce them.
  Where they do not, validation lives in the Action and a test guards it.

### D.2 `crew_roles`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `name` | string(120) | no | | e.g. "Sound Engineer" |
| `slug` | string(120) | no | | unique |
| `description` | text | yes | | |
| `is_supervisory` | boolean | no | false | Can lead a team |
| `requires_certification` | boolean | no | false | Hint for matching |
| `sort_order` | smallint | no | 0 | |
| `is_active` | boolean | no | true | |
| `created_at` / `updated_at` | timestamp | yes | | |

Indexes: `unique(slug)`, `index(is_active, sort_order)`.

### D.3 `crew_skills`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `name` | string(120) | no | | |
| `slug` | string(120) | no | | unique |
| `category` | string(60) | no | 'general' | e.g. audio, rigging, video |
| `description` | text | yes | | |
| `is_active` | boolean | no | true | |

Indexes: `unique(slug)`, `index(category, is_active)`.

### D.4 `crew_qualifications`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `name` | string(120) | no | | e.g. "IPAF 3a/3b" |
| `slug` | string(120) | no | | unique |
| `issuer` | string(120) | yes | | Awarding body |
| `description` | text | yes | | |
| `validity_months` | smallint | yes | | null = never expires |
| `requires_expiry` | boolean | no | false | Must have `expires_at` |
| `is_mandatory` | boolean | no | false | Company-wide default |
| `is_active` | boolean | no | true | |

Indexes: `unique(slug)`, `index(is_active)`.

### D.5 `crew_members`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `member_id` | FK members | yes | | `nullOnDelete`; CRM identity |
| `user_id` | FK users | yes | | `nullOnDelete`; unique; optional login |
| `supplier_member_id` | FK members | yes | | `nullOnDelete`; external crew supplier org |
| `engagement_type` | string(24) | no | 'employee' | `CrewEngagementType` |
| `status` | string(24) | no | 'active' | `CrewMemberStatus` |
| `name` | string(160) | no | | Display/fallback identity |
| `email` | string(190) | yes | | Fallback only |
| `phone` | string(60) | yes | | Fallback only |
| `job_title` | string(120) | yes | | Free-text |
| `home_timezone` | string(64) | no | 'UTC' | IANA |
| `home_postcode` | string(20) | yes | | Distance/travel heuristics |
| `travel_radius_km` | smallint | yes | | Willingness to travel |
| `internal_notes` | text | yes | | Private; permission-gated |
| `external_reference` | string(60) | yes | | Agency reference |
| `starts_on` | date | yes | | Engagement start |
| `ends_on` | date | yes | | Engagement end |
| `last_assigned_at` | datetime | yes | | Fairness tie-break cache |
| `created_at` / `updated_at` | timestamp | yes | | |
| `deleted_at` | timestamp | yes | | soft delete |

Indexes and constraints:

```text
unique(user_id)
index(status, engagement_type)
index(member_id)
index(supplier_member_id)
index(name)
index(engagement_type, status)
check(ends_on is null or starts_on is null or ends_on >= starts_on)
```

> `last_assigned_at` is a denormalised cache. It is updated inside the
> confirmation transaction and is never authoritative for scheduling.

### D.6 `crew_member_roles`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `crew_member_id` | FK crew_members | no | | `cascadeOnDelete` |
| `crew_role_id` | FK crew_roles | no | | `restrictOnDelete` |
| `proficiency` | string(16) | no | 'competent' | `CrewProficiency` |
| `is_primary` | boolean | no | false | |
| `years_experience` | tinyint | yes | | 0–80 |
| `created_at` / `updated_at` | timestamp | yes | | |

Indexes: `unique(crew_member_id, crew_role_id)`,
`index(crew_role_id, proficiency, is_primary)`.

### D.7 `crew_member_skills`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `crew_member_id` | FK crew_members | no | | `cascadeOnDelete` |
| `crew_skill_id` | FK crew_skills | no | | `restrictOnDelete` |
| `proficiency` | string(16) | no | 'competent' | `CrewProficiency` |
| `years_experience` | tinyint | yes | | |
| `verified_at` | datetime | yes | | |
| `verified_by` | FK users | yes | | `nullOnDelete` |
| `created_at` / `updated_at` | timestamp | yes | | |

Indexes: `unique(crew_member_id, crew_skill_id)`,
`index(crew_skill_id, proficiency)`.

### D.8 `crew_member_qualifications`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `crew_member_id` | FK crew_members | no | | `cascadeOnDelete` |
| `crew_qualification_id` | FK crew_qualifications | no | | `restrictOnDelete` |
| `reference_number` | string(80) | yes | | Certificate number |
| `issued_at` | date | yes | | |
| `expires_at` | date | yes | | null = no expiry |
| `verified_at` | datetime | yes | | |
| `verified_by` | FK users | yes | | `nullOnDelete` |
| `notes` | text | yes | | |
| `created_at` / `updated_at` | timestamp | yes | | |

Indexes: `index(crew_member_id, expires_at)`,
`index(crew_qualification_id, expires_at)`,
`unique(crew_member_id, crew_qualification_id, reference_number)`.

> `CrewQualificationStatus` is **derived**, never stored. It is computed from
> `expires_at`, `verified_at`, and `crew.qualification_expiry_warning_days`.

### D.9 `crew_role_requirements`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `crew_role_id` | FK crew_roles | no | | `cascadeOnDelete` |
| `requirement_type` | string(20) | no | | `CrewRequirementType` (skill/qualification) |
| `crew_skill_id` | FK crew_skills | yes | | `cascadeOnDelete` |
| `crew_qualification_id` | FK crew_qualifications | yes | | `cascadeOnDelete` |
| `is_mandatory` | boolean | no | true | |
| `min_proficiency` | string(16) | yes | | Applies to skills |
| `min_years_experience` | tinyint | yes | | |
| `created_at` / `updated_at` | timestamp | yes | | |

Constraints:

```text
index(crew_role_id, requirement_type)
check(
  (requirement_type = 'skill' and crew_skill_id is not null and crew_qualification_id is null)
  or
  (requirement_type = 'qualification' and crew_qualification_id is not null and crew_skill_id is null)
)
```

Both PostgreSQL and MySQL 8 enforce this CHECK. Validation must repeat it in the
Action so SQLite tests and error messages stay correct.

### D.10 `crew_rate_cards`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `store_id` | FK stores | yes | | `nullOnDelete`; null = company-wide |
| `crew_member_id` | FK crew_members | yes | | `cascadeOnDelete` |
| `crew_role_id` | FK crew_roles | yes | | `nullOnDelete` |
| `engagement_type` | string(24) | yes | | Optional scope |
| `currency_code` | char(3) | no | company base | ISO 4217 |
| `effective_from` | date | no | | |
| `effective_to` | date | yes | | null = open |
| `hourly_rate_minor` | bigint | yes | | |
| `daily_rate_minor` | bigint | yes | | |
| `fixed_rate_minor` | bigint | yes | | |
| `overtime_multiplier` | decimal(4,2) | no | 1.50 | |
| `weekend_multiplier` | decimal(4,2) | yes | | |
| `night_multiplier` | decimal(4,2) | yes | | |
| `holiday_multiplier` | decimal(4,2) | yes | | |
| `min_call_hours` | decimal(4,2) | yes | | |
| `daily_call_hours` | decimal(4,2) | yes | | Hours covered by a daily rate |
| `travel_rate_minor` | bigint | yes | | Per hour |
| `per_diem_minor` | bigint | yes | | Per day |
| `notes` | text | yes | | |
| `is_active` | boolean | no | true | |
| `created_by` | FK users | yes | | `nullOnDelete` |
| `created_at` / `updated_at` | timestamp | yes | | |
| `deleted_at` | timestamp | yes | | soft delete |

Indexes:

```text
index(crew_member_id, effective_from, effective_to)
index(crew_role_id, effective_from, effective_to)
index(store_id, effective_from, effective_to)
index(is_active)
check(effective_to is null or effective_to >= effective_from)
check(hourly_rate_minor is not null or daily_rate_minor is not null or fixed_rate_minor is not null)
```

> **PROPOSAL:** overlapping rate cards are rejected by an Action-level overlap
> query inside a transaction. A partial unique index is not portable. A future
> `crew_rate_card_locks` table is not recommended; a plain overlap check plus
> `lockForUpdate` is sufficient.

### D.11 `crew_requests`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `store_id` | FK stores | no | | `restrictOnDelete` |
| `project_id` | FK projects | no | | `restrictOnDelete` |
| `opportunity_id` | FK opportunities | yes | | `nullOnDelete`; optional job link |
| `venue_member_id` | FK members | yes | | `nullOnDelete` |
| `reference` | string(40) | yes | | Operator reference |
| `title` | string(160) | no | | e.g. "Main stage crew" |
| `status` | string(24) | no | 'draft' | `CrewRequestStatus` |
| `priority` | string(16) | no | 'normal' | `CrewRequestPriority` |
| `starts_at` | datetime | no | | UTC |
| `ends_at` | datetime | no | | UTC |
| `timezone` | string(64) | no | 'UTC' | IANA for display & rules |
| `location_name` | string(160) | yes | | |
| `notes` | text | yes | | |
| `requested_by` | FK users | yes | | `nullOnDelete` |
| `published_at` | datetime | yes | | |
| `approved_at` | datetime | yes | | |
| `approved_by` | FK users | yes | | `nullOnDelete` |
| `cancelled_at` | datetime | yes | | |
| `cancellation_reason` | text | yes | | |
| `created_at` / `updated_at` | timestamp | yes | | |
| `deleted_at` | timestamp | yes | | soft delete |

Indexes:

```text
index(project_id, status)
index(store_id, starts_at, ends_at)
index(status, starts_at)
index(opportunity_id)
unique(store_id, reference)
check(ends_at > starts_at)
```

### D.12 `crew_request_lines`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `crew_request_id` | FK crew_requests | no | | `cascadeOnDelete` |
| `crew_role_id` | FK crew_roles | no | | `restrictOnDelete` |
| `quantity` | smallint unsigned | no | | Requested headcount |
| `filled_quantity` | smallint unsigned | no | 0 | Maintained by Actions |
| `starts_at` | datetime | yes | | Line override, UTC |
| `ends_at` | datetime | yes | | Line override, UTC |
| `min_proficiency` | string(16) | yes | | |
| `notes` | text | yes | | |
| `sort_order` | smallint | no | 0 | |
| `created_at` / `updated_at` | timestamp | yes | | |

Indexes: `index(crew_request_id, crew_role_id)`, `index(crew_request_id)`.

Constraints:

```text
check(quantity > 0)
check(filled_quantity <= quantity)
check(ends_at is null or starts_at is null or ends_at > starts_at)
```

`filled_quantity` counts assignments in `confirmed` status only. Proposed and
invited assignments do not fill a line.

### D.13 `crew_request_line_requirements`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `crew_request_line_id` | FK crew_request_lines | no | | `cascadeOnDelete` |
| `requirement_type` | string(20) | no | | skill/qualification |
| `crew_skill_id` | FK crew_skills | yes | | `cascadeOnDelete` |
| `crew_qualification_id` | FK crew_qualifications | yes | | `cascadeOnDelete` |
| `is_mandatory` | boolean | no | true | |
| `min_proficiency` | string(16) | yes | | |
| `created_at` / `updated_at` | timestamp | yes | | |

Index: `index(crew_request_line_id, requirement_type)`. Same CHECK as
`crew_role_requirements`.

> Line requirements are **additive** to role requirements. A line inherits the
> role's requirements and may add stricter ones. It cannot relax a mandatory role
> requirement.

### D.14 `crew_assignments`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `crew_request_id` | FK crew_requests | no | | Denormalised; `cascadeOnDelete` |
| `crew_request_line_id` | FK crew_request_lines | no | | `cascadeOnDelete` |
| `crew_member_id` | FK crew_members | no | | `restrictOnDelete` |
| `project_id` | FK projects | no | | Denormalised; `restrictOnDelete` |
| `store_id` | FK stores | no | | Denormalised; `restrictOnDelete` |
| `status` | string(24) | no | 'proposed' | `CrewAssignmentStatus` |
| `active_assignment_key` | string(120) | yes | | Unique when active; null when terminal |
| `rate_card_id` | FK crew_rate_cards | yes | | `nullOnDelete` |
| `rate_type` | string(16) | no | 'hourly' | `CrewRateType` |
| `rate_amount_minor` | bigint | no | | **Snapshot** |
| `currency_code` | char(3) | no | | **Snapshot** |
| `exchange_rate_to_base` | decimal(16,6) | no | 1 | **Snapshot** |
| `overtime_multiplier` | decimal(4,2) | no | 1.50 | **Snapshot** |
| `min_call_hours` | decimal(4,2) | yes | | **Snapshot** |
| `daily_call_hours` | decimal(4,2) | yes | | **Snapshot**; hours covered by a daily rate |
| `allowances_snapshot` | json | yes | | **Snapshot** of named allowances |
| `cost_estimate_minor` | bigint | yes | | From planned shifts |
| `cost_estimate_breakdown` | json | yes | | Lines, multipliers, rules |
| `agreed_at` | datetime | yes | | |
| `agreed_by` | FK users | yes | | `nullOnDelete` |
| `invited_at` | datetime | yes | | |
| `responded_at` | datetime | yes | | |
| `confirmed_at` | datetime | yes | | |
| `cancelled_at` | datetime | yes | | |
| `cancellation_reason` | text | yes | | |
| `substitute_for_assignment_id` | FK crew_assignments | yes | | `nullOnDelete` |
| `requires_realignment` | boolean | no | false | Set by the project schedule impact review |
| `realignment_flagged_at` | datetime | yes | | |
| `realignment_reason` | text | yes | | Planner justification for keep/move |
| `created_by` | FK users | yes | | `nullOnDelete` |
| `created_at` / `updated_at` | timestamp | yes | | |
| `deleted_at` | timestamp | yes | | soft delete |

Indexes:

```text
unique(active_assignment_key)
index(crew_request_id, status)
index(crew_request_line_id, status)
index(crew_member_id, status)
index(project_id, status)
index(store_id, confirmed_at)
index(substitute_for_assignment_id)
index(project_id, requires_realignment)
check(rate_amount_minor >= 0)
```

`active_assignment_key` values:

```text
proposed | invited | confirmed   →  "line:{crew_request_line_id}:member:{crew_member_id}"
rejected | cancelled | substituted →  NULL
```

This gives a portable unique guarantee against duplicate assignments while
allowing a member to be re-proposed after rejection and allowing a substitute to
coexist with the superseded record.

### D.15 `crew_shifts`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `store_id` | FK stores | no | | `restrictOnDelete` |
| `project_id` | FK projects | no | | `restrictOnDelete` |
| `crew_assignment_id` | FK crew_assignments | yes | | `nullOnDelete`; null = open shift |
| `crew_member_id` | FK crew_members | yes | | `restrictOnDelete`; denormalised, null for open shifts |
| `venue_member_id` | FK members | yes | | `nullOnDelete` |
| `status` | string(24) | no | 'planned' | `CrewShiftStatus` |
| `type` | string(16) | no | 'show' | `CrewShiftType` |
| `starts_at` | datetime | no | | UTC |
| `ends_at` | datetime | no | | UTC |
| `timezone` | string(64) | no | 'UTC' | IANA |
| `break_minutes` | smallint unsigned | no | 0 | Unpaid by default |
| `is_overnight` | boolean | no | false | Derived from local dates |
| `location_name` | string(160) | yes | | |
| `notes` | text | yes | | |
| `sequence` | smallint | no | 0 | Ordering within assignment |
| `created_by` | FK users | yes | | `nullOnDelete` |
| `created_at` / `updated_at` | timestamp | yes | | |
| `deleted_at` | timestamp | yes | | soft delete |

Indexes:

```text
index(crew_assignment_id, starts_at)
index(crew_member_id, starts_at, ends_at)
index(project_id, starts_at, ends_at)
index(store_id, starts_at, ends_at)
index(starts_at, ends_at)
index(status, starts_at)
check(ends_at > starts_at)
check(break_minutes >= 0)
```

> `crew_member_id` is denormalised from the assignment so conflict detection can
> use a single-table index. It is written by `CreateCrewShift` and re-synced by
> `ReassignCrewAssignment` / `SubstituteCrewAssignment`; an observer asserts
> `crew_shifts.crew_member_id === crew_assignments.crew_member_id` on save.
>
> `break_minutes` must be less than the shift duration. This is enforced in the
> Action, not as a CHECK, because duration is derived.

### D.16 `crew_shift_breaks` (Phase 2, designed now)

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `crew_shift_id` | FK crew_shifts | no | | `cascadeOnDelete` |
| `starts_at` | datetime | no | | UTC |
| `ends_at` | datetime | no | | UTC |
| `is_paid` | boolean | no | false | |
| `label` | string(60) | yes | | "Meal", "Union break" |

Index: `index(crew_shift_id, starts_at)`, `check(ends_at > starts_at)`.

MVP uses `crew_shifts.break_minutes` and treats it as unpaid. Phase 2 migrates
existing values into a single unpaid break row per shift.

### D.17 `crew_availabilities`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `crew_member_id` | FK crew_members | no | | `cascadeOnDelete` |
| `type` | string(20) | no | 'unavailable' | `CrewAvailabilityType` |
| `status` | string(20) | no | 'requested' | `CrewAvailabilityStatus` |
| `all_day` | boolean | no | false | |
| `starts_at` | datetime | no | | UTC |
| `ends_at` | datetime | no | | UTC |
| `timezone` | string(64) | no | 'UTC' | IANA |
| `reason` | string(160) | yes | | |
| `notes` | text | yes | | |
| `requested_by` | FK users | yes | | `nullOnDelete` |
| `approved_at` | datetime | yes | | |
| `approved_by` | FK users | yes | | `nullOnDelete` |
| `created_at` / `updated_at` | timestamp | yes | | |

Indexes:

```text
index(crew_member_id, starts_at, ends_at)
index(status, starts_at)
check(ends_at > starts_at)
```

> **PROPOSAL:** an all-day request stores `starts_at` at 00:00 and `ends_at` at
> 23:59:59 in the **declared timezone**, converted to UTC. The `all_day` flag
> preserves intent for display; the conflict engine uses the UTC interval.

### D.18 `crew_timesheets`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `crew_assignment_id` | FK crew_assignments | no | | `restrictOnDelete` |
| `crew_member_id` | FK crew_members | no | | Denormalised; `restrictOnDelete` |
| `project_id` | FK projects | no | | Denormalised; `restrictOnDelete` |
| `store_id` | FK stores | no | | Denormalised; `restrictOnDelete` |
| `period_start` | date | no | | Inclusive |
| `period_end` | date | no | | Inclusive |
| `status` | string(20) | no | 'draft' | `CrewTimesheetStatus` |
| `total_minutes` | integer | no | 0 | Paid minutes |
| `total_pay_minor` | bigint | no | 0 | Snapshot at approval |
| `currency_code` | char(3) | no | | |
| `rounding_rule` | string(20) | yes | | Snapshot of the rule used |
| `overtime_breakdown` | json | yes | | Snapshot per day |
| `submitted_at` | datetime | yes | | |
| `submitted_by` | FK users | yes | | `nullOnDelete` |
| `approved_at` | datetime | yes | | |
| `approved_by` | FK users | yes | | `nullOnDelete` |
| `rejected_at` | datetime | yes | | |
| `rejected_by` | FK users | yes | | `nullOnDelete` |
| `rejection_reason` | text | yes | | |
| `locked_at` | datetime | yes | | |
| `exported_to_finance_at` | datetime | yes | | |
| `finance_reference` | string(80) | yes | | Finance-side id |
| `version` | smallint unsigned | no | 1 | Optimistic concurrency |
| `created_by` | FK users | yes | | `nullOnDelete` |
| `created_at` / `updated_at` | timestamp | yes | | |
| `deleted_at` | timestamp | yes | | soft delete |

Indexes:

```text
unique(crew_assignment_id, period_start, period_end)
index(crew_member_id, status)
index(project_id, status)
index(store_id, status)
index(status, period_end)
check(period_end >= period_start)
```

### D.19 `crew_time_entries`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `crew_timesheet_id` | FK crew_timesheets | no | | `cascadeOnDelete` |
| `crew_shift_id` | FK crew_shifts | yes | | `nullOnDelete` |
| `kind` | string(16) | no | 'work' | `CrewTimeEntryKind` |
| `work_date` | date | no | | Local date in timesheet timezone |
| `started_at` | datetime | no | | UTC |
| `ended_at` | datetime | no | | UTC |
| `break_minutes` | smallint unsigned | no | 0 | |
| `minutes` | integer | no | | Paid, after break & rounding |
| `is_overtime` | boolean | no | false | |
| `multiplier` | decimal(4,2) | yes | | Applied multiplier |
| `applied_rate_minor` | bigint | yes | | Snapshot |
| `amount_minor` | bigint | yes | | Snapshot |
| `currency_code` | char(3) | yes | | |
| `source` | string(16) | no | 'manual' | manual/import/clock |
| `notes` | text | yes | | |
| `created_by` | FK users | yes | | `nullOnDelete` |
| `created_at` / `updated_at` | timestamp | yes | | |

Indexes: `index(crew_timesheet_id, work_date)`, `index(crew_shift_id)`,
`index(crew_timesheet_id, kind)`.
`check(ended_at > started_at)`, `check(minutes >= 0)`.

### D.20 `crew_expenses`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | bigint PK | no | | |
| `store_id` | FK stores | no | | `restrictOnDelete` |
| `project_id` | FK projects | no | | `restrictOnDelete` |
| `crew_member_id` | FK crew_members | no | | `restrictOnDelete` |
| `crew_assignment_id` | FK crew_assignments | yes | | `nullOnDelete` |
| `category` | string(24) | no | | `CrewExpenseCategory` |
| `status` | string(20) | no | 'draft' | `CrewExpenseStatus` |
| `incurred_on` | date | no | | |
| `description` | string(255) | no | | |
| `amount_minor` | bigint | no | | Original currency |
| `currency_code` | char(3) | no | | |
| `exchange_rate` | decimal(16,6) | no | 1 | To base currency |
| `amount_base_minor` | bigint | no | | |
| `billable_to_client` | boolean | no | false | |
| `markup_percent` | decimal(5,2) | yes | | |
| `submitted_at` | datetime | yes | | |
| `submitted_by` | FK users | yes | | `nullOnDelete` |
| `approved_at` | datetime | yes | | |
| `approved_by` | FK users | yes | | `nullOnDelete` |
| `rejected_reason` | text | yes | | |
| `reimbursed_at` | datetime | yes | | |
| `finance_reference` | string(80) | yes | | |
| `created_at` / `updated_at` | timestamp | yes | | |
| `deleted_at` | timestamp | yes | | soft delete |

Indexes: `index(crew_member_id, status)`, `index(project_id, status)`,
`index(store_id, incurred_on)`, `check(amount_minor >= 0)`.

### D.21 Snapshot strategy (critical)

The brief requires historical compensation to remain stable. Three layers:

| Layer | Table | Mutability |
| --- | --- | --- |
| Policy | `crew_rate_cards` | Editable until used; effective-dated |
| Agreement | `crew_assignments.*_snapshot`, `rate_amount_minor`, `currency_code`, `overtime_multiplier`, `min_call_hours`, `allowances_snapshot` | Immutable after `confirmed` |
| Actuals | `crew_time_entries.applied_rate_minor`, `amount_minor`, `multiplier`, `crew_timesheets.total_pay_minor`, `rounding_rule`, `overtime_breakdown` | Immutable after `approved`/`locked` |

Rules (**PROPOSAL**, but required by the brief):

1. Confirming an assignment copies the resolved rate card into the assignment.
   Later rate-card edits do not touch confirmed assignments.
2. Renegotiating compensation never mutates a confirmed assignment. It cancels
   or substitutes it and creates a new assignment with a new snapshot. The old
   record and its snapshot remain readable.
3. Approving a timesheet freezes `total_pay_minor`, `rounding_rule`, and
   `overtime_breakdown`. Recalculation requires `RejectCrewTimesheet` first.
4. Locking a timesheet is terminal. Corrections happen through a new corrective
   timesheet or a Finance adjustment, never by editing locked rows.
5. Currency conversion is snapshotted at agreement/approval time; reports never
   re-convert historical amounts using today's rate.

### D.22 Migration order

```text
1. crew_roles
2. crew_skills
3. crew_qualifications
4. crew_members
5. crew_member_roles
6. crew_member_skills
7. crew_member_qualifications
8. crew_role_requirements
9. crew_rate_cards
10. crew_requests
11. crew_request_lines
12. crew_request_line_requirements
13. crew_assignments
14. crew_shifts
15. crew_availabilities
16. crew_timesheets
17. crew_time_entries
18. crew_expenses
19. crew_shift_breaks (Phase 2)
```

Every migration is additive. No existing table is altered. The only cross-domain
FKs are read-only references to `members`, `users`, `stores`, `projects`, and
`opportunities`.

---

## E. Enums, status transitions, and invalid transitions

### E.1 Enum inventory

All enums live in `App\Enums`, use a `string` backing value, `TitleCase` case
names, `label()`, and — where the value is a lifecycle — `canTransitionTo()`.
This matches `App\Enums\ProjectStatus`.

| Enum | Cases | Persisted |
| --- | --- | --- |
| `CrewEngagementType` | `Employee`, `Freelancer`, `Contractor`, `ExternalCrew` | yes |
| `CrewMemberStatus` | `Active`, `Inactive`, `Archived` | yes |
| `CrewProficiency` | `Trainee`, `Competent`, `Expert`, `Lead` | yes |
| `CrewRequirementType` | `Skill`, `Qualification` | yes |
| `CrewRequestStatus` | `Draft`, `Open`, `PartiallyFilled`, `Filled`, `Cancelled` | yes |
| `CrewRequestPriority` | `Low`, `Normal`, `High`, `Critical` | yes |
| `CrewAssignmentStatus` | `Proposed`, `Invited`, `Confirmed`, `Rejected`, `Cancelled`, `Substituted` | yes |
| `CrewShiftType` | `Setup`, `Show`, `Strike`, `Travel`, `Break`, `Standby`, `Other` | yes |
| `CrewShiftStatus` | `Planned`, `Confirmed`, `InProgress`, `Completed`, `Cancelled` | yes |
| `CrewAvailabilityType` | `Leave`, `Unavailable`, `Training`, `Sick`, `Other` | yes |
| `CrewAvailabilityStatus` | `Requested`, `Approved`, `Rejected`, `Cancelled` | yes |
| `CrewTimesheetStatus` | `Draft`, `Submitted`, `Approved`, `Rejected`, `Locked` | yes |
| `CrewTimeEntryKind` | `Work`, `Travel`, `Standby`, `Training`, `Other` | yes |
| `CrewRateType` | `Hourly`, `Daily`, `Fixed` | yes |
| `CrewExpenseCategory` | `Travel`, `Accommodation`, `PerDiem`, `Meals`, `Parking`, `Equipment`, `Other` | yes |
| `CrewExpenseStatus` | `Draft`, `Submitted`, `Approved`, `Rejected`, `Reimbursed` | yes |
| `CrewQualificationStatus` | `Valid`, `ExpiringSoon`, `Expired`, `PendingVerification` | **no (derived)** |
| `CrewConflictSeverity` | `Hard`, `Warning` | **no (derived)** |
| `CrewShortageStatus` | `Open`, `PartiallyCovered`, `Covered` | **no (derived)** |
| `CrewCostSource` | `RateCard`, `AssignmentSnapshot`, `Manual` | **no (derived)** |

### E.2 Status transition tables

#### `CrewMemberStatus`

| From | Allowed to | Notes |
| --- | --- | --- |
| `Active` | `Inactive`, `Archived` | Archiving requires no future confirmed assignment, or explicit override |
| `Inactive` | `Active`, `Archived` | |
| `Archived` | `Active` | Restore; history preserved |

Invalid: `Archived → Inactive` (restore first), any self-transition.

#### `CrewRequestStatus`

| From | Allowed to |
| --- | --- |
| `Draft` | `Open`, `Cancelled` |
| `Open` | `PartiallyFilled`, `Filled`, `Cancelled`, `Draft` |
| `PartiallyFilled` | `Filled`, `Open`, `Cancelled` |
| `Filled` | `PartiallyFilled`, `Open`, `Cancelled` |
| `Cancelled` | — (terminal) |

Derived rule: `PartiallyFilled`/`Filled` are computed from confirmed coverage
whenever an assignment changes. `Draft` is allowed from `Open` only while no
assignment exists in `proposed`/`invited`/`confirmed`.

Invalid: `Cancelled → Open`, `Filled → Draft` with active assignments,
`PartiallyFilled → Draft` with active assignments.

#### `CrewAssignmentStatus`

```text
Proposed ──▶ Invited ──▶ Confirmed ──▶ Substituted
    │            │            │
    │            ├──▶ Rejected│
    │            └──▶ Cancelled
    └──▶ Cancelled
Confirmed ──▶ Cancelled
```

| From | Allowed to |
| --- | --- |
| `Proposed` | `Invited`, `Cancelled` |
| `Invited` | `Confirmed`, `Rejected`, `Cancelled` |
| `Confirmed` | `Cancelled`, `Substituted` |
| `Rejected` | — (terminal) |
| `Cancelled` | — (terminal) |
| `Substituted` | — (terminal) |

Invalid: `Rejected → Confirmed`, `Cancelled → Confirmed`,
`Confirmed → Invited`, `Substituted → Confirmed`.

Clear `active_assignment_key` on `Rejected`, `Cancelled`, `Substituted`.

#### `CrewShiftStatus`

| From | Allowed to |
| --- | --- |
| `Planned` | `Confirmed`, `Cancelled` |
| `Confirmed` | `InProgress`, `Completed`, `Cancelled` |
| `InProgress` | `Completed`, `Cancelled` |
| `Completed` | — (terminal) |
| `Cancelled` | — (terminal) |

Invalid: `Completed → Confirmed`, `Cancelled → InProgress`.

Only `Confirmed` and `InProgress` shifts participate in hard conflicts. `Planned`
shifts produce warnings.

#### `CrewAvailabilityStatus`

| From | Allowed to |
| --- | --- |
| `Requested` | `Approved`, `Rejected`, `Cancelled` |
| `Approved` | `Cancelled` |
| `Rejected` | — (terminal) |
| `Cancelled` | — (terminal) |

Only `Approved` availability creates hard conflicts. `Requested` creates a
warning until decided.

#### `CrewTimesheetStatus`

| From | Allowed to |
| --- | --- |
| `Draft` | `Submitted` |
| `Submitted` | `Approved`, `Rejected` |
| `Rejected` | `Draft`, `Submitted` |
| `Approved` | `Locked` |
| `Locked` | — (terminal) |

Invalid: `Approved → Draft`, `Locked → *`, `Draft → Approved`,
`Submitted → Locked`.

**Approval is not payment.** `Locked` means "no further edits"; payment state is
`exported_to_finance_at` + `finance_reference`, owned by Finance.

#### `CrewExpenseStatus`

| From | Allowed to |
| --- | --- |
| `Draft` | `Submitted` |
| `Submitted` | `Approved`, `Rejected` |
| `Rejected` | `Draft` |
| `Approved` | `Reimbursed` |
| `Reimbursed` | — (terminal) |

Invalid: `Reimbursed → Approved`, `Approved → Draft`.

### E.3 Transition enforcement

**PROPOSAL:** a `CrewStatusRegistry` service mirrors the existing
`InvoiceStatusRegistry` pattern:

```php
final class CrewStatusRegistry
{
    /** @var array<class-string, list<string>> */
    private const KNOWN = [
        CrewRequest::class => ['draft', 'open', 'partially_filled', 'filled', 'cancelled'],
        CrewAssignment::class => ['proposed', 'invited', 'confirmed', 'rejected', 'cancelled', 'substituted'],
        CrewShift::class => ['planned', 'confirmed', 'in_progress', 'completed', 'cancelled'],
        CrewTimesheet::class => ['draft', 'submitted', 'approved', 'rejected', 'locked'],
        CrewExpense::class => ['draft', 'submitted', 'approved', 'rejected', 'reimbursed'],
    ];

    public function assertKnown(string $modelClass, string $status): void;

    public function assertCanTransitionTo(string $modelClass, string $from, string $to): void;
}
```

Every status-changing Action calls `assertCanTransitionTo()` **before** writing,
and the model enum remains the single source of truth for the graph.

### E.4 Derived statuses

| Derived value | Rule |
| --- | --- |
| `CrewQualificationStatus` | `expires_at < today` → `Expired`; `expires_at <= today + warning_days` → `ExpiringSoon`; `verified_at is null` → `PendingVerification`; else `Valid` |
| `CrewConflictSeverity` | See [I.2](#i2-conflict-classification) |
| `CrewShortageStatus` | `filled >= quantity` → `Covered`; `filled > 0` → `PartiallyCovered`; else `Open` |
| `CrewRequestStatus` | Recomputed by `RecalculateCrewRequestFill`; never set manually except `Draft`/`Open`/`Cancelled` |
| Assignment cost estimate | Recomputed by `RecalculateCrewAssignmentCost` from `crew_shifts` + snapshot |

---

## F. Actions, Queries, and DTOs

### F.1 Conventions

- One invokable class per operation: `public function __invoke(SomeData $data): SomeData`.
- `Gate::authorize()` is the first statement, as in `CreateProject`.
- Multi-row writes run inside `DB::transaction(...)`.
- Row locking uses `lockForUpdate()` on the rows whose invariants must hold.
- Validation failures throw `ValidationException::withMessages([...])` with the
  field key the UI binds to.
- Domain mutations dispatch `AuditableEvent` so audit + webhooks stay in one path.
- Actions never render, never read the request, and never return Eloquent models
  to the UI.
- Query objects are read-only and return DTOs, never models.

### F.2 Registry and configuration actions

| Action | Signature | Responsibility |
| --- | --- | --- |
| `CreateCrewRole` | `(CreateCrewRoleData): CrewRoleData` | Unique slug, normalise name, audit |
| `UpdateCrewRole` | `(CrewRole, UpdateCrewRoleData): CrewRoleData` | Cannot deactivate a role used by an open request |
| `DeleteCrewRole` | `(CrewRole): void` | Only when unused; otherwise deactivate |
| `SyncCrewRoleRequirements` | `(CrewRole, SyncRoleRequirementsData): CrewRoleData` | Replace requirement set transactionally |
| `CreateCrewSkill` | `(CreateCrewSkillData): CrewSkillData` | |
| `UpdateCrewSkill` | `(CrewSkill, UpdateCrewSkillData): CrewSkillData` | |
| `CreateCrewQualification` | `(CreateCrewQualificationData): CrewQualificationData` | Validity defaults |
| `UpdateCrewQualification` | `(CrewQualification, UpdateCrewQualificationData): CrewQualificationData` | Cannot shorten validity below existing `expires_at` without override |
| `CreateCrewMember` | `(CreateCrewMemberData): CrewMemberData` | Identity fallback, optional member/user links |
| `UpdateCrewMember` | `(CrewMember, UpdateCrewMemberData): CrewMemberData` | No identity writes to CRM |
| `ArchiveCrewMember` | `(CrewMember, ArchiveCrewMemberData): CrewMemberData` | Blocks future confirmed assignments |
| `RestoreCrewMember` | `(CrewMember): CrewMemberData` | |
| `LinkCrewMemberUser` | `(CrewMember, LinkCrewMemberUserData): CrewMemberData` | One login per crew member; detaches previous |
| `SyncCrewMemberRoles` | `(CrewMember, SyncCrewMemberRolesData): CrewMemberData` | At least one primary role |
| `SyncCrewMemberSkills` | `(CrewMember, SyncCrewMemberSkillsData): CrewMemberData` | |
| `AddCrewMemberQualification` | `(CrewMember, AddCrewMemberQualificationData): CrewMemberQualificationData` | Requires expiry when qualification requires it |
| `VerifyCrewMemberQualification` | `(CrewMemberQualification, VerifyCrewMemberQualificationData): CrewMemberQualificationData` | Records verifier + timestamp |
| `DeleteCrewMemberQualification` | `(CrewMemberQualification): void` | Hard delete allowed only when unverified and unused |
| `CreateCrewRateCard` | `(CreateCrewRateCardData): CrewRateCardData` | Overlap check |
| `UpdateCrewRateCard` | `(CrewRateCard, UpdateCrewRateCardData): CrewRateCardData` | Overlap check; confirmed snapshots untouched |
| `DeactivateCrewRateCard` | `(CrewRateCard): CrewRateCardData` | Sets `is_active = false`, closes `effective_to` |
| `ResolveCrewRate` | `(ResolveCrewRateData): ResolvedCrewRateData` | Pure resolution, no writes |

### F.3 Request actions

| Action | Signature | Responsibility |
| --- | --- | --- |
| `CreateCrewRequest` | `(CreateCrewRequestData): CrewRequestData` | Validate project/opportunity/store alignment |
| `UpdateCrewRequest` | `(CrewRequest, UpdateCrewRequestData): CrewRequestData` | Only `Draft`/`Open`; cannot shrink below filled |
| `PublishCrewRequest` | `(CrewRequest): CrewRequestData` | `Draft → Open`, sets `published_at`, notifies coordinators |
| `CancelCrewRequest` | `(CrewRequest, CancelCrewRequestData): CrewRequestData` | Cancels non-terminal assignments, keeps history |
| `AddCrewRequestLine` | `(CrewRequest, AddCrewRequestLineData): CrewRequestLineData` | Role + quantity + optional requirement overrides |
| `UpdateCrewRequestLine` | `(CrewRequestLine, UpdateCrewRequestLineData): CrewRequestLineData` | Blocks quantity < `filled_quantity` |
| `RemoveCrewRequestLine` | `(CrewRequestLine): void` | Only when no non-terminal assignment |
| `SyncCrewRequestLineRequirements` | `(CrewRequestLine, SyncLineRequirementsData): CrewRequestLineData` | Additive to role requirements |
| `RecalculateCrewRequestFill` | `(CrewRequest): CrewRequestData` | Recompute `filled_quantity` and status |
| `CloneCrewRequest` | `(CrewRequest, CloneCrewRequestData): CrewRequestData` | For repeat events; starts `Draft` |

### F.4 Assignment actions

| Action | Signature | Responsibility |
| --- | --- | --- |
| `ProposeCrewAssignment` | `(CrewRequestLine, ProposeCrewAssignmentData): CrewAssignmentData` | Validate availability, requirements, duplicates; snapshot rate; status `Proposed` |
| `InviteCrewAssignment` | `(CrewAssignment): CrewAssignmentData` | `Proposed → Invited`, sets `invited_at`, notifies member |
| `ConfirmCrewAssignment` | `(CrewAssignment): CrewAssignmentData` | Hard conflicts must be empty; locks row; fills line |
| `RejectCrewAssignment` | `(CrewAssignment, RejectCrewAssignmentData): CrewAssignmentData` | `Invited → Rejected`, reason required |
| `CancelCrewAssignment` | `(CrewAssignment, CancelCrewAssignmentData): CrewAssignmentData` | Cancels pending shifts, recomputes fill, notifies |
| `SubstituteCrewAssignment` | `(CrewAssignment, SubstituteCrewAssignmentData): CrewAssignmentData` | Supersedes original; new assignment inherits shifts optionally |
| `ReassignCrewAssignment` | `(CrewAssignment, ReassignCrewAssignmentData): CrewAssignmentData` | Move to another member in one transaction |
| `RecalculateCrewAssignmentCost` | `(CrewAssignment): CrewAssignmentData` | Recompute estimate from shifts + snapshot |
| `AcceptCrewAssignmentInvite` | `(CrewAssignment): CrewAssignmentData` | Self-service variant of confirm; same guards |
| `DeclineCrewAssignmentInvite` | `(CrewAssignment, DeclineCrewAssignmentData): CrewAssignmentData` | Self-service variant of reject |

### F.5 Shift actions

| Action | Signature | Responsibility |
| --- | --- | --- |
| `CreateCrewShift` | `(CrewAssignment\|CrewRequest, CreateCrewShiftData): CrewShiftData` | Validate range, breaks, timezone; conflict check |
| `UpdateCrewShift` | `(CrewShift, UpdateCrewShiftData): CrewShiftData` | Re-run conflicts; resnapshot cost |
| `ConfirmCrewShift` | `(CrewShift): CrewShiftData` | `Planned → Confirmed`; hard conflicts must be empty |
| `StartCrewShift` | `(CrewShift): CrewShiftData` | `Confirmed → InProgress` |
| `CompleteCrewShift` | `(CrewShift): CrewShiftData` | `Confirmed\|InProgress → Completed`; seeds time entry draft |
| `CancelCrewShift` | `(CrewShift, CancelCrewShiftData): CrewShiftData` | Notifies assigned member |
| `ReorderCrewShifts` | `(CrewAssignment, ReorderCrewShiftsData): list<CrewShiftData>` | Sequence only; no time changes |
| `BulkConfirmCrewShifts` | `(Project, BulkConfirmCrewShiftsData): BulkShiftResultData` | All-or-nothing per member, reports conflicts |
| `RealignAssignmentShifts` | `(CrewAssignment, RealignAssignmentShiftsData): list<CrewShiftData>` | **Explicit** response to project schedule change |
| `CopyCrewShiftsToAssignment` | `(CrewAssignment, CrewAssignment): list<CrewShiftData>` | Used by substitution |

### F.6 Availability actions

| Action | Signature | Responsibility |
| --- | --- | --- |
| `RequestCrewAvailability` | `(CrewMember, RequestCrewAvailabilityData): CrewAvailabilityData` | `Requested`; warns if overlaps confirmed shifts |
| `ApproveCrewAvailability` | `(CrewAvailability): CrewAvailabilityData` | Hard-conflict re-check; notifies member and planner |
| `RejectCrewAvailability` | `(CrewAvailability, RejectCrewAvailabilityData): CrewAvailabilityData` | Reason required |
| `CancelCrewAvailability` | `(CrewAvailability): CrewAvailabilityData` | |
| `SetCrewAvailability` | `(CrewMember, SetCrewAvailabilityData): CrewAvailabilityData` | Planner-entered `Approved` block |

### F.7 Timesheet actions

| Action | Signature | Responsibility |
| --- | --- | --- |
| `CreateCrewTimesheet` | `(CrewAssignment, CreateCrewTimesheetData): CrewTimesheetData` | Period uniqueness; seeds entries from shifts |
| `AddCrewTimeEntry` | `(CrewTimesheet, AddCrewTimeEntryData): CrewTimeEntryData` | Reject when not `Draft`/`Rejected` |
| `UpdateCrewTimeEntry` | `(CrewTimeEntry, UpdateCrewTimeEntryData): CrewTimeEntryData` | Same guard |
| `RemoveCrewTimeEntry` | `(CrewTimeEntry): void` | Same guard |
| `SubmitCrewTimesheet` | `(CrewTimesheet): CrewTimesheetData` | `Draft|Rejected → Submitted`; recalculate; notify approver |
| `ApproveCrewTimesheet` | `(CrewTimesheet, ApproveCrewTimesheetData): CrewTimesheetData` | Freeze totals; `Submitted → Approved` |
| `RejectCrewTimesheet` | `(CrewTimesheet, RejectCrewTimesheetData): CrewTimesheetData` | Reason required; `Submitted → Rejected` |
| `LockCrewTimesheet` | `(CrewTimesheet): CrewTimesheetData` | `Approved → Locked` |
| `RecalculateCrewTimesheet` | `(CrewTimesheet): CrewTimesheetData` | Pure recompute; only when not locked |
| `ExportCrewTimesheetToFinance` | `(CrewTimesheet, ExportToFinanceData): CrewTimesheetData` | Emits hand-off; writes `finance_reference` |

### F.8 Expense actions (Phase 2)

| Action | Signature | Responsibility |
| --- | --- | --- |
| `CreateCrewExpense` | `(CrewMember, CreateCrewExpenseData): CrewExpenseData` | Currency conversion snapshot |
| `UpdateCrewExpense` | `(CrewExpense, UpdateCrewExpenseData): CrewExpenseData` | Only `Draft`/`Rejected` |
| `SubmitCrewExpense` | `(CrewExpense): CrewExpenseData` | |
| `ApproveCrewExpense` | `(CrewExpense): CrewExpenseData` | |
| `RejectCrewExpense` | `(CrewExpense, RejectCrewExpenseData): CrewExpenseData` | |
| `MarkCrewExpenseReimbursed` | `(CrewExpense, MarkReimbursedData): CrewExpenseData` | Finance reference required |

### F.9 Planning actions

| Action | Signature | Responsibility |
| --- | --- | --- |
| `DetectCrewConflicts` | `(DetectCrewConflictsData): CrewConflictReportData` | Read-only; returns hard + warning conflicts |
| `MatchCrewCandidates` | `(CrewRequestLine, MatchCrewCandidatesData): CandidateMatchCollectionData` | Ranked candidates with blockers |
| `BuildCrewCoverageReport` | `(Project): CrewCoverageData` | Per-line coverage + shortages |
| `BuildCrewCostForecast` | `(Project): CrewCostForecastData` | Confirmed + proposed cost, by role |
| `BuildCrewUtilisationReport` | `(UtilisationFilterData): CrewUtilisationData` | Per member/period |
| `BuildCrewWorkHistory` | `(CrewMember, WorkHistoryFilterData): CrewWorkHistoryData` | Assignments, hours, projects |
| `FindExpiringCrewQualifications` | `(ExpiringQualificationsData): list<CrewMemberQualificationData>` | Campaign source |
| `AcknowledgeCrewConflict` | `(AcknowledgeCrewConflictData): void` | Records warning override with reason + actor |

### F.10 Query objects

| Query | Method | Returns |
| --- | --- | --- |
| `CrewAvailabilityQuery` | `windowsFor(int $crewMemberId, CarbonImmutable $from, CarbonImmutable $to): CrewAvailabilityWindowData` | Merged busy/free windows from availability + shifts + assignments |
| `CrewConflictQuery` | `detect(int $crewMemberId, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?int $ignoreShiftId = null): CrewConflictReportData` | Hard + warnings |
| `CrewCandidateQuery` | `forLine(CrewRequestLine $line, MatchCrewCandidatesData $filters): Collection<int, CandidateMatchData>` | Ranked candidates |
| `CrewCoverageQuery` | `forRequest(CrewRequest $request): CrewCoverageData` | Line coverage |
| `CrewShortageQuery` | `forProject(Project $project): Collection<int, CrewShortageData>` | Unfilled lines |
| `CrewCalendarQuery` | `forWindow(CrewCalendarFilterData $filter): Collection<int, CrewCalendarRowData>` | Member rows with shifts |
| `CrewUtilisationQuery` | `forPeriod(UtilisationFilterData $filter): CrewUtilisationData` | Utilisation |
| `CrewCostForecastQuery` | `forProject(Project $project): CrewCostForecastData` | Cost |
| `CrewWorkHistoryQuery` | `forMember(int $crewMemberId, WorkHistoryFilterData $filter): CrewWorkHistoryData` | History |
| `CrewExpiringQualificationQuery` | `dueWithin(int $days): Collection<int, CrewMemberQualificationData>` | Expiry list |
| `CrewTimesheetSummaryQuery` | `forApprovalQueue(TimesheetFilterData $filter): LengthAwarePaginator` | Approval queue |

### F.11 DTO inventory

DTOs are `Spatie\LaravelData\Data` classes with `fromModel()` where a model is
the source. Naming follows the existing `CreateXData` / `UpdateXData` / `XData`
split.

**Registry**

```text
CrewRoleData, CreateCrewRoleData, UpdateCrewRoleData
CrewSkillData, CreateCrewSkillData, UpdateCrewSkillData
CrewQualificationData, CreateCrewQualificationData, UpdateCrewQualificationData
CrewRoleRequirementData, SyncRoleRequirementsData
CrewMemberData, CreateCrewMemberData, UpdateCrewMemberData, ArchiveCrewMemberData
CrewMemberRoleData, SyncCrewMemberRolesData
CrewMemberSkillData, SyncCrewMemberSkillsData
CrewMemberQualificationData, AddCrewMemberQualificationData, VerifyCrewMemberQualificationData
LinkCrewMemberUserData
CrewRateCardData, CreateCrewRateCardData, UpdateCrewRateCardData
ResolveCrewRateData, ResolvedCrewRateData
```

**Requests and assignments**

```text
CrewRequestData, CreateCrewRequestData, UpdateCrewRequestData,
    CancelCrewRequestData, CloneCrewRequestData
CrewRequestLineData, AddCrewRequestLineData, UpdateCrewRequestLineData,
    SyncLineRequirementsData
CrewAssignmentData, ProposeCrewAssignmentData, RejectCrewAssignmentData,
    CancelCrewAssignmentData, SubstituteCrewAssignmentData,
    ReassignCrewAssignmentData, DeclineCrewAssignmentData
```

**Shifts and availability**

```text
CrewShiftData, CreateCrewShiftData, UpdateCrewShiftData,
    CancelCrewShiftData, ReorderCrewShiftsData, BulkConfirmCrewShiftsData,
    BulkShiftResultData, RealignAssignmentShiftsData
CrewAvailabilityData, RequestCrewAvailabilityData,
    RejectCrewAvailabilityData, SetCrewAvailabilityData,
    CrewAvailabilityWindowData
```

**Timesheets and expenses**

```text
CrewTimesheetData, CreateCrewTimesheetData, CrewTimeEntryData,
    AddCrewTimeEntryData, UpdateCrewTimeEntryData,
    ApproveCrewTimesheetData, RejectCrewTimesheetData, ExportToFinanceData
CrewExpenseData, CreateCrewExpenseData, UpdateCrewExpenseData,
    RejectCrewExpenseData, MarkReimbursedData
```

**Reports and planning**

```text
CandidateMatchData, CandidateMatchCollectionData
CrewConflictData, CrewConflictReportData, AcknowledgeCrewConflictData
CrewCoverageData, CrewCoverageLineData
CrewShortageData
CrewCalendarRowData, CrewCalendarFilterData
CrewUtilisationData, CrewUtilisationRowData, UtilisationFilterData
CrewCostForecastData, CrewCostForecastLineData
CrewWorkHistoryData, CrewWorkHistoryRowData, WorkHistoryFilterData
ExpiringQualificationsData
```

### F.12 Representative signatures

```php
final class ProposeCrewAssignment
{
    public function __invoke(
        CrewRequestLine $line,
        ProposeCrewAssignmentData $data,
    ): CrewAssignmentData {
        Gate::authorize('create', CrewAssignment::class);

        $member = CrewMember::query()->findOrFail($data->crew_member_id);
        $request = $line->request()->firstOrFail();

        Gate::authorize('view', $request);
        $this->assertRequestIsStaffable($request);
        $this->assertMemberIsActive($member);

        return DB::transaction(function () use ($line, $member, $request, $data): CrewAssignmentData {
            $lockedLine = CrewRequestLine::query()->lockForUpdate()->findOrFail($line->id);

            $this->assertNoActiveDuplicate($lockedLine, $member);
            $this->assertRequirementsMet($lockedLine, $member);

            $conflicts = app(CrewConflictQuery::class)
                ->detect($member->id, $data->starts_at, $data->ends_at);

            if ($conflicts->hardCount() > 0) {
                throw ValidationException::withMessages([
                    'crew_member_id' => $conflicts->hardMessages(),
                ]);
            }

            $rate = app(ResolveCrewRate::class)(new ResolveCrewRateData(
                crewMemberId: $member->id,
                crewRoleId: $lockedLine->crew_role_id,
                storeId: $request->store_id,
                onDate: $data->starts_at->toDateString(),
                currencyCode: $data->currency_code,
            ));

            $assignment = CrewAssignment::create([
                'crew_request_id' => $request->id,
                'crew_request_line_id' => $lockedLine->id,
                'crew_member_id' => $member->id,
                'project_id' => $request->project_id,
                'store_id' => $request->store_id,
                'status' => CrewAssignmentStatus::Proposed,
                'active_assignment_key' => $this->activeKey($lockedLine->id, $member->id),
                'rate_card_id' => $rate->rate_card_id,
                'rate_type' => $rate->rate_type,
                'rate_amount_minor' => $rate->amount_minor,
                'currency_code' => $rate->currency_code,
                'exchange_rate_to_base' => $rate->exchange_rate,
                'overtime_multiplier' => $rate->overtime_multiplier,
                'min_call_hours' => $rate->min_call_hours,
                'allowances_snapshot' => $rate->allowances,
                'created_by' => auth()->id(),
            ]);

            AuditableEvent::dispatch($assignment, 'crew.assignment.proposed', newValues: [...]);

            return CrewAssignmentData::fromModel($assignment->refresh());
        });
    }
}
```

```php
final class ConfirmCrewAssignment
{
    public function __invoke(CrewAssignment $assignment): CrewAssignmentData
    {
        Gate::authorize('confirm', $assignment);

        return DB::transaction(function () use ($assignment): CrewAssignmentData {
            $locked = CrewAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            app(CrewStatusRegistry::class)->assertCanTransitionTo(
                CrewAssignment::class,
                $locked->status->value,
                CrewAssignmentStatus::Confirmed->value,
            );

            $this->assertRequirementsStillMet($locked);
            $this->assertNoHardConflicts($locked);

            $locked->forceFill([
                'status' => CrewAssignmentStatus::Confirmed,
                'confirmed_at' => now('UTC'),
                'responded_at' => $locked->responded_at ?? now('UTC'),
                'agreed_at' => $locked->agreed_at ?? now('UTC'),
                'agreed_by' => $locked->agreed_by ?? auth()->id(),
            ])->save();

            CrewShift::query()
                ->where('crew_assignment_id', $locked->id)
                ->where('status', CrewShiftStatus::Planned)
                ->update(['status' => CrewShiftStatus::Confirmed]);

            app(RecalculateCrewRequestFill::class)($locked->request()->firstOrFail());

            AuditableEvent::dispatch($locked, 'crew.assignment.confirmed', ...);

            return CrewAssignmentData::fromModel($locked->refresh());
        });
    }
}
```

### F.13 Action authorisation matrix

| Action group | Permission | Policy |
| --- | --- | --- |
| Registry CRUD | `crew.create` / `crew.edit` / `crew.delete` | `CrewMemberPolicy`, `CrewRolePolicy`, … |
| Rate cards | `crew.rates.manage` | `CrewRateCardPolicy` (separate from member edit) |
| Requests | `crew.requests.*` | `CrewRequestPolicy` |
| Assignments | `crew.assignments.*` | `CrewAssignmentPolicy` |
| Shifts | `crew.shifts.*` | `CrewShiftPolicy` |
| Availability | `crew.availability.*`, `crew.availability.approve` | `CrewAvailabilityPolicy` |
| Timesheets | `crew.timesheets.*`, `crew.timesheets.approve` | `CrewTimesheetPolicy` |
| Expenses | `crew.expenses.*`, `crew.expenses.approve` | `CrewExpensePolicy` |
| Reports | `crew.reports.view` | Gate check in page/controller |

---

## G. Livewire pages, components, forms, and workflow screens

### G.1 Route map (web)

Crew routes are registered in `app/Domain/Crew/Presentation/Routes/web.php` with
`Route::livewire()` and named PHP component classes from `Presentation/Livewire`.
The existing `signals.setup-complete`, `auth`, `2fa`, `signals.session-timeout`,
`module:crew` and `can:crew.access` middleware remain in place.

| Route | Component | Name | Purpose |
| --- | --- | --- | --- |
| `crew` | `Dashboard` | `crew.dashboard` | Coverage, today's calls and approvals |
| `crew/members` | `Members\Index` | `crew.members.index` | Registry |
| `crew/members/create` | `Members\Form` | `crew.members.create` | Create member |
| `crew/members/{crewMember}/edit` | `Members\Form` | `crew.members.edit` | Edit member |
| `crew/members/{crewMember}` | `Members\Show` | `crew.members.show` | Registry details |
| `crew/roles` | `Configuration` | `crew.roles.index` | Role and requirement configuration |
| `crew/skills` | `Configuration` | `crew.skills.index` | Skill configuration |
| `crew/qualifications` | `Configuration` | `crew.qualifications.index` | Qualification configuration |
| `crew/rates` | `Rates` | `crew.rates.index` | Effective-dated rates |
| `crew/requests` | `Requests\Index` | `crew.requests.index` | Request board |
| `crew/requests/{crewRequest}` | `Requests\Show` | `crew.requests.show` | Lines, coverage and candidates |
| `crew/planner` | `Planner` | `crew.planner` | Staffing coverage |
| `crew/calendar` | `Calendar` | `crew.calendar` | Weekly member schedule |
| `crew/assignments/{crewAssignment}` | `Assignments\Show` | `crew.assignments.show` | Shifts, substitution, realignment and timesheets |
| `crew/timesheets` | `Timesheets\Index` | `crew.timesheets.index` | Timesheets and approvals |
| `crew/timesheets/{crewTimesheet}` | `Timesheets\Show` | `crew.timesheets.show` | Entry editor and approval |

Expense/report screens and the self-service portal remain the extensions listed in
the implementation status. API and domain action functionality is separate from
those planned screens.

API routes are listed in [H.6](#h6-rest-api-surface).

### G.2 Page specifications

#### `crew.dashboard`

- **Header KPIs:** open requests, confirmed assignments this week, unfilled
  headcount, expiring qualifications (30 days), pending timesheet approvals,
  pending expense approvals.
- **Sections:** today's calls (grouped by store), shortages by project, approval
  inbox, upcoming leave, qualification expiry.
- **Actions:** link to request, approve timesheet, publish request.
- **Data sources:** `CrewCoverageQuery`, `CrewShortageQuery`,
  `CrewExpiringQualificationQuery`, `CrewTimesheetSummaryQuery`.
- **Authorization:** `crew.access` + `crew.view`; approval widgets hidden without
  the approve permissions.

#### `crew.members.index`

- **Pattern:** mirror `projects.index` — compact DataTable with server-backed
  scopes (Active, Inactive, Archived, All), URL state, search, sorting, and a
  Dense side panel that creates/edits without losing list context.
- **Columns:** name, engagement type, primary role, status, primary skills,
  qualification status, home timezone, upcoming assignments.
- **Filters:** engagement type, status, role, skill, qualification, availability
  window, store reachability, has login.
- **Row actions:** view, edit, archive, restore, invite to login, add
  qualification.
- **Column registry:** `CrewMemberColumnRegistry` in `app/Domain/Crew/Views`.

#### `crew.members.show` (tabs)

| Tab | Content | Permission |
| --- | --- | --- |
| Overview | Identity, engagement, status, notes, links to CRM/user | `crew.view` |
| Roles & skills | Proficiency matrix, primary role | `crew.edit` |
| Qualifications | Certificate list with derived status and documents | `crew.view`; documents gated |
| Rates | Rate card history and effective dates | `crew.rates.view` |
| Availability | Declared leave and conflict warnings | `crew.availability.view` |
| Assignments | Upcoming and historical assignments | `crew.view` |
| Timesheets | Periods and approval status | `crew.timesheets.view` |
| Expenses | Claims | `crew.expenses.view` |
| Work history | Projects, hours, utilisation | `crew.reports.view` |
| Files | Attachments | `attachments` policy |

#### `crew.requests.index` and `crew.requests.show`

- **Index:** grouped by project and status; coverage bar per line; filters by
  project, store, status, priority, role, period, shortage.
- **Detail:** header (project, opportunity, period, timezone, status, venue),
  line table with requested/filled/remaining, requirement chips, candidate
  matcher panel, assignment list with status pills, and explicit
  `Publish`/`Cancel` actions.
- **Workflow:** Draft → Publish (Open) → line staffing → automatic
  PartiallyFilled/Filled. Filled is never set by hand.
- **Candidate panel:** calls `MatchCrewCandidates`; shows blockers (hard) and
  warnings; propose/invite inline; never auto-confirms.

#### `crew.planner`

- **Visual:** horizontal timeline of projects, lanes per role line, cards per
  assignment, coverage colouring.
- **Interactions:** open request, drag a candidate onto a line (proposal only),
  click a card to open assignment, toggle store filter, toggle "show shortages".
- **Rules:** dragging never confirms; it creates a `Proposed` assignment and
  re-runs conflicts. A blocked drop shows the hard-conflict reason.
- **Implementation:** Blade + Alpine for drag affordances; server state in named Livewire component classes.
  Reuse the existing planner shell conventions (`app/Livewire/Planner`,
  `resources/views/livewire/planner`) where practical.

#### `crew.calendar`

- **Visual:** month/week grid per crew member, with shift blocks, leave blocks,
  and conflict highlights.
- **Filters:** store, role, skill, qualification, availability, project.
- **Interactions:** create availability block (planner), open assignment,
  jump to timesheet.
- **Timezone:** each row renders in the member's `home_timezone`; the header can
  switch to the project timezone. All timestamps remain UTC server-side.

#### `crew.assignments.show`

- **Header:** member, request line, project, status pill, agreed rate snapshot
  (permission-gated), cost estimate.
- **Shifts:** ordered list with type, local start/end, duration, break, overnight
  badge, conflict badge; add/edit/confirm/cancel.
- **Timesheets:** periods, status, totals, link to editor.
- **Actions:** invite, confirm, reject, cancel, substitute, realign shifts.
- **Audit:** inline recent activity from `ActionLog`.

#### `crew.timesheets.index` and `crew.timesheets.show`

- **Index:** approval queue with status, member, project, period, total hours,
  total pay (permission-gated), submitted by/at.
- **Editor:** rows per date, entry kind, start/end, break, computed minutes,
  overtime flag, multiplier, amount; totals panel; variance vs planned shifts.
- **Approval:** approve/reject with reason, then lock. Approved totals are
  read-only.
- **Self-service:** `/crew/me/timesheets/{id}` uses the same editor with a
  restricted policy.

### G.3 Livewire component inventory

Class-based components under `app/Domain/Crew/Presentation/Livewire/` extend the
shared `CrewComponent`, which extends `Livewire\Component`. The shared access
concern checks the Crew module and access permission on every component request.

| Component | Responsibility |
| --- | --- |
| `Dashboard` | Crew operational overview |
| `Members\Index`, `Members\Form`, `Members\Show` | Registry, identity links, skills, roles, qualifications and availability |
| `Configuration` | Roles, skills, qualifications and role requirements |
| `Rates` | Effective-dated rate cards |
| `Requests\Index`, `Requests\Show` | Requests, role lines, candidate matching and proposals |
| `Assignments\Show` | Assignment lifecycle, shifts, substitution, explicit realignment and timesheets |
| `Timesheets\Index`, `Timesheets\Show` | Actual time editing and approval lifecycle |
| `Planner`, `Calendar` | Coverage board and weekly member schedule |

Each class renders a plain Blade view through the `crew::` view namespace.
Views and page-local partials live in `app/Domain/Crew/Presentation/Views/`;
Blade files do not declare anonymous Livewire/Volt classes. HTTP controllers,
Form Requests and route files live in the same Presentation layer.

### G.4 Form and validation conventions

- Validation lives in the class-based Livewire component (`$this->validate([...])`) and is
  repeated defensively in the Action for API callers.
- Field keys map to the DTO property names, exactly as `projects.index` maps
  `projectName` → `name` before building `CreateProjectData`.
- Date/time inputs are local to the displayed timezone. The shared
  `ChecksCrewAccess::crewRangeToUtc()` concern uses `StaffingGuard::dates()` to
  reject nonexistent local times and convert accepted ranges to UTC DTO values.
- Money inputs use the existing money input component and convert to minor units
  at the DTO boundary.
- Validation exceptions from Actions are mapped back to component field names,
  matching the `projects.index` pattern.

### G.5 Navigation

**CONFIRMED:** the header already has a `Crew` placeholder under Services.
**PROPOSAL:** replace the placeholder with `route('crew.dashboard')` behind
`@can('crew.access')` and `settings()->moduleEnabled('crew')`, and add the
Services sub-items: Crew, Requests, Planner, Timesheets, Reports.

### G.6 Column registries

Add to `app/Domain/Crew/Views` following `ProjectColumnRegistry` / `MemberColumnRegistry`:

```text
CrewMemberColumnRegistry
CrewRequestColumnRegistry
CrewAssignmentColumnRegistry
CrewShiftColumnRegistry
CrewTimesheetColumnRegistry
CrewExpenseColumnRegistry
```

Each implements the existing `ColumnRegistry` contract so the DataTable,
CSV export, and custom views work without new infrastructure.

---

## H. Domain events and cross-domain integrations

### H.1 Event conventions (CONFIRMED, inherited)

Crew uses the existing audit bus, not event sourcing.

| Mechanism | File | Behaviour |
| --- | --- | --- |
| `AuditableEvent` | `app/Events/AuditableEvent.php` | Carries the model, an `action` string, old/new values, metadata |
| `LogAction` | `app/Listeners/LogAction.php` | Writes `action_logs` rows |
| `DispatchWebhookForAuditableEvent` | `app/Listeners/DispatchWebhookForAuditableEvent.php` | Emits outbound webhooks, but **only** for `OWNED_MODELS` (`Opportunity`, `Invoice`, `ShortageResolution`, `ShortageWaitlistMonitor`) |
| `NotificationDispatchListener` | `app/Listeners/NotificationDispatchListener.php` | Maps `action` → notification types through `EventNotificationMapRegistry` + `config/notifications.php` |

> **Important integration constraint (CONFIRMED):** because
> `DispatchWebhookForAuditableEvent` ignores any model outside `OWNED_MODELS`,
> Crew actions must follow the **direct-dispatch** pattern used by non-owned
> entities (tax, member, product, role): dispatch the webhook from the Action via
> `WebhookService` with a full entity-keyed payload. Adding Crew models to
> `OWNED_MODELS` would give a lean payload and duplicate deliveries.
>
> **PROPOSAL:** keep `AuditableEvent` as the single audit call, and call
> `WebhookService` explicitly in the same transaction boundary for the actions
> listed in [H.7](#h7-outbound-webhook-catalogue).

Event naming is `crew.<entity>.<past_tense_verb>`, lowercase with dots, exactly
matching how `opportunity.converted_to_order` and `shortage.resolution.created`
are named today. The audit action string **is** the webhook event name.

### H.2 Event catalogue

| Action string | Model | Fired by | Payload highlights |
| --- | --- | --- | --- |
| `crew.member.created` | `CrewMember` | `CreateCrewMember` | engagement type, status, linked member/user |
| `crew.member.updated` | `CrewMember` | `UpdateCrewMember` | changed fields |
| `crew.member.archived` | `CrewMember` | `ArchiveCrewMember` | reason, future assignment count |
| `crew.member.restored` | `CrewMember` | `RestoreCrewMember` | — |
| `crew.member.user_linked` | `CrewMember` | `LinkCrewMemberUser` | previous/new `user_id` |
| `crew.role.created` / `.updated` / `.deleted` | `CrewRole` | registry actions | name, slug, active |
| `crew.skill.created` / `.updated` | `CrewSkill` | registry actions | name, category |
| `crew.qualification.created` / `.updated` | `CrewQualification` | registry actions | validity, mandatory flag |
| `crew.member_qualification.added` | `CrewMemberQualification` | `AddCrewMemberQualification` | qualification, expiry |
| `crew.member_qualification.verified` | `CrewMemberQualification` | `VerifyCrewMemberQualification` | verifier, verified_at |
| `crew.rate_card.created` / `.updated` / `.deactivated` | `CrewRateCard` | rate actions | scope, effective window, rate type |
| `crew.request.created` | `CrewRequest` | `CreateCrewRequest` | project, store, period, line count |
| `crew.request.updated` | `CrewRequest` | `UpdateCrewRequest` | changed fields |
| `crew.request.published` | `CrewRequest` | `PublishCrewRequest` | period, total headcount |
| `crew.request.cancelled` | `CrewRequest` | `CancelCrewRequest` | reason, cancelled assignment count |
| `crew.request.filled` | `CrewRequest` | `RecalculateCrewRequestFill` | derived when coverage reaches 100% |
| `crew.request.line_added` / `.line_updated` / `.line_removed` | `CrewRequestLine` | line actions | role, quantity, filled |
| `crew.assignment.proposed` | `CrewAssignment` | `ProposeCrewAssignment` | member, line, rate snapshot |
| `crew.assignment.invited` | `CrewAssignment` | `InviteCrewAssignment` | invited_at |
| `crew.assignment.confirmed` | `CrewAssignment` | `ConfirmCrewAssignment` | confirmed_at, rate snapshot, cost estimate |
| `crew.assignment.rejected` | `CrewAssignment` | `RejectCrewAssignment` | reason |
| `crew.assignment.cancelled` | `CrewAssignment` | `CancelCrewAssignment` | reason, cancelled shift count |
| `crew.assignment.substituted` | `CrewAssignment` | `SubstituteCrewAssignment` | substitute assignment id |
| `crew.assignment.reassigned` | `CrewAssignment` | `ReassignCrewAssignment` | previous/new member |
| `crew.shift.created` / `.updated` | `CrewShift` | shift actions | assignment, UTC range, type, status |
| `crew.shift.confirmed` | `CrewShift` | `ConfirmCrewShift` | range, conflicts cleared |
| `crew.shift.started` | `CrewShift` | `StartCrewShift` | actual start |
| `crew.shift.completed` | `CrewShift` | `CompleteCrewShift` | actual end, seed time entry id |
| `crew.shift.cancelled` | `CrewShift` | `CancelCrewShift` | reason, notice hours |
| `crew.shift.realigned` | `CrewShift` | `RealignAssignmentShifts` | old/new range, reason, actor |
| `crew.availability.requested` | `CrewAvailability` | `RequestCrewAvailability` | type, UTC window |
| `crew.availability.approved` | `CrewAvailability` | `ApproveCrewAvailability` | approver, window |
| `crew.availability.rejected` / `.cancelled` | `CrewAvailability` | availability actions | reason |
| `crew.timesheet.submitted` | `CrewTimesheet` | `SubmitCrewTimesheet` | period, total minutes |
| `crew.timesheet.approved` | `CrewTimesheet` | `ApproveCrewTimesheet` | frozen `total_pay_minor`, rounding rule |
| `crew.timesheet.rejected` | `CrewTimesheet` | `RejectCrewTimesheet` | reason |
| `crew.timesheet.locked` | `CrewTimesheet` | `LockCrewTimesheet` | locked_at |
| `crew.timesheet.exported_to_finance` | `CrewTimesheet` | `ExportCrewTimesheetToFinance` | `finance_reference`, amount |
| `crew.expense.submitted` / `.approved` / `.rejected` / `.reimbursed` | `CrewExpense` | expense actions | category, amount, reference |
| `crew.conflict.acknowledged` | `CrewShift` | `AcknowledgeCrewConflict` | severity, codes, reason |
| `crew.shortage.detected` | `CrewRequestLine` | shortage scan | unfilled quantity, days to start |

### H.3 Notification keys

**PROPOSAL:** add a `crewTypes()` group to
`database/seeders/NotificationTypeSeeder.php` (the existing `types()` method
already composes private per-domain groups) and map them in
`config/notifications.php` under `event_map`.

| Notification type key | Audience | Default channels | Recipients |
| --- | --- | --- | --- |
| `crew.assignment.invited` | External (crew) | `mail` | assigned crew member's email |
| `crew.assignment.confirmed` | Internal + crew | `database`, `mail` | assigned member, request owner |
| `crew.assignment.cancelled` | Internal + crew | `database`, `mail` | assigned member, request owner |
| `crew.assignment.substituted` | Internal | `database` | request owner, replaced member |
| `crew.assignment.invite_reminder` | External | `mail` | assigned crew member |
| `crew.request.published` | Internal | `database` | users with `crew.assignments.create` in the store |
| `crew.request.filled` | Internal | `database` | request owner |
| `crew.availability.requested` | Internal | `database` | users with `crew.availability.approve` |
| `crew.availability.approved` / `.rejected` | External | `mail` | crew member |
| `crew.shift.changed` | External | `mail` | assigned member (call time moved) |
| `crew.shift.cancelled` | External | `mail` | assigned member |
| `crew.timesheet.submitted` | Internal | `database` | users with `crew.timesheets.approve` |
| `crew.timesheet.approved` / `.rejected` | External | `mail` | crew member |
| `crew.qualification.expiring` | Internal | `database` | crew manager + member (if user) |
| `crew.qualification.expired` | Internal | `database` | crew manager |
| `crew.shortage.detected` | Internal | `database` | request owner |
| `crew.expense.submitted` / `.approved` / `.rejected` | Internal/External | `database`, `mail` | approver / claimant |

Recipient resolution reuses `RecipientResolverRegistry`. **PROPOSAL:** add a
`crew_member` resolver that resolves `crew_members.email` when `member_id` is
absent or the member has no email, so a walk-in freelancer still receives mail.

`SystemCommunicationTemplates::seededTypeKeys()` gains the external-facing Crew
types (invite, confirm, cancel, shift changed, timesheet decision) so operators
can edit the wording like every other customer communication.

### H.4 Inbound contracts (other domains → Crew)

| Source | Signal | Crew reaction | Status |
| --- | --- | --- | --- |
| Projects | `project.schedule_changed` (new) | Create an impact review: list confirmed shifts outside the new project window, mark `requires_realignment`, notify planner. **Never auto-edit shifts.** | **CONFIRMED GAP** — no Project action dispatches `AuditableEvent` today (`app/Actions/Projects/*` has no dispatch calls). New work required. |
| Projects | `project.archived` | Warn if confirmed assignments exist; block archive or force explicit cancellation | **PROPOSAL** |
| Opportunities | `opportunity.cancelled` / status change to cancelled | Notify the crew planner; do **not** auto-cancel shifts | **PROPOSAL** |
| Members | `member.merged` | Re-point `crew_members.member_id`, `supplier_member_id`, `venue_member_id` via an explicit Crew listener | **PROPOSAL** |
| Members | `member.anonymised` | Null fallback `email`/`phone` on `crew_members`, keep operational history | **PROPOSAL** |
| Users | `user.deactivated` | Keep the crew member; `user_id` remains but login is disabled. Never cascade-delete a crew member. | **PROPOSAL** |
| Stores | store archived | Block new requests for that store; existing records remain readable | **PROPOSAL** |
| Finance | `finance.crew_payable_paid` | Stamp `crew_expenses.reimbursed_at` / `crew_timesheets.finance_reference` | **PROPOSAL**, Phase 2 |

Implementation seam (**PROPOSAL**): one listener per signal under
`app/Domain/Crew/Listeners/`, registered in `CrewServiceProvider` exactly like
the existing listeners. No listener writes into Projects, Members, Users, or
Finance.

### H.5 Outbound contract (Crew → Finance)

Crew never creates an invoice, bill, or payment. It publishes an approved,
frozen amount and Finance decides how to pay it.

**PROPOSAL:** `ExportCrewTimesheetToFinance` dispatches
`crew.timesheet.exported_to_finance` with:

```text
financable_type      crew_timesheet | crew_expense
financable_id        int
store_id             int
project_id           int
crew_member_id       int
supplier_member_id   int|null
currency_code        string(3)
amount_minor         int          (frozen total_pay_minor or amount_base_minor)
period_start/end     date
breakdown            json         (role, hours, rate, multipliers, allowances)
finance_reference    string|null  (written back by Finance)
```

Finance may reject the hand-off by writing `finance_reference = null` plus a
reason, which Crew records as a warning on the timesheet header. **Payment state
is never stored as a Crew status** — `locked` is the terminal Crew state and is
independent of paid/unpaid.

### H.6 REST API surface

**CONFIRMED conventions:** `routes/api.php` group `v1` with `ForceJsonResponse`,
`throttle:api`, `auth:sanctum`, `LogApiRequest`, `signals.active-user`; resources
declare `permissions()` and `abilities()` and use `ResourceActions` +
`FiltersQueries`; POST mutations use the `idempotency` middleware.

**PROPOSAL:** add `crew:read` / `crew:write` to
`CoreAbilityDefinitions::all()` and register the following:

| Method | URI | Controller method | Ability |
| --- | --- | --- | --- |
| GET | `/v1/crew/members` | `index` | `crew:read` |
| POST | `/v1/crew/members` | `store` | `crew:write` |
| GET | `/v1/crew/members/{crew_member}` | `show` | `crew:read` |
| PATCH | `/v1/crew/members/{crew_member}` | `update` | `crew:write` |
| DELETE | `/v1/crew/members/{crew_member}` | `destroy` | `crew:write` |
| GET | `/v1/crew/roles` | `index` | `crew:read` |
| GET | `/v1/crew/requests` | `index` | `crew:read` |
| POST | `/v1/crew/requests` | `store` | `crew:write` |
| GET | `/v1/crew/requests/{crew_request}` | `show` | `crew:read` |
| PATCH | `/v1/crew/requests/{crew_request}` | `update` | `crew:write` |
| POST | `/v1/crew/requests/{crew_request}/publish` | `publish` | `crew:write` |
| POST | `/v1/crew/requests/{crew_request}/cancel` | `cancel` | `crew:write` |
| GET | `/v1/crew/assignments` | `index` | `crew:read` |
| POST | `/v1/crew/request-lines/{crew_request_line}/assignments` | `store` | `crew:write` |
| GET | `/v1/crew/assignments/{crew_assignment}` | `show` | `crew:read` |
| POST | `/v1/crew/assignments/{crew_assignment}/invite` | `invite` | `crew:write` |
| POST | `/v1/crew/assignments/{crew_assignment}/confirm` | `confirm` | `crew:write` |
| POST | `/v1/crew/assignments/{crew_assignment}/reject` | `reject` | `crew:write` |
| POST | `/v1/crew/assignments/{crew_assignment}/cancel` | `cancel` | `crew:write` |
| POST | `/v1/crew/assignments/{crew_assignment}/substitute` | `substitute` | `crew:write` |
| GET | `/v1/crew/shifts` | `index` | `crew:read` |
| POST | `/v1/crew/assignments/{crew_assignment}/shifts` | `store` | `crew:write` |
| PATCH | `/v1/crew/shifts/{crew_shift}` | `update` | `crew:write` |
| POST | `/v1/crew/shifts/{crew_shift}/confirm` | `confirm` | `crew:write` |
| DELETE | `/v1/crew/shifts/{crew_shift}` | `destroy` | `crew:write` |
| GET | `/v1/crew/availabilities` | `index` | `crew:read` |
| POST | `/v1/crew/members/{crew_member}/availabilities` | `store` | `crew:write` |
| POST | `/v1/crew/availabilities/{crew_availability}/approve` | `approve` | `crew:write` |
| GET | `/v1/crew/timesheets` | `index` | `crew:read` |
| GET | `/v1/crew/timesheets/{crew_timesheet}` | `show` | `crew:read` |
| POST | `/v1/crew/timesheets/{crew_timesheet}/submit` | `submit` | `crew:write` |
| POST | `/v1/crew/timesheets/{crew_timesheet}/approve` | `approve` | `crew:write` |
| POST | `/v1/crew/timesheets/{crew_timesheet}/reject` | `reject` | `crew:write` |
| POST | `/v1/crew/timesheets/{crew_timesheet}/lock` | `lock` | `crew:write` |
| GET | `/v1/crew/reports/coverage` | `coverage` | `crew:read` |
| GET | `/v1/crew/reports/utilisation` | `utilisation` | `crew:read` |
| GET | `/v1/crew/reports/cost-forecast` | `costForecast` | `crew:read` |

Rules:

- Every endpoint authorises twice: token ability **and** policy permission, the
  same double check `ProjectController` performs.
- `index` endpoints support `filter[...]`, `sort`, `include[]`, and pagination
  via `FiltersQueries`; allowed values are declared per controller.
- Mutating POSTs carry `->middleware('idempotency')`.
- Timesheet `approve`/`lock` accept an `Idempotency-Key`; the frozen totals are
  returned unchanged on replay.
- **Never** expose `internal_notes`, bank details, or rate amounts without the
  corresponding permission, even to a valid token. `CrewMemberData` must omit
  `internal_notes` unless the caller has `crew.view.sensitive`.

### H.7 Outbound webhook catalogue

**PROPOSAL:** append to `WebhookEventRegistry::CORE_EVENT_NAMES` (the allow-list
is the only gate; unknown names are silently dropped today):

```text
crew.member.created
crew.member.updated
crew.member.archived
crew.member.restored
crew.request.created
crew.request.published
crew.request.cancelled
crew.request.filled
crew.assignment.proposed
crew.assignment.invited
crew.assignment.confirmed
crew.assignment.rejected
crew.assignment.cancelled
crew.assignment.substituted
crew.shift.created
crew.shift.updated
crew.shift.confirmed
crew.shift.cancelled
crew.availability.approved
crew.timesheet.submitted
crew.timesheet.approved
crew.timesheet.rejected
crew.timesheet.locked
crew.timesheet.exported_to_finance
crew.expense.submitted
crew.expense.approved
crew.expense.rejected
crew.expense.reimbursed
crew.shortage.detected
```

Payloads are entity-keyed and reuse the DTO shape (`CrewAssignmentData`,
`CrewShiftData`, `CrewTimesheetData`), matching how member/product webhooks
carry their DTOs. Do **not** include rate amounts in `crew.assignment.*`
payloads unless the webhook endpoint's token has `crew:read` **and** the
subscription was created with the `include_rates` flag.

### H.8 Integration anti-patterns (explicitly forbidden)

1. Querying `projects` and mutating `crew_shifts` inside a Project action.
2. Writing `members` fields from Crew to "fill in" a crew member.
3. Creating a `CrewMember` automatically when a `User` is invited, or vice versa.
4. Emitting `crew.*` webhooks without allow-list registration (silent drop).
5. Reusing the opportunity event bridge for Crew models (double dispatch).
6. Treating a timesheet `locked` state as "paid".

---

## I. Availability, conflict detection, and crew shortage algorithms

### I.1 Interval model

All scheduling maths uses **half-open UTC intervals** `[start, end)`.

| Stored value | Meaning |
| --- | --- |
| `starts_at`, `ends_at` | Absolute UTC instants (`dateTime`) |
| `timezone` | IANA zone used for **display and local-date rules** |
| `work_date` | Local calendar date, derived from `started_at` in the effective timezone |
| `is_overnight` | Derived: local date of `starts_at` ≠ local date of `ends_at` |
| `all_day` | Intent flag; the interval is already expanded to local 00:00–24:00 |

Why half-open: two shifts `09:00–13:00` and `13:00–17:00` must **not** conflict
(a back-to-back handover is legal). A closed-interval model would report a
false conflict.

```text
overlaps(a, b)  ⇔  a.start < b.end  ∧  b.start < a.end
touch(a, b)     ⇔  a.end == b.start ∨ b.end == a.start     (not a conflict)
```

### I.2 Conflict classification

`CrewConflictSeverity::Hard` blocks confirmation. `Warning` requires an
explicit, audited acknowledgement by a user with `crew.conflicts.override`.

| Code | Condition | Severity | Rationale |
| --- | --- | --- | --- |
| `SHIFT_OVERLAP_CONFIRMED` | Overlaps a shift on another assignment with status `confirmed`/`in_progress` | **Hard** | Physical impossibility |
| `SHIFT_OVERLAP_SAME_ASSIGNMENT` | Two confirmed shifts of the same assignment overlap | **Hard** | Data error |
| `SHIFT_OVERLAP_PLANNED` | Overlaps only `planned` shifts | Warning | Plan may still move |
| `LEAVE_APPROVED` | Overlaps an `approved` availability of type `leave`/`sick`/`unavailable` | **Hard** | Contractual commitment |
| `LEAVE_REQUESTED` | Overlaps a `requested` availability | Warning | Not yet decided |
| `DOUBLE_BOOKED_PROJECT` | Same member, two different projects, overlapping confirmed windows | **Hard** | Travel is impossible |
| `MEMBER_ARCHIVED` / `MEMBER_INACTIVE` | Member not `active` | **Hard** | Cannot be staffed |
| `ENGAGEMENT_WINDOW` | Shift outside `starts_on`/`ends_on` | **Hard** | Not under contract |
| `QUALIFICATION_EXPIRED` | A **mandatory** qualification expired before the shift date | **Hard** | Compliance / insurance |
| `QUALIFICATION_MISSING` | Mandatory qualification never recorded | **Hard** | Compliance |
| `QUALIFICATION_EXPIRING` | Expires within `qualification_expiry_warning_days` | Warning | Plan a renewal |
| `SKILL_MISSING_MANDATORY` | Mandatory skill not on the member | **Hard** | Cannot perform role |
| `SKILL_BELOW_MINIMUM` | Skill present but proficiency < required | **Hard** if the requirement is mandatory, else Warning | Quality |
| `ROLE_NOT_HELD` | Member does not hold the requested role | Warning | Candidate matching already filters role holders; this only fires on a manual override |
| `REST_BELOW_MINIMUM` | Gap to previous confirmed shift < `min_rest_hours` | **Hard** when `rest_enforcement = hard`, else Warning | Fatigue / safety |
| `WEEKLY_HOURS_EXCEEDED` | Projected local-week hours > `weekly_overtime_threshold_hours` | Warning | Cost, not legality |
| `TRAVEL_RADIUS_EXCEEDED` | Estimated distance > `travel_radius_km` | Warning | Willingness, not capability |
| `PENDING_AVAILABILITY_REQUEST` | Confirming would pre-empt an undecided leave request | Warning | Process risk |
| `DUPLICATE_ACTIVE_ASSIGNMENT` | `active_assignment_key` collision | **Hard** | Data integrity |
| `REQUEST_CANCELLED` / `REQUEST_FILLED` | Line no longer staffable | **Hard** | Invalid operation |
| `STORE_MISMATCH` | Assignment store ≠ request store | **Hard** | Scoping breach |

Hard conflicts are returned as a `CrewConflictReportData` with
`hardCount() > 0`; Actions throw `ValidationException` keyed to
`crew_member_id` or `starts_at` so the UI can highlight the offending field.

### I.3 Overlap query (portable SQL)

```sql
-- Confirmed shift overlap for one member, excluding the row being edited.
select s.id, s.crew_assignment_id, s.starts_at, s.ends_at, s.status
from crew_shifts s
join crew_assignments a on a.id = s.crew_assignment_id
where a.crew_member_id = :member_id
  and s.deleted_at is null
  and s.status in ('confirmed', 'in_progress')
  and s.id <> coalesce(:ignore_shift_id, 0)
  and s.starts_at < :end
  and s.ends_at   > :start
order by s.starts_at
limit 50;
```

The hot query is single-table because `crew_shifts` carries the denormalised
`crew_member_id` with the composite index
`crew_shifts(crew_member_id, starts_at, ends_at)`; the join to
`crew_assignments` only resolves ownership/status details afterwards.

> **Schema amendment (PROPOSAL, already reflected in [D.15](#d15-crew_shifts)):**
> `crew_shifts.crew_member_id` is nullable (open shifts) and `restrictOnDelete`.
> It is denormalised from the assignment at write time and re-synced by
> `ReassignCrewAssignment` / `SubstituteCrewAssignment`.

The same predicate covers availability:

```sql
select 1
from crew_availabilities av
where av.crew_member_id = :member_id
  and av.status = 'approved'
  and av.starts_at < :end
  and av.ends_at   > :start
limit 1;
```

**Portability note:** PostgreSQL `tstzrange` + GiST is the best index for this
shape, but MySQL 8 has no equivalent, and the repository has no MySQL connection
configured. Use btree + the half-open predicate; it is correct on both engines.

### I.4 Overnight, timezone, and DST

| Case | Rule |
| --- | --- |
| **Overnight shift** | `ends_at > starts_at` in UTC; `is_overnight` is derived, never trusted from input. A 22:00→06:00 local shift is one interval, not two. |
| **Cross-midnight overtime** | Overtime is bucketed by **local** date of the *entry start*, except travel and standby, which use the shift's local date. |
| **DST spring forward** | A local time inside the skipped hour does not exist. Reject with `starts_at: "This local time does not exist on this date"` and suggest the next valid instant. |
| **DST fall back** | A local time occurs twice. Default to the **first** occurrence (earlier offset) and show the resolved UTC instant in the UI so the operator can correct it. |
| **Duration across DST** | Always `ends_at - starts_at` in UTC. A 12-hour local shift across a spring-forward boundary is 11 elapsed hours and pays 11, not 12. |
| **All-day leave across DST** | Expand to local 00:00 → next-day 00:00, then convert; the resulting UTC duration may be 23 or 25 hours. |
| **Timezone change on a record** | Changing `timezone` never rewrites `starts_at`/`ends_at`. It only changes local-date derivation, so **re-run the calculation** and record the recomputation in audit metadata. |

Implementation notes (**PROPOSAL**):

- Use `CarbonImmutable` end-to-end, as `App\Support\Timezone` already does.
- Local-time input is parsed with an explicit zone
  (`CarbonImmutable::createFromFormat('Y-m-d H:i', $value, $tz)`), never with
  `strtotime`, and then converted with `->utc()`.
- Ambiguity detection uses `CarbonTimeZone::getTransitions()` for the day.
- `is_overnight` is set by a model observer on `saving`, derived from the
  record's own `timezone`, so it cannot drift.

### I.5 Rest and turnaround

```text
gap_minutes = next_shift.start - previous_shift.end      (same member, confirmed)
violation   = gap_minutes < min_rest_hours × 60
```

- Default `min_rest_hours = 11` (**PROPOSAL**, common EU/UK practice; must be
  confirmed by the business).
- Only `confirmed` and `in_progress` shifts count. `planned` shifts produce the
  `SHIFT_OVERLAP_PLANNED`-style warning instead.
- The comparison is instant-based, so DST does not distort it.
- Overlapping (negative gap) shifts are already caught by the overlap check;
  rest only adds the "too close but not overlapping" case.

### I.6 Availability windows

`CrewAvailabilityQuery::windowsFor()` returns a merged, ordered list of busy
intervals per member:

```text
busy = approved availability
     ∪ confirmed shifts
     ∪ in-progress shifts
     ∪ (optionally) planned shifts, flagged
     ∪ cross-store assignments

free = [window.start, window.end) minus busy, merged and sorted
```

Used by:

- the member picker badge ("free", "busy", "conflict"),
- the calendar page,
- candidate ranking,
- the planner drag target ("drop here" vs "blocked").

**PROPOSAL:** cap the window to 366 days and refuse wider ranges, otherwise a
wide report can materialise a very large interval list.

### I.7 Candidate matching

Two stages: SQL narrows to eligible members, PHP scores them. Eligibility is
hard-blocking; scoring only orders the survivors.

#### Stage 1 — eligibility SQL

```sql
select m.id
from crew_members m
where m.deleted_at is null
  and m.status = 'active'
  and exists (
      select 1 from crew_member_roles r
      where r.crew_member_id = m.id and r.crew_role_id = :role_id
  )
  -- no mandatory skill missing
  and not exists (
      select 1 from crew_request_line_requirements req
      where req.crew_request_line_id = :line_id
        and req.is_mandatory = true
        and req.requirement_type = 'skill'
        and not exists (
            select 1 from crew_member_skills ms
            where ms.crew_member_id = m.id
              and ms.crew_skill_id = req.crew_skill_id
        )
  )
  -- no mandatory qualification missing or expired
  and not exists (
      select 1 from crew_request_line_requirements req
      where req.crew_request_line_id = :line_id
        and req.is_mandatory = true
        and req.requirement_type = 'qualification'
        and not exists (
            select 1 from crew_member_qualifications mq
            where mq.crew_member_id = m.id
              and mq.crew_qualification_id = req.crew_qualification_id
              and (mq.expires_at is null or mq.expires_at >= :shift_date)
        )
  )
  -- no approved leave
  and not exists (
      select 1 from crew_availabilities av
      where av.crew_member_id = m.id
        and av.status = 'approved'
        and av.starts_at < :end
        and av.ends_at   > :start
  )
  -- no confirmed shift overlap
  and not exists (
      select 1 from crew_shifts s
      where s.crew_member_id = m.id
        and s.deleted_at is null
        and s.status in ('confirmed', 'in_progress')
        and s.starts_at < :end
        and s.ends_at   > :start
  )
  -- not already actively assigned to this line
  and not exists (
      select 1 from crew_assignments a
      where a.crew_request_line_id = :line_id
        and a.crew_member_id = m.id
        and a.status in ('proposed', 'invited', 'confirmed')
  );
```

Role requirements are folded into the same `not exists` blocks by unioning
`crew_role_requirements` with the line's own requirements (`is_mandatory = true`
only; optional requirements become warnings, not filters).

#### Stage 2 — scoring (0–100)

| Component | Max | Formula |
| --- | --- | --- |
| Role proficiency | 30 | `trainee 8, competent 18, expert 25, lead 30` |
| Primary role bonus | 10 | `is_primary = true` → 10 |
| Skill depth | 15 | `min(15, 3 × matching skills beyond mandatory)` |
| Qualification coverage | 10 | `10` all mandatory valid, `6` expiring soon, `0` otherwise |
| Availability margin | 10 | `10` fully free, `5` free with < 2h slack, `0` adjacent to another booking |
| Travel proximity | 10 | `10` within 25 km, decaying linearly to `0` at `travel_radius_km` |
| Fairness (recency) | 10 | `10` never assigned, decaying to `0` at ≥ 60 days since `last_assigned_at` |
| Overtime risk | 5 | `5` projected week ≤ threshold, `0` above |
| **Total** | **100** | |

Tie-break order: higher total → lower `last_assigned_at` (nulls last) → lower
`crew_member_id` (deterministic tests).

Each candidate carries:

```text
CrewCandidateMatchData
  crew_member_id, name, engagement_type
  score (0..100), score_breakdown (array<string,int>)
  hard_blockers (list<string code>)
  warnings (list<string code>)
  next_free_at (CarbonImmutable|null)
  current_week_minutes
```

A candidate with hard blockers is **returned** (so the planner sees why) but is
not droppable and `ProposeCrewAssignment` rejects it. The UI must never hide a
blocked candidate silently.

### I.8 Shortage detection

Coverage per line:

```text
confirmed = count(assignments where status = 'confirmed')
pending   = count(assignments where status in ('proposed','invited'))
remaining = max(0, quantity - confirmed)
shortage  = remaining > 0
```

| Derived `CrewShortageStatus` | Condition |
| --- | --- |
| `Covered` | `confirmed >= quantity` |
| `PartiallyCovered` | `0 < confirmed < quantity` |
| `Open` | `confirmed = 0` |

Severity (**PROPOSAL**):

```text
days_to_start < 0                      → overdue (critical)
days_to_start <= critical_shortage_days (default 7) → critical
days_to_start <= 21                    → warning
else                                   → informational
```

`CrewShortageQuery` runs one aggregate query per request (not per line):

```sql
select l.id,
       l.quantity,
       count(a.id) filter (where a.status = 'confirmed') as confirmed,
       count(a.id) filter (where a.status in ('proposed','invited')) as pending
from crew_request_lines l
left join crew_assignments a
       on a.crew_request_line_id = l.id and a.deleted_at is null
where l.crew_request_id = :request_id
group by l.id, l.quantity;
```

`count(...) filter (where ...)` is PostgreSQL syntax. MySQL 8 lacks `FILTER`, so
the portable form is `sum(case when a.status = 'confirmed' then 1 else 0 end)`.
**Use the `sum(case …)` form** so both engines work without branching.

Shortage scanning is exposed as a scheduled command
(**PROPOSAL**): `crew:scan-shortages --days=21`, registered next to the existing
shortage scanner and emitting `crew.shortage.detected` at most once per line per
day (idempotency by `line_id + date` in cache).

### I.9 Performance rules

- Never load all shifts for a member to test one interval; always bound by
  `starts_at < :end and ends_at > :start`.
- Never call the conflict query inside a loop over candidates when the same
  window is used — batch by member id and group results in PHP.
- Calendar pages restrict to one week or one month and to at most 50 members per
  screen.
- Candidate matching caps at 200 pre-scored rows (`limit` after eligibility) and
  logs a warning if truncation occurs.
- Utilisation and cost forecasts are read-only queries over indexed columns;
  cache per project for 60 seconds with a cache key that includes the max
  `updated_at` of the project's assignments and shifts.

### I.10 Concurrency of conflict detection

Overlap detection is a read; two concurrent proposals for the same member can
both pass. Three layers stop that:

1. **Serialise per member.** Inside the transaction, acquire
   `select * from crew_members where id = :id for update` before the conflict
   query. Two proposers for the same member queue; the second sees the first's
   row once it commits.
2. **Unique key.** `unique(active_assignment_key)` prevents the same member
   being proposed twice to the same line, independent of timing.
3. **Re-check at confirmation.** `ConfirmCrewAssignment` re-runs the full
   conflict query under the same member lock, because a *different* assignment
   may have been confirmed between proposal and confirmation.

Do **not** rely on `DB::transaction` alone: it gives atomicity, not mutual
exclusion. On PostgreSQL a `SERIALIZABLE` transaction would also work but adds
retry handling; the explicit member lock is simpler and matches the existing
`lockForUpdate` style.

---

## J. Shift, timesheet, and compensation calculation rules

> Every rule in this section is a **PROPOSAL** unless tagged **CONFIRMED**.
> Defaults live in settings so the business can change them without a code
> change. Nothing here is implemented yet.

### J.1 Units and calculation pipeline

- **Time:** whole minutes, integer arithmetic. Never `float` hours.
- **Money:** integer minor units (`*_minor`), `brick/money` for conversion and
  formatting, never `float`.
- **Multipliers:** `decimal(4,2)`, converted to integer basis points
  (`× 100`) before multiplication, then rounded once.

```text
raw interval (UTC)
  → elapsed minutes            floor((ended_at − started_at) / 60)
  → minus break minutes        max(0, elapsed − break)
  → rounding rule              none | up | nearest | down, step N minutes
  → call minimum uplift        per assignment per local date
  → overtime split             daily bucket, then weekly bucket
  → multiplier                 single highest applicable (no stacking)
  → line amount                round_half_up(minutes ÷ 60 × rate × multiplier)
  → allowances                 per assignment | day | hour
  → assignment total           sum(line amounts) + allowances
  → timesheet total            sum(assignment-day totals) + adjustments
```

Each step that changes the amount writes its reason into
`cost_estimate_breakdown` (estimate) or `overtime_breakdown` (actuals), so an
operator can always reconstruct a figure.

### J.2 Shift duration

```text
elapsed_minutes   = floor((ends_at − starts_at) / 60)
paid_minutes      = max(0, elapsed_minutes − break_minutes)
billable_minutes  = paid_minutes            (MVP: breaks are unpaid)
```

- `break_minutes < elapsed_minutes` is validated in the Action.
- Multiple breaks (Phase 2, `crew_shift_breaks`) replace `break_minutes` with
  `sum(unpaid break minutes)`; paid breaks are excluded from the deduction.
- A shift shorter than the break is rejected, never silently clamped.

**Rounding** (settings `crew.rounding_mode`, `crew.rounding_increment_minutes`):

| Mode | Rule | Example (7 → step 15) |
| --- | --- | --- |
| `none` | exact minutes | 7 |
| `up` | `ceil(m / step) × step` | 15 |
| `nearest` | `round(m / step) × step` | 0 |
| `down` | `floor(m / step) × step` | 0 |

Rounding is applied **per time entry**, not per timesheet, so a systematic
round-up cannot be hidden inside a total. The applied rule is snapshotted on the
timesheet as `rounding_rule` at submission and frozen at approval.

### J.3 Call minimum

```text
for each (assignment, local_date):
    worked = sum(paid_minutes for entries whose local date == local_date)
    if worked < min_call_hours × 60:
        uplift = min_call_hours × 60 − worked
        add synthetic line "call minimum uplift" (code CALL_MIN)
```

- `min_call_hours` comes from the assignment snapshot (copied from the rate card
  at proposal time), **not** from the current rate card.
- Applies to `work`, `travel`, and `standby` entries combined per local date.
- Does not apply to a day with no entries (no-show is not a paid call).
- The uplift line is stored in `overtime_breakdown` with minutes and amount; it
  is never written as a `crew_time_entries` row, because it is not worked time.

### J.4 Overtime

Two independent buckets (**PROPOSAL**, "daily first" policy):

```text
daily_threshold  = crew.daily_overtime_threshold_hours      default 8
weekly_threshold = crew.weekly_overtime_threshold_hours     default 40
week_start       = crew.week_starts_on                      default monday
```

```text
daily_ot(day)   = max(0, paid_with_minimum(day) − daily_threshold×60)
weekly_ot(week) = max(0, Σ(paid_with_minimum in week) − weekly_threshold×60 − Σ daily_ot(week))
```

- Weekly overtime is computed **after** daily overtime is removed, so the same
  minutes are never paid twice.
- Both are measured in **local dates** using the record's timezone.
- Default multipliers: daily OT `1.50`, weekly OT `1.50`, night `1.25`,
  weekend `1.25`, public holiday `2.00`.

**Precedence (single multiplier, no stacking):**

```text
holiday  >  weekend  >  night  >  overtime  >  1.00
```

If `crew.stack_multipliers` is `true` (not the default), multipliers multiply:
`1.5 × 1.25 = 1.875`. **PROPOSAL:** keep stacking off; stacked multipliers are
the most common source of payroll disputes.

**Night window** (settings `crew.night_window_start` / `crew.night_window_end`,
defaults `00:00`–`06:00` local): the multiplier applies to the **portion** of a
shift inside the window. Portion splitting uses the local clock, so a
22:00–08:00 shift splits into night (00:00–06:00) and day (06:00–08:00).

### J.5 Rate types

| Type | Formula | Notes |
| --- | --- | --- |
| `hourly` | `minutes ÷ 60 × rate_amount_minor × multiplier` | Standard |
| `daily` | covered portion at day rate; excess at hourly equivalent | `hourly_equiv = round(daily_rate ÷ daily_call_hours)` |
| `fixed` | `rate_amount_minor` once per assignment | No overtime, no call minimum |

**Daily rate detail** (**PROPOSAL**):

```text
daily_call_hours = crew.daily_call_hours                 default 10
covered          = min(paid_minutes, daily_call_hours×60)
excess           = max(0, paid_minutes − covered)
day_amount       = daily_rate_minor
excess_amount    = round(excess ÷ 60 × (daily_rate ÷ daily_call_hours) × ot_multiplier)
total            = day_amount + excess_amount
```

- A partial day is paid at the full day rate (no pro-rata) unless
  `crew.prorate_partial_days = true`.
- A multi-day assignment pays one day rate per **local date with any worked
  entry**; a day with only a 1-hour setup still pays a full day. This is
  intentional and must be confirmed by the business (see [N](#n-unresolved-business-decisions)).

**Fixed rate detail** (**PROPOSAL**):

- A fixed rate covers the whole assignment, regardless of shift count.
- Additional days are a **new assignment** (creating a new snapshot), never a
  mutation of the fixed amount.
- Overtime and call minimum do not apply; allowances still do.

### J.6 Allowances

`allowances_snapshot` is a JSON array copied from the rate card at proposal:

```json
[
  {"code":"PER_DIEM","label":"Per diem","amount_minor":5000,"currency":"GBP","per":"day"},
  {"code":"NIGHT","label":"Night allowance","amount_minor":2500,"currency":"GBP","per":"night"},
  {"code":"KIT","label":"Kit allowance","amount_minor":10000,"currency":"GBP","per":"assignment"}
]
```

| `per` | Quantity |
| --- | --- |
| `assignment` | 1 if any work is recorded |
| `day` | count of distinct local dates with worked minutes ≥ `crew.per_diem_min_minutes` (default 240) |
| `night` | count of local dates where the shift intersects the night window |
| `hour` | sum of eligible hours (travel allowance) |

Allowances are added after multipliers, are never multiplied, and are listed
separately in the breakdown. A change to the rate card does not change a
confirmed assignment's allowances.

### J.7 Travel

- Travel entries are `CrewTimeEntryKind::Travel`, optionally linked to a travel
  shift.
- Paid at `travel_rate_minor` when set, else the assignment's base rate.
- **No overtime multiplier** by default (`crew.travel_earns_overtime = false`).
- Travel minutes count toward the **weekly** threshold but are excluded from the
  **daily** overtime bucket. This is a **PROPOSAL**; some jurisdictions treat all
  hours identically.
- Travel is never mixed into a work entry; the editor offers a distinct kind so
  reporting stays honest.

### J.8 Money arithmetic and currency

```text
line_amount = round_half_up( (minutes × rate_minor × multiplier_bp) / (60 × 100 × 100) )
```

Rules:

1. Multiply in integers, divide once, round **half-up** to the minor unit.
2. Sum rounded integers; never sum unrounded values and round at the end.
3. `currency_code` and `exchange_rate_to_base` are snapshotted on the
   assignment; `amount_base_minor = round(amount_minor × exchange_rate_to_base)`.
4. Reports read the snapshot; they never re-convert using today's rate.
5. `brick/money` is used for display and cross-currency totals, matching
   `FormatsMoney`.
6. Zero is a valid amount (volunteer, complimentary, internal recharge).

### J.9 Timesheet totals and variance

```text
total_minutes    = Σ paid_minutes (after rounding, including call-minimum uplift)
total_pay_minor  = Σ line amounts + allowances        (frozen at approval)
planned_minutes  = Σ paid_minutes of the assignment's confirmed shifts in the period
variance_minutes = total_minutes − planned_minutes
```

- Positive variance is normal (overrun); negative variance is highlighted in the
  approval queue because it usually means a missed entry.
- `variance_minutes` is derived, never stored.
- Approval freezes `total_minutes`, `total_pay_minor`, `rounding_rule`,
  `overtime_breakdown`, and each entry's `applied_rate_minor` / `amount_minor`.
- Recalculation after approval is impossible without `RejectCrewTimesheet`
  (which is only allowed from `Submitted`). A locked timesheet is corrected by a
  new corrective timesheet, never by mutation.

### J.10 Worked examples

All examples use GBP, `rounding_mode = nearest`, `rounding_increment = 15 min`,
`daily_threshold = 8h`, daily OT multiplier `1.5`, `min_call_hours = 4`.

| # | Scenario | Calculation | Pay |
| --- | --- | --- | --- |
| 1 | Hourly 4500/h, 09:00–19:00, 60 min break | 540 paid min → 8h at 4500 + 1h at 6750 | `36 000 + 6 750 = 42 750` |
| 2 | Hourly 4500/h, 09:00–11:30, min call 4h | 150 min → uplift to 240 min at 4500/h | `18 000` |
| 3 | Daily 35 000, 12h call, `daily_call_hours = 10` | 10h covered, 2h × 3500/h × 1.5 | `35 000 + 10 500 = 45 500` |
| 4 | Fixed 80 000, any hours | fixed once | `80 000` |
| 5 | Hourly 4000/h, 22:00–06:00 (8h), night window 00:00–06:00, night ×1.25 | 6h at 5000 + 2h at 4000 | `30 000 + 8 000 = 38 000` |
| 6 | Hourly 4500/h across spring-forward, local 22:00–06:00 | 7 elapsed hours (DST) | 7h billed, not 8 |

Example 6 is the single most important test case: elapsed time is measured in
UTC, so a DST transition changes the **paid** duration even though the local
clock looks like 8 hours.

### J.11 Proposed settings (`CrewSettings`)

New `app/Domain/Crew/Settings/CrewSettings.php` extending
`App\Settings\SettingsDefinition`, group `crew`, following `SchedulingSettings`.
It is registered into the settings registry by `CrewServiceProvider`:

| Key | Default | Rule |
| --- | --- | --- |
| `rounding_mode` | `nearest` | in `none,up,nearest,down` |
| `rounding_increment_minutes` | `15` | integer 1–60 |
| `daily_overtime_threshold_hours` | `8` | integer 0–24 |
| `weekly_overtime_threshold_hours` | `40` | integer 0–168 |
| `week_starts_on` | `monday` | in `monday,sunday` |
| `daily_ot_multiplier` | `1.50` | numeric 1–4 |
| `weekly_ot_multiplier` | `1.50` | numeric 1–4 |
| `night_multiplier` | `1.25` | numeric 1–4 |
| `weekend_multiplier` | `1.25` | numeric 1–4 |
| `holiday_multiplier` | `2.00` | numeric 1–5 |
| `night_window_start` | `00:00` | time |
| `night_window_end` | `06:00` | time |
| `daily_call_hours` | `10` | numeric 1–24 |
| `min_rest_hours` | `11` | numeric 0–24 |
| `rest_enforcement` | `hard` | in `hard,warning,off` |
| `travel_earns_overtime` | `false` | boolean |
| `stack_multipliers` | `false` | boolean |
| `prorate_partial_days` | `false` | boolean |
| `per_diem_min_minutes` | `240` | integer 0–1440 |
| `qualification_expiry_warning_days` | `30` | integer 0–365 |
| `critical_shortage_days` | `7` | integer 0–90 |
| `invite_expiry_hours` | `72` | integer 1–720 |
| `cancellation_pay_hours` | `48` | integer 0–336 |
| `default_break_minutes` | `30` | integer 0–240 |
| `allow_self_service_timesheets` | `false` | boolean |
| `allow_conflict_override` | `true` | boolean |
| `pii_retention_months` | `24` | integer 0–120 |
| `max_timesheet_period_days` | `31` | integer 1–92 |

`cancellation_pay_hours` supports the **PROPOSAL** that cancelling a confirmed
call inside the notice window still pays the call minimum. It is a policy
decision, not a technical one (see [N](#n-unresolved-business-decisions)).

---

## K. Authorization, privacy, concurrency, and audit controls

### K.1 Permission catalogue

**CONFIRMED** shape: `database/seeders/PermissionSeeder.php` entries are
`key => [label, description, group, sub_group, layer, dependencies]`, where
`layer` is `area` or `action` and every action depends on its area. **PROPOSAL:**
add group `Crew`:

| Permission | Layer | Depends on |
| --- | --- | --- |
| `crew.access` | area | — |
| `crew.view` | action | `crew.access` |
| `crew.create` | action | `crew.view` |
| `crew.edit` | action | `crew.view` |
| `crew.delete` | action | `crew.edit` |
| `crew.view.sensitive` | action | `crew.view` |
| `crew.rates.view` | action | `crew.view` |
| `crew.rates.manage` | action | `crew.rates.view` |
| `crew.requests.view` | action | `crew.view` |
| `crew.requests.create` | action | `crew.requests.view` |
| `crew.requests.edit` | action | `crew.requests.view` |
| `crew.requests.publish` | action | `crew.requests.edit` |
| `crew.requests.cancel` | action | `crew.requests.edit` |
| `crew.assignments.view` | action | `crew.requests.view` |
| `crew.assignments.create` | action | `crew.assignments.view` |
| `crew.assignments.confirm` | action | `crew.assignments.create` |
| `crew.assignments.cancel` | action | `crew.assignments.confirm` |
| `crew.assignments.substitute` | action | `crew.assignments.confirm` |
| `crew.shifts.view` | action | `crew.assignments.view` |
| `crew.shifts.manage` | action | `crew.shifts.view` |
| `crew.availability.view` | action | `crew.view` |
| `crew.availability.request` | action | `crew.availability.view` |
| `crew.availability.approve` | action | `crew.availability.view` |
| `crew.timesheets.view` | action | `crew.view` |
| `crew.timesheets.create` | action | `crew.timesheets.view` |
| `crew.timesheets.approve` | action | `crew.timesheets.view` |
| `crew.timesheets.lock` | action | `crew.timesheets.approve` |
| `crew.timesheets.export` | action | `crew.timesheets.approve` |
| `crew.expenses.view` | action | `crew.view` |
| `crew.expenses.create` | action | `crew.expenses.view` |
| `crew.expenses.approve` | action | `crew.expenses.view` |
| `crew.reports.view` | action | `crew.view` |
| `crew.conflicts.override` | action | `crew.assignments.confirm` |
| `crew.settings.manage` | action | `crew.access` |

Separation of duties: `crew.timesheets.approve` must **not** be implied by
`crew.edit`. A person who can edit a timesheet must not automatically approve
it. **PROPOSAL:** block self-approval when the approver is the crew member's
linked user, unless they hold `crew.timesheets.lock` (break-glass, audited).

### K.2 Policies

| Policy | Methods | Rule |
| --- | --- | --- |
| `CrewMemberPolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `viewSensitive` | `delete` means archive; hard delete requires `forceDelete` + audit |
| `CrewRateCardPolicy` | `viewAny`, `view`, `create`, `update`, `delete` | Separate from member edit |
| `CrewRequestPolicy` | `viewAny`, `view`, `create`, `update`, `publish`, `cancel` | `view` also requires the request's store to be accessible |
| `CrewAssignmentPolicy` | `viewAny`, `view`, `create`, `confirm`, `reject`, `cancel`, `substitute`, `reassign` | `confirm` requires no hard conflicts unless `crew.conflicts.override` |
| `CrewShiftPolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `confirm`, `start`, `complete` | `delete` only for `planned`/`cancelled` |
| `CrewAvailabilityPolicy` | `viewAny`, `view`, `create`, `approve`, `reject`, `cancel` | Self-view allowed when `crew_member.user_id === auth()->id()` |
| `CrewTimesheetPolicy` | `viewAny`, `view`, `create`, `update`, `submit`, `approve`, `reject`, `lock`, `export` | `update` only while `draft`/`rejected` |
| `CrewExpensePolicy` | `viewAny`, `view`, `create`, `update`, `submit`, `approve`, `reject`, `reimburse` | Receipt required to submit |

Store scoping: models using `HasStoreScoping` are filtered by `StoreScope`
automatically. Policies must still check `view` on the parent object, because
`StoreScope` returns early when there is no authenticated user (jobs, console).

### K.3 Privacy and data protection

**CONFIRMED:** `Member` already supports `member.anonymised` and Crew can link to
it. Crew stores only what operations need.

| Data | Classification | Handling |
| --- | --- | --- |
| Name, role, skills, qualifications | Operational | Visible to planners |
| Email, phone | Personal | Needed for invitations; show only with `crew.view` |
| Home postcode | Personal (location) | Used for travel heuristics; **not** exported in list APIs |
| `internal_notes` | Sensitive | `crew.view.sensitive` only; excluded from DTO by default |
| Rate/compensation | Confidential | `crew.rates.view`; never in list payloads |
| Payroll/tax/bank data | **Out of scope** | Must never be stored in Crew |
| Qualification documents | Personal documents | `Attachment` policy; private disk; signed URLs |
| Availability reason (`sick`, etc.) | Special category risk | Free text capped; reason optional; not shown in calendar tooltips to users without `crew.availability.view` |

Rules (**PROPOSAL**):

1. **No payroll data.** No NI/SSN, bank account, tax code, or date of birth.
2. **Retention.** Operational records (assignments, shifts, timesheets, audit)
   are kept for the financial retention period (default 7 years). Personal
   contact data on an archived crew member is anonymised after
   `crew.pii_retention_months` (default 24) of inactivity, keeping the
   operational record and costs.
3. **Right of access.** `BuildCrewWorkHistory` is the export surface for a crew
   member's own data; it must include assignments, hours, and pay where the
   requester is the subject.
4. **Right to erasure.** Anonymisation replaces `name`, `email`, `phone`, and
   `home_postcode` with nulls/placeholders on `crew_members`; it never deletes
   `crew_timesheets` or `crew_assignments`, because those are financial records.
5. **Self-service is opt-in.** `user_id` is optional; no Crew feature may
   require it. The `/crew/me` pages are hidden when the user has no linked crew
   member.
6. **Logging.** Action logs may contain `crew_member_id` but must not contain
   rate amounts in `old_values` for users without `crew.rates.view` (redact in
   the presenter, not the database).

### K.4 Concurrency, idempotency, and race conditions

| Risk | Control |
| --- | --- |
| Duplicate assignment for the same line+member | `unique(active_assignment_key)`; key nulled on terminal states |
| Duplicate shift creation (double submit) | `Idempotency-Key` middleware on POST; client-generated key stored by the existing idempotency store |
| Two planners confirm overlapping shifts for the same member | `lockForUpdate` on `crew_members` row, then conflict query, then write |
| Fill counters drift | `filled_quantity` recomputed inside the same transaction from `confirmed` rows, never incremented blindly |
| Stale timesheet edit overwrites an approval | `version` column + `where('version', $expected)->update(...)`; mismatch → `409`-style `ValidationException` |
| Approval while an edit is in flight | Approval re-reads the timesheet with `lockForUpdate` and refuses if `status !== submitted` |
| Duplicate `crew.shortage.detected` | Cache key `crew:shortage:{line}:{date}` with 24h TTL |
| Webhook double delivery on retry | Existing webhook log idempotency; Crew events carry a stable event id |
| Concurrent availability approval vs shift confirmation | Both lock the member row first; last writer sees the other's committed state |
| Rate card edited mid-proposal | Rate is resolved **inside** the transaction under `lockForUpdate` on the rate card rows |

Lock ordering (to avoid deadlocks): **member → request → request line →
assignment → shifts → timesheet**. Every Crew transaction that takes more than
one of these follows that order.

**PROPOSAL:** a `CrewAssignmentConfirmation` guard re-checks, under lock:
assignment status, request not cancelled, line still has capacity, member still
active, mandatory qualifications still valid **as of the shift date**, and zero
hard conflicts.

### K.5 Audit controls

Every state change dispatches `AuditableEvent`, which writes `action_logs` with
`user_id`, `auditable_type/id`, `old_values`, `new_values`, `ip_address`,
`user_agent`, and optional `metadata`.

Additional Crew requirements (**PROPOSAL**):

| Requirement | Detail |
| --- | --- |
| Conflict overrides are auditable | `AcknowledgeCrewConflict` writes `crew.conflict.acknowledged` with reason, codes, and the actor; the assignment stores `conflict_override_by/at` in `metadata` on the audit row |
| Rate changes are traceable | `crew.rate_card.*` records old/new amounts; never redacted in the audit row, only in the presenter |
| Timesheet approval records the frozen totals | `new_values` includes `total_pay_minor`, `rounding_rule`, `version` |
| Project realignment records both ranges | `crew.shift.realigned` carries `old` and `new` UTC ranges plus the reason |
| Deletion is soft | Members, requests, assignments, shifts, timesheets, expenses use `SoftDeletes`; hard delete only via an explicit, permissioned action |
| Audit is append-only | `action_logs` has `UPDATED_AT = null`; no updates or deletes |

The existing `ActionLogController` and CSV export then cover Crew with no new
infrastructure.

### K.6 Project schedule change policy (critical requirement)

**CONFIRMED:** Projects have `starts_at` / `ends_at`
(`database/migrations/2026_10_10_000001_create_projects_table.php`) and no crew
integration. Project actions do not currently emit audit events.

**PROPOSAL:** the contract is *announce, review, decide*. Never *propagate*.

```text
Projects: UpdateProject changes starts_at/ends_at
    │
    ├─ dispatch project.schedule_changed {project_id, old, new, actor}
    │
Crew listener (does not write to Projects)
    ├─ compute impact:
    │     confirmed shifts outside [new.start − buffer, new.end + buffer]
    │     assignments whose every shift is now out of range
    │     travel days that no longer fit
    ├─ flag each affected assignment `requires_realignment = true`
    ├─ notify request owners + planners (crew.shift.changed? no — see below)
    └─ open an "Impact review" panel on the project's crew page
    │
Planner decides, explicitly, per assignment:
    ├─ RealignAssignmentShifts  → moves shifts, re-checks conflicts, audits old/new
    ├─ Keep as is               → clears the flag, records a justification
    └─ CancelCrewAssignment     → applies cancellation policy, notifies member
```

Rules:

1. Confirmed shifts are **never** silently moved, stretched, or deleted.
2. A project date change alone never changes `crew_assignments.rate_amount_minor`
   or any snapshot.
3. Realignment is an explicit Action with its own permission
   (`crew.shifts.manage`) and its own audit event.
4. If realignment creates a hard conflict, it is refused; a warning can be
   overridden with `crew.conflicts.override`.
5. Crew never calls `UpdateProject`. If the operational reality changed, the
   planner edits the project in Projects.
6. Cancellation after the notice window follows `cancellation_pay_hours`; the
   member is notified with the reason.

**Impact query:**

```sql
select a.id, count(s.id) as out_of_range_shifts
from crew_assignments a
join crew_shifts s on s.crew_assignment_id = a.id
where a.project_id = :project_id
  and a.status = 'confirmed'
  and s.deleted_at is null
  and s.status in ('planned','confirmed','in_progress')
  and (s.starts_at < :new_start or s.ends_at > :new_end)
group by a.id;
```

### K.7 Attachment and document security

- Qualification certificates and expense receipts use the polymorphic
  `Attachment` relation (`HasAttachments`), on the private disk.
- Downloads go through signed, expiring URLs or an authorised controller; never
  a public path.
- MIME and size validation follows the existing attachment rules.
- Deleting a qualification does not delete its attachment silently; the Action
  removes the link and leaves an audit trail.
- **PROPOSAL:** qualification documents are visible only to
  `crew.view` + the attachment policy, and never included in list payloads.

---

## L. Pest test strategy and critical edge cases

### L.1 Principles and placement

**CONFIRMED:** Pest 4, `RefreshDatabase` on `tests/Feature`, factories beside
the domain models under `app/Domain/Crew/Database/Factories`, a separate
`tests/Pgsql` lane bound to the base
`TestCase` with its own `UsesPostgres` trait and a `pgsql` tag that skips when
Postgres is unreachable (`tests/Pest.php`).

Test placement (**PROPOSAL**, mirroring `tests/Feature/Projects`):

| Suite | Location | Purpose |
| --- | --- | --- |
| Feature (sqlite) | `tests/Feature/Crew/` | Actions, policies, Livewire pages, notifications, API contracts |
| Postgres | `tests/Pgsql/Crew/` | Constraints, `lockForUpdate`, concurrency, partial/unique indexes, `jsonb` behaviour |
| Unit | `tests/Unit/Crew/` | Pure calculators (duration, rounding, overtime, money) |
| Browser (optional) | `tests/Browser/Crew/` | Only if the existing browser suite is used for planner drag behaviour |

Run the narrowest set while working:

```bash
php artisan test --compact tests/Feature/Crew/Actions/ConfirmCrewAssignmentTest.php
vendor/bin/pest tests/Pgsql/Crew --filter=concurrent
```

### L.2 Test layers

| Layer | What is proved |
| --- | --- |
| **Model/schema** | Casts, relations, soft deletes, FK behaviour, unique keys, indexes exist |
| **Action** | Authorisation, validation, invariants, snapshot immutability, audit event |
| **Query** | Conflict detection, availability merge, coverage, candidate ranking |
| **Calculator (unit)** | Every formula in [J](#j-shift-timesheet-and-compensation-calculation-rules) with boundary values |
| **Livewire** | Page renders, form validation, side-panel state, permission gating |
| **API** | Status codes, abilities, filters/sorts/includes, idempotency, no data leakage |
| **Postgres** | Real locking, real CHECK/unique enforcement, concurrent transactions |
| **Notification/audit** | Type mapped, recipients resolved, `action_logs` row written, webhook emitted once |

### L.3 Factories and states

**PROPOSAL:** one factory per model, with states that make tests read like
scenarios.

```text
CrewMemberFactory
  -> employee() -> freelancer() -> contractor() -> externalCrew()
  -> active() -> inactive() -> archived()
  -> withUser() -> withoutUser() -> withMember()
  -> primaryRole(CrewRole) -> withSkill(CrewSkill, proficiency)
  -> withQualification(CrewQualification, expiresAt: ...)
  -> expiringQualification() -> expiredQualification()

CrewRateCardFactory
  -> hourly(int $minor = 4500) -> daily(int $minor = 35000) -> fixed(int $minor)
  -> forMember(CrewMember) -> forRole(CrewRole) -> forStore(Store)
  -> effectiveFrom(Carbon) -> expired()

CrewRequestFactory
  -> draft() -> open() -> partiallyFilled() -> filled() -> cancelled()
  -> forProject(Project) -> forStore(Store)
  -> withLine(CrewRole, quantity: 2)

CrewAssignmentFactory
  -> proposed() -> invited() -> confirmed() -> rejected() -> cancelled()
  -> substituted() -> forLine(CrewRequestLine) -> forMember(CrewMember)

CrewShiftFactory
  -> planned() -> confirmed() -> inProgress() -> completed() -> cancelled()
  -> setup() -> show() -> strike() -> travel()
  -> overnight() -> acrossSpringForward() -> acrossFallBack()

CrewAvailabilityFactory
  -> requested() -> approved() -> rejected() -> leave() -> sick()
  -> allDay() -> covering(Carbon $start, Carbon $end)

CrewTimesheetFactory
  -> draft() -> submitted() -> approved() -> rejected() -> locked()
  -> withEntry(minutes: 480) -> withOvertime()
```

Rules:

- Factories produce **valid** records by default; invalid states are explicit.
- Never set `filled_quantity` manually in a test that exercises an Action; use
  the Action so the invariant is covered.
- Store scoping: factories must accept an explicit `store_id` when the model is
  store-scoped; tests that assert scoping create two stores and two users.

### L.4 Time control

- `Carbon::setTestNow()` / `$this->travelTo()` for deterministic "today".
- Always assert in UTC and convert explicitly in the test when asserting local
  behaviour; never assert a local string that depends on the machine timezone.
- DST tests use fixed IANA zones (`Europe/London`, `America/New_York`,
  `Australia/Sydney`) and fixed dates, never "next March".
- Timezone tests set `config('app.timezone')` to `UTC` and prove storage is UTC.

### L.5 Critical edge-case catalogue

#### Lifecycle and invariants

| # | Case | Expected |
| --- | --- | --- |
| 1 | `CrewRequest` without any assignment | Stays `open`; never `filled` |
| 2 | Assignment confirmed twice | Second call is idempotent or rejected by transition guard; fill counter unchanged |
| 3 | Reject then re-propose same member on same line | Allowed: `active_assignment_key` was cleared on rejection |
| 4 | Propose same member twice to the same line | Rejected by unique key, not only by a PHP check |
| 5 | Substitute an assignment | Original → `substituted`, key cleared; new assignment has its own snapshot and (optionally) copied shifts |
| 6 | Cancel a request with confirmed assignments | Assignments cancelled transactionally, shifts cancelled, members notified, history preserved |
| 7 | Shrink a line below `filled_quantity` | `ValidationException` on `quantity` |
| 8 | Remove a line with a non-terminal assignment | Rejected |
| 9 | Archive a member with a future confirmed assignment | Blocked unless explicit override, and then audited |
| 10 | Timesheet approved, then edited | Rejected; totals unchanged |
| 11 | Locked timesheet, then approve again | Rejected by transition guard |
| 12 | Rate card edited after confirmation | Confirmed assignment amounts byte-identical |
| 13 | Rate card deleted | Confirmed assignment still resolves its snapshot; `rate_card_id` nulled |
| 14 | `CrewMember` with no `user_id` | Every action works; no notification failure |
| 15 | `CrewMember` with no `member_id` | Name/email/phone fallbacks used |

#### Time, timezone, DST

| # | Case | Expected |
| --- | --- | --- |
| 16 | Shift 22:00–06:00 | One shift, `is_overnight = true`, 480 minutes |
| 17 | Back-to-back 09:00–13:00 and 13:00–17:00 | No conflict |
| 18 | 1-minute overlap | Hard conflict |
| 19 | Spring-forward local 01:30 | Rejected as non-existent, message names the date |
| 20 | Fall-back local 01:30 | Resolves to the first occurrence; UTC shown in UI |
| 21 | 8-hour local shift across spring-forward | Billed 7 hours |
| 22 | All-day leave across spring-forward | 23-hour UTC interval, still blocks the whole local day |
| 23 | Member in `Pacific/Auckland`, project in `Europe/London` | Conflict uses UTC; display uses each row's own zone |
| 24 | Change `timezone` on a shift | Instants unchanged; recomputation recorded in audit |
| 25 | Weekly overtime across a DST week | Local-date buckets, not UTC-date buckets |

#### Compensation

| # | Case | Expected |
| --- | --- | --- |
| 26 | 7 minutes, nearest 15 | 0 minutes (documented) |
| 27 | 8 minutes, nearest 15 | 15 minutes |
| 28 | Worked 2h on a 4h minimum call | Paid 4h, uplift line present |
| 29 | Break longer than shift | Validation error |
| 30 | Daily rate, 12h call, 10h covered | Day rate + 2h × hourly-equivalent × 1.5 |
| 31 | Daily rate across two local dates | Two day rates |
| 32 | Fixed rate with 3 shifts | One fixed amount |
| 33 | Night + weekend + overtime overlap | **One** multiplier (highest), not the product |
| 34 | Currency with 0 decimals (JPY) | Minor units equal major units; no rounding drift |
| 35 | Currency with 3 decimals (KWD) | Correct 3dp scale |
| 36 | Large amount (e.g. 10 000 000 minor) | No float drift; exact integer |
| 37 | Allowance `per: day` with 3 worked days | 3 × amount |
| 38 | Allowance `per: day` with 3h on one day | 0 (below `per_diem_min_minutes`) |
| 39 | Re-approve after reject | Totals recomputed, then frozen again |
| 40 | Historical report after rate change | Uses snapshot, not current rate |

#### Availability and matching

| # | Case | Expected |
| --- | --- | --- |
| 41 | Approved leave overlaps shift | Hard conflict |
| 42 | Requested leave overlaps shift | Warning only |
| 43 | Planned shift overlaps | Warning only |
| 44 | Confirmed shift in another store overlaps | Hard conflict (person, not store) |
| 45 | Mandatory qualification expired on shift date | Hard conflict |
| 46 | Qualification expires the day after | Allowed, expiring-soon warning |
| 47 | Candidate with a hard blocker | Returned in the list, not droppable, reason visible |
| 48 | Candidate matching for 500 members | Bounded query, no N+1 (assert query count) |
| 49 | Rest gap 10h with `min_rest_hours = 11` | Hard conflict when enforcement is hard |
| 50 | Rest gap exactly 11h | No conflict (boundary) |

#### Concurrency (Postgres lane)

| # | Case | Expected |
| --- | --- | --- |
| 51 | Two proposals, same member, same line | Exactly one assignment row |
| 52 | Two confirmations of overlapping shifts, same member | One succeeds, one fails with a conflict; no silent double booking |
| 53 | Concurrent `filled_quantity` recalculation | Final value equals the true confirmed count |
| 54 | Concurrent timesheet update with stale `version` | Second write rejected |
| 55 | Concurrent approve + submit | Approve fails if status is not `submitted` at lock time |
| 56 | Concurrent availability approval + shift confirmation | One of them observes the other; no contradictory state |
| 57 | Duplicate `active_assignment_key` insert | DB unique violation surfaced as a validation error |

#### Authorization and privacy

| # | Case | Expected |
| --- | --- | --- |
| 58 | User with `crew.view` only | Cannot confirm, approve, or see rates |
| 59 | User in store A | Cannot view store B request (404/403) |
| 60 | User with no linked crew member opens `/crew/me` | 403 or redirect, no error |
| 61 | Crew member linked to a user tries to approve own timesheet | Blocked unless break-glass permission |
| 62 | API token with `crew:read` calls `POST /assignments/{id}/confirm` | 403 |
| 63 | API list response | No `internal_notes`, no rate amounts without permission |
| 64 | Unauthenticated request to any Crew route | Redirect (web) / 401 (API) |
| 65 | Module disabled (`crew` flag off) | 404 on all Crew routes |
| 66 | Archived member viewed by unauthorised user | Not listed, not searchable |

#### Events, notifications, audit

| # | Case | Expected |
| --- | --- | --- |
| 67 | Confirm assignment | Exactly one `crew.assignment.confirmed` audit row and one webhook |
| 68 | Webhook retry | No duplicate `webhook_logs` delivery for the same event id |
| 69 | Notification type inactive | No dispatch, no exception |
| 70 | Crew member without email | Mail channel skipped, database channel still works |
| 71 | Conflict override | Audit row records reason and actor |
| 72 | Project schedule change | Confirmed shifts untouched; impact flag set; no `crew.shift.*` event until realignment |
| 73 | Realignment | Old and new ranges both present in audit |
| 74 | Member merge from CRM | Crew links re-pointed, no duplicate crew member created |

#### Livewire and API

| # | Case | Expected |
| --- | --- | --- |
| 75 | Member list filters/sorts persist via URL | Same pattern as `projects.index` |
| 76 | Validation error from an Action | Mapped to the component field key |
| 77 | Candidate panel drop with hard conflict | Error toast, no assignment created |
| 78 | Timesheet editor recalculation | Totals update without saving |
| 79 | API filter by unknown column | 422, not SQL error |
| 80 | API pagination + `include` | No N+1; includes limited to allow-list |

### L.6 Assertion guidance

- Assert **state**, not implementation: `expect($assignment->refresh()->status)->toBe(Confirmed)`.
- Assert **immutability** by capturing the full money snapshot before and after
  an unrelated change and comparing arrays exactly.
- Assert **query counts** for list endpoints and candidate matching.
- Assert **audit rows** with `assertDatabaseHas('action_logs', [...])`.
- Assert **no side effects** on failure: after a `ValidationException`, the row
  count and statuses are unchanged.
- Avoid snapshot-testing whole DTOs; assert the fields the contract guarantees.

### L.7 Coverage expectations

| Area | Minimum |
| --- | --- |
| Calculators (J) | 100% branch coverage; every formula has a boundary test |
| Actions (F) | Every action: happy path, authorisation failure, invariant failure, audit |
| Enums (E) | Every transition allowed and every invalid transition tested |
| Policies (K) | Permission × ability matrix, including store scoping |
| Queries (I) | Empty, single, boundary-touching, overlapping, multi-store |
| API (H.6) | Each endpoint: success, validation, authorisation, filtering |

Do not chase a global percentage; the tables above are the contract.

---

## M. Complete proposed folder tree

The entire Crew domain lives under `app/Domain/Crew` and is wired by its own
`CrewServiceProvider` (registered in `bootstrap/providers.php`). Nothing
Crew-specific is added to `app/Actions`, `app/Enums`, `app/Models`,
`app/Policies`, `app/Services`, `app/Settings`, `database/factories`, or
`database/migrations`.

```text
app/
└── Domain/
    └── Crew/
        ├── CrewServiceProvider.php      (migrations, factories, policies, registries, settings group)
        ├── Actions/
        │   ├── Registry/
        │   │   ├── CreateCrewRole.php
        │   │   ├── UpdateCrewRole.php
        │   │   ├── DeleteCrewRole.php
        │   │   ├── SyncCrewRoleRequirements.php
        │   │   ├── CreateCrewSkill.php
        │   │   ├── UpdateCrewSkill.php
        │   │   ├── CreateCrewQualification.php
        │   │   ├── UpdateCrewQualification.php
        │   │   ├── CreateCrewMember.php
        │   │   ├── UpdateCrewMember.php
        │   │   ├── ArchiveCrewMember.php
        │   │   ├── RestoreCrewMember.php
        │   │   ├── LinkCrewMemberUser.php
        │   │   ├── SyncCrewMemberRoles.php
        │   │   ├── SyncCrewMemberSkills.php
        │   │   ├── AddCrewMemberQualification.php
        │   │   ├── VerifyCrewMemberQualification.php
        │   │   └── DeleteCrewMemberQualification.php
        │   ├── Rates/
        │   │   ├── CreateCrewRateCard.php
        │   │   ├── UpdateCrewRateCard.php
        │   │   ├── DeactivateCrewRateCard.php
        │   │   └── ResolveCrewRate.php
        │   ├── Requests/
        │   │   ├── CreateCrewRequest.php
        │   │   ├── UpdateCrewRequest.php
        │   │   ├── PublishCrewRequest.php
        │   │   ├── CancelCrewRequest.php
        │   │   ├── CloneCrewRequest.php
        │   │   ├── AddCrewRequestLine.php
        │   │   ├── UpdateCrewRequestLine.php
        │   │   ├── RemoveCrewRequestLine.php
        │   │   ├── SyncCrewRequestLineRequirements.php
        │   │   └── RecalculateCrewRequestFill.php
        │   ├── Assignments/
        │   │   ├── ProposeCrewAssignment.php
        │   │   ├── InviteCrewAssignment.php
        │   │   ├── ConfirmCrewAssignment.php
        │   │   ├── RejectCrewAssignment.php
        │   │   ├── CancelCrewAssignment.php
        │   │   ├── SubstituteCrewAssignment.php
        │   │   ├── ReassignCrewAssignment.php
        │   │   ├── RecalculateCrewAssignmentCost.php
        │   │   ├── AcceptCrewAssignmentInvite.php
        │   │   └── DeclineCrewAssignmentInvite.php
        │   ├── Shifts/
        │   │   ├── CreateCrewShift.php
        │   │   ├── UpdateCrewShift.php
        │   │   ├── ConfirmCrewShift.php
        │   │   ├── StartCrewShift.php
        │   │   ├── CompleteCrewShift.php
        │   │   ├── CancelCrewShift.php
        │   │   ├── ReorderCrewShifts.php
        │   │   ├── BulkConfirmCrewShifts.php
        │   │   ├── RealignAssignmentShifts.php
        │   │   └── CopyCrewShiftsToAssignment.php
        │   ├── Availability/
        │   │   ├── RequestCrewAvailability.php
        │   │   ├── ApproveCrewAvailability.php
        │   │   ├── RejectCrewAvailability.php
        │   │   ├── CancelCrewAvailability.php
        │   │   └── SetCrewAvailability.php
        │   ├── Timesheets/
        │   │   ├── CreateCrewTimesheet.php
        │   │   ├── AddCrewTimeEntry.php
        │   │   ├── UpdateCrewTimeEntry.php
        │   │   ├── RemoveCrewTimeEntry.php
        │   │   ├── SubmitCrewTimesheet.php
        │   │   ├── ApproveCrewTimesheet.php
        │   │   ├── RejectCrewTimesheet.php
        │   │   ├── LockCrewTimesheet.php
        │   │   ├── RecalculateCrewTimesheet.php
        │   │   └── ExportCrewTimesheetToFinance.php
        │   ├── Expenses/
        │   │   ├── CreateCrewExpense.php
        │   │   ├── UpdateCrewExpense.php
        │   │   ├── SubmitCrewExpense.php
        │   │   ├── ApproveCrewExpense.php
        │   │   ├── RejectCrewExpense.php
        │   │   └── MarkCrewExpenseReimbursed.php
        │   └── Planning/
        │       ├── DetectCrewConflicts.php
        │       ├── MatchCrewCandidates.php
        │       ├── BuildCrewCoverageReport.php
        │       ├── BuildCrewCostForecast.php
        │       ├── BuildCrewUtilisationReport.php
        │       ├── BuildCrewWorkHistory.php
        │       ├── FindExpiringCrewQualifications.php
        │       └── AcknowledgeCrewConflict.php
        ├── Console/
        │   └── Commands/
        │       ├── ScanCrewShortagesCommand.php
        │       └── ExpireCrewQualificationsCommand.php
        ├── Data/
        │   ├── Registry/            (role, skill, qualification, member, rate DTOs)
        │   ├── Requests/            (request + line DTOs)
        │   ├── Assignments/         (assignment DTOs)
        │   ├── Shifts/              (shift DTOs)
        │   ├── Availability/        (availability DTOs)
        │   ├── Timesheets/          (timesheet + entry DTOs)
        │   ├── Expenses/            (expense DTOs)
        │   └── Planning/            (candidate, conflict, coverage, forecast DTOs)
        ├── Database/
        │   ├── Factories/           (Crew*Factory.php)
        │   ├── Migrations/          (2026_10_31_000001–000019)
        │   └── Seeders/             (CrewRoleSeeder, CrewSkillSeeder, CrewQualificationSeeder, CrewDemoSeeder)
        ├── Enums/
        │   ├── CrewAssignmentStatus.php
        │   ├── CrewAvailabilityStatus.php
        │   ├── CrewAvailabilityType.php
        │   ├── CrewConflictSeverity.php
        │   ├── CrewCostSource.php
        │   ├── CrewEngagementType.php
        │   ├── CrewExpenseCategory.php
        │   ├── CrewExpenseStatus.php
        │   ├── CrewMemberStatus.php
        │   ├── CrewProficiency.php
        │   ├── CrewQualificationStatus.php
        │   ├── CrewRateType.php
        │   ├── CrewRequestPriority.php
        │   ├── CrewRequestStatus.php
        │   ├── CrewRequirementType.php
        │   ├── CrewShiftStatus.php
        │   ├── CrewShiftType.php
        │   ├── CrewShortageStatus.php
        │   ├── CrewTimeEntryKind.php
        │   └── CrewTimesheetStatus.php
        ├── Presentation/
        │   ├── Http/
        │   │   ├── Controllers/Api/V1/Crew/
        │   │   │   ├── CrewMemberController.php
        │   │   │   ├── CrewRequestController.php
        │   │   │   ├── CrewAssignmentController.php
        │   │   │   ├── CrewShiftController.php
        │   │   │   ├── CrewAvailabilityController.php
        │   │   │   ├── CrewTimesheetController.php
        │   │   │   └── CrewReportController.php
        │   │   └── Requests/Crew/       (validated API input)
        │   ├── Livewire/
        │   │   ├── Concerns/ChecksCrewAccess.php
        │   │   ├── CrewComponent.php
        │   │   ├── Dashboard.php
        │   │   ├── Configuration.php
        │   │   ├── Rates.php
        │   │   ├── Planner.php
        │   │   ├── Calendar.php
        │   │   ├── Members/             (Index, Form, Show)
        │   │   ├── Requests/            (Index, Show)
        │   │   ├── Assignments/Show.php
        │   │   └── Timesheets/          (Index, Show)
        │   ├── Routes/
        │   │   ├── web.php
        │   │   └── api.php
        │   └── Views/                  (plain Blade, registered as crew::)
        │       ├── dashboard.blade.php
        │       ├── configuration.blade.php
        │       ├── rates.blade.php
        │       ├── planner.blade.php
        │       ├── calendar.blade.php
        │       ├── members/            (index, form, show)
        │       ├── requests/           (index, show)
        │       ├── assignments/show.blade.php
        │       ├── timesheets/         (index, show)
        │       └── partials/           (navigation, errors)
        ├── Listeners/
        │   ├── HandleProjectScheduleChanged.php
        │   ├── HandleProjectArchived.php
        │   ├── HandleMemberMerged.php
        │   ├── HandleMemberAnonymised.php
        │   ├── HandleUserDeactivated.php
        │   └── HandleCrewPayablePaid.php
        ├── Models/
        │   ├── CrewMember.php
        │   ├── CrewMemberRole.php
        │   ├── CrewMemberSkill.php
        │   ├── CrewMemberQualification.php
        │   ├── CrewRole.php
        │   ├── CrewRoleRequirement.php
        │   ├── CrewSkill.php
        │   ├── CrewQualification.php
        │   ├── CrewRateCard.php
        │   ├── CrewRequest.php
        │   ├── CrewRequestLine.php
        │   ├── CrewRequestLineRequirement.php
        │   ├── CrewAssignment.php
        │   ├── CrewShift.php
        │   ├── CrewShiftBreak.php
        │   ├── CrewAvailability.php
        │   ├── CrewTimesheet.php
        │   ├── CrewTimeEntry.php
        │   └── CrewExpense.php
        ├── Observers/
        │   ├── CrewShiftObserver.php          (derive is_overnight, guard break < duration)
        │   └── CrewTimesheetObserver.php      (guard edits when not draft/rejected)
        ├── Policies/
        │   ├── Concerns/
        │   │   └── ChecksStoreAccess.php      (shared store-visibility guard)
        │   ├── CrewMemberPolicy.php
        │   ├── CrewRateCardPolicy.php
        │   ├── CrewRequestPolicy.php
        │   ├── CrewAssignmentPolicy.php
        │   ├── CrewShiftPolicy.php
        │   ├── CrewAvailabilityPolicy.php
        │   ├── CrewTimesheetPolicy.php
        │   └── CrewExpensePolicy.php
        ├── Queries/
        │   ├── CrewAvailabilityQuery.php
        │   ├── CrewConflictQuery.php
        │   ├── CrewCandidateQuery.php
        │   ├── CrewCoverageQuery.php
        │   ├── CrewShortageQuery.php
        │   ├── CrewCalendarQuery.php
        │   ├── CrewUtilisationQuery.php
        │   ├── CrewCostForecastQuery.php
        │   ├── CrewWorkHistoryQuery.php
        │   ├── CrewExpiringQualificationQuery.php
        │   └── CrewTimesheetSummaryQuery.php
        ├── Services/
        │   ├── CrewStatusRegistry.php
        │   ├── CrewDurationCalculator.php
        │   ├── CrewOvertimeCalculator.php
        │   ├── CrewRateResolver.php
        │   ├── CrewCompensationCalculator.php
        │   ├── CrewConflictDetector.php
        │   ├── CrewCandidateScorer.php
        │   ├── CrewCoverageCalculator.php
        │   ├── CrewCalendarRowBuilder.php
        │   └── CrewDaysCalculator.php        (DST-safe local-day enumeration)
        └── Settings/
            └── CrewSettings.php

tests/
├── Feature/Crew/
│   ├── Actions/ (one file per action group)
│   ├── Queries/
│   ├── Livewire/
│   ├── Api/
│   ├── Policies/
│   ├── Notifications/
│   └── Settings/
├── Unit/Crew/ (calculators, enums, DST day enumeration)
└── Pgsql/Crew/ (concurrency, constraints, locking)
```

**Not created:** no `Repositories/`, no `Mappers/`, no `CQRS/`, and no new
top-level `app/Crew/` or bare `app/Actions/Crew/` folder. The domain keeps the
established layering but roots every Crew artifact in `app/Domain/Crew`, with
schema and factories in its own `Database/` namespace. All Crew HTTP handlers,
Livewire components, routes and Blade views are grouped in `Presentation/`. The
provider registers the `crew::` view namespace. Only tests and necessary shared
permission, provider, notification, webhook and navigation integration live outside
the Crew domain.

---

## N. Unresolved business decisions

The recommended defaults below were approved on 1 November 2026. Runtime settings and explicit rate-card terms remain the source of configured policy. Later-phase integrations remain separately scoped.

| # | Decision | Options | Recommendation | Impact if wrong |
| --- | --- | --- | --- | --- |
| N1 | Overtime pay model | (a) multiplier replaces base for OT minutes, (b) base for all minutes + premium on OT | **(a)** replace | Changes every timesheet total |
| N2 | Do multipliers stack? | single highest vs multiplicative | **single highest** | Payroll disputes |
| N3 | Daily rate partial day | full day vs pro-rata | **full day** | Cost forecasting |
| N4 | `daily_call_hours` for daily rates | 8 / 10 / 12 / per rate card | **10, per rate card** | Overtime boundary |
| N5 | Break pay | unpaid (MVP) vs paid | **unpaid** | Totals |
| N6 | Weekend definition | Sat+Sun vs Fri+Sat vs configurable | **Sat+Sun, configurable per store** | Multipliers |
| N7 | Night window | 00:00–06:00 vs 22:00–06:00 | **00:00–06:00 default, configurable** | Multipliers |
| N8 | Rest enforcement | hard / warning / off | **hard for employees, warning for freelancers** | Safety + compliance |
| N9 | Cancellation pay window | none / 24h / 48h / 72h | **48h, call minimum paid** | Cost + fairness |
| N10 | Invite expiry | manual / 24h / 72h | **72h, auto-expire to `cancelled`** | Coverage |
| N11 | Who may confirm an assignment | planner / coordinator / crew member | **planner with `crew.assignments.confirm`**, member confirm is self-service only | Authority |
| N12 | Is a crew member scoped to a store? | global / per store | **global** | Duplicate people, conflicts missed |
| N13 | Are rate cards store-specific? | global only / store override | **global with optional store override** | Pay equity |
| N14 | Timesheet granularity | per assignment per period / per project / per member | **per assignment per period** | Approval load |
| N15 | Self-service timesheets | never / optional / required | **optional, off by default** | Privacy scope |
| N16 | Per diem trigger | any work / ≥ 4h / ≥ 6h | **≥ 4h** | Cost |
| N17 | Travel pay | unpaid / base rate / travel rate / overtime | **travel rate if set, else base; no OT** | Cost + law |
| N18 | Qualification expiry behaviour | block assignment / warn only | **block if mandatory** | Compliance |
| N19 | Project schedule change | auto-move / flag for review | **flag for review** (required by brief) | Data trust |
| N20 | Expense reimbursement owner | Crew / Finance | **Finance pays, Crew tracks state** | Accounting |
| N21 | Currency of agreed rate | store currency / member currency / project currency | **store currency, snapshot on confirmation** | Reporting |
| N22 | Rounding mode | none / up / nearest / down | **nearest 15 min** | Payroll |
| N23 | Do proposed assignments reserve capacity? | no / soft hold | **soft hold, shows as `pending` in coverage, never blocks** | Double booking |
| N24 | Crew member deletion | soft only / hard allowed | **soft only; anonymise on request** | GDPR vs audit |
| N25 | Public holiday calendar source | manual list / country calendar table | **list values per country** (existing `list_values`) | Multipliers |
| N26 | Do external crew members need supplier cost tracking? | no / yes | **Phase 2, `supplier_member_id` + expense** | Margin reporting |
| N27 | Timesheet approval separation | same person / different person | **different person unless break-glass** | Fraud risk |
| N28 | Minimum shift length | none / 2h / 4h | **none; `min_call_hours` covers pay** | Scheduling noise |

---

## O. Implementation roadmap

### O.1 Phases

| Phase | Scope | Depends on | Exit criteria |
| --- | --- | --- | --- |
| **0. Foundations** | Enums, settings group, permissions, module gate, status registry, migrations 1–19, factories, policies skeleton | — | `php artisan migrate` clean on sqlite + pgsql; permission seeder green; enum transition tests pass |
| **1. Registry** | `CrewMember`, roles, skills, qualifications, rate cards, member pages, registry API | 0 | A crew member can be created without a `User`, given roles/skills/qualifications, and priced by a rate card |
| **2. Requests** | Requests, lines, requirements, publish/cancel, coverage, request pages | 1 | A published request shows requested vs filled vs remaining per line |
| **3. Assignments** | Propose/invite/confirm/reject/cancel/substitute, conflict detector, duplicate key | 2 | Confirming fills the line exactly once; duplicates are impossible under concurrency |
| **4. Shifts** | Shift CRUD, overnight, DST, bulk confirm, realignment, planner + calendar | 3 | Back-to-back shifts do not conflict; DST shift bills elapsed UTC; project changes never move shifts silently |
| **5. Availability** | Request/approve/reject leave, windows query, calendar overlay | 1 | Approved leave hard-blocks; requested leave warns |
| **6. Compensation** | Duration/rounding/overtime/allowance calculators, cost estimate, worked examples as tests | 3 | Every compensation case in [L.5](#l5-critical-edge-case-catalogue) is green; snapshots immutable |
| **7. Timesheets** | Timesheets, entries, submit/approve/reject/lock, variance, Finance hand-off | 6 | Approval freezes totals; locked records cannot be edited; payment state remains external |
| **8. Planning & reports** | Candidate matching, shortages, utilisation, cost forecast, work history, dashboard | 4, 5, 7 | Candidate list is ranked and explainable; shortage scan notifies once per line per day |
| **9. API & webhooks** | REST controllers, abilities, webhook allow-list, payloads, docs | 1–8 | Every endpoint is authenticated, authorised, filtered, and documented by Scramble |
| **10. Expenses** | Expense claims, receipts, approval, reimbursement reference | 7, Finance contract | Claim can be submitted with a receipt and marked reimbursed by reference only |
| **11. Hardening** | Postgres concurrency lane, privacy/anonymisation, retention command, performance pass | all | Concurrency, privacy, and performance acceptance criteria below are met |

### O.2 Acceptance criteria (definition of done)

**Schema and models**

- [ ] All 19 tables migrate on sqlite and pgsql; rollback is clean.
- [ ] No DB `ENUM` columns; every status is a string cast to a native enum.
- [ ] Every FK declares its delete behaviour; every range and status query has a supporting index.
- [ ] `unique(active_assignment_key)` prevents duplicate active assignments.
- [ ] Money columns are `*_minor` integers; every model exposing money uses `FormatsMoney`.

**Behaviour**

- [ ] `CrewRequest`, `CrewAssignment`, `CrewShift`, and time entries are separate aggregates with separate lifecycles.
- [ ] One assignment can own many shifts; one member can work across projects without breaking conflict detection.
- [ ] Confirm is blocked by any hard conflict; warnings require an audited override.
- [ ] Overnight and DST cases produce the documented paid minutes.
- [ ] Confirmed compensation is immutable; renegotiation creates a new assignment.
- [ ] Timesheet approval freezes totals and is independent of payment.
- [ ] A project schedule change never mutates a confirmed shift; realignment is explicit and audited.

**Identity and privacy**

- [ ] Every Crew operation works with `user_id = null`.
- [ ] No payroll, bank, or tax data is stored.
- [ ] `internal_notes` and rate amounts are excluded from unauthorised DTOs and API lists.
- [ ] Anonymisation removes personal contact data while keeping financial history.

**Integration**

- [ ] Crew never writes to `projects`, `members`, `users`, `invoices`, `supplier_bills`, or stock tables.
- [ ] Every listed action writes an `action_logs` row.
- [ ] Every webhook name is registered in `WebhookEventRegistry` and delivered once.
- [ ] Notification types are seeded, mappable in `config/notifications.php`, and editable as templates.

**Quality**

- [ ] `vendor/bin/pint --dirty --format agent` clean.
- [ ] No N+1 in member lists, coverage, or candidate matching (query-count tests).
- [ ] Postgres concurrency tests pass for duplicate proposal, overlapping confirmation, and stale timesheet writes.
- [ ] Scramble docs generate without errors for all Crew endpoints.

### O.3 Delivery guardrails

1. Each phase ships behind `settings()->moduleEnabled('crew')`; the module flag
   already exists in `FeatureProfile`.
2. No phase may alter existing tables. All changes are additive migrations.
3. No phase may change `Projects`, `Members`, or `Users` behaviour without a
   separate, approved change; the only exception is the new
   `project.schedule_changed` audit event, which is additive.
4. The navigation placeholder in
   `resources/views/components/layouts/app/header.blade.php` is replaced only in
   phase 1, behind `@can('crew.access')`.
5. `docs/documentation.json` gains a Crew entry only when phase 1 is
   implemented and reviewed.
6. Phase 6 must land before phase 7; timesheets cannot be approved without
   frozen, tested compensation maths.

---

## Appendix 1. Permission keys (copy-ready)

```text
crew.access
crew.view
crew.create
crew.edit
crew.delete
crew.view.sensitive
crew.rates.view
crew.rates.manage
crew.requests.view
crew.requests.create
crew.requests.edit
crew.requests.publish
crew.requests.cancel
crew.assignments.view
crew.assignments.create
crew.assignments.confirm
crew.assignments.cancel
crew.assignments.substitute
crew.shifts.view
crew.shifts.manage
crew.availability.view
crew.availability.request
crew.availability.approve
crew.timesheets.view
crew.timesheets.create
crew.timesheets.approve
crew.timesheets.lock
crew.timesheets.export
crew.expenses.view
crew.expenses.create
crew.expenses.approve
crew.reports.view
crew.conflicts.override
crew.settings.manage
```

## Appendix 2. Webhook event names (copy-ready)

```text
crew.member.created
crew.member.updated
crew.member.archived
crew.member.restored
crew.request.created
crew.request.published
crew.request.cancelled
crew.request.filled
crew.assignment.proposed
crew.assignment.invited
crew.assignment.confirmed
crew.assignment.rejected
crew.assignment.cancelled
crew.assignment.substituted
crew.shift.created
crew.shift.updated
crew.shift.confirmed
crew.shift.cancelled
crew.availability.approved
crew.timesheet.submitted
crew.timesheet.approved
crew.timesheet.rejected
crew.timesheet.locked
crew.timesheet.exported_to_finance
crew.expense.submitted
crew.expense.approved
crew.expense.rejected
crew.expense.reimbursed
crew.shortage.detected
```

## Appendix 3. Notification type keys (copy-ready)

```text
crew.assignment.invited
crew.assignment.confirmed
crew.assignment.cancelled
crew.assignment.substituted
crew.assignment.invite_reminder
crew.request.published
crew.request.filled
crew.availability.requested
crew.availability.approved
crew.availability.rejected
crew.shift.changed
crew.shift.cancelled
crew.timesheet.submitted
crew.timesheet.approved
crew.timesheet.rejected
crew.qualification.expiring
crew.qualification.expired
crew.shortage.detected
crew.expense.submitted
crew.expense.approved
crew.expense.rejected
```

## Appendix 4. Settings keys (copy-ready)

```text
crew.rounding_mode
crew.rounding_increment_minutes
crew.daily_overtime_threshold_hours
crew.weekly_overtime_threshold_hours
crew.week_starts_on
crew.daily_ot_multiplier
crew.weekly_ot_multiplier
crew.night_multiplier
crew.weekend_multiplier
crew.holiday_multiplier
crew.night_window_start
crew.night_window_end
crew.daily_call_hours
crew.min_rest_hours
crew.rest_enforcement
crew.travel_earns_overtime
crew.stack_multipliers
crew.prorate_partial_days
crew.per_diem_min_minutes
crew.qualification_expiry_warning_days
crew.critical_shortage_days
crew.invite_expiry_hours
crew.cancellation_pay_hours
crew.default_break_minutes
crew.allow_self_service_timesheets
crew.allow_conflict_override
crew.pii_retention_months
crew.max_timesheet_period_days
```

## Appendix 5. Open follow-ups outside this document

| Follow-up | Owner | Note |
| --- | --- | --- |
| Add `docs/documentation.json` entry for Crew | Docs | Intentionally not edited; requires approval |
| Add `project.schedule_changed` audit event to Projects | Projects | Prerequisite for [K.6](#k6-project-schedule-change-policy-critical-requirement) |
| Add `crew` to `WebhookEventRegistry::CORE_EVENT_NAMES` | API | Phase 9 |
| Add `crew:read` / `crew:write` token abilities | API | Phase 9 |
| Add `App\Domain\Crew\Settings\CrewSettings` to the settings registry | Settings | Done in Phase 0 via `CrewServiceProvider` |
| Decide whether MySQL support is required | Platform | No MySQL connection exists today |
| Confirm every item in [N](#n-unresolved-business-decisions) | Product | Blocks phase 6 sign-off |

---

**End of design specification.** The implementation status and remaining extensions are described at the top of this page. The unchecked roadmap above is retained as the original design checklist, not a live delivery report.
