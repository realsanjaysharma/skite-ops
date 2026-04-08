# Recovered Role And Access Model

## Access Philosophy

- role-based access only
- least privilege by default
- Ops remains final control authority
- field users get minimal actions
- read-only access is preferred where possible
- the same underlying data is reused with different scope by role
- landing page after login is role-specific, not generic

## Role Landing Behavior

- Ops lands on the Master Operations Dashboard
- Head Supervisor lands on Supervisor Attendance and Watering Oversight
- Green Belt Supervisor lands on the Supervisor Upload page
- Outsourced Maintainer lands on the Outsourced Upload page
- Monitoring Team lands on the Monitoring Upload or monitoring work view
- Fabrication Lead lands on My Tasks
- Sales lands on a read-only assigned task progress page
- Client Servicing lands on a read-only assigned task progress page
- Media Planning lands on a read-only assigned task progress page
- Authority Representative lands on Authority View
- Management lands on a read-only dashboard

## Roles

### Ops / Operations Manager

- Login: yes
- Scope: full system
- Can see: all belts, sites, uploads, requests, tasks, issues, free media, dashboards, audit, and reports
- Can do: approve or reject requests, create tasks, assign work, review uploads, govern authority visibility, close issues, manage users and mappings, confirm free-media transitions, perform auditable overrides
- Cannot do: bypass governance or audit

### Head Supervisor

- Login: yes
- Scope: maintained green belts only
- Can see: all maintained belts, watering state, supervisor activity, green-belt uploads
- Can do: mark watering on any maintained belt on the same day, record supervisor attendance, enter labour counts, start and close maintenance cycles, move issues from OPEN to IN_PROGRESS
- Cannot do: approve uploads, close issues, act as Ops, enter advertisement modules

### Green Belt Supervisor

- Login: yes
- Scope: assigned green belts only
- Can see: assigned belts and own upload context
- Can do: upload work proof, upload issue proof, mark issue yes or no, add short comments, mark watering on assigned belts
- Cannot do: see other belts, approve, close issues, set priority, access dashboards, access advertisement or monitoring domains, see authority review status or rejection feedback on uploads

### Outsourced Maintainer / Agency

- Login: yes
- Scope: assigned outsourced belts only
- Can see: assigned outsourced belts
- Can do: upload work proof, raise issue
- Cannot do: mark watering, see compliance, use internal dashboards, access maintained belts, monitoring, or task control

### Monitoring Team Member

- Login: yes
- Scope: site and monitoring context
- Can see: site details, upload context, navigation support
- Can do: upload monitoring proof, upload free-media discovery proof, add optional comment
- Cannot do: approve, edit site master truth, govern campaigns, access authority-proof workflow

### Fabrication / Installation Lead

- Login: yes
- Scope: assigned tasks only
- Can see: assigned task list and task detail
- Can do: read instructions, assign non-login workers to task execution, upload mandatory After Work proof, optionally upload Before Work proof, mark work done, use Call Ops shortcut from task interface
- Cannot do: create tasks, approve tasks, reassign tasks, edit task definitions

### Fabrication / Installation Workers

- Login: no
- Access: shared read-only web link
- Representation: internal resource entries, not system users
- Can see: same-day task list and instructions
- Can do: read only
- Cannot do: upload, comment, mark done, edit

### Sales Team

- Login: yes
- Scope: advertisement and client-facing proof
- Landing page: read-only assigned task progress page
- Can see: monitoring proof, client-facing proof views, and progress status for tasks linked to their requests, clients, or campaigns
- Can do: filter, download, manually share through WhatsApp, raise requests to Ops
- Cannot do: upload monitoring proof, approve, edit site or campaign state, work inside task execution flow

### Client Servicing Team

- Login: yes
- Scope: advertisement and client-facing proof
- Landing page: read-only assigned task progress page
- Can see: monitoring proof, client-facing proof views, and progress status for tasks linked to their requests, clients, or campaigns
- Can do: filter, download, manually share through WhatsApp, raise requests to Ops
- Cannot do: upload, approve, edit site or campaign state, work inside task execution flow

### Media Planning Team

- Login: yes
- Scope: planning-facing site, proof, and free-media visibility
- Landing page: read-only assigned task progress page
- Can see: site metadata, board and pole proof, free and available media views, and progress status for tasks linked to their requests, clients, campaigns, or planning asks
- Can do: filter, download, raise free-media or action requests to Ops
- Cannot do: upload monitoring proof directly, approve, modify site truth, work inside task execution flow

### Authority Representative / Authorized Person

- Login: yes
- Scope: assigned belts only
- Can see: approved authority-visible green-belt proof for assigned belts
- Can do: filter by date, belt, supervisor, and work type, download, manually share through WhatsApp, use summary views
- Cannot do: upload, edit, approve, comment, see internal-only notes, see advertisement data

### Management

- Login: yes
- Scope: global read-only oversight
- Can see: all uploads, all supervisors, all belts, monitoring activity, open tasks and issues, dashboards, and reports
- Can do: filter and review
- Cannot do: approve, assign, edit, or share externally as a system actor

## Non-Login Or Not Fully Finalized Actors

- Daily labour: tracked as count, not as user identity by default
- Gardener: tracked through separate daily resource counts, not as login role
- Night guards: tracked through separate daily resource counts, not as login roles
- Clients: not system users
- External authority body: not system users

## Role Boundary Rules

- Head Supervisor is not Ops
- Authority Representative is not an approval actor
- Sales, Client Servicing, and Media Planning are requesters and consumers, not execution controllers
- Outsourced Maintainer is not the same as internal Supervisor
- Fabrication workers remain non-login unless explicitly redesigned later
- Management is visibility-only

## Dynamic Roles And Permission Model

The recovered transcripts lock this behavior:

- Ops can create new roles when needed
- role creation is constrained through predefined permission groups
- new roles can be linked to vertical access and allowed modules
- arbitrary micro-permission toggles are not allowed
- one role maps to one permission group in v1

This implies a governed RBAC model, not open-ended permission design.

## Ownership Pattern

- Ops owns governance, approvals, closures, assignments, and overrides
- Head Supervisor owns green-belt operational oversight entries
- field roles own raw proof creation
- commercial and support roles own requests
- authority and management roles consume controlled views only

## Reporting Scope Rule

- per-user activity reports are allowed
- reports must remain scoped to the relevant domain or role scope
- cross-vertical visibility should not become uncontrolled by default
