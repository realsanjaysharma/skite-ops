# AI Tool Handoff Guide - Skite Ops

## Purpose

This is the evergreen handoff guide for AI coding tools working on Skite Ops. It documents stable patterns, commands, and doc authority. It does not track current project status.

Current status and the next task live only in:

- `docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md`

## How To Start Any Session

1. Read this file.
2. Read `docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md`.
3. Follow `Current Next Scoped Task`.
4. Read only the reference docs listed for that task.
5. Implement only that task, validate, update the progress file, then stop.

Do not re-read the whole docs folder every session.

## Project Identity

- Name: Skite Ops
- Stack: PHP 8+ / MySQL or MariaDB / XAMPP local dev
- Frontend: vanilla JS app shell in `public/`
- Entry point: `index.php`
- Router style: query-string route, for example `?route=module/action`
- Backend architecture: Controller -> Service -> Repository -> Database
- API style: JSON REST-like endpoints
- DB name: `skite_ops` from `.env`
- PHP path: `C:\xampp\php\php.exe`
- Base URL: `http://localhost/skite/index.php?route=`

## Documentation Authority

When docs conflict, use this precedence:

1. `docs/10_recovered_product/*` controls product intent and behavior.
2. `docs/11_build_specs/*` controls implementation contracts.
3. Legacy docs are historical mirrors only.
4. Code is checked against the canonical docs, not the old legacy docs.

## Backend Patterns

Every backend module follows Controller -> Service -> Repository.

### Files

```text
app/controllers/<ModuleName>Controller.php
app/services/<ModuleName>Service.php
app/repositories/<ModuleName>Repository.php
```

### Route Registration

Add protected routes to `config/route_registry.php`:

```php
'module/action' => [
    'controller' => 'ModuleNameController',
    'method'     => 'methodName',
    'module_key' => 'domain.module_key',
    'capability' => 'read|upload|approve|manage',
],
```

Use `module_key => null` only for intentionally dynamic routes such as shared upload surfaces where the controller/service resolves access from role context.

### Controller Pattern

Controllers should extend `BaseController` when possible.

```php
class ExampleController extends BaseController
{
    public function actionName(): void
    {
        if (!$this->requireMethod('POST')) return;

        $input = $this->getInput();
        $actor = $this->getActor();

        try {
            $service = new ExampleService();
            $result = $service->doSomething($input, $actor['user_id'], $actor['role_key']);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
```

Controller responsibility:

- HTTP method checks
- input shape parsing
- response shaping
- no deep business rules
- no SQL

### Service Pattern

Services own business rules, state transitions, record-scope checks, transactions, and audit logging.

```php
class ExampleService
{
    private ExampleRepository $repo;
    private AuditService $auditService;

    public function __construct()
    {
        $this->repo = new ExampleRepository();
        $this->auditService = new AuditService();
    }

    public function doSomething(array $data, int $actorUserId, string $actorRoleKey): array
    {
        $this->repo->beginTransaction();
        try {
            // validate business rules
            // mutate through repository
            // audit governed changes
            $this->repo->commit();
            return [];
        } catch (Throwable $e) {
            $this->repo->rollback();
            throw $e;
        }
    }
}
```

Important: do not call `rollback()` manually before throwing inside the `try`; the catch block rolls back once.

### Repository Pattern

Repositories own SQL only and extend `BaseRepository`.

```php
class ExampleRepository extends BaseRepository
{
    public function findById(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM table_name WHERE id = ?', [$id]);
    }

    public function findAll(array $filters, int $page, int $limit): array
    {
        return $this->fetchAll('SELECT ...', []);
    }

    public function create(array $data): int
    {
        $this->execute('INSERT INTO table_name (...) VALUES (...)', []);
        return (int) $this->lastInsertId();
    }
}
```

`BaseRepository` exposes public `beginTransaction()`, `commit()`, and `rollback()` methods. All repositories share the same PDO singleton.

### Response Shape

Use `Response::success($data)` and `Response::error($message, $statusCode)`.

List endpoints return:

```php
Response::success([
    'items' => $items,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
    ],
]);
```

### Audit Logging

For governed mutations:

```php
$this->auditService->log(
    $actorUserId,
    'ACTION_TYPE',
    'entity_type',
    $entityId,
    $oldValues,
    $newValues,
    $reason
);
```

### RBAC

- Module keys live in `config/rbac.php`.
- Routes and capabilities live in `config/route_registry.php`.
- Middleware enforces module-level access.
- Services enforce record-scope rules.
- Capability groups: `VIEW -> read`, `UPLOAD -> read/upload`, `APPROVE -> read/approve`, `MANAGE -> read/upload/approve/manage`.

## Frontend Patterns

The frontend is vanilla JS. Do not migrate to React/Vue unless the project owner explicitly reopens the frontend architecture decision.

### Files

```text
public/index.html
public/css/style.css
public/js/app.js
public/js/core/api.js
public/js/core/auth.js
public/js/core/navigation.js
public/js/core/ui.js
public/js/views/modules.js
```

### View Registration

Each page/module should be registered in `public/js/views/modules.js`:

```js
Views.register('domain.module_key', {
  async render({ params = {} }) {
    const data = await Api.get('route/list', params);
    return UI.page('Title', 'Subtitle')
      + UI.panel('Records', UI.table(columns, normalizeItems(data)));
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('domain.module_key', payload));
  }
});
```

### UI Helpers

Prefer existing helpers:

- `UI.page(title, subtitle, actions)`
- `UI.panel(title, body, actions)`
- `UI.table(columns, rows, options)`
- `UI.filters(fields, label)`
- `UI.field(field)`
- `UI.showModal(title, body)`
- `UI.toast(message, type)`
- `UI.status(value)`

Use `UI.escape()` before injecting user-controlled strings into `innerHTML`.

### Form and Action Helpers

- `openSimpleForm(title, fields, submitLabel, handler)` for simple modal forms.
- `simpleAction(route, payload, successMessage)` for POST mutations.
- `simpleAction()` already closes modal, shows toast, and calls `App.refresh()`.
- `App.navigate(moduleKey, params)` for navigation.

### Navigation and RBAC

- `public/js/core/navigation.js` maps frontend module keys to backend routes.
- Keep `NavMap` aligned with `config/rbac.php` and `config/route_registry.php`.
- Detail modules should be hidden from sidebar and opened through rows/cards.
- Role-specific landing modules should stay hidden from unrelated roles.
- Run `tests/test_frontend_route_map.php` after navigation changes.

### Cache Marker

Current cache version is listed in `10_IMPLEMENTATION_PROGRESS.md`.

If you change frontend JS, bump the relevant `?v=N` in `public/index.html`.

## Codebase Pitfalls

- Use exact schema/API field names. Unknown payload keys may be silently dropped by repositories.
- Lead assignment field is `assigned_lead_user_id`, not `lead_user_id`.
- Labour fields are `labour_count`, `gardener_count`, `night_guard_count`.
- Task statuses are `OPEN`, `RUNNING`, `COMPLETED`, `CANCELLED`, `ARCHIVED`.
- Task `vertical_type` values are `GREEN_BELT`, `ADVERTISEMENT`, `MONITORING`.
- Watering stores only `DONE` and `NOT_REQUIRED`; `PENDING` is derived.
- Site categories are `GREEN_BELT`, `CITY`, `HIGHWAY`.
- Lighting values are `LIT`, `NON_LIT`.
- Issue uploads and `NOT_ELIGIBLE` uploads must never become authority `APPROVED`.
- `task/start` uses module key `task.my_tasks` so Fabrication Lead can start assigned tasks.
- `upload/create` is a shared dynamic route; do not force one static module key onto it.

## Validation Commands

Run only the checks relevant to the task, but always run syntax checks after edits.

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

HTTP integration scripts:

```bash
bash tests/http_integration_test.sh
bash tests/http_integration_mutations.sh
```

Local test credentials commonly used:

- Base URL: `http://localhost/skite/index.php?route=`
- Ops email: `ops.test.phase2@skite.local`
- Password: `TestPass123!`

## Key Doc Quick Reference

| Need | Read |
|---|---|
| Current task and queue | `docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md` |
| RBAC roles, permission groups, landing pages | `docs/11_build_specs/01_RBAC_PERMISSION_GROUP_SPEC.md` |
| Schema/tables/fields | `docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md` |
| API/routes/payloads | `docs/11_build_specs/03_API_AND_ROUTE_CONTRACT.md` |
| Page fields/actions | `docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md` |
| State transitions | `docs/11_build_specs/05_WORKFLOW_STATE_MACHINE_SPEC.md` |
| Upload/storage/retention | `docs/11_build_specs/06_UPLOAD_STORAGE_RETENTION_SPEC.md` |
| Reports/formulas | `docs/11_build_specs/07_REPORTS_ALERTS_AND_FORMULAS.md` |
| Settings/external actions | `docs/11_build_specs/08_SYSTEM_SETTINGS_AND_EXTERNAL_ACTIONS.md` |
| Acceptance criteria | `docs/11_build_specs/09_MODULE_ACCEPTANCE_CHECKLISTS.md` |
| Product intent | `docs/10_recovered_product/00_FINAL_PRODUCT_BEHAVIOR_MODEL.md` |

## Session Start Prompt

```text
Read docs/AI_TOOL_HANDOFF_GUIDE.md and docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md.
Continue only the current next scoped task.
Use locked docs only.
Run relevant validation, update progress, then stop.
```

## Update Rule

Any AI tool that modifies this project must update:

1. `docs/11_build_specs/10_IMPLEMENTATION_PROGRESS.md` when task status, validation, blockers, or next task changes.
2. This file only when stable reusable patterns change.

