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
- `app/repositories/BaseRepository.php`
- `app/repositories/UserRepository.php`
- `app/repositories/RbacRepository.php`
- `app/repositories/AuditRepository.php`
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

## Current Recommended Next Task

Begin Phase 3 (field operations).

Use:

- `docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md` (watering_records, supervisor_attendance, labour_entries, maintenance_cycles, issues, uploads)
- `docs/11_build_specs/03_API_AND_ROUTE_CONTRACT.md` (watering/*, attendance/*, labour/*, cycle/*, upload/*, issue/* routes)
- `docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md` (Supervisor Upload, Watering, Attendance, Labour, Issue pages)
- `docs/11_build_specs/05_WORKFLOW_STATE_MACHINE_SPEC.md` (maintenance cycle and issue state machines)
- `docs/11_build_specs/06_UPLOAD_STORAGE_RETENTION_SPEC.md` (upload storage, validation, parent context matrix)
- `docs/11_build_specs/09_MODULE_ACCEPTANCE_CHECKLISTS.md`

Expected scope:

- upload service foundation (file validation, storage path, metadata insertion)
- supervisor upload and my-uploads flows
- outsourced upload flow
- watering record CRUD with scheduler logic
- supervisor attendance
- labour entries
- issue management CRUD and lifecycle

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

## After Phase 1

Recommended next order:

1. Green Belt Master + detail
2. belt assignments
3. upload/storage foundation
4. watering + attendance + labour
5. issues + request-to-task flow
6. fabrication lead + worker tracking
7. site master + monitoring
8. campaigns + free media
9. authority view
10. reports + settings + cleanup

## Prompt Shortcut

You can use prompts like:

- `Continue from docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md and implement the current next task.`
- `Update the implementation progress file after finishing the module.`
- `Review current module against the progress file and acceptance checklist.`

## Update Rule

Update this file whenever:

- a phase starts
- a module is completed
- a blocker is discovered
- the recommended next task changes
