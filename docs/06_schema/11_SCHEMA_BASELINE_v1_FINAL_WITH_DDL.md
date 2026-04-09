# Skyte Ops Schema Baseline

## Authority Note

- Purpose: Repo-facing schema mirror for onboarding and navigation.
- Authority Level: Mirror only.
- If Conflict: `docs/11_build_specs/*` wins on implementation behavior and schema intent. This file must be updated to match it.

## Purpose

This file is the repo-facing schema baseline summary for the recovered product.
It mirrors:

- `docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md`
- `docs/06_schema/schema_v1_full.sql`

Use this file for table-set orientation and structural guarantees.
Use the SQL file for executable DDL and the specification file for table-by-table meaning.

## Baseline Principles

- operational truth is stored once and reused through role-scoped views
- dashboards, alerts, and summaries stay derived
- monitoring due truth comes from stored monthly due dates
- authority visibility is stored on uploads; external sending is not
- history is preserved through append-safe records, soft delete, archive, and purge markers where relevant

## Canonical Table Groups

### Access And Governance

- `users`
- `roles`
- `permission_groups`
- `role_permission_mappings`
- `role_module_scopes`
- `audit_logs`
- `system_settings`

### Green Belt Domain

- `green_belts`
- `belt_supervisor_assignments`
- `belt_authority_assignments`
- `belt_outsourced_assignments`
- `maintenance_cycles`
- `watering_records`
- `supervisor_attendance`
- `labour_entries`

### Execution Resources

- `fabrication_workers`
- `worker_daily_entries`
- `task_worker_assignments`

### Advertisement And Monitoring

- `sites`
- `site_monitoring_due_dates`
- `campaigns`
- `campaign_sites`
- `free_media_records`

### Proof, Issues, Requests, And Tasks

- `uploads`
- `issues`
- `task_requests`
- `tasks`

## Key Structural Guarantees

- one active permission group per role in v1
- one unique module-scope row per role and module key
- historical supervisor assignment, not a single supervisor field on green belts
- historical authority assignment, not a single hardwired authority field
- historical outsourced-belt assignment for outsourced maintainer scoping
- one active maintenance cycle per belt enforced in service logic
- one watering row per belt and date when an explicit action exists
- one attendance row per supervisor and date
- one labour row per belt and date
- one worker daily entry per worker and date
- one site due-date row per site and due date
- one upload row per physical file

## Important Modeling Boundaries

- green belts and advertisement sites are separate entities
- upload is not issue
- issue is not task
- task request is not task
- authority view is filtered access to uploads, not duplicate storage
- authority work-type filtering depends on stored upload `work_type`
- monitoring discovery filtering depends on stored upload discovery-mode metadata
- custom monitoring due-date plans replace default cadence for that site

## Deletion And Retention Rules

- users are soft-deleted
- tasks are archived, not deleted
- uploads support soft delete and purge markers
- rejected uploads become manual cleanup candidates after the configured threshold
- issue evidence remains permanent in v1

## Migration Order

Use the canonical roadmap order from `docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md`.
Do not rebuild schema order from the old trimmed model.
