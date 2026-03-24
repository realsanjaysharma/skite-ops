
# 04_PAGE_CATALOG.md
Version: Architecture Freeze v1 (FINAL V4 – LOCKED)
Status: Complete & Governance-Aligned

------------------------------------------------------------
PURPOSE
------------------------------------------------------------

Defines every operational page in the system including:
- Access control
- Edit permissions
- Lock enforcement
- Alert routing
- Archive behavior
- Override behavior
- Compliance visibility
- Future placeholders

No new page may be introduced without updating this file.

============================================================
1. DASHBOARDS
============================================================

1.1 Master Operations Dashboard
Access:
- Ops
- Management (Read-only)

Green Belt Section:
- Active Belts Count
- Expiry Warnings
- Watering Missing Alerts
- Attendance Missing Alerts
- Labour Missing Alerts
- Cycle Delay Alerts
- Outsourced Activity Summary (7-day uploads + issues)

Advertisement Section:
- Open Tasks
- Running Tasks
- Completed Tasks
- Cancelled Tasks
- High Priority Tasks
- Archived Tasks Count

Monitoring Section:
- Sites Due Today
- Overdue Sites
- Monitoring Upload Count Today
- Monitoring Attendance Missing

Restrictions:
- Navigation only
- No editing

------------------------------------------------------------

1.2 Green Belt Dashboard
Access:
- Ops
- Head Supervisor

Maintained Belts Table:
- Belt Name
- Watering Status Today
- Attendance Status
- Labour Entered (Yes/No)
- Active Cycle (Days Open)
- Open Issues Count
- Expiry Flag

Outsourced Belts Table:
- Belt Name
- Upload Count (Last 7 Days)
- Open Issues Count

Alerts visible here and in Notification Panel.

------------------------------------------------------------

1.3 Advertisement Dashboard
Access:
- Ops
- Management

Displays:
- Task Status Breakdown
- High Priority Tasks
- Worker Attendance Missing
- Workers Without Daily Entry (after 6 PM)
- Archive Toggle Summary

------------------------------------------------------------

1.4 Monitoring Dashboard
Access:
- Ops
- Monitoring Team (limited)

Displays:
- Sites Due Today
- Overdue Sites
- Monitoring Uploads Today
- Attendance Missing

============================================================
2. GREEN BELT DOMAIN
============================================================

2.1 Green Belt Master (List)
Access: Ops

Fields:
- Belt Name
- Authority
- Permission Status
- Expiry Date
- Maintenance Mode (Maintained / Outsourced)
- Watering Frequency (Daily / Alternate / Weekly / None)
- Hidden Flag

Actions:
- Create
- Edit
- Change Watering Frequency (applies from today)
- Change Maintenance Mode
- Hide/Unhide Belt
    - If cycle active → confirmation popup → auto-close cycle
- Open Detail

------------------------------------------------------------

2.2 Green Belt Detail
Access:
- Ops
- Head Supervisor (limited)

A. Maintenance Cycle Section
- Current Status
- Days Open
- Start Date
- Backdate Start (within current month only)
- Close Cycle
- Cycle History Table

B. Watering Section
- Today Status
- Monthly Grid
- Compliance %
- Override Button (Ops only)
    - Modal required
    - Override reason mandatory

Rules:
- Supervisor & Head can mark only current date.
- Ops can backdate within same month (audit logged).

C. Labour Section
- Daily Labour Entries
- Aggregated Supervisor Labour View

D. Upload View Section
- Work Uploads
- Issue Uploads
- Date Filter
- No editing here

Navigation:
- Upload Review Page (Ops only)

------------------------------------------------------------

2.3 Upload Review Page
Access: Ops

Features:
- Filter by Belt
- Filter by Date Range
- Filter by Upload Type
- Bulk Select
- Select All / Deselect
- Approve Selected
- Reject Selected (internal note required)

Rules:
- Issue uploads cannot be approved.
- Approved uploads become visible to Authorized Person.

------------------------------------------------------------

2.4 Supervisor Upload Page
Access:
- Supervisor
- Head Supervisor

Features:
- Select Assigned Belt
- Upload Type (Work / Issue)
- Photo Upload
- Optional Comment
- Mark Watering (current date only)
- View My Uploads (7-day grouped view)
- Delete own upload within 5 minutes (soft delete)

------------------------------------------------------------

2.5 Green Belt Attendance Page
Access: Head Supervisor

Features:
- Mark Present / Absent
- Past-month records are locked by default. Only Ops role can perform override on locked records. All overrides must require a reason and must be recorded in audit_logs. Overrides are action-specific and do not unlock the entire month or dataset.
- Morning reminder alert if not marked
- Displays full supervisor list under Head Supervisor authority.
- Attendance must be recorded for each supervisor daily.

------------------------------------------------------------

2.6 Labour Entry Page
Access:
- Head Supervisor (current month)
- Ops (override)

Features:
- Select Belt
- Select Date
- Enter Labour Count
- Past-month records are locked by default. Only Ops role can perform override on locked records. All overrides must require a reason and must be recorded in audit_logs. Overrides are action-specific and do not unlock the entire month or dataset.
- Missing entry alert

============================================================
3. ISSUE MANAGEMENT
============================================================

Issue Management Page
Access:
- Ops
- Head Supervisor (limited)

States:
- Open
- In Progress
- Closed

Actions:
- Change Status
- Set Priority (Ops only)
- Link to Task
- Close Issue (Ops only)

============================================================
4. FABRICATION & MONITORING DOMAIN
============================================================

4.1 Task List Page
Access:
- Ops
- Fabrication Lead

Features:
- Filters
- Archive Toggle (Show/Hide Archived)
- Create Task (Ops)
- Cancel Task (Ops)
- Manual Archive (older tasks)

------------------------------------------------------------

4.2 Monitoring Site Management
Access:
- Ops
- Supervisor (view only)

Features:
- Create site
- Edit site
- Activate / deactivate site

------------------------------------------------------------

4.3 Task Detail Page
Access:
- Ops
- Assigned Lead

Fields:
- Task Category
- Vertical Type
- Priority
- Location Text
- Task Source
- Completion Note
- Upload Proof

------------------------------------------------------------

4.3 Worker Management Page
Access: Ops

Fields:
- Worker Name
- Skill Type
- Status (Active / Inactive / On Leave)

------------------------------------------------------------

4.4 Worker Attendance Page
Access: Ops

Features:
- Mark Present / Absent / Half-Day
- Past-month records are locked by default. Only Ops role can perform override on locked records. All overrides must require a reason and must be recorded in audit_logs. Overrides are action-specific and do not unlock the entire month or dataset.
- ON_LEAVE suppresses alerts

------------------------------------------------------------

4.5 Worker Daily Entry Page
Access: Ops

Features:
- Multiple entries per worker
- Link to Task (optional)
- 6 PM alert if no entry
- Past-month records are locked by default. Only Ops role can perform override on locked records. All overrides must require a reason and must be recorded in audit_logs. Overrides are action-specific and do not unlock the entire month or dataset.
Rule:
- Daily work entry allowed only if worker attendance is marked for that date.
- If attendance not marked, system blocks work entry and shows alert.

------------------------------------------------------------

4.6 Monitoring Upload Page
Access: Monitoring Team

Features:
- Select Site
- Upload Photo
- Optional Comment

------------------------------------------------------------

4.7 Monitoring Frequency Management Page
Access: Ops

Features:
- Define frequency per site
- Editable anytime

============================================================
5. REPORTS SECTION
============================================================

Menu: Reports

Available Reports:
- Belt Health Summary (Monthly)
- Supervisor Activity Report (Monthly)
- Worker Activity Report (Monthly)
- Advertisement Monthly Report

Rules:
- Calendar month only
- CSV Export supported
- Archived tasks included
Export Format:
- CSV only (v1)
- No PDF generation in v1

============================================================
6. AUTHORITY VIEW
============================================================

Authority Dashboard
Access: Authorized Person

Features:
- Assigned Belts Only
- Approved Uploads Only
- Date Filter
- Belt-wise Grouping
- Download Option

============================================================
7. ADMIN & GOVERNANCE
============================================================

7.1 User Management
- Create User
- Assign Role
- Assign Vertical Scope
- Activate / Deactivate

7.2 Audit Log Viewer
- View Overrides
- View Watering Backdates
- View Status Changes
- View Cycle Auto-Closures

============================================================
8. GLOBAL COMPONENT
============================================================

Notification Panel
- Grouped Alerts
- Navigation Only
- Visible across dashboards

============================================================
9. FUTURE (PHASE 2)
============================================================

Map View (Reserved)
- Geographic visualization of belts and sites
- Not part of v1 schema

------------------------------------------------------------

STATUS:
Architecture Locked.
No structural changes allowed without updating Decisions Log.
