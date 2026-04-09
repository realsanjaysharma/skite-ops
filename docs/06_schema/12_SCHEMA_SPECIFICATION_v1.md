# Skyte Ops Schema Specification

## Authority Note

- Purpose: Repo-facing schema mirror for onboarding and navigation.
- Authority Level: Mirror only.
- If Conflict: `docs/11_build_specs/*` wins on implementation behavior and schema intent. This file must be updated to match it.

## Purpose

This file explains the purpose and important behavioral meaning of each table in the recovered schema.
It mirrors:

- `docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md`
- `docs/11_build_specs/05_WORKFLOW_STATE_MACHINE_SPEC.md`
- `docs/11_build_specs/06_UPLOAD_STORAGE_RETENTION_SPEC.md`

## Access And Governance Tables

### `users`

Core authenticated identity for login roles.
Preserves active/inactive state, force-reset behavior, login-lock tracking, and soft-delete history.

### `roles`

Seeded and dynamic role catalog.
Each role has one landing module and one permission-group mapping in v1.

### `permission_groups`

Stable capability bundles such as `VIEW`, `UPLOAD`, `APPROVE`, and `MANAGE`.

### `role_permission_mappings`

One role to one permission-group mapping in v1.
This prevents permission-spaghetti role design.

### `role_module_scopes`

Approved module keys for each role.
These drive menus, route protection, and landing validation.

### `audit_logs`

Immutable governance trail for approvals, overrides, status changes, cleanup actions, and sensitive mutations.

### `system_settings`

Controlled operational settings such as Ops phone number, cleanup thresholds, expiry defaults, and helper toggles.

## Green Belt Domain Tables

### `green_belts`

Legal and operational truth for belts.
Stores identity, location, permission state, maintenance mode, and watering configuration.

### `belt_supervisor_assignments`

Historical supervisor ownership.
This prevents current-state-only reporting drift.

### `belt_authority_assignments`

Historical authority visibility mapping.
Controls which authority representative can see which approved belts.

### `belt_outsourced_assignments`

Historical outsourced-belt mapping.
Controls which outsourced maintainer or agency can see which outsourced belts for upload scoping.

### `maintenance_cycles`

Governed cycle history for maintained belts.
Supports active and closed cycle tracking plus controlled auto-close behavior.

### `watering_records`

Stores explicit daily watering truth for `DONE` and `NOT_REQUIRED`.
`PENDING` is derived from no row.

### `supervisor_attendance`

Stores same-day supervisor presence or absence.
Attendance is an operational signal, not a hard blocker for uploads.

### `labour_entries`

Stores labour counts per belt and date, including separate gardener and night-guard counts.

## Execution Resource Tables

### `fabrication_workers`

Non-login worker resource catalog for fabrication execution.

### `worker_daily_entries`

Universal daily worker truth.
Tracks attendance plus daily activity context, optionally linked to a task or site.

### `task_worker_assignments`

Fabrication-only occupancy layer for assigning workers to active tasks and deriving availability.

## Advertisement And Monitoring Tables

### `sites`

Advertisement site and asset master.
May reference a green belt for location context but remains a separate entity.

### `site_monitoring_due_dates`

Stored monthly monitoring due truth.
Supports multiple due dates per site, copy-forward, and bulk-copy planning behavior.

### `campaigns`

Campaign lifecycle truth for client advertising work.

### `campaign_sites`

History of which sites were linked to which campaigns and when.

### `free_media_records`

Governed free-media history from discovery or campaign-end sources through confirmation, expiry, or consumption.
When the source is monitoring discovery, `source_reference_id` points back to representative discovery proof.

## Proof, Issues, Requests, And Tasks

### `uploads`

Unified proof table for green belts, sites, and tasks.
Stores file metadata, proof type, optional `work_type`, discovery-mode flag for monitoring uploads, review metadata, GPS, authority visibility, soft-delete state, and purge markers.

### `issues`

Governed operational problem record.
May reference a belt or a site and may optionally link to a task.

### `task_requests`

Pre-approval intake object created by requester roles before Ops creates a real task.

### `tasks`

Ops-governed execution unit with task source, assignment, progress, remarks, lifecycle, and archival state.

## Important Schema Rules

- `parent_type = ISSUE` is not part of the recovered upload model
- authority work-type filtering and summary generation depend on stored upload `work_type`
- issue proof remains internal evidence and authority-ineligible
- task completion proof requires `AFTER_WORK` before final completion handoff
- monthly reports derive from operational tables and do not write back into truth tables
- worker availability is derived from daily entries plus fabrication assignment state
- monitoring due/completed/overdue views derive from due-date rows plus same-day site proof

## Sync Rule

If this file and the canonical schema roadmap diverge, update this file.
It should stay as a readable schema explanation, not a second competing schema source.
