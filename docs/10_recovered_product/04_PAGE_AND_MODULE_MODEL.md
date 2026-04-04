# Recovered Page And Module Model

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

### Outsourced Upload

Separate upload flow for outsourced belts.
This should not be merged into the internal supervisor experience.

### Upload Review

Ops governance page between raw field evidence and authority visibility.

### Authority View

Read-only authority portal for approved green-belt proof only.
The system controls visibility; external sharing remains outside system control.

### Site And Asset Master

Operational source of truth for advertisement sites and assets.

### Campaign Management

Campaign lifecycle page covering site linkage and campaign-end review.

### Monitoring Upload

Field proof page for monitoring and free-media discovery.

### Raise Request Page

Commercial and support intake page for action requests that must go through Ops.

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

### System Settings

Controlled tuning page for thresholds and system-level operational settings.
This may also store approved operational shortcuts such as the Ops phone number used by Call Ops.
