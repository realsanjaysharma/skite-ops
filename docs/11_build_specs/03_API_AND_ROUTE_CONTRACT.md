# API And Route Contract

## Authority Note

- Purpose: Canonical implementation-spec document.
- Authority Level: Implementation truth.
- If Conflict: This file controls implementation behavior. `docs/10_recovered_product/*` controls product meaning and scope. Repo-facing mirror docs must be updated to match, not treated as competing truth.

## Purpose

This file defines the implementation-level HTTP contract for the recovered product.
It freezes:

- route naming
- method usage
- request payload patterns
- response shapes
- session and CSRF behavior
- role-based landing behavior

This contract is intentionally aligned with the current repo entry-point style in [index.php](C:/xampp/htdocs/skite/index.php), where routes are resolved through:

```text
index.php?route=<module>/<action>
```

## Source Inputs

- [index.php](C:/xampp/htdocs/skite/index.php)
- [AuthController.php](C:/xampp/htdocs/skite/app/controllers/AuthController.php)
- [UserController.php](C:/xampp/htdocs/skite/app/controllers/UserController.php)
- [AuthMiddleware.php](C:/xampp/htdocs/skite/app/middleware/AuthMiddleware.php)
- [Response.php](C:/xampp/htdocs/skite/app/helpers/Response.php)
- [Csrf.php](C:/xampp/htdocs/skite/app/helpers/Csrf.php)
- [constants.php](C:/xampp/htdocs/skite/config/constants.php)
- [01_RBAC_PERMISSION_GROUP_SPEC.md](C:/xampp/htdocs/skite/docs/11_build_specs/01_RBAC_PERMISSION_GROUP_SPEC.md)
- [04_PAGE_FIELD_AND_ACTION_SPEC.md](C:/xampp/htdocs/skite/docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md)
- [05_WORKFLOW_STATE_MACHINE_SPEC.md](C:/xampp/htdocs/skite/docs/11_build_specs/05_WORKFLOW_STATE_MACHINE_SPEC.md)

## Core Contract Rules

- routes use exactly two lowercase path parts: `module/action`
- reads use `GET`
- create, update, lifecycle, approval, and delete-like actions use `POST`
- JSON API remains the default contract style
- file uploads use `multipart/form-data`
- middleware enforces session and route authorization before controllers
- controllers validate HTTP shape only
- services enforce business rules
- route behavior must follow role-based landing requirements

## Request Transport Rules

### Read Routes

- method: `GET`
- inputs come from query params
- recommended shared list params:
  - `page`
  - `limit`
  - `sort`
  - `direction`

### Mutating Routes

- method: `POST`
- default payload type: `application/json`
- fallback support for form posts is allowed where current controllers already support it
- all authenticated mutating routes except `auth/login` and `auth/logout` require `X-CSRF-Token`

### Upload Routes

- method: `POST`
- payload type: `multipart/form-data`
- contextual scalar fields travel alongside `files[]`

## Standard Response Envelope

The repo already uses this envelope through [Response.php](C:/xampp/htdocs/skite/app/helpers/Response.php).
This contract keeps that shape.

### Success

```json
{
  "success": true,
  "data": {}
}
```

### Error

```json
{
  "success": false,
  "error": "Message"
}
```

## Status Code Guidance

- `200` success for reads and successful mutations
- `400` validation or business-rule failure
- `401` no valid authenticated session
- `403` forbidden, invalid CSRF, or force-reset restriction
- `404` record not found where route exists but target does not
- `405` wrong HTTP method

## List Response Shape

List endpoints should still use the standard success envelope.
Inside `data`, use:

```json
{
  "items": [],
  "pagination": {
    "page": 1,
    "limit": 50,
    "total": 0
  }
}
```

If pagination is not yet implemented, `data.items` may be returned first and `pagination` can be added in the same envelope later without breaking the outer contract.

## Auth And Session Contract

### Session Fields

After successful login, session should hold at least:

- `user_id`
- `role_id`
- `logged_in`
- `force_password_reset`
- `csrf_token`

### `auth/login`

- method: `POST`
- auth: public
- request fields:
  - `email`
  - `password`
- response fields:
  - `user`
  - `requires_password_reset`
  - `csrf_token`
  - `landing_module_key`
  - `landing_route`
- errors:
  - invalid credentials
  - locked account
  - inactive or deleted user

### `auth/logout`

- method: `POST`
- auth: authenticated
- request fields: none
- response fields: `null`
- side effects:
  - clear session
  - destroy cookie-backed session

### `auth/reset-password`

- method: `POST`
- auth: authenticated
- special rule:
  - must remain available during forced reset flow
- request fields:
  - `password`
- response fields: `null`
- side effects:
  - clears `force_password_reset`

### `auth/session`

- method: `GET`
- auth: authenticated
- purpose:
  - restore frontend bootstrap state
  - renew role landing context
  - return fresh CSRF token if needed
- response fields:
  - `user`
  - `requires_password_reset`
  - `csrf_token`
  - `landing_module_key`
  - `landing_route`

## Post-Login Landing Contract

Frontend navigation should be driven by the login or session response.

Required landing mapping:

| Role | `landing_module_key` | `landing_route` |
|---|---|---|
| `OPS_MANAGER` | `dashboard.master_ops` | `dashboard/master` |
| `HEAD_SUPERVISOR` | `green_belt.watering_oversight` | `oversight/watering` |
| `GREEN_BELT_SUPERVISOR` | `green_belt.supervisor_upload` | `upload/supervisor` |
| `OUTSOURCED_MAINTAINER` | `green_belt.outsourced_upload` | `upload/outsourced` |
| `MONITORING_TEAM` | `monitoring.upload` | `monitoring/upload` |
| `FABRICATION_LEAD` | `task.my_tasks` | `task/my` |
| `SALES_TEAM` | `task.progress_read` | `taskprogress/list` |
| `CLIENT_SERVICING` | `task.progress_read` | `taskprogress/list` |
| `MEDIA_PLANNING` | `task.progress_read` | `taskprogress/list` |
| `AUTHORITY_REPRESENTATIVE` | `green_belt.authority_view` | `authority/view` |
| `MANAGEMENT` | `dashboard.management` | `dashboard/management` |

Interpretation note:

- `landing_route` is the UI shell or page target after login
- the data contracts needed by that page are defined separately below
- a landing route string does not require every page to duplicate its own JSON contract section if that page reads through existing list or detail endpoints

If `requires_password_reset = true`, frontend must force password reset flow before navigating to the landing route.

## Dashboard And Oversight Routes

### `dashboard/master`

- method: `GET`
- auth: Ops
- response fields:
  - summary cards required by the Master Operations Dashboard page spec

### `dashboard/green-belt`

- method: `GET`
- auth: Ops, Head Supervisor
- query params:
  - `zone`
  - `supervisor_user_id`
  - `maintenance_mode`

### `dashboard/advertisement`

- method: `GET`
- auth: Ops, Management
- response fields:
  - summary cards required by the Advertisement Dashboard page spec

### `dashboard/monitoring`

- method: `GET`
- auth: Ops, Management
- response fields:
  - summary cards required by the Monitoring Dashboard page spec

### `dashboard/management`

- method: `GET`
- auth: Management
- response fields:
  - read-only cross-domain summary cards required by the Management Dashboard page spec

### `oversight/watering`

- method: `GET`
- auth: Head Supervisor, Ops
- query params:
  - `zone`
  - `supervisor_user_id`
  - `maintenance_mode`
- response fields:
  - attendance grid data
  - watering grid data
  - labour panel data
  - quick exceptions

## Governance And User Routes

### `user/list`

- method: `GET`
- auth: Ops
- query params:
  - `page`
  - `limit`
  - `is_active`
  - `role_id`
- response item shape:
  - `id`
  - `full_name`
  - `email`
  - `role_id`
  - `role_name`
  - `is_active`
  - `force_password_reset`

### `user/get`

- method: `GET`
- auth: Ops
- query params:
  - `user_id`
- response fields:
  - user object with role context

### `user/create`

- method: `POST`
- auth: Ops
- request fields:
  - `full_name`
  - `email`
  - `password`
  - `role_id`
- response fields:
  - created user object

### `user/update`

- method: `POST`
- auth: Ops
- request fields:
  - `user_id`
  - `full_name`
  - `email`
  - `role_id`
- response fields:
  - updated user object

### `user/activate`

- method: `POST`
- auth: Ops
- request fields:
  - `user_id`

### `user/deactivate`

- method: `POST`
- auth: Ops
- request fields:
  - `user_id`

### `user/restore`

- method: `POST`
- auth: Ops
- request fields:
  - `user_id`

### `role/list`

- method: `GET`
- auth: Ops
- response item shape:
  - `id`
  - `role_key`
  - `role_name`
  - `is_system_role`
  - `is_active`
  - `permission_group_key`
  - `landing_module_key`

### `role/get`

- method: `GET`
- auth: Ops
- query params:
  - `role_id`
- response fields:
  - role object
  - allowed module keys
  - permission group

### `role/create`

- method: `POST`
- auth: Ops
- request fields:
  - `role_name`
  - `role_key`
  - `description`
  - `permission_group_id`
  - `landing_module_key`
  - `module_keys[]`
- validation rules:
  - exactly one permission group
  - landing module must be included in selected module keys

### `role/update`

- method: `POST`
- auth: Ops
- request fields:
  - `role_id`
  - `role_name`
  - `description`
  - `permission_group_id`
  - `landing_module_key`
  - `module_keys[]`

## Green Belt Domain Routes

### `belt/list`

- method: `GET`
- auth: Ops, Head Supervisor scoped read if later exposed
- query params:
  - `zone`
  - `permission_status`
  - `maintenance_mode`
  - `hidden`
  - `supervisor_user_id`
- response item shape:
  - `id`
  - `belt_code`
  - `common_name`
  - `authority_name`
  - `zone`
  - `permission_status`
  - `maintenance_mode`
  - `is_hidden`

### `belt/get`

- method: `GET`
- auth: Ops, Head Supervisor scoped read
- query params:
  - `belt_id`
- response fields:
  - belt object
  - assignment history
  - recent cycle summary
  - recent watering summary

### `belt/create`

- method: `POST`
- auth: Ops
- request fields:
  - `belt_code`
  - `common_name`
  - `authority_name`
  - `zone`
  - `location_text`
  - `latitude`
  - `longitude`
  - `permission_start_date`
  - `permission_end_date`
  - `permission_status`
  - `maintenance_mode`
  - `watering_frequency`
  - `is_hidden`

### `belt/update`

- method: `POST`
- auth: Ops
- request fields:
  - `belt_id`
  - editable fields from create payload

### `supervisorassignment/list`

- method: `GET`
- auth: Ops
- query params:
  - `belt_id`
  - `supervisor_user_id`

### `supervisorassignment/create`

- method: `POST`
- auth: Ops
- request fields:
  - `belt_id`
  - `supervisor_user_id`
  - `start_date`
  - `end_date`

### `supervisorassignment/close`

- method: `POST`
- auth: Ops
- request fields:
  - `assignment_id`
  - `end_date`

### `authorityassignment/list`

- method: `GET`
- auth: Ops
- query params:
  - `belt_id`
  - `authority_user_id`

### `authorityassignment/create`

- method: `POST`
- auth: Ops
- request fields:
  - `belt_id`
  - `authority_user_id`
  - `start_date`
  - `end_date`

### `authorityassignment/close`

- method: `POST`
- auth: Ops
- request fields:
  - `assignment_id`
  - `end_date`

### `outsourcedassignment/list`

- method: `GET`
- auth: Ops
- query params:
  - `belt_id`
  - `outsourced_user_id`
  - `active_only`

### `outsourcedassignment/create`

- method: `POST`
- auth: Ops
- request fields:
  - `belt_id`
  - `outsourced_user_id`
  - `start_date`
  - `end_date`

### `outsourcedassignment/close`

- method: `POST`
- auth: Ops
- request fields:
  - `assignment_id`
  - `end_date`

### `watering/list`

- method: `GET`
- auth: Ops, Head Supervisor, Green Belt Supervisor within scope
- query params:
  - `date`
  - `belt_id`
  - `supervisor_user_id`

### `watering/mark`

- method: `POST`
- auth: Green Belt Supervisor, Head Supervisor, Ops
- request fields:
  - `belt_id`
  - `watering_date`
  - `status`
  - `reason_text`
- validation rules:
  - supervisors and Head Supervisor same-day only
  - Ops override requires reason when outside normal allowed flow

### `attendance/list`

- method: `GET`
- auth: Ops, Head Supervisor
- query params:
  - `date`
  - `supervisor_user_id`

### `attendance/mark`

- method: `POST`
- auth: Head Supervisor, Ops
- request fields:
  - `supervisor_user_id`
  - `attendance_date`
  - `status`
  - `override_reason`

### `labour/list`

- method: `GET`
- auth: Ops, Head Supervisor
- query params:
  - `date`
  - `belt_id`

### `labour/mark`

- method: `POST`
- auth: Head Supervisor, Ops
- request fields:
  - `belt_id`
  - `entry_date`
  - `labour_count`
  - `gardener_count`
  - `night_guard_count`
  - `override_reason`

### `cycle/list`

- method: `GET`
- auth: Ops, Head Supervisor
- query params:
  - `belt_id`
  - `status`

### `cycle/start`

- method: `POST`
- auth: Head Supervisor, Ops
- request fields:
  - `belt_id`
  - `start_date`

### `cycle/close`

- method: `POST`
- auth: Head Supervisor, Ops
- request fields:
  - `cycle_id`
  - `end_date`
  - `close_reason`

## Upload And Review Routes

### `upload/create`

- method: `POST`
- auth: allowed field roles only
- content type: `multipart/form-data`
- request fields:
  - `parent_type`
  - `parent_id`
  - `upload_type`
  - `work_type`
  - `photo_label`
  - `comment_text`
  - `discovery_mode`
  - `files[]`
  - `gps_latitude`
  - `gps_longitude`
- response fields:
  - `created_uploads[]`
  - each item contains:
    - `id`
    - `parent_type`
    - `parent_id`
    - `upload_type`
    - `work_type`
    - `is_discovery_mode`
    - `photo_label`
    - `authority_visibility`
    - `created_at`
- service note:
  - when `parent_type = SITE` and `discovery_mode = true`, upload creation must also create or refresh the site's `free_media_records` row in `DISCOVERED` state with `source_type = MONITORING_DISCOVERY`

### `upload/list`

- method: `GET`
- auth: scoped by role
- query params:
  - `parent_type`
  - `parent_id`
  - `date_from`
  - `date_to`
  - `upload_type`
  - `discovery_mode`
  - `authority_visibility`

### `upload/my-list`

- method: `GET`
- auth: upload-capable field role
- query params:
  - `date_from`
  - `date_to`

### `upload/delete`

- method: `POST`
- auth: upload creator in self-delete window only
- request fields:
  - `upload_id`

### `upload/review`

- method: `POST`
- auth: Ops
- request fields:
  - `upload_ids[]`
  - `decision`
- validation rules:
  - `decision` in `APPROVED`, `REJECTED`, `HIDDEN`
  - `NOT_ELIGIBLE` is system-derived, not manual review action

### `upload/cleanup-list`

- method: `GET`
- auth: Ops
- query params:
  - `date_from`
  - `date_to`
  - `belt_id`
  - `supervisor_user_id`

### `upload/purge`

- method: `POST`
- auth: Ops
- request fields:
  - `upload_ids[]`
- validation rules:
  - only rejected uploads older than cleanup threshold are eligible

## Issue, Request, Task, And Worker Routes

### `issue/list`

- method: `GET`
- auth: Ops, Head Supervisor scoped read
- query params:
  - `status`
  - `priority`
  - `belt_id`
  - `site_id`

### `issue/get`

- method: `GET`
- auth: Ops, Head Supervisor scoped read
- query params:
  - `issue_id`

### `issue/create`

- method: `POST`
- auth: Ops only for direct issue creation
- request fields:
  - `source_type`
  - `source_reference_id`
  - `belt_id`
  - `site_id`
  - `title`
  - `description`
  - `priority`

### `issue/in-progress`

- method: `POST`
- auth: Ops, Head Supervisor within green-belt scope
- request fields:
  - `issue_id`

### `issue/close`

- method: `POST`
- auth: Ops
- request fields:
  - `issue_id`

### `issue/link-task`

- method: `POST`
- auth: Ops
- request fields:
  - `issue_id`
  - `task_id`

### `request/list`

- method: `GET`
- auth: Ops, requester scoped read if later exposed
- query params:
  - `status`
  - `requester_user_id`
  - `client_name`

### `request/get`

- method: `GET`
- auth: Ops, requester scoped read
- query params:
  - `request_id`

### `request/create`

- method: `POST`
- auth: Sales, Client Servicing, Media Planning
- request fields:
  - `request_type`
  - `client_name`
  - `campaign_id`
  - `site_id`
  - `belt_id`
  - `description`

### `request/approve`

- method: `POST`
- auth: Ops
- request fields:
  - `request_id`

### `request/reject`

- method: `POST`
- auth: Ops
- request fields:
  - `request_id`
  - `rejection_reason`

### `task/list`

- method: `GET`
- auth: scoped by role
- query params:
  - `status`
  - `priority`
  - `vertical_type`
  - `assigned_lead_user_id`
  - `client_name`
  - `campaign_id`

### `taskprogress/list`

- method: `GET`
- auth: Sales, Client Servicing, Media Planning
- query params:
  - `status`
  - `client_name`
  - `campaign_id`
  - `site_id`
  - `date_from`
  - `date_to`

### `taskprogress/get`

- method: `GET`
- auth: Sales, Client Servicing, Media Planning
- query params:
  - `task_id`

### `task/get`

- method: `GET`
- auth: scoped by role
- query params:
  - `task_id`

### `task/create`

- method: `POST`
- auth: Ops
- request fields:
  - `request_id`
  - `linked_issue_id`
  - `task_source_type`
  - `assigned_lead_user_id`
  - `task_category`
  - `vertical_type`
  - `work_description`
  - `location_text`
  - `priority`
  - `start_date`
  - `expected_close_date`

### `task/update`

- method: `POST`
- auth: Ops
- request fields:
  - `task_id`
  - editable task fields

### `task/progress`

- method: `POST`
- auth: assigned Fabrication Lead
- request fields:
  - `task_id`
  - `progress_percent`
  - `remark_1`
  - `remark_2`
  - `completion_note`

### `task/work-done`

- method: `POST`
- auth: assigned Fabrication Lead
- request fields:
  - `task_id`
  - `progress_percent`
  - `completion_note`
- validation rules:
  - required `AFTER_WORK` proof must already exist or be included in the same completion flow

### `task/archive`

- method: `POST`
- auth: Ops
- request fields:
  - `task_id`

### `taskworker/assign`

- method: `POST`
- auth: assigned Fabrication Lead, Ops
- request fields:
  - `task_id`
  - `worker_ids[]`
  - `assignment_role`

### `taskworker/release`

- method: `POST`
- auth: assigned Fabrication Lead, Ops
- request fields:
  - `assignment_id`
  - `release_date`

### `workday/list`

- method: `GET`
- auth: Ops, Fabrication Lead scoped read
- query params:
  - `worker_id`
  - `entry_date`
  - `activity_type`

### `workday/mark`

- method: `POST`
- auth: Ops, Fabrication Lead where allowed
- request fields:
  - `worker_id`
  - `entry_date`
  - `attendance_status`
  - `activity_type`
  - `task_id`
  - `site_id`
  - `work_plan`
  - `work_update`

## Site, Monitoring, Campaign, And Free Media Routes

### `site/list`

- method: `GET`
- auth: Ops, scoped read roles where applicable
- query params:
  - `site_category`
  - `lighting_type`
  - `route_or_group`
  - `is_active`

### `site/get`

- method: `GET`
- auth: Ops, scoped read roles where applicable
- query params:
  - `site_id`

### `site/create`

- method: `POST`
- auth: Ops
- request fields:
  - `site_code`
  - `location_text`
  - `site_category`
  - `green_belt_id`
  - `route_or_group`
  - `ownership_name`
  - `board_type`
  - `lighting_type`
  - `latitude`
  - `longitude`
  - `is_active`

### `site/update`

- method: `POST`
- auth: Ops
- request fields:
  - `site_id`
  - editable site fields

### `monitoringplan/list`

- method: `GET`
- auth: Ops
- query params:
  - `month`
  - `site_category`
  - `lighting_type`
  - `route_or_group`

### `monitoringplan/save`

- method: `POST`
- auth: Ops
- request fields:
  - `site_id`
  - `plan_month`
  - `due_dates[]`

### `monitoringplan/copy-next-month`

- method: `POST`
- auth: Ops
- request fields:
  - `site_id`
  - `source_month`
  - `target_month`

### `monitoringplan/bulk-copy`

- method: `POST`
- auth: Ops
- request fields:
  - `site_ids[]`
  - `route_or_group`
  - `source_month`
  - `target_month`
  - `replace_existing`

### `monitoring/history`

- method: `GET`
- auth: Ops, Monitoring Team, scoped read roles where exposed later
- query params:
  - `date_from`
  - `date_to`
  - `site_id`
  - `site_category`
  - `client_name`
  - `campaign_id`
  - `discovery_mode`

### `campaign/list`

- method: `GET`
- auth: Ops
- query params:
  - `status`
  - `client_name`
  - `site_category`

### `campaign/get`

- method: `GET`
- auth: Ops
- query params:
  - `campaign_id`

### `campaign/create`

- method: `POST`
- auth: Ops
- request fields:
  - `campaign_code`
  - `client_name`
  - `campaign_name`
  - `start_date`
  - `expected_end_date`
  - `site_ids[]`

### `campaign/update`

- method: `POST`
- auth: Ops
- request fields:
  - `campaign_id`
  - editable campaign fields

### `campaign/end`

- method: `POST`
- auth: Ops
- request fields:
  - `campaign_id`
  - `actual_end_date`

### `campaign/confirm-free-media`

- method: `POST`
- auth: Ops
- request fields:
  - `campaign_id`
  - `site_id`
  - `expiry_date`

### `freemedia/list`

- method: `GET`
- auth: Ops, Media Planning, optionally Sales and Client Servicing if enabled later
- query params:
  - `status`
  - `site_category`
  - `route_or_group`
  - `expiry_window_days`

## Authority And Report Routes

### `authority/view`

- method: `GET`
- auth: Authority Representative
- query params:
  - `date`
  - `belt_id`
  - `supervisor_user_id`
  - `work_type`

### `authority/summary`

- method: `GET`
- auth: Authority Representative, Ops
- query params:
  - `date`
  - `belt_id`
  - `supervisor_user_id`
  - `work_type`

### `authority/share-helper`

- method: `GET`
- auth: Authority Representative, Ops
- query params:
  - same filters as current authority view
- response fields:
  - `message_text`
  - `whatsapp_url`

### `audit/list`

- method: `GET`
- auth: Ops
- query params:
  - `actor_user_id`
  - `entity_type`
  - `action_type`
  - `date_from`
  - `date_to`

### `settings/list`

- method: `GET`
- auth: Ops
- response fields:
  - `items[]`
  - each item contains:
    - `setting_key`
    - `setting_value`
    - `value_type`
    - `description`

### `settings/update`

- method: `POST`
- auth: Ops
- request fields:
  - `setting_key`
  - `setting_value`

### `report/belt-health`

- method: `GET`
- auth: Ops, Management
- query params:
  - `month`
  - `zone`
  - `supervisor_user_id`
  - `format`

### `report/supervisor-activity`

- method: `GET`
- auth: Ops, Management
- query params:
  - `month`
  - `supervisor_user_id`
  - `format`

### `report/worker-activity`

- method: `GET`
- auth: Ops, Management
- query params:
  - `month`
  - `worker_id`
  - `worker_skill_tag`
  - `format`

### `report/advertisement-operations`

- method: `GET`
- auth: Ops, Management
- query params:
  - `month`
  - `site_category`
  - `route_or_group`
  - `client_or_campaign`
  - `format`

### Report Export Rule

- when `format=csv`, response is file download
- when `format` is omitted, response is normal JSON preview

## Error Cases To Standardize

Common business-rule errors:

- invalid JSON payload
- missing required field
- invalid `user_id`, `belt_id`, `site_id`, `task_id`, or other identifier
- CSRF token missing or invalid
- record not found
- record outside role scope
- operation not allowed in current lifecycle state
- forced password reset required before other actions

## Route Permission Map Rule

The current [AuthMiddleware.php](C:/xampp/htdocs/skite/app/middleware/AuthMiddleware.php) uses a hardcoded route-to-role map.
As the system grows, route protection should evolve to:

- `route -> module_key`
- middleware checks authenticated user
- middleware resolves role
- middleware checks allowed module scope
- controller or service applies record-scope rules

Do not keep scaling the app with only numeric role arrays per route.

## Implementation Notes

- keep the existing outer JSON response envelope
- keep route names lowercase
- keep the two-part route pattern unless the front controller is deliberately redesigned
- prefer new controller methods over route-specific business logic inside `index.php`
- if route count becomes large, move the route map out of [index.php](C:/xampp/htdocs/skite/index.php) into a dedicated registry file
