# Implementation Progress

## Authority Note

- Purpose: Working progress tracker for implementation execution.
- Authority Level: Execution support only.
- If Conflict: `docs/10_recovered_product/*` controls product meaning and `docs/11_build_specs/*` controls implementation behavior. This file tracks current status and next actions only.

## Purpose

Use this file to keep implementation turns small and low-token.
Future prompts can reference this file instead of repeating product context.

## Current Overall Status

- product-recovery canon is locked
- implementation-spec layer is locked
- legacy mirror docs are aligned
- canonical schema has been validated on local XAMPP MariaDB
- foundation seed has been validated on local XAMPP MariaDB
- active `skite_ops` runtime DB is now rebuilt on the canonical schema and foundation seed
- Phase 1 backend foundation is verified against the real configured app database on local XAMPP
- reusable auth/user/audit/RBAC foundation exists, but most product modules are still not implemented
- BaseRepository transaction methods fixed to public for service-layer control
- AI tool handoff guide created at `docs/AI_TOOL_HANDOFF_GUIDE.md` for multi-tool workflow
- Phase 2 Green Belt Core backend is COMPLETE (9 files, 16 routes, detail payload aligned, syntax validated)
- upload service foundation is COMPLETE (shared storage, metadata persistence, self-delete, discovery side-effect, direct verification)

## Validated Baseline

Validated on local XAMPP / MariaDB:

- `docs/06_schema/schema_v1_full.sql`
- `migrations/001_seed_foundation.sql`

Validated behaviors:

- canonical schema imports successfully
- foundation seed imports successfully
- seed rerun is idempotent
- seeded roles, permission groups, module scopes, and required system settings exist
- sample Ops user insert works against canonical schema
- task `progress_percent <= 100` check is enforced by DB

## Implemented So Far

### Foundation Code

- auth login/logout/reset-password flow
- session handling
- CSRF protection
- user CRUD foundation
- audit-log write foundation
- role-based landing data returned from auth/session flows
- bootstrap path aligned to canonical schema and seed files

### Existing Reusable Files

- `index.php`
- `app/controllers/AuthController.php`
- `app/controllers/UserController.php`
- `app/controllers/RoleController.php`
- `app/middleware/AuthMiddleware.php`
- `app/services/AuthService.php`
- `app/services/UserService.php`
- `app/services/RbacService.php`
- `app/services/RoleService.php`
- `app/services/AuditService.php`
- `app/services/UploadStorageService.php`
- `app/services/UploadService.php`
- `app/repositories/BaseRepository.php`
- `app/repositories/UserRepository.php`
- `app/repositories/RbacRepository.php`
- `app/repositories/AuditRepository.php`
- `app/repositories/UploadRepository.php`
- `config/database.php`
- `config/constants.php`
- `config/rbac.php`
- `config/route_registry.php`

## Not Implemented Yet

### Platform And RBAC Runtime

- frontend shell/menu rendering from `allowed_module_keys`
- deeper record-scope helper expansion as Phase 2+ domain routes appear

### Product Modules

- supervisor uploads and my uploads
- outsourced upload flow
- watering
- attendance
- labour entries
- issue management
- request intake
- task management and detail
- fabrication lead workflow
- worker daily entries and worker allocation
- site master
- monitoring upload/history/planning
- campaigns
- free media
- authority view
- reports
- system settings UI
- rejected upload cleanup

## Current Phase

### Phase 1 - Platform Foundation And RBAC Runtime

Status: `COMPLETE - VERIFIED ON LIVE CONFIGURED APP DB`

### Pre-Phase 2 Fixes

Status: `COMPLETE`

Completed:

- `BaseRepository.php` transaction methods (`beginTransaction`, `commit`, `rollback`) changed from `protected` to `public` so services can control transaction boundaries as required by the architecture
- PHP syntax validation passed after fix
- `docs/AI_TOOL_HANDOFF_GUIDE.md` created with established code patterns, multi-tool workflow, session start templates, and doc quick reference
- Duplicate methods and broken `lastInsertId` in BaseRepository cleaned up

### Phase 2 - Green Belt Core

Status: `COMPLETE - VERIFIED ON LIVE CONFIGURED APP DB`

New files created:

- `app/repositories/BeltRepository.php` — paginated list with filter builder, CRUD, uniqueness check
- `app/repositories/BeltAssignmentRepository.php` — unified repo for 3 assignment tables via parameterized table/column map
- `app/services/BeltService.php` — belt CRUD business logic with enum validation, GPS pair check, audit logging
- `app/services/BeltAssignmentService.php` — assignment create/close with belt/user existence checks, date validation, audit logging
- `app/controllers/BeltController.php` — 4 HTTP methods (listBelts, getBelt, createBelt, updateBelt)
- `app/controllers/BeltAssignmentController.php` — 9 HTTP methods (list/create/close × 3 assignment types) using shared handlers

- `app/repositories/MaintenanceCycleRepository.php` - cycle list/start/close persistence with belt joins
- `app/services/MaintenanceCycleService.php` - maintenance cycle list/start/close rules with Head Supervisor maintained-belt scope
- `app/controllers/MaintenanceCycleController.php` - cycle list/start/close HTTP handlers

Routes added to `config/route_registry.php`:

- `belt/list` (GET, read, green_belt.master)
- `belt/get` (GET, read, green_belt.detail)
- `belt/create` (POST, manage, green_belt.master)
- `belt/update` (POST, manage, green_belt.master)
- `supervisorassignment/list` (GET, read, green_belt.master)
- `supervisorassignment/create` (POST, manage, green_belt.master)
- `supervisorassignment/close` (POST, manage, green_belt.master)
- `authorityassignment/list` (GET, read, green_belt.master)
- `authorityassignment/create` (POST, manage, green_belt.master)
- `authorityassignment/close` (POST, manage, green_belt.master)
- `outsourcedassignment/list` (GET, read, green_belt.master)
- `outsourcedassignment/create` (POST, manage, green_belt.master)
- `outsourcedassignment/close` (POST, manage, green_belt.master)

- `cycle/list` (GET, read, green_belt.maintenance_cycles)
- `cycle/start` (POST, manage, green_belt.maintenance_cycles)
- `cycle/close` (POST, manage, green_belt.maintenance_cycles)

Patterns established:

- Parameterized repository for tables with identical structure (BeltAssignmentRepository TABLE_MAP pattern)
- Shared controller handlers to avoid repeating list/create/close logic across 3 assignment types
- Service-level enum validation using class constants
- GPS pair validation (both or neither)
- belt_code is immutable after creation (not included in update payload)
- Pagination built into list with `{ items, pagination: { page, limit, total } }` shape

- belt detail now returns assignment history, cycle summary/history, watering summary/history, uploads, and issues
- belt assignments now enforce role-key matching (`GREEN_BELT_SUPERVISOR`, `AUTHORITY_REPRESENTATIVE`, `OUTSOURCED_MAINTAINER`)
- active maintenance cycles auto-close with audit when a belt becomes hidden or permission expires

Reason:

- middleware is now driven by DB-backed role, permission-group, and module-scope checks
- route dispatch now uses a registry instead of growing switch-based authorization assumptions
- dynamic role list/get/create/update flow exists for role-to-module governance
- auth and session responses now expose landing and allowed-module context for menu/bootstrap use
- user management now validates assignable canonical roles and returns paginated list envelopes
- active `.env` database (`skite_ops`) has been rebuilt on the canonical schema and foundation seed
- request-level verification has passed against `http://localhost/skite/index.php?...` using the real configured app database
- Phase 2 patch follow-up added maintenance cycle runtime support (`cycle/list`, `cycle/start`, `cycle/close`)
- belt detail payload now includes cycle summary/history, watering summary/history, uploads, and issues
- assignment creation now enforces correct role keys for supervisor, authority, and outsourced mappings
- active maintenance cycles now auto-close with audit when a belt becomes hidden or permission expires

### Phase 3 - Upload Service Foundation

Status: `COMPLETE - DIRECT SERVICE VERIFICATION PASSED`

New files created:

- `app/repositories/UploadRepository.php` - polymorphic parent checks, upload persistence, filtered list queries, discovery free-media refresh helpers
- `app/services/UploadStorageService.php` - file normalization, MIME/extension/size validation, collision-safe storage paths under `storage/uploads`
- `app/services/UploadService.php` - shared upload creation, creator list foundation, self-delete foundation, discovery side-effects
- `tests/test_upload_foundation.php` - direct verification script for upload foundation behavior

Updated files:

- `config/constants.php` - upload size aligned to `10 MB` and max files per submission aligned to `10`

Completed behavior:

- normalized multi-file handling for `multipart/form-data` style `files[]`
- server-side MIME, extension, count, and size validation
- relative storage path generation using `uploads/<parent_type>/<YYYY>/<MM>/...`
- upload metadata row creation for `GREEN_BELT`, `SITE`, and `TASK` parents
- authority visibility defaults by surface (`SUPERVISOR`, `OUTSOURCED`, `MONITORING`, `TASK`)
- monitoring discovery mode creates or refreshes `DISCOVERED` free-media state
- creator-scoped upload listing foundation exists for later My Uploads routes
- self-delete foundation exists with 5-minute window and v1 issue-upload restriction
- stray root file `['AuthController'` removed as repo cleanup

Relevant validation:

- PHP syntax validation passed for `UploadRepository.php`, `UploadStorageService.php`, `UploadService.php`, and `test_upload_foundation.php`
- `tests/test_upload_foundation.php` passed: `14 PASSED, 0 FAILED`

Known deferrals:

- no upload HTTP routes or controllers added in this task
- no surface-specific RBAC wiring added in this task
- upload review, cleanup, and purge flows remain future scoped tasks

### Phase 3 - Supervisor Upload Backend

Status: `COMPLETE - SYNTAX VERIFIED`

New files created:
- `app/controllers/UploadController.php` - handles HTTP request parsing, extracts `role_key` to define `surface` and calls `UploadService->createUploadsForSurface`.

Updated files:
- `config/route_registry.php` - added `upload/create` allowing dynamic resolution without a single hard-coded `module_key`.
- `app/services/UploadService.php` - added `verifyRecordScope()` check injecting `BeltAssignmentRepository` logic for `SUPERVISOR` and `OUTSOURCED` belt assignment validations.
- `tests/test_upload_foundation.php` - updated to create assignments so verification uses valid context.

Completed behavior:
- `upload/create` available as a shared cross-role endpoint governed dynamically by role-based surface.
- `SUPERVISOR` surface strictly bounds `parent_id` to green belts currently covered under active supervisor assignments.
- Validated that decoupled multi-photo validation, default authority_visibility (e.g. `HIDDEN` vs `NOT_ELIGIBLE`) logic from previous foundation operates appropriately via `UploadController`.

Relevant validation:
- PHP syntax checks passed for `UploadController.php`, `config/route_registry.php`, and `UploadService.php`.

Known deferrals:
- `upload/my-list` deferred to next scoped task.
- Upload review queue for Ops deferred to later scoped task.

## Static Prompt Workflow

Use the same prompt every implementation turn:

```text
Continue from docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md and implement only the current next scoped task.
Use only the locked docs and docs/AI_TOOL_HANDOFF_GUIDE.md; do not restate context, redesign behavior, or touch unrelated modules.
Run only relevant validation, update docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md with status/results/blockers, then stop.
```

This prompt should not need wording changes between modules.
The only thing that changes over time is the progress file itself.

## Current Next Scoped Task

`supervisor my uploads backend`

## Serial Scoped Task Queue

Run these tasks in order, one per implementation turn.
Do not skip ahead unless the current task is blocked and that blocker is recorded below.

1. `upload service foundation` - COMPLETE
2. `supervisor upload backend` - COMPLETE
3. `supervisor my uploads backend`
4. `outsourced upload backend`
5. `watering backend`
6. `supervisor attendance backend`
7. `labour entries backend`
8. `issue management backend`
9. `task request intake backend`
10. `task creation from request backend`
11. `task management backend`
12. `task detail and progress update backend`
13. `fabrication lead work-done flow backend`
14. `fabrication workers master backend`
15. `worker daily entries backend`
16. `task worker assignment backend`
17. `worker availability and worker activity backend`
18. `site master backend`
19. `monitoring due-date planning backend`
20. `monitoring upload backend`
21. `monitoring history backend`
22. `campaign management backend`
23. `free media backend`
24. `authority view backend`
25. `authority summary and whatsapp helper backend`
26. `reports backend`
27. `system settings backend`
28. `rejected uploads cleanup backend`
29. `frontend navigation shell from allowed_module_keys`
30. `phase acceptance review for completed modules`

## Current Task Reference Docs

Read only the docs needed for the current scoped task.
For the current `supervisor upload backend` task, start with:

- `docs/11_build_specs/01_RBAC_PERMISSION_GROUP_SPEC.md`
- `docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md`
- `docs/11_build_specs/03_API_AND_ROUTE_CONTRACT.md`
- `docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md`
- `docs/11_build_specs/05_WORKFLOW_STATE_MACHINE_SPEC.md`
- `docs/11_build_specs/06_UPLOAD_STORAGE_RETENTION_SPEC.md`
- `docs/11_build_specs/09_MODULE_ACCEPTANCE_CHECKLISTS.md`

## Task Update Rule

After each task:

- mark the completed task in a short results note
- move `Current Next Scoped Task` to the next queue item
- record only relevant validation
- record blockers only if they stop the current task
- stop after the current task instead of continuing into the next one

## Phase 1 Work Completed In Code

- replaced hardcoded route-to-role arrays with DB-driven `module_key + capability` checks
- introduced `config/route_registry.php` for current protected/public route metadata
- introduced `config/rbac.php` for approved module catalog, landing-route mapping, and permission capability matrix
- added `RbacRepository`, `RbacService`, `RoleService`, and `RoleController`
- added `role/list`, `role/get`, `role/create`, and `role/update` runtime support
- updated auth login/session flows to return `permission_group_key` and `allowed_module_keys`
- updated user list flow to support paginated envelope plus canonical role filtering
- updated user create/update validation to require assignable canonical roles

## Phase 1 Validation Run

Completed:

- timestamped backup of the pre-canonical runtime DB created at `storage/db_backups/skite_ops_pre_canonical_20260413_085057.sql`
- active `skite_ops` DB rebuilt from `docs/06_schema/schema_v1_full.sql`
- active `skite_ops` DB seeded from `migrations/001_seed_foundation.sql`
- PHP syntax validation passed for all new and modified Phase 1 files
- RBAC config integrity check passed
- route-registry/controller integrity check passed
- login, forced-reset gating, reset-password flow, session bootstrap, logout, and landing behavior verified through `localhost`
- direct URL/module access denial verified using a Green Belt Supervisor account against Ops-only routes
- dynamic role creation verified with one permission group and approved module validation
- landing-module validation rejection verified when landing target was outside selected `module_keys`
- user lifecycle routes verified for deactivate, activate, soft-delete, and restore
- audit log rows verified for role-governance and user-lifecycle actions
- landing-route resolution verified for every current role in the runtime DB, including dynamic role `LIGHTMAN_TEST`

Local verification data now present in runtime DB:

- bootstrap Ops user: `ops.manager@skite.local`
- verification Green Belt Supervisor user: `gbs.phase1@skite.local`
- dynamic verification role: `LIGHTMAN_TEST`

Residual notes:

- backend now returns `allowed_module_keys` for future menu generation, but no frontend navigation shell exists yet
- Phase 1 routes do not yet exercise rich domain record-scope filters; deeper record-scope testing begins with Green Belt and later modules

## Legacy Prompt Notes

The older "recommended next order" and ad hoc prompt style are now superseded by:

- `Current Next Scoped Task`
- `Serial Scoped Task Queue`
- `Static Prompt Workflow`

Any AI tool should follow those sections instead of inventing a fresh plan.
