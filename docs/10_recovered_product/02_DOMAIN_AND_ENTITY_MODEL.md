# Recovered Domain And Entity Model

## Modeling Principle

The intended product is built on shared operational truth.

Different roles see different slices of the same underlying records.
The system should derive views, alerts, and summaries from core data rather than copying truth into separate silos.

## Access And Permission Domain

### Roles

Roles remain governed system actors.
The product supports both the constitutional role set and controlled future role creation.

### Permission Groups

Permission groups are predefined access bundles such as view, upload, approve, and manage patterns.
They prevent arbitrary permission spaghetti.
In v1, one role maps to one permission group.

### Role-Permission Mapping

Role-to-permission-group linking is a real required layer because dynamic roles are allowed, but only through controlled permission groups and vertical/module access.

## Green Belt Domain

### Green Belts

A green belt is a legal and operational entity.

It is intended to carry:

- system ID
- immutable `belt_code`
- common or display name
- authority-facing name
- zone or location text
- latitude and longitude
- permission dates and permission status
- maintenance mode
- watering frequency
- hidden state

Important rule:
operational applicability is derived from permission status, maintenance mode, and hidden state.

### Belt-To-Supervisor Assignments

Supervisor ownership is time-based and must be historical.
This is required for:

- accountability
- monthly activity reporting
- labour attribution
- historical responsibility

### Belt-To-Authority Assignments

Authority access is belt-specific.
This assignment controls which authority user can see which approved proof.

### Maintenance Cycles

Maintenance cycle is a governed operational entity that stores:

- cycle start
- cycle end
- who started it
- who closed it
- historical duration

### Watering Records

Watering is its own governed record model.
It should not be reduced to photo inference alone.

If `Not Required` is used as a daily explicit action, it should support a short reason.
Transcript recovery currently supports that reason being optional in v1 and mandatory later.

### Supervisor Attendance

Attendance is a support and governance record, separate from uploads and watering.

### Labour Entries

Labour is tracked as count-based operational input, not full worker identity management by default.
Separate daily counts can also be tracked for gardener and night-guard support where needed.

## Fabrication And Execution Resources

### Fabrication Workers

Fabrication workers are tracked as operational resource entries rather than full login users by default.
This supports monthly worker activity and daily workload visibility without creating low-value login complexity.

### Worker Daily Entries

`worker_daily_entries` is the universal daily work-truth layer.

It exists to support:

- one worker on one date style daily logging
- attendance
- task-linked execution where relevant
- monitoring, driving, support, and other daily activities
- worker-wise monthly activity
- day-by-day operational visibility

### Task-Worker Assignment

`task_worker_assignment` is a fabrication-specific assignment layer.

It exists to support:

- multiple workers per task
- lead-managed worker allocation
- fabrication workload visibility
- fabrication-specific "who is free today" style derived views

Recovered design lock:

- `worker_daily_entries` remains the primary daily truth layer
- `task_worker_assignment` exists only for fabrication use
- other daily work does not need this extra assignment layer

## Advertisement And Monitoring Domain

### Site / Asset Master

Advertisement site and asset truth is a core backbone.

It is intended to carry:

- `site_code`
- category such as green belt, city, or highway
- optional green-belt linkage
- location and coordinates
- ownership
- board type
- lighting type
- operational status
- monitoring relevance
- monthly monitoring due-date schedule
- copy-forward support for next-month scheduling
- multiple due dates selectable from a calendar per site
- bulk plan-copy support across multiple selected sites or operational groups

Green belts and advertisement sites remain separate entities even when related.

Recovered monitoring schedule direction now supports:

- Ops selects a site's due dates in advance for the month from a calendar
- a site may have multiple due dates within the same month
- the system should support practical patterns such as Monday and Friday or multiple month dates
- Ops can copy the same pattern into the next month and then adjust it if needed
- Ops can copy the same monthly pattern across multiple sites or groups such as highway routes
- the selected monthly dates are the operational due truth for that site

### Campaigns

Campaigns are real business and operational entities.
They represent advertiser or client campaign context, dates, lifecycle, and linkage to sites.

### Campaign-Site Links

Campaigns and sites need a linking layer because:

- one campaign can span many sites
- one site can host campaigns over time
- history matters

### Free Media State

Free media is a governed planning and operational concept tied to site truth.

It needs support for:

- discovered free media
- Ops-confirmed free-media transitions
- aging and recheck behavior
- planning visibility

## Proof, Issues, Requests, And Tasks

### Uploads / Proof

Uploads are evidence objects, not truth objects.

They support:

- green-belt work proof
- issue proof
- monitoring proof
- task completion proof
- outsourced activity proof

Uploads should retain:

- creator
- parent context
- timestamps
- optional comments or tags
- governance visibility state
- soft-delete behavior where appropriate
- purge markers where hard purge is allowed later
- stored GPS metadata for Ops review
- task-proof photo labeling where required

Important recovered rules:

- GPS is stored for Ops review only; no automatic mismatch logic is required in v1
- task completion proof uses labeled photos: After Work required, Before Work optional
- supervisor uploads remain upload-focused and should not expose authority review outcome back to supervisors
- approved authority visibility is governed access, not duplicate storage or in-system external send tracking
- rejected uploads can become manual cleanup candidates after 30 days
- rejected-upload purge should retain minimal metadata and purge markers for governance safety

### Issues

Issues are governed operational problems.
They may be backed by uploads, but they have their own lifecycle and should not be collapsed into uploads or tasks.

### Task Requests

Task requests are a critical intended entity.
They capture the pre-approval stage where Sales, Client Servicing, Media Planning, or other roles ask Ops for action.

Requests can be:

- submitted
- reviewed
- approved
- rejected
- converted into task

### Tasks

Tasks are Ops-governed execution units created after approval.
They support:

- assignment
- execution status
- proof of completion
- final Ops review
- closure
- assigned-by context
- target or close-date context
- progress tracking where operationally useful
- remark fields where needed for execution history

### Issue-To-Task Relationship

Issues and tasks may be linked, but they are not the same thing.
Task completion may support issue resolution, but final issue closure remains a governed action.

## Governance And Derived Outputs

### Audit Records

The system must preserve review, approval, rejection, override, and key lifecycle history in an auditable form.

### System Settings

Configurable thresholds and system tuning values should be stored explicitly rather than hidden in scattered constants forever.

This can also hold controlled operational settings such as:

- contact numbers like Ops phone shortcut
- approved toggles that are meant to be system-driven rather than hardcoded

### Derived Outputs

The following should mostly be derived from stored records:

- compliance percentages
- dashboard counts
- alert states
- authority-facing filtered views
- management summaries
- overdue and readiness indicators
- worker availability views
- domain-scoped user activity reports

## Stored Versus Derived Rule

Should be stored:

- entity truth
- assignments
- approvals and rejections
- lifecycle transitions
- evidence records

Should stay derived:

- dashboard totals
- status summaries
- alert signals
- compliance results
