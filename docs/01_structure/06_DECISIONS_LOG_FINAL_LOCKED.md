

# 06_DECISIONS_LOG.md
Version: FINAL (Architecture Freeze v1 – COMPLETE)
Status: Governance Locked

------------------------------------------------------------
PURPOSE
------------------------------------------------------------

This document records all structural, behavioral, and governance decisions.
If a rule exists here, it is considered frozen unless explicitly revised
with a new numbered decision entry.

============================================================
SECTION 1: ARCHITECTURE & DEPLOYMENT
============================================================

Decision 001 – Architecture Freeze
System architecture is frozen at v1.
No structural changes allowed without updating this log.

Decision 002 – Vertical Separation
Green Belt, Advertisement, and Monitoring operate as separate domains.
Cross-domain reporting allowed.
Cross-domain editing not allowed.

Decision 003 – Phase Separation
Map View is Phase 2.
Not included in v1 schema or data model.

Decision 004 – Hosting Target
System must be deployable on shared hosting (PHP + MySQL).
No VPS-specific dependency in v1.

============================================================
SECTION 2: GREEN BELT GOVERNANCE
============================================================

Decision 005 – Maintenance Cycle Model
Each belt has manual maintenance cycles.
Head Supervisor may backdate cycle start within current month only.
Cycle must be closed manually unless auto-closed by rule.

Decision 006 – Auto-Close Rules
Active cycle auto-closes when:
- Belt is hidden
- Belt permission becomes Expired
System must log auto-closure in audit log.

Decision 007 – Watering Frequency Model
Watering Required replaced by Watering Frequency enum:
- Daily
- Alternate
- Weekly
- None
Editable by Ops only.

Decision 008 – Watering Frequency Change Behavior
When frequency changes:
- Applies from date of change
- No retroactive compliance recalculation
Past records remain unchanged.

Decision 009 – Watering Enforcement
Supervisor & Head can mark only current date.
Ops may backdate within same month (audit logged).
Override requires mandatory reason input.

Decision 010 – Watering Unique Constraint
Only one watering record allowed per belt per date.
Enforced at database level.

Decision 011 – Watering Compliance Scope
Watering compliance applies only to:
- Active belts
- Maintenance Mode = Maintained
Outsourced belts excluded.

Decision 012 – Labour Model
Labour entered per belt per day.
Editable only within current month.
Automatically aggregated to supervisor via belt assignment.

Decision 013 – Attendance Model (Green Belt)
Head Supervisor records attendance.
"Past-month records are locked by default.
Only Ops role can perform override on locked records.
All overrides must require a reason and must be recorded in audit_logs.
Overrides are action-specific and do not unlock the entire month or dataset."
Attendance missing triggers alert.

Decision 014 – Attendance Non-Blocking Rule
If supervisor marked Absent:
- Watering allowed
- Upload allowed
- Labour allowed
System only displays absence status.

Decision 015 – Outsourced Belt Model
Outsourced belts:
- No watering compliance tracking
- No daily upload expectation
- Visible in dashboard for activity monitoring only

============================================================
SECTION 3: UPLOAD & ISSUE GOVERNANCE
============================================================

Decision 016 – Upload Classification
Every upload must be explicitly classified as:
- Work
- Issue
Single type only.

Decision 017 – Upload Parent Immutability
Upload must belong to exactly one parent (belt/site/task).
Parent cannot change after creation.

Decision 018 – Upload Review Model
Only Ops can approve/reject uploads.
Issue uploads cannot be approved.
Rejection reason internal only.

Decision 019 – Supervisor Self-Delete Rule
Supervisor may delete own upload within 5 minutes.
After 5 minutes, locked (soft delete only).

Decision 020 – Upload Retention Policy
Approved uploads never auto-deleted.
Rejected uploads require manual purge.
Self-deleted uploads auto-purged after 30 days.

Decision 021 – Issue Lifecycle
States:
- Open
- In Progress
- Closed
Only Ops can close.
Optional one-to-one task link.
Task completion does NOT auto-close linked issue.
UI may derive "Resolution Attempted" when a linked task is completed.

============================================================
SECTION 4: FABRICATION & WORKER GOVERNANCE
============================================================

Decision 022 – Task Status Model
Task states:
- Open
- Running
- Completed
- Cancelled
- Archived (manual)

Decision 023 – Archive Rule
Manual archive only.
Archive never deletes records.

Decision 024 – Worker Attendance Model
Ops records worker attendance.
"Past-month records are locked by default.
Only Ops role can perform override on locked records.
All overrides must require a reason and must be recorded in audit_logs.
Overrides are action-specific and do not unlock the entire month or dataset."
ON_LEAVE suppresses alerts.

Decision 025 – Worker Daily Entry Dependency
Daily work entry allowed only if attendance exists for that date.
6 PM alert if no entry recorded.

Decision 026 – Task Metadata Standardization
Each task must include:
- Category
- Vertical Type
- Priority
- Location Text
- Task Source

============================================================
SECTION 5: REPORTING & VISIBILITY
============================================================

Decision 027 – Report Scope
All reports are calendar-month based.
CSV export only (v1).
Archived tasks included in reports.

Decision 028 – Authority Visibility Model
Authorized Person can see:
- Assigned belts only
- Approved uploads only
Historical uploads remain visible after belt expiry.

============================================================
SECTION 6: PERMISSION & ROLE MODEL
============================================================

Decision 029 – Role-Based Access Only
No micro-permission toggles per user.
Predefined role groups only.

Decision 030 – Vertical Scope Restriction
Users limited to assigned vertical.
No cross-vertical modification rights.

Decision 031 – Audit Logging Requirement
All overrides, backdates, status changes, auto-closures logged.
Audit log accessible only by Ops.

============================================================
SECTION 7: ALERT & LOCK MODEL
============================================================

Decision 032 – Month Lock Rule
Attendance, labour, watering entries, and worker entries follow this rule:
"Past-month records are locked by default.
Only Ops role can perform override on locked records.
All overrides must require a reason and must be recorded in audit_logs.
Overrides are action-specific and do not unlock the entire month or dataset."

Decision 033 – Notification Panel Model
Alerts grouped by category.
Panel provides navigation only.
No editing from panel.


============================================================
SECTION 8: SCHEMA FINALIZATION (v1 LOCK)
============================================================

Decision 034 – Supervisor Assignment Structural Revision
default_supervisor_id removed from green_belts.
Historical supervisor ownership moved to belt_supervisor_assignments.
Single source of truth enforced.
Date overlap prevention enforced at service layer.

Decision 035 – Maintenance Cycle Active Enforcement Model
Single active maintenance cycle per belt enforced at service layer.
Database uses INDEX (belt_id, is_active).
UNIQUE constraint intentionally rejected.
Rationale: Allow multiple historical closed cycles.

Decision 036 – Schema v1 Freeze Declaration
Total 18 tables finalized.
All ENUM vocabularies locked.
No structural redesign permitted without migration.
Schema Supremacy Rule activated.

Decision 037 – Sites Entity Introduction
Minimal sites master introduced via migration 002_add_sites_table.sql.
Relationships and monitoring frequency remain intentionally deferred.

Decision 038 – API Response Contract
All API responses must follow a strict JSON structure:

Success:
{ "success": true, "data": <payload> }

Error:
{ "success": false, "error": "<message>" }

No alternative response formats are allowed.

Decision 039 – HTTP Method Discipline
All routes must follow strict HTTP method semantics:

- GET → Read operations only
- POST → Create operations, authentication, and lifecycle actions
- PUT → Update operations
- DELETE → Delete operations

No deviation from this mapping is allowed.

Decision 040 – Route Parameter Standardization
All entity routes must use a standardized identifier parameter:

- Use `id` for all entity identifiers
- Do not use variations like user_id, belt_id, etc. in route definitions

Internal data structures may use specific field names, but route interfaces must remain consistent.

Decision 041 – Login Audit Constraint
No audit log must be created when login fails due to a non-existent email.

Audit logging is allowed only when a valid user record exists.

============================================================
STATUS
============================================================

Architecture Fully Frozen – v1 Stable.
All governance rules consolidated.
### Email Uniqueness Rule

Decision:
Email uniqueness is enforced globally, including soft-deleted users.

Rationale:
- Prevent identity ambiguity
- Maintain audit consistency
- Ensure reliable tracking of historical ownership

Impact:
- Users cannot re-register using the same email after deletion
- Email remains permanently reserved once used
- System prioritizes governance over convenience
