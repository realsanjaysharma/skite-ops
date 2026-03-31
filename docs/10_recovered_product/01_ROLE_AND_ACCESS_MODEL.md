# Recovered Role And Access Model

## Access Philosophy

- role-based access only
- least privilege by default
- Ops remains final control authority
- field users get minimal actions
- read-only access is preferred where possible
- the same underlying data is reused with different scope by role

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
- Cannot do: see other belts, approve, close issues, set priority, access dashboards, access advertisement or monitoring domains

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
- Can do: read instructions, upload completion proof, mark work done
- Cannot do: create tasks, approve tasks, reassign tasks, edit task definitions

### Fabrication / Installation Workers

- Login: no
- Access: shared read-only web link
- Can see: same-day task list and instructions
- Can do: read only
- Cannot do: upload, comment, mark done, edit

### Sales Team

- Login: yes
- Scope: advertisement and client-facing proof
- Can see: monitoring proof and client-facing proof views
- Can do: filter, download, manually share through WhatsApp, raise requests to Ops
- Cannot do: upload monitoring proof, approve, edit site or campaign state

### Client Servicing Team

- Login: yes
- Scope: advertisement and client-facing proof
- Can see: monitoring proof and client-facing proof views
- Can do: filter, download, manually share through WhatsApp, raise requests to Ops
- Cannot do: upload, approve, edit site or campaign state

### Media Planning Team

- Login: yes
- Scope: planning-facing site, proof, and free-media visibility
- Can see: site metadata, board and pole proof, free and available media views
- Can do: filter, download, raise free-media or action requests to Ops
- Cannot do: upload monitoring proof directly, approve, modify site truth

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
- Gardener: mentioned in operations reality, not finalized as system role
- Night guards: mentioned in operations reality, not finalized as system role
- Clients: not system users
- External authority body: not system users

## Role Boundary Rules

- Head Supervisor is not Ops
- Authority Representative is not an approval actor
- Sales, Client Servicing, and Media Planning are requesters and consumers, not execution controllers
- Outsourced Maintainer is not the same as internal Supervisor
- Fabrication workers remain non-login unless explicitly redesigned later
- Management is visibility-only

## Ownership Pattern

- Ops owns governance, approvals, closures, assignments, and overrides
- Head Supervisor owns green-belt operational oversight entries
- field roles own raw proof creation
- commercial and support roles own requests
- authority and management roles consume controlled views only
