# Skyte Ops Data And Flow Notes

## Authority Note

- Purpose: Repo-facing mirror for onboarding and navigation.
- Authority Level: Mirror only.
- If Conflict: `docs/10_recovered_product/*` wins on product meaning and `docs/11_build_specs/*` wins on implementation behavior.

## Purpose

This file is the repo-facing structural mirror of the recovered entity and workflow model.
It is no longer the top authority for scope.

Primary references:

- `docs/10_recovered_product/02_DOMAIN_AND_ENTITY_MODEL.md`
- `docs/10_recovered_product/03_WORKFLOWS_AND_LIFECYCLES.md`
- `docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md`
- `docs/11_build_specs/05_WORKFLOW_STATE_MACHINE_SPEC.md`

## Global Structural Rules

- store operational truth once and reuse it through role-scoped views
- do not store dashboard counters, alert rows, or authority summaries as primary truth
- no hidden lifecycle mutations
- override paths must be attributable and auditable
- issue lifecycle remains separate from task lifecycle
- upload existence remains separate from upload visibility

## Core Entity Boundaries

### Governance And Access

- users
- roles
- permission groups
- role-permission mappings
- role-module scopes
- audit logs
- system settings

### Green Belt Domain

- green belts
- belt-supervisor assignments
- belt-authority assignments
- belt-outsourced assignments
- maintenance cycles
- watering records
- supervisor attendance
- labour entries

### Execution Resources

- fabrication workers
- worker_daily_entries
- fabrication-only task_worker_assignments

### Advertisement And Monitoring

- sites
- site_monitoring_due_dates
- campaigns
- campaign_sites
- free_media_records

### Proof, Issues, Requests, And Tasks

- uploads
- issues
- task_requests
- tasks

## Stored Versus Derived Truth

### Stored

- core masters and assignments
- operational entries
- uploads and review metadata
- issues and tasks
- monitoring monthly due dates
- free-media records
- settings and audit history

### Derived

- dashboards
- alerts
- worker availability
- watering compliance percentages
- monitoring due/completed/overdue views
- authority summary text
- monthly report rows

## Major Workflow Boundaries

### Green Belt Proof

- field proof is created first
- Ops review governs authority visibility later
- issue proof never becomes authority proof

### Watering

- watering is its own daily operational record
- `DONE` and `NOT_REQUIRED` are stored
- `PENDING` is derived from no row

### Maintenance Cycles

- one active cycle per belt at a time
- manual start and close
- hidden or expired belts can force governed auto-close behavior

### Requests And Tasks

- requesters create requests, not tasks
- Ops approves or rejects request intake
- approved requests convert into tasks
- execution handoff and Ops closure remain separate

### Worker Tracking

- worker_daily_entries are the universal daily truth
- fabrication task-worker assignments add occupancy context for fabrication only

### Monitoring Due Truth

- site due truth comes from Ops-managed monthly due-date rows
- a site can have multiple dates in a month
- dates can be copied into future months
- dates can be bulk-copied across multiple sites or groups
- custom due-date plans replace default cadence for that site

### Authority Output

- authority access uses approved work proof only
- output is visibility-driven, not duplicate-file-driven
- WhatsApp helper output is a convenience layer, not send-state truth

## Upload And Retention Notes

- one file equals one upload row
- work proof and issue proof remain separate through `upload_type`
- optional upload `work_type` stays stored where authority filtering or summary grouping needs it
- monitoring discovery mode stays stored on uploads where history filtering needs it
- rejected uploads remain internal and become cleanup candidates after 30 days
- self-deleted uploads purge after the configured threshold
- issue proof remains permanent evidence in v1
- purged uploads keep minimal governance-safe metadata

## Additional Wiring Notes

- outsourced belt scope must come from explicit outsourced-assignment records, not from supervisor mappings
- monitoring discovery mode must create or refresh governed free-media discovery state, not just store an upload

## Reporting Notes

- monthly reports are calendar-month only
- CSV is the only export format in v1
- per-user reports remain domain-scoped
- archived tasks stay historically visible where the report model requires them

## Sync Rule

When this file and the build-spec layer diverge, the build-spec layer wins on implementation detail.
When this file and the recovered canon diverge on product truth, the recovered canon wins.
