# 03_DESIGN_PRINCIPLES.md

Version: Architecture Freeze v1 Status: Engineering & Implementation
Discipline Blueprint

------------------------------------------------------------------------

## 1. PURPOSE

This document defines the engineering philosophy and implementation
discipline for the Integrated Operations & Monitoring System.

It governs:

-   Coding standards
-   Architectural boundaries
-   Schema discipline
-   UI discipline
-   Security enforcement
-   Commenting policy
-   Future extensibility control
-   Change management rules

This document ensures the system remains maintainable, debuggable, and
interview-grade in structure.

------------------------------------------------------------------------

## 2. CORE ARCHITECTURAL PHILOSOPHY

The system must be:

-   Governance-first
-   Audit-safe
-   Explicit (no hidden behavior)
-   Deterministic
-   Controller-enforced
-   Schema-driven
-   Extensible without structural breakage

No automation may mutate operational data silently.

------------------------------------------------------------------------

## 3. SEPARATION OF CONCERNS

Strict separation required between:

1.  Database Schema
2.  Business Logic (Service Layer)
3.  UI Rendering
4.  Permission Enforcement
5.  Alert Calculation
6.  Reporting Queries

Rules:

-   No business logic inside UI templates.
-   No permission checks only in front-end.
-   No direct database mutation from view layer.
-   No mixing compliance logic with rendering logic.

------------------------------------------------------------------------

## 4. SCHEMA DESIGN PRINCIPLES

1.  Every table must have:

    -   id (Primary Key)
    -   created_at
    -   updated_at

2.  ENUM fields allowed (v1 decision).

3.  All UNIQUE constraints must be explicitly defined.

4.  All foreign keys must use proper constraints.

5.  Parent-child relationships marked immutable must not be updateable
    at database level.

6.  No redundant data storage when calculation is possible.

7.  Alerts must not be stored as rows in database.

------------------------------------------------------------------------

## 5. BACKEND LAYER DISCIPLINE

Controllers handle HTTP input/output only and must not contain business logic.
Controllers handle request validation (format, required fields).
Services handle business validation and domain rules.
All business rules and system behavior must be implemented in the Service layer.
Services are the single source of truth for all business rules and system behavior.
Repositories are responsible only for database access and must not contain business logic.
Authorization (RBAC) must be enforced at the middleware layer before reaching controllers. Services must not perform role-based access checks.

Controllers must:

-   Validate request format before action
-   Validate required fields before calling services
-   Return clear error responses

Middleware must enforce role access before controller execution.
Service layer must enforce month-lock and override logging rules.

------------------------------------------------------------------------

## 6. COMMENTING POLICY (MANDATORY)

All code must be self-explanatory with structured comments.

Each:

-   Table
-   Field
-   Function
-   Conditional rule
-   Override block
-   Lock rule

Must include comment explaining:

-   Why it exists
-   What constraint it enforces
-   What assumption it depends on

Comment style must be clear English, not shorthand developer slang.

------------------------------------------------------------------------

## 7. NO SILENT DATA MUTATION RULE

The system must never:

-   Auto-close issues silently
-   Auto-modify watering logs
-   Auto-correct attendance
-   Auto-adjust labour counts
-   Auto-change permission status without explicit rule

Only allowed automatic state changes:

-   Permission auto-expiry (Agreement Signed → Expired)
-   Maintenance cycle auto-close when belt expires or hidden

All such changes must be traceable.

------------------------------------------------------------------------

## 8. LOCK ENFORCEMENT RULE

Month-lock enforcement applies to:

-   Attendance
-   Labour logs
-   Watering entries
-   Daily work entry

Rule:

"Past-month records are locked by default.
Only Ops role can perform override on locked records.
All overrides must require a reason and must be recorded in audit_logs.
Overrides are action-specific and do not unlock the entire month or dataset."

------------------------------------------------------------------------

## 9. ALERT ENGINE PRINCIPLE

Alerts must:

-   Be calculated dynamically
-   Not persist in database
-   Be grouped by category
-   Be derived from current data state

Alerts must not modify data.

------------------------------------------------------------------------

## 10. UI DESIGN PRINCIPLES

Field Users (Supervisors / Monitoring):

-   Minimal mandatory inputs
-   No forced tagging complexity
-   Clear single-action buttons
-   Mobile-first layout

Ops & Management:

-   Structured dashboards
-   Clear alert grouping
-   Monthly reports exportable
-   No operational overload on dashboards

------------------------------------------------------------------------

## 11. PERFORMANCE PRINCIPLES

Since system targets shared hosting:

-   Avoid heavy joins in dashboards
-   Use indexed foreign keys
-   Paginate upload lists
-   Avoid loading 100+ images at once
-   Optimize monthly report queries

No background cron dependencies required for core functionality.

------------------------------------------------------------------------

## 12. EXTENSIBILITY RULE

Future feature addition must:

1.  Update 05_DATA_AND_FLOW_NOTES.md first.
2.  Update Decisions Log.
3.  Update Roles & Access if needed.
4.  Avoid breaking existing schema.
5.  Avoid altering historical data.

No direct schema modification without documentation update.

------------------------------------------------------------------------

## 13. VERSION DISCIPLINE

Each structural change requires:

-   Documentation update
-   Git commit with structured message
-   Version increment note

------------------------------------------------------------------------

## 14. INTERVIEW-READINESS STANDARD

System must demonstrate:

-   Clear separation of concerns
-   Clean RBAC enforcement
-   Audit-safe lifecycle transitions
-   Deterministic compliance engines
-   Proper documentation layering
-   Git workflow discipline

This is not just an internal tool. It is a portfolio-grade system.

------------------------------------------------------------------------

STATUS: Engineering discipline locked under Architecture Freeze v1.
