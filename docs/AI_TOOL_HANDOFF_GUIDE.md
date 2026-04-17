# AI Tool Handoff Guide — Skite Ops

## Purpose

This file exists so any AI coding tool (Antigravity, Cursor, Codex, Claude, ChatGPT) can pick up this project mid-build without losing context. Read this file FIRST before doing any work.

## How To Start Any Session

```
1. Read this file (docs/AI_TOOL_HANDOFF_GUIDE.md)
2. Read docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md
3. Identify the "Current Recommended Next Task" section
4. Read ONLY the doc files listed under that task
5. Begin work
```

Do NOT re-read the entire docs folder every session. The progress file tells you exactly which docs matter for the current task.

## Project Identity

- **Name:** Skite Ops
- **Stack:** PHP 8+ / MySQL (MariaDB) / XAMPP local dev
- **Entry point:** `index.php` — query-string router (`?route=module/action`)
- **Architecture:** Controller → Service → Repository → Database
- **API style:** JSON-only REST-like API, no server-rendered HTML yet
- **DB name:** `skite_ops` (configured in `.env`)
- **PHP path:** `C:\xampp\php\php.exe`
- **Base URL:** `http://localhost/skite/index.php?route=`

## Documentation Authority Rules

When docs conflict, use this precedence:

1. `docs/10_recovered_product/*` — wins on WHAT the system should do
2. `docs/11_build_specs/*` — wins on HOW it should be implemented
3. Legacy docs (`01_structure`, `02_interface`, etc.) — mirrors only, never override canon

## Established Code Patterns

Every new module MUST follow these patterns. Do not invent new ones.

### File Structure Per Module

```
app/
  controllers/<ModuleName>Controller.php    — HTTP validation + response shaping only
  services/<ModuleName>Service.php          — Business rules, validation, lifecycle
  repositories/<ModuleName>Repository.php   — SQL queries only, extends BaseRepository
```

### Route Registration

Add routes to `config/route_registry.php` following this exact format:

```php
'module/action' => [
    'controller' => 'ModuleNameController',
    'method'     => 'methodName',
    'module_key' => 'domain.module_key',       // from config/rbac.php module_catalog
    'capability' => 'read|upload|approve|manage',
],
```

### Controller Pattern

```php
class ExampleController {
    public function actionName(): void {
        // 1. Check HTTP method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }
        // 2. Parse input (JSON body or query params)
        $input = json_decode(file_get_contents('php://input'), true);
        // 3. Validate required fields exist (format only, not business rules)
        if (empty($input['field_name'])) {
            Response::error('field_name is required', 400);
            return;
        }
        // 4. Call service
        try {
            $service = new ExampleService();
            $result = $service->doSomething($input, $_SESSION['user_id']);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
```

### Service Pattern

```php
class ExampleService {
    private ExampleRepository $repo;
    private AuditService $auditService;

    public function __construct() {
        $this->repo = new ExampleRepository();
        $this->auditService = new AuditService();
    }

    public function doSomething(array $data, int $actorUserId): array {
        // 1. Business validation (not HTTP format validation)
        // 2. Execute business logic
        // 3. Use transactions for multi-table mutations
        //    $this->repo->beginTransaction();
        //    try { ... $this->repo->commit(); }
        //    catch (...) { $this->repo->rollback(); throw; }
        // 4. Write audit log for governed mutations
        // 5. Return result array
    }
}
```

### Repository Pattern

```php
class ExampleRepository extends BaseRepository {
    public function findById(int $id): ?array {
        return $this->fetchOne("SELECT * FROM table WHERE id = ?", [$id]);
    }

    public function findAll(array $filters, int $page, int $limit): array {
        // Build WHERE clause from filters
        // Return $this->fetchAll(...)
    }

    public function create(array $data): int {
        $this->execute("INSERT INTO table (...) VALUES (...)", [...]);
        return (int) $this->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        return $this->execute("UPDATE table SET ... WHERE id = ?", [...]);
    }

    public function countAll(array $filters): int {
        $row = $this->fetchOne("SELECT COUNT(*) as total FROM table WHERE ...", [...]);
        return (int) ($row['total'] ?? 0);
    }
}
```

### Response Envelope

Always use `Response::success($data)` and `Response::error($message, $statusCode)`.

Success: `{ "success": true, "data": {...} }`
Error: `{ "success": false, "error": "Message" }`

### List Response Shape

```php
Response::success([
    'items' => $items,
    'pagination' => [
        'page'  => $page,
        'limit' => $limit,
        'total' => $total,
    ]
]);
```

### Audit Logging

For any governed mutation (create, update, delete, lifecycle change, override):

```php
$this->auditService->log(
    $actorUserId,
    'ACTION_TYPE',      // e.g. BELT_CREATED, ASSIGNMENT_CLOSED
    'entity_type',      // e.g. green_belt, belt_supervisor_assignment
    $entityId,
    $oldValues,         // array or null
    $newValues,         // array or null
    $overrideReason     // string or null
);
```

### Constants

All magic numbers must come from `config/constants.php`. Never hardcode values.

### RBAC

- Module keys are in `config/rbac.php` under `module_catalog`
- Landing routes are in `config/rbac.php` under `landing_routes`
- Capability matrix: VIEW→read, UPLOAD→read+upload, APPROVE→read+approve, MANAGE→all
- Middleware handles module-level access automatically via route_registry `module_key`
- Record-scope filtering is the SERVICE layer's responsibility

## Transaction Method Access

`BaseRepository` exposes `beginTransaction()`, `commit()`, and `rollback()` as **public** methods. Services call these on any repository instance since all repos share the same PDO singleton.

## Multi-Tool Workflow

### Before Ending Any Session

Update `docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md` with:

1. **What was just completed** — files created, routes added, tests passed
2. **File patterns established** — any new patterns introduced (note them here too)
3. **Current recommended next task** — what to build next, which docs to read
4. **Known issues** — anything broken or deferred

### Tool Selection Guide

| Task Type | Best Tool | Why |
|---|---|---|
| Full module scaffolding (controller+service+repo) | Antigravity or Codex | Filesystem access, can read existing patterns |
| Small edits, route additions | Cursor + Copilot | In-editor, fast |
| Complex business logic planning | Claude.ai or ChatGPT | Paste doc sections, discuss logic |
| Debugging PHP errors | Any chat tool | Paste error + code, get fix |
| Report formula implementation | Antigravity | Needs to read multiple doc files simultaneously |

### Session Start Prompt Template

Use this when opening any AI tool for this project:

```
Read docs/AI_TOOL_HANDOFF_GUIDE.md and docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md
from the Skite Ops project at c:\xampp\htdocs\skite.

Continue from the current recommended next task.
Follow the established code patterns documented in the handoff guide.
```

## Current Project Status

### Completed

- Phase 0: Doc and contract alignment — LOCKED
- Phase 1: Platform foundation and RBAC — VERIFIED ON LIVE DB
- BaseRepository transaction fix — DONE (methods now public)

### In Progress

- Phase 2: Green Belt Core — NOT STARTED

### Build Phase Order

```
Phase 2  → Green Belt Master + assignments
Phase 3  → Field operations (upload, watering, attendance, labour, issues)
Phase 4  → Request → Task → Fabrication execution
Phase 5  → Advertisement sites, monitoring, campaigns, free media
Phase 6  → Authority view, read portals
Phase 7  → Reports, alerts, dashboards, settings
Phase 8  → Hardening, pagination, transaction coverage, deployment
Frontend → Technology decision still pending
```

### Infrastructure Items To Build Alongside Modules

These should be built when first needed, not as a separate phase:

- [ ] **Pagination helper** — build during Phase 2 `belt/list`
- [ ] **Record-scope helper** — build during Phase 3 when supervisor/authority scoping begins
- [ ] **Upload service** — build during Phase 3 supervisor upload
- [ ] **Settings service** — build during Phase 7 or when first setting is needed
- [ ] **Frontend architecture decision** — must be made before Phase 6

## Key Doc Quick Reference

| Need to know... | Read this |
|---|---|
| What tables exist and their columns | `docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md` |
| What routes/endpoints to create | `docs/11_build_specs/03_API_AND_ROUTE_CONTRACT.md` |
| What fields/filters each page needs | `docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md` |
| What state transitions are allowed | `docs/11_build_specs/05_WORKFLOW_STATE_MACHINE_SPEC.md` |
| Upload rules and storage layout | `docs/11_build_specs/06_UPLOAD_STORAGE_RETENTION_SPEC.md` |
| Dashboard/report formulas | `docs/11_build_specs/07_REPORTS_ALERTS_AND_FORMULAS.md` |
| System settings and external actions | `docs/11_build_specs/08_SYSTEM_SETTINGS_AND_EXTERNAL_ACTIONS.md` |
| Module done-when criteria | `docs/11_build_specs/09_MODULE_ACCEPTANCE_CHECKLISTS.md` |
| Current progress and next task | `docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md` |
| RBAC role→module→capability mapping | `docs/11_build_specs/01_RBAC_PERMISSION_GROUP_SPEC.md` |
| Build phase order | `docs/11_build_specs/00_IMPLEMENTATION_MASTER_PLAN.md` |
| Product intent and scope | `docs/10_recovered_product/00_FINAL_PRODUCT_BEHAVIOR_MODEL.md` |

## File Naming Conventions

- Controllers: `<EntityName>Controller.php` — PascalCase, singular (e.g., `BeltController.php`)
- Services: `<EntityName>Service.php` — PascalCase, singular (e.g., `BeltService.php`)
- Repositories: `<EntityName>Repository.php` — PascalCase, singular (e.g., `BeltRepository.php`)
- Route modules: lowercase, matching route prefix (e.g., `belt/list` → `BeltController`)

## PHP Syntax Check Command

Always validate PHP files after creation:

```
C:\xampp\php\php.exe -l <filepath>
```

## Update Rule

Any AI tool that modifies this project should update:

1. This file if new patterns are established
2. `docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md` if any module work is completed
