# Skyte Ops --- Schema Specification v1

Architectural Defense & Design Rationale Document

This document explains: - Why each table exists - Why each constraint
exists - Why alternative designs were rejected - How lifecycle rules are
enforced - How governance integrity is preserved

This document is aligned with Schema Baseline v1.

------------------------------------------------------------------------

# 1. roles

## Purpose

Defines RBAC authority boundaries across operational verticals.

## Design Rationale

Vertical scope is controlled via ENUM to prevent runtime privilege
drift. We intentionally avoided dynamic permission flags to ensure
governance clarity.

## Constraints

-   role_name UNIQUE
-   vertical_scope ENUM
    ('GREEN_BELT','ADVERTISEMENT','MONITORING','ALL')

## Why ENUM?

Permissions must not change casually. Governance systems require
controlled vocabulary.

------------------------------------------------------------------------

# 2. users

## Purpose

Stores authenticated actors of the system.

## Design Rationale

Users are governance actors. Every operational mutation must trace back
to a user.

## Key Decisions

-   Soft delete enabled to preserve historical accountability.
-   deleted_by self-reference ensures audit chain continuity.
-   ON DELETE SET NULL prevents FK cascade damage.

## Rejected Alternative

Hard delete was rejected due to audit loss risk.

### Email Uniqueness Policy

Email is globally unique across the system.

- A user's email remains reserved even after soft deletion.
- Soft-deleted users (is_deleted = 1) continue to block reuse of the same email.
- This ensures identity consistency and prevents historical ambiguity.

Rationale:
This system is governance-driven and audit-focused. Allowing email reuse would create ambiguity in logs, reports, and ownership tracking.

Therefore:
Email reuse after deletion is NOT allowed.

------------------------------------------------------------------------

# 3. system_meta

## Purpose

Tracks schema_version for migration control.

## Design Rationale

Schema changes must be versioned and traceable. This prevents silent
structural drift.

------------------------------------------------------------------------

# 4. audit_logs

## Purpose

Immutable mutation trail.

## Design Rationale

All structural and operational changes must be traceable. Entity_type +
entity_id indexing ensures fast trace retrieval.

## Governance Role

Supports override accountability and compliance review.

------------------------------------------------------------------------

# 5. sites

## Purpose

Stores monitoring site master records.

## Fields

-   id
-   name
-   location
-   is_active
-   created_at
-   updated_at

## Design Rationale

Sites are introduced as a minimal entity so monitoring assets can be
represented explicitly without defining broader relationships in this
step.

------------------------------------------------------------------------

# 6. green_belts

## Purpose

Core operational entity representing maintained assets.

## Design Rationale

Belt contains compliance-defining attributes: - permission_status -
maintenance_mode - watering_frequency

## Why Not Store Compliance Flag?

Compliance is dynamic. Storing compliance would create drift risk.

## Index Strategy

Composite index (maintenance_mode, permission_status, hidden) Optimizes
compliance filtering.

------------------------------------------------------------------------

# 7. belt_supervisor_assignments

## Purpose

Historical supervisor ownership resolution.

## Design Rationale

We rejected default_supervisor_id in green_belts to avoid dual source of
truth. Historical table preserves accountability per date.

## Why No DB Overlap Constraint?

Date range overlap logic requires complex checks. Enforced in service
layer for flexibility.

## Governance Impact

Labour and watering responsibility derived dynamically per date.

------------------------------------------------------------------------

# 8. belt_authority_assignments

## Purpose

Visibility control for authority users.

## Design Rationale

Multiple authority users per belt allowed. No UNIQUE(belt_id) to
preserve flexibility.

------------------------------------------------------------------------

# 9. maintenance_cycles

## Purpose

Defines operational maintenance window per belt.

## Design Rationale

-   Multiple historical cycles allowed.
-   Single active cycle enforced via service-layer transaction.

## Why Not UNIQUE(belt_id, is_active)?

Would block multiple closed cycles. Service-layer enforcement chosen
intentionally.

## Lifecycle

Active → Closed. No reopen allowed.

## Governance Role

Determines compliance evaluation window.

------------------------------------------------------------------------

# 10. watering_logs

## Purpose

Daily watering compliance record.

## Constraints

UNIQUE(belt_id, watering_date)

## Design Rationale

Enforces single watering per day structurally. Override requires reason
for governance transparency.

## Why Not Store "watering_done" Flag?

Logs represent truth. Compliance derived dynamically.

------------------------------------------------------------------------

# 11. supervisor_attendance

## Purpose

Tracks daily supervisor presence.

## Key Rule

Does not block watering (intentional).

## Rationale

Attendance and watering are independent governance signals.

------------------------------------------------------------------------

# 12. labour_entries

## Purpose

Tracks manpower allocation per belt per day.

## Constraint

UNIQUE(belt_id, labour_date)

## Design Rationale

Ensures deterministic reporting. Override requires reason.

------------------------------------------------------------------------

# 13. tasks

## Purpose

Operational unit of work for Advertisement & Monitoring verticals.

## Lifecycle

OPEN → RUNNING → COMPLETED\
OPEN → CANCELLED

Backward transitions prohibited.

## Design Rationale

-   priority ENUM includes CRITICAL.
-   archived flag separates visibility from lifecycle state.

## Rejected Alternative

Soft delete rejected to preserve operational history.

------------------------------------------------------------------------

# 13. issues

## Purpose

Operational problem tracking linked to BELT or SITE.

## Key Constraint

UNIQUE(task_id) ensures 1:1 issue-task relationship.

## Design Rationale

Issue and Task separation preserves domain clarity. Issue parent
polymorphism limited to BELT/SITE for clarity.

------------------------------------------------------------------------

# 14. uploads

## Purpose

Evidence storage for work and issue validation.

## Parent Model

Supports BELT, SITE, TASK, ISSUE (single parent only).

V1 service-layer rule is narrower:
- parent_type = ISSUE must be rejected.
- Only BELT parent WORK uploads are eligible for authority approval.
- SITE and TASK uploads remain internal.

## Why Polymorphic?

Prevents duplication of upload tables. Maintains structural simplicity.

## Soft Delete

Allowed. Approved uploads permanent (service-layer enforced).

------------------------------------------------------------------------

# 15. workers

## Purpose

Field labour registry.

## Design Rationale

No login credentials stored. Workers are operational entities, not
system actors.

------------------------------------------------------------------------

# 16. worker_attendance

## Purpose

Daily worker presence record.

## Constraint

UNIQUE(worker_id, attendance_date)

## Governance Rule

Worker daily entry requires attendance (service-layer enforced).

------------------------------------------------------------------------

# 17. worker_daily_entries

## Purpose

Tracks planned vs executed work per worker per day.

## Design Rationale

Multiple entries per day allowed. Linked optionally to task.

## Governance Interaction

Past-month records are locked by default.
Only Ops role can perform override on locked records.
All overrides must require a reason and must be recorded in audit_logs.
Overrides are action-specific and do not unlock the entire month or dataset.
Attendance dependency enforced.

------------------------------------------------------------------------

# Global Design Principles

-   No stored compliance flags.
-   No cascade deletes on governance entities.
-   ENUM vocabularies frozen under Schema v1.
-   Past-month records are locked by default. Only Ops role can perform override on locked records. All overrides must require a reason and must be recorded in audit_logs. Overrides are action-specific and do not unlock the entire month or dataset. Month-lock is enforced at service layer.
-   Controllers handle HTTP input/output only and must not contain business logic.
-   Controllers handle request validation (format, required fields).
-   Services handle business validation and domain rules.
-   All business rules and system behavior must be implemented in the Service layer.
-   Repositories are responsible only for database access and must not contain business logic.
-   Authorization (RBAC) must be enforced at the middleware layer before reaching controllers. Services must not perform role-based access checks.
-   Database enforces structural truth only.

------------------------------------------------------------------------

# Architectural Integrity Summary

This schema: - Eliminates duplicate source of truth. - Preserves
historical accountability. - Separates structural integrity from
business logic. - Enforces governance without over-embedding logic in
DB. - Supports extensibility (finance as separate future module).

Schema v1 is architecturally stable and constitutionally frozen.
