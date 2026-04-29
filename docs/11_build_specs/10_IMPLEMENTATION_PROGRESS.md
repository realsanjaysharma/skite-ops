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
Continue the last agent's work in this Skite Ops repo.

Read these two files first:
1. docs/AI_TOOL_HANDOFF_GUIDE.md — read only the "Codebase Pitfalls and Safety Rules" section
2. docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md — this is the active execution tracker

From 10_IMPLEMENTATION_PROGRESS.md, identify and implement only the current next scoped task (one phase from the Gap Closure Phase Queue).

For detailed phase instructions, read only the matching phase section from the implementation plan file referenced in the progress file under "Implementation Plan Reference" — do not read the entire plan file.

Use only the locked docs. Do not redesign product behavior, skip ahead, or touch unrelated modules unless a real blocker appears.

After finishing the current phase:
- run the relevant validation commands listed in the progress file
- update docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md: set next scoped task, mark the completed phase, add validation results
- update docs/AI_TOOL_HANDOFF_GUIDE.md only if a new pitfall was discovered
- stop — do not continue into the next phase
```

## Current Next Scoped Task

Phase 9: New v1 surfaces (4 Missing Modules) (multi-file)

## Implementation Plan Reference

The detailed step-by-step instructions for each phase are in:

```
C:\Users\radhi\.gemini\antigravity\brain\676b78f8-8bdc-45ba-abe9-cab84768198f\implementation_plan.md
```

**Instructions for agents:** When starting a phase, open this file, find the section matching the current phase number (e.g. "Phase 1 — Backend: Report Formula Correctness"), and follow only that section's instructions. Do not read the entire file.

## Gap Closure Phase Queue

Run these phases in order, one per implementation turn.

1. `[x] Phase 1: Backend report formula correctness` — fix hardcoded watering compliance (0/0/100%), historical supervisor attribution, and monitoring coverage in ReportService/ReportRepository. Files: `app/services/ReportService.php`, `app/repositories/ReportRepository.php`. Spec: `docs/11_build_specs/07_REPORTS_ALERTS_AND_FORMULAS.md`. Result: [PASS] Syntax scan. [PASS] Manual API verification for Belt Health, Supervisor Activity, and Ad Ops reports showing real compliance/coverage data.
2. `[x] Phase 2: Backend task completion guard` — enforce AFTER_WORK proof requirement before task completion in TaskService. Confirm self-delete window source. Files: `app/services/TaskService.php`, `config/constants.php`. Result: [PASS] Aligned DomainException message in TaskService. [PASS] Verified guard via test script. [PASS] Confirmed UPLOAD_SELF_DELETE_WINDOW_MINUTES in constants.php.
3. `[x] Phase 3: Backend dashboard formula correctness` — add 3 missing Master Ops cards (campaign_ending_soon, free_media_active, monitoring_due_today), fix Green Belt dashboard (add active_cycle_count, watering_pending), fix Monitoring dashboard qualifying completion logic. Files: `app/services/DashboardService.php`. Result: [PASS] All spec-required keys present on all dashboards. [PASS] Formulas correctly handle qualifying completions and soft-deletes.
4. `[x] Phase 4: Frontend monitoring pipeline` — add discovery mode toggle to monitoring.upload, add discovery_mode filter to monitoring.history, add bulk-copy panel to monitoring.plan. Files: `public/js/views/modules.js`. Result: [PASS] Syntax scan. [PASS] JS syntax OK. [PASS] Bulk Copy UI now in-page with radio selection for Route vs Site IDs. [PASS] Monitoring upload includes silent GPS capture.
5. `[x] Phase 5: Frontend dashboard upgrades` — replaced generic dashboardView() with custom views for Master Ops, Green Belt, and Monitoring dashboards. Added metric cards with clickable navigation, Green Belt attention list, and Monitoring discovery count. Files: `public/js/views/modules.js`, `public/js/core/ui.js`, `app/services/DashboardService.php`, `app/repositories/BeltRepository.php`. Result: [PASS] Syntax scan. [PASS] JS syntax OK. [PASS] Clickable cards verified via code analysis and route map tests.
6. `[x] Phase 6: Frontend belt detail UX` — auto-detect active cycle for close modal, added "End Assignment" buttons to assignment tables, registered standalone `green_belt.maintenance_cycles` view for global cycle management. Files: `public/js/views/modules.js`. Result: [PASS] Syntax scan. [PASS] JS syntax OK. [PASS] Route map verified.
7. `[x] Phase 7: Frontend Head Supervisor landing + GPS + request UX` — unified `green_belt.watering_oversight` into a 4-section daily surface, added silent GPS capture to `uploadView` factory and `monitoring.upload`, implemented role-based split for `task.request_intake` (Form + My Requests for non-Ops). Files: `public/js/views/modules.js`. Result: [PASS] Syntax scan. [PASS] JS syntax OK. [PASS] Route map verified. [PASS] Unified landing correctly uses Promise.all for parallel loads.
8. `[x] Phase 8: Frontend global polish` — added `renderPagination` component to key views (Audit, Upload Review, Master), integrated `loadSupervisors` select dropdowns in filters, added "Raise Request" link to Free Media inventory, and implemented WhatsApp toggle guard in Authority View. Fixed missing `UI.nextMonth` in `ui.js`. Files: `public/js/views/modules.js`, `public/js/core/ui.js`. Result: [PASS] Syntax scan. [PASS] JS syntax OK. [PASS] Route map verified. [PASS] Pagination wiring confirmed. [PASS] Dropdown loading confirmed.
9. `Phase 9: New v1 surfaces` — add 4 new modules end-to-end: governance.alert_panel, task.worker_daily_entry, commercial.client_media_library, commercial.media_planning_inventory. Files: `config/rbac.php`, `config/route_registry.php`, `app/controllers/DashboardController.php`, `app/controllers/SiteController.php`, `app/controllers/FreeMediaController.php`, `app/services/DashboardService.php`, `public/js/core/navigation.js`, `public/js/views/modules.js`, `migrations/003_new_modules_seed.sql`. Run migration SQL on live DB.

## Critical Gotchas

Full list is in `docs/AI_TOOL_HANDOFF_GUIDE.md` under **Codebase Pitfalls and Safety Rules**. Read that section before writing any payload, enum value, or route key.

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
```

Local test credentials:

- Base URL: `http://localhost/skite/index.php?route=`
- Ops email: `ops.test.phase2@skite.local`
- Password: `TestPass123!`

## Established Backend Patterns

- Controllers extend `BaseController` and use `$this->requireMethod()`, `$this->getInput()`, `$this->getActor()`.
- Services own business rules, record scope, transactions, and audit logging.
- Repositories own SQL only and extend `BaseRepository`.
- List responses use `{ items, pagination: { page, limit, total } }`.
- Use `AuditService` for governed mutations.

## Established Frontend Patterns

- Register pages with `Views.register(moduleKey, { render, afterRender })` in `public/js/views/modules.js`.
- Use `UI.page`, `UI.panel`, `UI.table`, `UI.filters`, `UI.field`, `UI.showModal`, `UI.toast`.
- Use `openSimpleForm()` for mutation modals; `simpleAction(route, payload, msg)` for POST mutations.
- Use `App.navigate(moduleKey, params)` for route changes.
- Keep `Navigation.NavMap` aligned with `config/rbac.php` and `config/route_registry.php`.

## Asset Cache Markers

Current: `modules.js?v=19`, `style.css?v=3`. Bump after each frontend phase.

## Task Update Rule

After each phase:

- update `Current Next Scoped Task` to the next phase
- mark exactly one queue item complete
- add a short result note with files changed and validation run
- record blockers only if they stop the current phase
- stop — do not continue into the next phase
- if a new pitfall was discovered, add it to `docs/AI_TOOL_HANDOFF_GUIDE.md` under **Codebase Pitfalls and Safety Rules**
