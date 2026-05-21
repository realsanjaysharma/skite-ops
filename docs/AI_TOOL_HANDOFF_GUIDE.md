# AI Tool Handoff Guide - Skite Ops

## Purpose

Evergreen technical reference for any AI agent working on Skite Ops.
Contains stable backend/frontend patterns, codebase pitfalls, and validation commands.
Does **not** track project status — that lives in `docs/AGENT_START.md` and `docs/PRODUCT_BACKLOG.md`.

## How To Start Any Session

1. Read `docs/AGENT_START.md` — current state, focus, what NOT to touch.
2. Read `docs/PRODUCT_BACKLOG.md` — planned work and page status.
3. Read `.claude/CLAUDE.md` — governance rules.
4. Read this file (Codebase Pitfalls section especially) before writing any code.

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

1. `docs/06_schema/schema_v1_full.sql` — final authority on database structure
2. `.claude/CLAUDE.md` — final authority on architecture and governance
3. `docs/PRODUCT_BACKLOG.md` — final authority on current product feature state
4. `docs/PRODUCT_LOG.md` — final authority on why decisions were made
5. `docs/10_recovered_product/*` — original product intent (reference only)
6. `docs/11_build_specs/*` — ARCHIVED pre-build specs (historical reference only)

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

## Codebase Pitfalls and Safety Rules

This section is the single place for all recurring traps, enum facts, field name rules, RBAC edges, and security/safety constraints. Add here when any of these are discovered — do not scatter them across the progress file.

- **Never write `\`` or `\${` in JavaScript source files.** Escaped backticks are only valid inside an existing template literal. At the statement level they are an Illegal token (SyntaxError) and will silently prevent the entire script from executing — `Views`, `App`, and all other globals will appear undefined to subsequent scripts. Write plain `` ` `` and `${`. (Introduced in commit 5c8163b, fixed in 54eff19/beb093c — 44 instances across modules.js.)
- **Always verify closing bracket counts after writing nested addEventListener + forEach + openSimpleForm patterns.** A missing `});` at the wrong indentation level shifts all subsequent closers and produces a "missing ) after argument list" error at the wrong line.
- **When writing files with PowerShell `WriteAllText`, use `[System.Text.UTF8Encoding]::new($false)` (no BOM) or `[System.IO.File]::WriteAllBytes`.** The default `UTF8` encoding adds a BOM that some parsers reject.
- **`afterRender` must destructure `{ params = {} }` if it references `params`.** `app.js` calls `afterRender({ moduleKey, config, params })`. Writing `async afterRender()` with no arguments means `params` is out of scope — causes ReferenceError. Always write `async afterRender({ params = {} })` when the function needs filter values. (Found in upload_review and supervisor_attendance, fixed in 15a9a3e.)
- Use exact schema/API field names. Unknown payload keys may be silently dropped by repositories.
- Lead assignment field is `assigned_lead_user_id`, not `lead_user_id`.
- Labour fields are `labour_count`, `gardener_count`, `night_guard_count`.
- Task statuses are `OPEN`, `RUNNING`, `COMPLETED`, `CANCELLED`, `ARCHIVED`.
- Task `vertical_type` values are `GREEN_BELT`, `ADVERTISEMENT`, `MONITORING`.
- Watering stores only `DONE` and `NOT_REQUIRED`; `PENDING` is derived.
- Watering records use column `watering_date`, not `date`.
- Site categories are `GREEN_BELT`, `CITY`, `HIGHWAY`.
- Lighting values are `LIT`, `NON_LIT`.
- Issue uploads and `NOT_ELIGIBLE` uploads must never become authority `APPROVED`.
- Upload review bulk selection must disable rows that are already approved, rejected, issue-type, or `NOT_ELIGIBLE`. Backend also rejects these before any visibility update.
- Always escape user-controlled strings with `UI.escape()` before injecting into `innerHTML` in upload or comment modals.
- `tests/test_upload_review_safety.php` verifies that mixed ISSUE + WORK batch approval is blocked at the backend.
- `task/start` uses module key `task.my_tasks` so Fabrication Lead can start assigned tasks.
- `upload/create` is a shared dynamic route; do not force one static module key onto it.
- `UI.cards` supports `clickable` class and `data-nav` via the `attr` property in the item object.
- `belt/list` now includes `open_issue_count` and `active_cycle_id` in the `items` payload for dashboard attention lists.
- Module filters should use `loadSupervisors()` select dropdown instead of `supervisor_user_id` number input where possible.
- All high-volume list panels should include `renderPagination(data.pagination, moduleKey, params)` and be wired with `attachPagination()` in `afterRender`.
- **AUTHORITY_REPRESENTATIVE landing page renders "Forbidden" in the content panel on every page load.** The `authority/view` API endpoint works correctly and returns the right data, but the SPA's initial render triggers a call that returns 403. Do not mistake this for an RBAC misconfiguration — the data layer is correct. This is a frontend rendering bug. (Confirmed in T01 and T22.)
- **Watering correction (changing a DONE/NOT_REQUIRED record) is restricted to OPS_MANAGER only.** HEAD_SUPERVISOR gets HTTP 403 "Only Ops can correct watering status once it is marked." even when supplying an `override_reason`. The test plan says HEAD_SUPERVISOR should see "Correction requires an override reason" — that message only fires for OPS when `override_reason` is missing. (Found in T14.)
- **Issues do not have an IS-XXXXX sequence ID field.** The test plan references `IS-XXXXX` format but no such field exists in the `issues` table, the `issue/create` response, `issue/list`, or anywhere in the frontend/backend codebase. Issues are identified by plain integer `id` only. (Found in T25.)
- **Belt detail panel: SUPERVISOR/AUTHORITY name columns render blank; END DATE column shows `[object Object]` for null values.** The assignment panels in the Green Belt detail view fail to render the user's name and format null end dates. The underlying API data is correct (name is in `assigned_user_name`, end_date is null). This is a JS rendering bug in the detail view. (Found in T06, T07.)
- **Supervisor Upload belt selection uses a free-text `type=number` input, not a dropdown restricted to assigned belts.** The backend correctly returns HTTP 403 for unassigned belts, but there is no frontend restriction preventing a supervisor from typing any belt ID. (Found in T12.)
- **Watering list API uses `watering_status` (not `status`) and `record_id` (not `id`) as field names** in the oversight response. Filter by `date` param. Items without a stored record appear with `record_id: null` and `watering_status: "PENDING"` — these are derived rows, not DB rows.
- **Issue lifecycle RBAC facts:** Only OPS_MANAGER can close issues (`issue/close`). HEAD_SUPERVISOR can move to IN_PROGRESS (`issue/in-progress`) but cannot close. Closing an issue does NOT auto-close any linked task (`tasks.linked_issue_id`). (Confirmed T17, T26.)
- **The `authority_assignments` table is named `belt_authority_assignments`** (not `authority_assignments`). Similarly supervisor assignments live in `belt_supervisor_assignments` and outsourced in `belt_outsourced_assignments`. (Found when debugging T22.)
- **Supervisor Upload parent_type must be `GREEN_BELT`** (not `BELT`) when calling `upload/create` as GREEN_BELT_SUPERVISOR. The SUPERVISOR surface config requires exactly `GREEN_BELT` as parent_type or it throws "Invalid parent_type for this upload surface." The file field name is `files[]` (not `photos[]`). (Found in T09.)
- **The `issues` table has no `is_deleted` column** — issues use status transitions (OPEN → IN_PROGRESS → CLOSED) rather than soft delete. Do not add `is_deleted` filters to issue queries. (Found in T25 pre-flight.)
- **`task_requests` and `issues` expose derived readable IDs.** Use `request_code` (`RQ-00001`) and `issue_code` (`IS-00001`) from API responses for UI/test display; the database still uses integer `id` as the primary key. (Fixed after T25, T27.)
- **Task request status should become `CONVERTED` when a task is created from it.** A request is `APPROVED` after `request/approve`, then `task/create` with `request_id` moves it to `CONVERTED`. (Verified after T28.)
- **TASK completion proof uses `upload_type=WORK` plus `photo_label=AFTER_WORK`.** Do not send `upload_type=AFTER_WORK`; the upload type remains canonical `WORK`, and `photo_label` carries BEFORE/AFTER labeling. (Verified after T29.)
- **`task_requests` context field is required:** `request/create` requires at least one of `campaign_id`, `site_id`, or `belt_id`. Omitting all three returns 400 "At least one operational context field must be provided." (Found in T27.)

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
| Current state + what to work on | `docs/AGENT_START.md` |
| Planned / done / deferred features + page status | `docs/PRODUCT_BACKLOG.md` |
| Why decisions were made | `docs/PRODUCT_LOG.md` |
| Governance + architecture rules | `.claude/CLAUDE.md` |
| Schema — exact column names, types, ENUMs | `docs/06_schema/schema_v1_full.sql` |
| Product intent and role definitions | `docs/10_recovered_product/01_ROLE_AND_ACCESS_MODEL.md` |
| Original build specs (historical, archived) | `docs/11_build_specs/` |
| QA test history | `tests/TEST_RESULTS.md` (read-only) |

## Session Start Prompt

Copy-paste to start any agent session:

```text
Read docs/AGENT_START.md first, then docs/PRODUCT_BACKLOG.md, then .claude/CLAUDE.md.
Do not touch any code until you have read all three.
After reading, tell me what the current focus is and what you should NOT touch —
confirm you understand before we proceed.
```

## Update Rule

Any agent that works on this project must update **at the end of every session**:

1. `docs/AGENT_START.md` — last completed, current focus, known open issues
2. `docs/PRODUCT_BACKLOG.md` — Improvement Sequence table, role header count, page row status
3. `docs/PRODUCT_LOG.md` — append key decisions made this session

Update **this file** when stable reusable knowledge changes:
- Add to **Codebase Pitfalls and Safety Rules** when any repeatable trap is discovered:
  field name mismatch, wrong enum value, RBAC gap, silent failure, missing validation,
  XSS risk, approval bypass, CSS specificity trap, browser compatibility issue, etc.
- Add to **Frontend Patterns** or **Backend Patterns** when a new reusable approach is established.
- Do **not** add task notes, test results, or completion history here — those belong
  in PRODUCT_BACKLOG.md and PRODUCT_LOG.md.

