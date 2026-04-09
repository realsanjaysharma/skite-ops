# Recovered Page And Module Model

## Authority Note

- Purpose: Canonical recovered product document.
- Authority Level: Product truth.
- If Conflict: This file controls product meaning and scope. `docs/11_build_specs/*` controls implementation behavior. Repo-facing mirror docs must be updated to match, not treated as competing truth.

## Product Surface Principle

Dashboards provide visibility and navigation.
Real actions happen on dedicated pages.

Post-login landing should be role-specific rather than generic.

## Dashboards

- Master Operations Dashboard
- Green Belt Dashboard
- Advertisement Dashboard
- Monitoring Dashboard
- Management Dashboard

These should summarize pressure, status, and navigation targets rather than act as overloaded action pages.

## Green Belt Operations Pages

- Green Belt Master
- Green Belt Detail
- Supervisor Upload
- Supervisor My Uploads
- Outsourced Upload
- Watering and Watering Status
- Maintenance Cycle Controls
- Supervisor Attendance
- Labour Entry
- Upload Review
- Issue Management
- Authority View

## Advertisement And Monitoring Pages

- Site and Asset Master
- Campaign Management
- Monitoring Upload
- Monitoring Plan
- Monitoring History
- Free and Available Media Page
- Task Management
- Task Detail
- Fabrication Lead My Tasks

## Commercial And Support Pages

- Client Media Library
- Raise Request Page
- Media Planning Inventory View
- Free Media Request View

## Governance And Admin Pages

- User Management
- Access and Mapping Control
- Audit Log Viewer
- Rejected Uploads Cleanup
- Notification and Alert Panel
- Reports
- System Settings

## Purpose Notes

### Green Belt Master

Source-of-truth page for belt identity, legal state, maintenance mode, assignment visibility, and operational configuration.

### Green Belt Detail

Drill-down page showing belt information, watering history, cycle history, uploads, and issues.

### Supervisor Upload

Low-friction field evidence page for maintained belts.
It may capture soft GPS metadata silently for Ops verification and must not expose authority review results back to supervisors.
Where work-context filtering matters later, the page should support an optional stored `work_type` tag for work uploads.

### Outsourced Upload

Separate upload flow for outsourced belts.
This should not be merged into the internal supervisor experience.
It should resolve available belts from explicit outsourced-belt assignments, not from internal supervisor assignment logic.

### Upload Review

Ops governance page between raw field evidence and authority visibility.

### Authority View

Read-only authority portal for approved green-belt proof only.
The system controls visibility; external sharing remains outside system control.
It should support filtered download, one-click WhatsApp helper sharing with a pre-filled message, and the authority summary model locked in `07_AUTHORITY_SHARE_AND_SUMMARY_MODEL.md`.
Any work-type filter shown here must be backed by stored upload work-type data rather than comment parsing.

### Site And Asset Master

Operational source of truth for advertisement sites and assets.
This remains separate from Green Belt Master even when an advertisement site references a green belt location.

### Campaign Management

Campaign lifecycle page covering site linkage and campaign-end review.

### Monitoring Upload

Field proof page for monitoring and free-media discovery.
When discovery mode is used, the upload flow should create or refresh the governed discovered free-media state instead of leaving discovery as an unlinked photo-only event.

### Monitoring Plan

Soft Ops-approved planning surface for monitoring.
The system can generate a suggested due list and plan, but monitoring work is not blocked if Ops does not formally approve it.
Ops should be able to select multiple due dates for each site from a monthly calendar and copy the same pattern into the next month when needed.
Ops should also be able to copy the same monthly plan across multiple selected sites or groups such as highway sites.
Stored monthly due dates are the true operational basis for monitoring due lists.

### Raise Request Page

Commercial and support intake page for action requests that must go through Ops.

### Assigned Task Progress Page

Read-only task-progress surface for Sales, Client Servicing, and Media Planning.
It shows progress and status for tasks linked to their requests, clients, campaigns, or planning asks without turning those roles into execution users.
Implementation should use dedicated read-only task-progress routes rather than colliding with execution-side progress-update routes.

### Task Management

Ops control page for approved execution work.

### Task Detail

Execution detail page should support:

- assigned lead context
- worker allocation context
- mandatory After Work proof
- optional Before Work proof
- Call Ops shortcut

### Fabrication Lead My Tasks

Role-specific task workspace where the lead sees assigned tasks and records worker allocation when required.

### Rejected Uploads Cleanup

Admin or Ops-only cleanup surface for rejected uploads that are old enough to be permanently purged under the retention policy.

### Reports

Separate reporting surface for monthly and operational reporting, including CSV export behavior when finalized.
Per-user reports must remain domain-scoped.
Exact CSV columns, grouping, filters, and inclusion rules are locked in `06_REPORT_AND_EXPORT_MODEL.md`.

### System Settings

Controlled tuning page for thresholds and system-level operational settings.
This may also store approved operational shortcuts such as the Ops phone number used by Call Ops.

### Supervisor Attendance And Watering Oversight

Head Supervisor landing surface focused on:

- same-day watering oversight
- supervisor attendance
- operational visibility over maintained belts
