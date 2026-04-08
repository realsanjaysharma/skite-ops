# Skyte Ops Roles And Access

## Purpose

This file is the repo-facing legacy mirror of the recovered role model.
It should stay aligned with:

- `docs/10_recovered_product/01_ROLE_AND_ACCESS_MODEL.md`
- `docs/11_build_specs/01_RBAC_PERMISSION_GROUP_SPEC.md`

If this file conflicts with those sources, the recovered role model and build-spec RBAC contract win.

## Access Model

Skite Ops uses role-based access control with these locked rules:

- least privilege by default
- module access through role scope
- record scope enforced after module access
- one role maps to one permission group in v1
- dynamic role creation is allowed only through predefined permission groups and approved module scopes
- no arbitrary micro-permission toggles

## Landing By Role

| Role | Landing Surface |
|---|---|
| Ops / Operations Manager | Master Operations Dashboard |
| Head Supervisor | Supervisor Attendance and Watering Oversight |
| Green Belt Supervisor | Supervisor Upload |
| Outsourced Maintainer | Outsourced Upload |
| Monitoring Team Member | Monitoring Upload |
| Fabrication / Installation Lead | My Tasks |
| Sales Team | Assigned Task Progress page |
| Client Servicing Team | Assigned Task Progress page |
| Media Planning Team | Assigned Task Progress page |
| Authority Representative | Authority View |
| Management | Management Dashboard |

## Core Roles

### Ops / Operations Manager

- full governance across all domains
- approves requests, governs uploads, assigns work, closes issues, confirms free-media transitions, manages users and mappings, and performs auditable overrides
- cannot bypass audit or silent governance

### Head Supervisor

- maintained green belts only
- can mark same-day watering, attendance, labour, and maintenance cycles
- can move issues from `OPEN` to `IN_PROGRESS`
- cannot approve uploads, close issues, or enter advertisement governance

### Green Belt Supervisor

- assigned belts only
- can upload work proof or issue proof
- can mark same-day watering on assigned belts
- can view own recent uploads
- cannot see authority review state or rejection feedback

### Outsourced Maintainer

- assigned outsourced belts only
- those belts come from explicit outsourced-belt assignment, not supervisor assignment
- can upload work proof and issue proof
- does not participate in watering, attendance, or labour compliance

### Monitoring Team Member

- site-based monitoring scope
- can upload monitoring proof and free-media discovery proof
- can use navigation support
- cannot edit site master data or approve anything

### Fabrication / Installation Lead

- assigned tasks only
- can update progress, remarks, worker allocation, upload proof, and mark work done
- must provide `AFTER_WORK` proof before completion handoff
- can use the Call Ops helper
- cannot create, approve, or reassign tasks

### Fabrication / Installation Workers

- no normal system login
- represented as worker resources and daily work entries
- may receive read-only task visibility through a shared helper surface

### Sales Team

- read-only operational consumption plus request creation
- can see monitoring/client-facing proof and task progress linked to their requests, clients, or campaigns
- can raise requests to Ops
- cannot execute or govern tasks

### Client Servicing Team

- same operational posture as Sales, with request and read-only progress visibility

### Media Planning Team

- planning-facing read access to proof, site context, free media, and task progress linked to planning work
- can raise requests to Ops
- cannot govern site truth or task execution

### Authority Representative

- assigned belts only
- sees approved green-belt work proof only
- can filter by date, belt, supervisor, and work type, download, and use the WhatsApp helper from approved authority context
- cannot upload, approve, or see internal-only material

### Management

- global read-only oversight across allowed dashboards, summaries, and reports
- no mutation rights

## Non-Login Or Resource Actors

These are not standard login roles:

- fabrication workers
- daily labour
- gardener
- night guards
- clients
- external authority body

## Boundary Rules

- Head Supervisor is not Ops
- authority users are not approval actors
- Sales, Client Servicing, and Media Planning are requesters and consumers, not execution controllers
- outsourced maintainers are not internal supervisors
- management remains visibility-only

## Review And Visibility Rules

- supervisors do not see authority review outcomes on uploads
- authority users see approved work proof only
- issue uploads are not authority-visible
- requester roles use read-only task progress surfaces
- role-safe visibility must be enforced both in UI and server-side behavior

## Enforcement Rule

UI hiding alone is not enough.

Access must be enforced through:

- authenticated session checks
- role and permission-group resolution
- module scope
- record scope
- action compatibility with the role's permission group
