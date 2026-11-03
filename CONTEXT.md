# Signals Rental Framework

Shared language for the rental-management application and its business domains.

## Language

**API**:
The integration boundary through which external clients discover capabilities, authenticate, submit requests, and observe usage. Business resources remain owned by their respective domains.
_Avoid_: API Platform, ApiPlatform

**Administration**:
The application boundary for managing users, roles, permissions, invitations, and ownership. Authentication determines identity and access sessions but does not own user administration.
_Avoid_: Admin domain, User Management

**Settings**:
Application-wide configuration shared across features. Feature-specific configuration remains part of the domain that owns the affected behavior.
_Avoid_: Preferences, System Settings

**Numbering**:
The shared capability for allocating internal counters and formatting user-visible identifiers. The business entity receiving a number remains owned by its own domain.
_Avoid_: Document Numbering, Sequence Management

**System**:
The operational boundary for application health, runtime infrastructure diagnostics, and controlled maintenance tools. Initial installation and business configuration remain outside this boundary.
_Avoid_: Platform, Setup, System Settings
