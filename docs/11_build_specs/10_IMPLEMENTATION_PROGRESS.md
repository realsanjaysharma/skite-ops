# Implementation Progress

## Authority Note

- Purpose: low-token execution tracker for AI agents.
- Authority level: execution support only.
- If conflict: `docs/10_recovered_product/*` controls product meaning; `docs/11_build_specs/*` controls implementation behavior.
- Do not treat this file as a product spec. Use it to know what to do next and what traps to avoid.
- Historical implementation detail was moved to `docs/11_build_specs/10_IMPLEMENTATION_HISTORY_ARCHIVE.md`; open it only when a completed-phase narrative is needed.

## Agent Start Here

Product canon and implementation spec are locked — do not reinterpret or reopen them.
Legacy mirror docs (`docs/01_structure`, `docs/02_interface`, etc.) are aligned but superseded — ignore them.
Canonical schema (`docs/06_schema/schema_v1_full.sql`) and foundation seed (`migrations/001_seed_foundation.sql`) are validated and running on the live `skite_ops` MariaDB database. The DB is clean and safe to test against.

Current status: backend modules are implemented and pass syntax plus HTTP smoke coverage used so far. The vanilla JS frontend shell is implemented and RBAC-aware. Most frontend modules have custom views, but several remaining views still need full action-level completion and final browser polish.

Current frontend asset cache marker: `?v=8`.
If you change `public/js/views/modules.js`, `public/js/core/*.js`, or `public/js/app.js`, bump the matching script version in `public/index.html`.

## Static Prompt Workflow

Use the same prompt every implementation turn:

```text
Continue from docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md and implement only the current next scoped task.
Use only the locked docs and docs/AI_TOOL_HANDOFF_GUIDE.md; do not restate context, redesign behavior, or touch unrelated modules.
Run only relevant validation, update docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md with status/results/blockers, then stop.
```

## Current Next Scoped Task

`task.progress_read full view`

## Remaining Frontend Task Queue

Run these tasks in order, one per implementation turn. Do not skip ahead unless the current task is blocked and the blocker is recorded here.

1. `task.my_tasks full view` - COMPLETE
2. `green_belt.upload_review full view` - COMPLETE
3. `green_belt.issue_management full view` - COMPLETE
4. `green_belt.authority_view full view` - COMPLETE
5. `governance.user_management full view` - COMPLETE
6. `governance.access_mappings full view` - COMPLETE
7. `task.progress_read full view` - CURRENT
8. `dashboard and analytics final pass`
9. `final browser walkthrough and polish`

## Remaining View Quick Reference

| Module | Main routes | Required UI actions | Key constraints |
|---|---|---|---|
| `green_belt.issue_management` | `issue/list`, `issue/get`, `issue/in-progress`, `issue/close`, `issue/link-task` | view detail, mark in progress, link task, close issue | Ops and Head Supervisor scope rules stay service-enforced; issue evidence never becomes authority-approved |
| `green_belt.authority_view` | `authority/view`, `authority/summary`, `authority/share-helper` | filters, summary, download/share helper | show only `APPROVED` green-belt `WORK` uploads scoped to assigned authority belts |
| `governance.user_management` | `user/list`, `user/get`, `user/create`, `user/update`, `user/deactivate`, `user/activate`, `user/delete`, `user/restore` | create/edit, activate/deactivate, soft delete/restore | Ops governance only; use canonical role IDs/keys from backend payloads |
| `governance.access_mappings` | `role/list`, `role/get`, `role/create`, `role/update` | create/edit dynamic roles and module scope | one role = one permission group; landing module must be inside selected module scope |
| `task.progress_read` | `taskprogress/list`, `taskprogress/get` | read-only list and detail drill-in | no fabrication execution controls; Sales/Client Servicing/Media Planning use this for progress tracking |
| `dashboard and analytics final pass` | dashboard routes, report routes | polish cards, drill-ins, CSV links | metrics must come from backend routes/services, not duplicated frontend formulas |

## Critical Gotchas

Full list is in `docs/AI_TOOL_HANDOFF_GUIDE.md` under **Codebase Pitfalls and Safety Rules**. Read that section before writing any payload, enum value, or route key. Task-specific warnings are noted inline in **Current Task Reference Docs** below.

## Test Commands

Use the smallest relevant set for the task, then update this file with results.

```powershell
C:\xampp\php\php.exe tests\syntax_scan.php
node --check public\js\app.js
node --check public\js\core\api.js
node --check public\js\core\auth.js
node --check public\js\core\navigation.js
node --check public\js\core\ui.js
node --check public\js\views\modules.js
C:\xampp\php\php.exe tests\test_frontend_route_map.php
C:\xampp\php\php.exe tests\test_frontend_nav.php
C:\xampp\php\php.exe tests\test_gap_resolution.php
C:\xampp\php\php.exe tests\test_upload_review_safety.php
```

HTTP integration scripts exist and may require the right shell/runtime:

```bash
bash tests/http_integration_test.sh
bash tests/http_integration_mutations.sh
```

Local test credentials commonly used by scripts:

- Base URL: `http://localhost/skite/index.php?route=`
- Ops email: `ops.test.phase2@skite.local`
- Password: `TestPass123!`

## Completed Backend Summary

Backend detail is intentionally compressed to reduce agent token usage. Use `config/route_registry.php` as the source of truth for route names.

| Area | Status | Main controllers/services/repos | Key routes |
|---|---|---|---|
| Auth, RBAC, users, roles | Implemented, live smoke verified | `AuthController`, `UserController`, `RoleController`; `AuthService`, `RbacService`, `UserService`, `RoleService`; `UserRepository`, `RbacRepository` | `auth/*`, `user/*`, `role/*` |
| Green Belt core and assignments | Implemented | `BeltController`, `BeltAssignmentController`, `MaintenanceCycleController`; belt and assignment services/repos | `belt/*`, `supervisorassignment/*`, `authorityassignment/*`, `outsourcedassignment/*`, `cycle/*` |
| Field operations | Implemented | `UploadController`, `WateringController`, `AttendanceController`, `LabourController`, `OversightController`; related services/repos | `upload/*`, `watering/*`, `attendance/*`, `labour/*`, `oversight/watering` |
| Issues, requests, tasks | Implemented | `IssueController`, `RequestController`, `TaskController`, `TaskProgressController`; related services/repos | `issue/*`, `request/*`, `task/*`, `taskprogress/*` |
| Fabrication workers | Implemented | `WorkerController`, `WorkerEntryController`, `TaskWorkerController`; related services/repos | `worker/*`, `workday/*`, `taskworker/*` |
| Advertisement, monitoring, media | Implemented | `SiteController`, `MonitoringPlanController`, `MonitoringHistoryController`, `MonitoringUploadController`, `CampaignController`, `FreeMediaController`; related services/repos | `site/*`, `monitoringplan/*`, `monitoring/upload`, `monitoring/history`, `campaign/*`, `freemedia/*` |
| Authority portal | Implemented | `AuthorityViewController`, `AuthorityViewService`, `AuthorityViewRepository` | `authority/view`, `authority/summary`, `authority/share-helper` |
| Reports, settings, cleanup, audit | Implemented | `ReportController`, `SystemSettingsController`, `AuditController`, extended upload cleanup flow | `report/*`, `settings/*`, `upload/cleanup-list`, `upload/purge`, `audit/list` |
| Backend hardening | Implemented | `BaseController`; centralized audit and transaction patterns | fixed route registry, class loading, task state transitions, upload review safety |

## Established Backend Patterns

- Controllers extend `BaseController` when possible and use `$this->requireMethod()`, `$this->getInput()`, and `$this->getActor()`.
- Services own business rules, record scope, transactions, and audit logging.
- Repositories own SQL only and extend `BaseRepository`.
- List responses use `{ items, pagination: { page, limit, total } }`.
- Use `AuditService` for governed mutations.
- Do not put role-only decisions in controllers when middleware/service scope can enforce them.

## Established Frontend Patterns

- Frontend remains vanilla JS for now.
- Register pages with `Views.register(moduleKey, { render, afterRender })` in `public/js/views/modules.js`.
- Use `UI.page`, `UI.panel`, `UI.table`, `UI.filters`, `UI.field`, `UI.showModal`, and `UI.toast` instead of ad hoc markup when possible.
- Use `openSimpleForm()` for simple mutation modals.
- Use `simpleAction(route, payload, successMessage)` for POST mutations; it handles toast, modal close, and refresh.
- Use `App.navigate(moduleKey, params)` for route changes.
- Keep `Navigation.NavMap` aligned with `config/rbac.php` and `config/route_registry.php`; `tests/test_frontend_route_map.php` checks this.

## Completed Frontend Summary

| Area | Status |
|---|---|
| Shell, auth bootstrap, RBAC sidebar, mobile nav | Complete |
| Dashboard base screens | Complete, needs final analytics polish |
| Green Belt master/detail | Custom view complete |
| Field upload, my uploads, outsourced upload, monitoring upload | Base custom views complete |
| Watering, attendance, labour | Custom views complete |
| Site master, campaigns, free media | Custom views complete |
| Monitoring plan/history | Custom views complete |
| Task request, task management, task detail, workers | Custom views complete |
| Upload review and cleanup | Custom view complete with backend safety guard |
| Settings and audit logs | Custom views complete |
| Issue management | Custom view complete with modal drill-in and task linking |
| Authority view | Custom view complete with summary cards, image gallery modal, and whatsapp share button |
| User management | Custom view complete with create, edit, activate, deactivate, soft delete, and restore actions |
| Remaining | access mappings, task progress read, final dashboard/browser polish |

## Current Task Reference Docs

For `governance.user_management full view`, start with:

- `docs/11_build_specs/03_API_AND_ROUTE_CONTRACT.md` — `user/list`, `user/create`, `user/update`, `user/deactivate`, `user/activate`, `user/delete`, `user/restore` payloads
- `docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md` — §27 User Management (columns, actions)
- `docs/11_build_specs/09_MODULE_ACCEPTANCE_CHECKLISTS.md` — §1 Platform Foundation and RBAC (user management acceptance gates)

## Task Update Rule

After each task:

- update `Current Next Scoped Task`
- mark exactly one queue item complete
- add a short result note with files changed and validation run
- record blockers only if they stop the current task
- stop instead of continuing into the next queue item
- if a new pitfall or safety fix was discovered (wrong field name, enum mismatch, RBAC gap, silent failure, missing validation, XSS risk, approval bypass, etc.) add it to the **Codebase Pitfalls and Safety Rules** section of `docs/AI_TOOL_HANDOFF_GUIDE.md`, not here
