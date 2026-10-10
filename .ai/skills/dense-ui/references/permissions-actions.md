# Permissions, actions, and safety

## Domain state versus permission

Ask in order:

1. Can the domain perform the action at all?
2. Is it valid in the current state/context?
3. Is the user authorized?
4. What representation gives the correct reason without exposing sensitive information?

Examples:
- No permission: “You do not have permission to adjust stock.”
- State restriction: “Stock cannot be adjusted while this stocktake is active.”
- Domain restriction: “Serialized assets cannot use bulk adjustment.”

Do not misrepresent a domain rule as authorization failure.

## Visibility model

- HIDE: action is irrelevant, sensitive, or should not be exposed in this context.
- DISABLE: action is relevant but temporarily unavailable; explain how it becomes available when that is clear.
- READ-ONLY: show the value and context while editing is not allowed.
- UNAVAILABLE: action is not applicable in the current state; show an explanation when discoverability matters.
- ENABLE: action is valid and authorized.

Do not leave unexplained disabled controls. Do not expose sensitive data merely to explain permissions.

## Action hierarchy

Classify actions as PRIMARY, FREQUENT, SECONDARY, CONTEXTUAL, BULK, DESTRUCTIVE, or RARE/ADVANCED.

Visible actions tend to be frequent, time-sensitive, core to the workflow, or required repeatedly. Overflow actions tend to be secondary, administrative, rare, context-specific, or destructive-but-infrequent. Use one primary action per context where practical.

## Destructive safety

Confirmation is justified for irreversible, financially/operationally significant, broad, external, or authoritative mutations. Show target, scope, consequence, and final action label.

Prefer undo, archive/restore, reverse transaction, or recoverable state when the domain supports it. Do not confirm every low-risk mutation.

## Bulk operations

When selection exists, show count and scope: “14 assets selected.” For bulk mutation communicate affected count, filters/select-all scope, consequence, progress, partial failure, and result access.

Example:
“Move 42 assets to Thessaloniki Warehouse?”
Afterward: “39 assets moved. 3 could not be moved.” Provide failure reasons and safe retry.

## Action decision tree

~~~mermaid
flowchart TD
 A[Action] --> B{Frequent, core, or time-sensitive?}
 B -->|Yes| V[Visible action]
 B -->|No| C{Relevant but unavailable now?}
 C -->|Yes| D{Clear way to enable?}
 D -->|Yes| X[Disabled with explanation]
 D -->|No| H[Hide or explain unavailable]
 C -->|No| E{Rare, secondary, or contextual?}
 E -->|Yes| O[Overflow action]
 E -->|No| V
~~~

## Safety tree

~~~mermaid
flowchart TD
 A[Mutation] --> B{Reversible or undoable?}
 B -->|Yes| U[Prefer undo/reversal]
 B -->|No| C{Destructive, consequential, broad, external, or authoritative?}
 C -->|Yes| Q[Contextual confirmation]
 C -->|No| I[Immediate action with clear result]
~~~

