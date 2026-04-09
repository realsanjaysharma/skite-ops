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
- reusable auth/user/audit foundation exists, but only a small part of the product is implemented

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
- `app/middleware/AuthMiddleware.php`
- `app/services/AuthService.php`
- `app/services/UserService.php`
- `app/services/AuditService.php`
- `app/repositories/BaseRepository.php`
- `app/repositories/UserRepository.php`
- `app/repositories/AuditRepository.php`
- `config/database.php`
- `config/constants.php`

## Not Implemented Yet

### Platform And RBAC Runtime

- DB-driven RBAC middleware using permission groups and module scopes
- access/mapping governance module
- seeded-role-aware user-management behavior beyond current foundation

### Product Modules

- Green Belt Master and detail
- belt assignment management
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

Status: `READY TO START`

Reason:

- docs are locked
- schema is validated
- seed path exists
- current middleware is still hardcoded and not yet driven by canonical RBAC tables

## Current Recommended Next Task

Implement DB-driven RBAC runtime and seeded-role boot flow.

Use:

- `docs/11_build_specs/01_RBAC_PERMISSION_GROUP_SPEC.md`
- `docs/11_build_specs/03_API_AND_ROUTE_CONTRACT.md`
- `docs/11_build_specs/09_MODULE_ACCEPTANCE_CHECKLISTS.md`
- `docs/11_build_specs/00_IMPLEMENTATION_MASTER_PLAN.md`

Expected scope:

- replace hardcoded numeric-role middleware assumptions
- load role, permission-group, and module-scope data from DB
- align protected route checks with canonical module model
- keep forced password-reset flow intact
- preserve current auth/user foundation where reusable

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
