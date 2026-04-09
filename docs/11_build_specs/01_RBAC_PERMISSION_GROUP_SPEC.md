# RBAC And Permission Group Spec

## Authority Note

- Purpose: Canonical implementation-spec document.
- Authority Level: Implementation truth.
- If Conflict: This file controls implementation behavior. `docs/10_recovered_product/*` controls product meaning and scope. Repo-facing mirror docs must be updated to match, not treated as competing truth.

## Purpose

This file defines the implementation-grade RBAC model for the recovered product.
It translates transcript-locked role truth into:

- seeded constitutional roles
- controlled future role creation
- predefined permission groups
- module-scope access
- record-scope filtering
- post-login landing behavior

This file should be treated as the RBAC implementation source of truth until code-level policy helpers exist.

## Source Docs

- `docs/10_recovered_product/01_ROLE_AND_ACCESS_MODEL.md`
- `docs/10_recovered_product/04_PAGE_AND_MODULE_MODEL.md`
- `docs/10_recovered_product/07_AUTHORITY_SHARE_AND_SUMMARY_MODEL.md`

## Locked RBAC Rules

- role-based access is mandatory
- least privilege is the default
- dynamic role creation is allowed
- dynamic roles must use predefined permission groups
- arbitrary micro-permission toggles are not allowed
- one role maps to exactly one permission group in v1
- module scope is attached to the role, not directly to the user
- landing after login is role-specific
- record scope must still be enforced even after module access succeeds

## RBAC Data Model

Required tables:

- `roles`
- `permission_groups`
- `role_permission_mappings`
- `role_module_scopes`

Required enforcement shape:

- one active permission-group mapping per role
- one unique module-scope row per `role_id + module_key`
- user access resolves through `user -> role -> permission group + module scope`
- role scope must be checked before page rendering and before controller action execution

## Seeded Constitutional Roles

These roles must exist in seeded form:

- `OPS_MANAGER`
- `HEAD_SUPERVISOR`
- `GREEN_BELT_SUPERVISOR`
- `OUTSOURCED_MAINTAINER`
- `MONITORING_TEAM`
- `FABRICATION_LEAD`
- `SALES_TEAM`
- `CLIENT_SERVICING`
- `MEDIA_PLANNING`
- `AUTHORITY_REPRESENTATIVE`
- `MANAGEMENT`

These are not RBAC roles:

- fabrication workers
- daily labour
- gardener
- night guards
- clients
- external authority body

## Permission Group Definitions

### `VIEW`

Read-first group for consumption surfaces.

Allowed capability family:

- open page
- read records
- filter, sort, search, and group
- open read-only detail
- export allowed data
- use approved share helper actions

Not allowed by default:

- create operational truth
- approve or reject
- change lifecycle state
- edit master data

Allowed v1 exception:

- request-intake modules may allow controlled request submission for designated read-first roles

### `UPLOAD`

Field-entry group for proof creation and same-day operational entries.

Allowed capability family:

- all `VIEW` actions inside allowed modules
- create uploads
- create same-day operational records
- update own execution-side fields where explicitly allowed
- view own recent activity where explicitly allowed

Not allowed:

- governance approval or rejection
- broad master-data edit
- unrestricted reassignment

### `APPROVE`

Review-layer group for governance flows.

Allowed capability family:

- all `VIEW` actions inside allowed modules
- approve or reject eligible records
- govern visibility state within approved modules

Not allowed by default:

- broad master-data management
- global settings changes

### `MANAGE`

Control-layer group for master data and governed operations.

Allowed capability family:

- all `VIEW` actions inside allowed modules
- create or edit master records
- create, assign, and reassign governed work
- change lifecycle state
- edit allowed mappings
- edit approved settings
- perform approval/rejection inside modules that expose such flows

Not allowed:

- silent governance bypass
- record mutation outside allowed module scope

## Module Catalog

Module keys should stay stable because they are used by RBAC, menus, landing logic, and route guards.

### Dashboards

- `dashboard.master_ops`
- `dashboard.green_belt`
- `dashboard.advertisement`
- `dashboard.monitoring`
- `dashboard.management`

### Green Belt Domain

- `green_belt.master`
- `green_belt.detail`
- `green_belt.supervisor_upload`
- `green_belt.my_uploads`
- `green_belt.outsourced_upload`
- `green_belt.watering_oversight`
- `green_belt.maintenance_cycles`
- `green_belt.supervisor_attendance`
- `green_belt.labour_entries`
- `green_belt.upload_review`
- `green_belt.issue_management`
- `green_belt.authority_view`

### Advertisement And Monitoring Domain

- `advertisement.site_master`
- `advertisement.campaign_management`
- `monitoring.upload`
- `monitoring.plan`
- `monitoring.history`
- `media.free_media_inventory`

### Task And Request Domain

- `task.request_intake`
- `task.progress_read`
- `task.management`
- `task.detail`
- `task.my_tasks`
- `task.worker_allocation`

### Governance Domain

- `governance.user_management`
- `governance.access_mappings`
- `governance.audit_logs`
- `governance.rejected_upload_cleanup`

### Reporting And Settings

- `reports.monthly`
- `settings.system`

## Recommended Route Targets

These are the logical route targets used for landing and navigation guards.
Final URL names can differ, but the target meaning must stay the same.

| Role | Landing Module |
|---|---|
| `OPS_MANAGER` | `dashboard.master_ops` |
| `HEAD_SUPERVISOR` | `green_belt.watering_oversight` |
| `GREEN_BELT_SUPERVISOR` | `green_belt.supervisor_upload` |
| `OUTSOURCED_MAINTAINER` | `green_belt.outsourced_upload` |
| `MONITORING_TEAM` | `monitoring.upload` |
| `FABRICATION_LEAD` | `task.my_tasks` |
| `SALES_TEAM` | `task.progress_read` |
| `CLIENT_SERVICING` | `task.progress_read` |
| `MEDIA_PLANNING` | `task.progress_read` |
| `AUTHORITY_REPRESENTATIVE` | `green_belt.authority_view` |
| `MANAGEMENT` | `dashboard.management` |

## Seeded Role Mapping

Recommended v1 mapping:

| Role | Permission Group | Allowed Modules |
|---|---|---|
| `OPS_MANAGER` | `MANAGE` | all modules |
| `HEAD_SUPERVISOR` | `MANAGE` | `dashboard.green_belt`, `green_belt.detail`, `green_belt.watering_oversight`, `green_belt.maintenance_cycles`, `green_belt.supervisor_attendance`, `green_belt.labour_entries`, `green_belt.issue_management` |
| `GREEN_BELT_SUPERVISOR` | `UPLOAD` | `green_belt.supervisor_upload`, `green_belt.my_uploads` |
| `OUTSOURCED_MAINTAINER` | `UPLOAD` | `green_belt.outsourced_upload` |
| `MONITORING_TEAM` | `UPLOAD` | `monitoring.upload`, `monitoring.history` |
| `FABRICATION_LEAD` | `UPLOAD` | `task.my_tasks`, `task.detail`, `task.worker_allocation` |
| `SALES_TEAM` | `VIEW` | `task.progress_read`, `task.request_intake`, `monitoring.history`, `media.free_media_inventory` |
| `CLIENT_SERVICING` | `VIEW` | `task.progress_read`, `task.request_intake`, `monitoring.history`, `media.free_media_inventory` |
| `MEDIA_PLANNING` | `VIEW` | `task.progress_read`, `task.request_intake`, `media.free_media_inventory`, `monitoring.history` |
| `AUTHORITY_REPRESENTATIVE` | `VIEW` | `green_belt.authority_view` |
| `MANAGEMENT` | `VIEW` | `dashboard.advertisement`, `dashboard.monitoring`, `dashboard.management`, `reports.monthly` |

## Record Scope Rules

Module access alone is not enough. Record scope must also pass.

### `OPS_MANAGER`

- unrestricted record scope

### `HEAD_SUPERVISOR`

- maintained green belts only
- no outsourced-only internal compliance actions
- no advertisement/site master mutation

### `GREEN_BELT_SUPERVISOR`

- only belts assigned through active supervisor assignment
- only self-created uploads in My Uploads
- no review-state visibility

### `OUTSOURCED_MAINTAINER`

- only outsourced belts assigned through active outsourced-belt assignment
- no maintained-belt access

### `MONITORING_TEAM`

- only site-driven upload context
- no site master editing

### `FABRICATION_LEAD`

- only tasks assigned to that lead
- worker allocation only for that lead's tasks

### `SALES_TEAM`

- only task progress rows linked to the user's own requests, clients, or campaigns under the final linking rule adopted later in task queries
- read-only

### `CLIENT_SERVICING`

- only task progress rows linked to the user's own requests, clients, or campaigns under the final linking rule adopted later in task queries
- read-only

### `MEDIA_PLANNING`

- only task progress rows linked to the user's own planning requests or visible planning context
- read-only

### `AUTHORITY_REPRESENTATIVE`

- only approved authority-visible uploads for belts assigned through active authority assignment
- no issue uploads
- no hidden or rejected proof

### `MANAGEMENT`

- read-only global scope

## Menu Generation Rule

- top navigation and side navigation must be generated from allowed module scope
- hidden modules must not appear in navigation
- direct route access must still be checked server-side even if the menu hides the link

## Dynamic Role Creation Rules

Dynamic role creation must follow this flow:

1. Ops creates a role name and description.
2. Ops selects exactly one permission group.
3. Ops selects allowed modules from the approved module catalog.
4. Ops chooses one landing module from the role's own allowed modules.
5. Ops saves the role.

Dynamic roles must not support:

- free-form per-action toggles
- direct controller or route grants
- direct SQL-driven grants
- landing pages outside the approved module catalog
- hidden flags that are not represented in module scope

## Example Dynamic Role

Example: `LIGHTMAN`

- permission group: `UPLOAD`
- allowed modules: `task.my_tasks`, `task.detail`
- landing module: `task.my_tasks`

This role would still not automatically gain:

- task creation
- reassignment
- site master access
- approval powers

## Enforcement Order

Every protected route should pass these checks in order:

1. authenticated user exists
2. user is active
3. role exists and is active
4. requested module is in allowed module scope
5. record-level scope passes
6. action is compatible with permission group

If any check fails:

- return access denied for web routes
- return `403` for API routes
- do not partially render sensitive data

## UI Behavior Rules

- supervisors must not see upload approval or rejection outcome
- authority representatives may use the WhatsApp share helper only from approved authority views
- read-only progress roles must not see task-execution buttons
- management may see dashboards and reports but no mutation controls

## Implementation Notes

- middleware should resolve module access before controller execution
- services should enforce business rules, not role lists
- controllers may translate current route into `module_key + action`
- module scope should drive menus, redirects, and route protection
- record-scope filters should live in repositories or query helpers so list and detail pages stay consistent
