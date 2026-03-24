# 02_ROLES_AND_ACCESS.md

Version: Architecture Freeze v1 Status: Role Governance Blueprint

------------------------------------------------------------------------

## 1. PURPOSE

This document defines:

-   All system roles
-   Permission boundaries
-   Vertical scope separation
-   Override authority
-   Visibility constraints
-   Governance enforcement model

If a permission is not explicitly granted here, it is considered denied.

------------------------------------------------------------------------

## 2. ACCESS CONTROL MODEL

Access Model Type: Role-Based Access Control (RBAC)

Characteristics:

-   Predefined permission groups only
-   No arbitrary micro-permission toggles
-   No user-specific custom hacks
-   Vertical-scoped visibility
-   Controller-level enforcement required

------------------------------------------------------------------------

## 3. CORE ROLES

------------------------------------------------------------------------

3.1 Operations Manager (Ops)

Authority Level: Full Governance

Scope: - All verticals - All belts - All sites - All tasks - All
uploads - All reports

Permissions:

Green Belt: - Create/Edit belts - Change watering frequency - Override
watering (same month only) - Approve/Reject uploads - Set issue
priority - Close issues - Link issue to task - Override attendance -
Override labour count - Start/Close maintenance cycle - Hide/Unhide belt

Fabrication & Monitoring: - Create tasks - Assign tasks - Cancel tasks -
Complete tasks - Archive tasks (manual only) - Record worker
attendance - Record worker daily work entry - Override attendance within
month

System: - Manage users - Assign roles - Configure vertical access - View
all alerts - Export all reports - Access audit logs

Sites: - Full CRUD

Cannot: - Bypass audit logging - Delete approved uploads - Modify
immutable parent associations

------------------------------------------------------------------------

Sites:

-   Ops -> full CRUD
-   Supervisor -> view only

------------------------------------------------------------------------

3.2 Head Supervisor (Green Belt)

Scope: - All MAINTAINED belts

Permissions:

-   Start/Close maintenance cycle
-   Mark watering (current date only)
-   Mark supervisor attendance
-   Upload work/issue photos
-   Change issue status: OPEN → IN_PROGRESS

Cannot: - Approve uploads for authority - Close issues - Override
watering - Modify labour logs after month lock - Access advertisement
modules

------------------------------------------------------------------------

3.3 Green Belt Supervisor

Scope: - Assigned belts only

Permissions: - Upload work or issue - Mark watering (current date
only) - Delete own upload within 5 minutes - View own upload history
- View sites

Cannot: - Approve uploads - Close issues - Set issue priority - Override
watering - View other belts - Access reports - Access advertisement
modules

------------------------------------------------------------------------

3.4 Outsourced Maintainer

Scope: - Assigned outsourced belts only

Permissions: - Upload work or issue - Raise issues

Restrictions: - No watering compliance - No labour tracking - No
attendance tracking - No dashboard access - No approval rights

------------------------------------------------------------------------

3.5 Monitoring Team Member

Scope: - Assigned monitoring sites

Permissions: - Upload monitoring photos - Add optional comment - View
site location

Cannot: - Approve uploads - Modify tasks - Access green belt
governance - Access reports outside monitoring

------------------------------------------------------------------------

3.6 Fabrication Lead

Scope: - Tasks assigned to them

Permissions: - View task details - Upload completion photos - Mark task
status RUNNING / COMPLETED - Assign internal workers (recorded by Ops)

Cannot: - Create tasks - Cancel tasks - Archive tasks - Modify task
source/priority

------------------------------------------------------------------------

3.7 Fabrication Worker (No Login)

-   No system login
-   Recorded in worker entity
-   Attendance recorded by Ops
-   Work entries recorded by Ops

------------------------------------------------------------------------

3.8 Sales Team

Scope: - Advertisement vertical only

Permissions: - View approved monitoring uploads - View free/available
media - Raise task request (no direct task creation) - Export
advertisement reports

Cannot: - Modify tasks - Approve uploads - Access green belt governance

------------------------------------------------------------------------

3.9 Media Planning Team

Scope: - Advertisement & Monitoring data

Permissions: - View monitoring uploads - View free media inventory -
Export media data

Cannot: - Modify operational data - Approve uploads

------------------------------------------------------------------------

3.10 Authorized Person (Authority Representative)

Scope: - Belts assigned to them

Permissions: - View approved uploads only - Download approved uploads -
View belt summary

Cannot: - See rejected uploads - Modify data - View advertisement domain

------------------------------------------------------------------------

3.11 Management

Scope: - All vertical dashboards (read-only)

Permissions: - View summary dashboards - View monthly reports - Export
CSV

Cannot: - Modify data - Approve anything - Override any compliance

------------------------------------------------------------------------

## 4. ROLE EXTENSIBILITY

System allows:

-   Creation of new roles
-   Linking role to vertical scope
-   Assigning permission group

Restrictions:

-   Cannot create arbitrary micro-permissions
-   Must select from predefined permission blocks

------------------------------------------------------------------------

## 5. MONTH-LOCK RULE

For Attendance, Labour, Watering, and Daily Work Entry:

"Past-month records are locked by default.
Only Ops role can perform override on locked records.
All overrides must require a reason and must be recorded in audit_logs.
Overrides are action-specific and do not unlock the entire month or dataset."

------------------------------------------------------------------------

## 6. OVERRIDE AUTHORITY RULE

Only Ops may:

-   Backdate watering
-   Override attendance
-   Override labour count
-   Close issues
-   Archive tasks
-   Modify watering frequency

All override actions must: - Require reason - Be logged in audit

------------------------------------------------------------------------

## 7. VISIBILITY RULES

-   Authority sees only APPROVED uploads.
-   Rejected uploads invisible to authority.
-   Supervisor cannot see rejection reason.
-   Parent association immutable.
-   Archived tasks hidden from operational UI.

------------------------------------------------------------------------

## 8. SECURITY ENFORCEMENT

All permission checks must occur:

-   At controller level
-   Not only UI level

Front-end restriction alone is invalid.

------------------------------------------------------------------------

STATUS: Role governance locked under Architecture Freeze v1.
