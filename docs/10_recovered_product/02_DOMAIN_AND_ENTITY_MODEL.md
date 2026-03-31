# Recovered Domain And Entity Model

## Modeling Principle

The intended product is built on shared operational truth.

Different roles see different slices of the same underlying records.
The system should derive views, alerts, and summaries from core data rather than copying truth into separate silos.

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

### Supervisor Attendance

Attendance is a support and governance record, separate from uploads and watering.

### Labour Entries

Labour is tracked as count-based operational input, not full worker identity management by default.

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

Green belts and advertisement sites remain separate entities even when related.

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

Uploads should retain creator, parent context, timestamps, optional comments or tags, governance visibility state, and soft-delete behavior where appropriate.

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
They support assignment, execution status, proof of completion, final Ops review, and closure.

### Issue-To-Task Relationship

Issues and tasks may be linked, but they are not the same thing.
Task completion may support issue resolution, but final issue closure remains a governed action.

## Governance And Derived Outputs

### Audit Records

The system must preserve review, approval, rejection, override, and key lifecycle history in an auditable form.

### System Settings

Configurable thresholds and system tuning values should be stored explicitly rather than hidden in scattered constants forever.

### Derived Outputs

The following should mostly be derived from stored records:

- compliance percentages
- dashboard counts
- alert states
- authority-facing filtered views
- management summaries
- overdue and readiness indicators

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
