# 05_DATA_AND_FLOW_NOTES.md

Version: Architecture Freeze v1 (Final) Status: Schema-Ready
Authoritative Blueprint

------------------------------------------------------------------------

0.  PURPOSE

This document defines the complete structural, behavioral, and
governance model of the system.

It governs: - Entities - Relationships - Lifecycle rules - Lock rules -
Override rules - Compliance engines - Alert logic - Archive behavior -
Reporting dependencies - Cross-entity effects

If behavior is not defined here, it does not exist.

------------------------------------------------------------------------

1.  GLOBAL SYSTEM RULES

2.  Every primary entity must include:

    -   id (Primary Key)
    -   created_at
    -   updated_at

3.  All override actions must include:

    -   override_by
    -   override_reason
    -   audit log entry

4.  Alerts are NOT stored in database.

    -   Alerts are calculated dynamically from current state.

5.  All reports operate on calendar month only.

6.  No circular foreign key dependencies allowed.

7.  Immutable relationships may not be modified after creation.

8.  No silent data mutation anywhere in system.

9.  User Operational State Rule

    A user is operationally usable only when:

    -   is_deleted = 0
    -   is_active = 1

    Non-deleted users may still be inactive and must not be treated as usable.

------------------------------------------------------------------------

2.  GREEN BELT DOMAIN

2.1 Green Belt (Master Entity)

Fields:

id

common_name

authority_name

zone

permission_status ENUM: APPLIED AGREEMENT_SIGNED RENEWAL_IN_PROCESS EXPIRED

permission_start_date

permission_end_date (nullable)

maintenance_mode ENUM: MAINTAINED OUTSOURCED

watering_frequency ENUM: DAILY ALTERNATE_DAY WEEKLY NOT_REQUIRED

watering_frequency_effective_from DATE

hidden BOOLEAN

Supervisor ownership is handled via belt_supervisor_assignments.

There is no supervisor field inside green_belts.

Assignment Rules:

Historical model

One active assignment per belt

No overlapping date ranges

Supervisor resolved dynamically by date

Rule:

When watering_frequency changes, watering_frequency_effective_from is set to current date.

Compliance calculations must respect effective_from date.

Past compliance calculations must not be recalculated.

------------------------------------------------------------------------

2.2 Permission Lifecycle Rules

-   Only AGREEMENT_SIGNED may auto-transition to EXPIRED.
-   If permission_end_date \< current_date → status becomes EXPIRED.
-   Auto-transition to EXPIRED must create audit log entry.
-   System must store previous_status and transition_timestamp.
-   When EXPIRED:
    -   Active maintenance cycle auto-closes.
    -   Watering compliance stops.
    -   Labour compliance stops.
    -   Attendance expectations stop.
-   Approved uploads remain historically visible.

------------------------------------------------------------------------

2.3 Maintenance Mode Rules

MAINTAINED: - Watering compliance active - Labour tracking active -
Attendance active - Maintenance cycle active

OUTSOURCED: - No watering compliance - No labour compliance - No
attendance compliance - Uploads allowed - Issues allowed

------------------------------------------------------------------------

2.4 Hidden Rule

If hidden = true: - Active maintenance cycle auto-closes - Watering
compliance stops - Labour compliance stops - Attendance expectation
stops - Historical data preserved

------------------------------------------------------------------------

3.  WATERING MODEL

3.1 Watering Frequency

-   Editable by Ops only
-   Effective from change date
-   No retroactive recalculation
-   Change logged in audit

If NOT_REQUIRED: - Watering UI hidden - No watering alerts generated

------------------------------------------------------------------------

3.2 Watering Log

UNIQUE (belt_id, watering_date)

Fields: - id - belt_id - watering_date - marked_by - marked_at -
override_by (nullable) - override_reason (nullable)

------------------------------------------------------------------------

3.3 Marking Rules

Supervisor / Head: - May mark current server date only - Cutoff = 20:00
server time - Cannot backdate - Cannot future-date

Past-month records are locked by default.
Only Ops role can perform override on locked records.
All overrides must require a reason and must be recorded in audit_logs.
Overrides are action-specific and do not unlock the entire month or dataset.

------------------------------------------------------------------------

3.4 Compliance Logic

DAILY: - Required every calendar day

ALTERNATE_DAY: - Required if days_since_last_watered \>= 2

WEEKLY: - Required if days_since_last_watered \>= 7

If no prior watering exists: - Watering expected immediately after belt
activation

Compliance stops when: - maintenance_mode = OUTSOURCED -
permission_status = EXPIRED - hidden = true

------------------------------------------------------------------------

3.5 Monthly Watering Compliance %

watered_days / expected_watering_days

Displayed in: - Belt Monthly Health Report - Supervisor Monthly Activity
Report

------------------------------------------------------------------------

4.  MAINTENANCE CYCLE

Fields: - id - belt_id - cycle_start_date - cycle_end_date (nullable) -
started_by - closed_by

Rules: - Manual start by Head Supervisor - Manual close by Head
Supervisor - Ops override allowed (audit required) - Alert if cycle open
\> 4 days - Auto-close if belt hidden or expired - Supervisor
reassignment allowed mid-cycle (audit logged)

------------------------------------------------------------------------

5.  ATTENDANCE SYSTEM

5.1 Green Belt Attendance

UNIQUE (supervisor_id, date)

-   Marked by Head Supervisor
-   Morning alert if missing
-   Past-month records are locked by default.
-   Only Ops role can perform override on locked records.
-   All overrides must require a reason and must be recorded in audit_logs.
-   Overrides are action-specific and do not unlock the entire month or dataset.
-   Does NOT block watering or uploads
-   If missing, alert visible on Master & Ops Dashboard

------------------------------------------------------------------------

5.2 Monitoring & Fabrication Attendance

UNIQUE (worker_id, date)

-   Marked by Ops
-   Morning alert if missing
-   Past-month records are locked by default.
-   Only Ops role can perform override on locked records.
-   All overrides must require a reason and must be recorded in audit_logs.
-   Overrides are action-specific and do not unlock the entire month or dataset.
-   If worker_status = ON_LEAVE:
    -   Attendance alert suppressed
    -   6 PM work-entry alert suppressed

------------------------------------------------------------------------

6.  LABOUR TRACKING

UNIQUE (belt_id, date)

Fields: - belt_id - date - labour_count

Rules: - Only valid when maintenance_mode = MAINTAINED - Morning alert
if missing - Past-month records are locked by default. Only Ops role can
perform override on locked records. All overrides must require a reason
and must be recorded in audit_logs. Overrides are action-specific and do
not unlock the entire month or dataset. - Aggregation based on
supervisor assigned on that specific date - Does NOT affect health
engine

------------------------------------------------------------------------

7.  UPLOAD MODEL

Fields: - id - upload_type ENUM (WORK, ISSUE) - parent_type ENUM (BELT,
SITE, TASK) - parent_id - authority_visibility ENUM (HIDDEN, APPROVED,
REJECTED) - created_by - created_at - soft_deleted BOOLEAN 
Default:
- authority_visibility = HIDDEN on creation.

Uploads can be associated with:

-   Green Belt
-   Site
-   Task

Eligibility:
- Only BELT parent WORK uploads may transition to APPROVED.
- ISSUE uploads cannot be APPROVED.
- SITE and TASK uploads remain internal in v1.
- REJECTED state requires internal rejection note.

Rules: - Parent association immutable - Supervisor may delete within 5
minutes - After 5 minutes → locked - Self-deleted uploads auto-purged
after 30 days - Rejected uploads require manual purge by Ops - Approved
uploads permanent - Issue uploads permanent unless soft-deleted in
5-minute window - Approved uploads remain visible after belt expiry

------------------------------------------------------------------------

8.  ISSUE MODEL

States: - OPEN - IN_PROGRESS - CLOSED

Rules: - Only Ops can close - Optional 1:1 link to Task - Task
completion does NOT auto-close issue - UI may derive "Resolution
Attempted" when linked task is completed - No backward transitions

------------------------------------------------------------------------

9.  FABRICATION & MONITORING DOMAIN

9.1 Task

Fields: - id - category - vertical - priority - source - location_text -
assigned_lead - status ENUM (OPEN, RUNNING, COMPLETED, CANCELLED) -
completion_note - archived BOOLEAN
Archive Rules:
- If archived = true → task becomes read-only.
- Archived tasks excluded from operational lists.
- Archived tasks included in historical reports.
- Archive does NOT delete data.
Rules: - CANCELLED → read-only - Archive is manual only - Archived tasks
hidden from operational lists - Archived tasks included in historical
reports


------------------------------------------------------------------------

9.2 Worker

Fields: - id - name - skill_type - status ENUM (ACTIVE, ON_LEAVE,
INACTIVE)

------------------------------------------------------------------------

9.3 Daily Work Entry

Fields: - worker_id - date - task_id (nullable) - activity_type -
work_plan - work_update - remarks

Rules: - Multiple entries allowed per worker per day - 6 PM alert if no
entry - Past-month records are locked by default. Only Ops role can
perform override on locked records. All overrides must require a reason
and must be recorded in audit_logs. Overrides are action-specific and do
not unlock the entire month or dataset. - Work entry allowed only if worker attendance exists for that date 
- Does NOT auto-update task
progress

------------------------------------------------------------------------

10. ALERT ENGINE

Alerts are dynamic and grouped by category.

Green Belt Alerts: - Attendance missing - Labour missing - Watering
missing - Cycle delay - Expiry warning

Fabrication/Monitoring Alerts: - Attendance missing - Work entry
missing - High priority tasks - Monitoring overdue

------------------------------------------------------------------------

11. REPORTING MODEL

Calendar month only.

Reports: - Belt Health Summary - Supervisor Activity - Worker Activity -
Advertisement Monthly Report

CSV export supported for all reports. Archived tasks included in
reports.

------------------------------------------------------------------------

12. GOVERNANCE RULES

-   All overrides require reason
-   All overrides logged
-   Role-based access enforced at controller level
-   No silent data mutation allowed

------------------------------------------------------------------------

STATUS: Finalized Architecture Blueprint. Schema may now be designed
from this document without ambiguity.
