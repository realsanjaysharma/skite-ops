# Canonical Schema Roadmap

## Authority Note

- Purpose: Canonical implementation-spec document.
- Authority Level: Implementation truth.
- If Conflict: This file controls implementation behavior. `docs/10_recovered_product/*` controls product meaning and scope. Repo-facing mirror docs must be updated to match, not treated as competing truth.

## Purpose

This file defines the canonical recovered schema target before SQL rewrite.
It is the schema-planning source of truth for:

- table set
- key columns
- enums
- foreign keys
- uniqueness rules
- indexing rules
- derived versus stored boundaries
- migration order

## Source Docs

- `docs/10_recovered_product/02_DOMAIN_AND_ENTITY_MODEL.md`
- `docs/10_recovered_product/03_WORKFLOWS_AND_LIFECYCLES.md`
- `docs/10_recovered_product/06_REPORT_AND_EXPORT_MODEL.md`
- `docs/10_recovered_product/07_AUTHORITY_SHARE_AND_SUMMARY_MODEL.md`
- `docs/11_build_specs/01_RBAC_PERMISSION_GROUP_SPEC.md`

## Schema Design Rules

- use `created_at` and `updated_at` on primary operational tables
- use explicit enums in v1 where the domain is stable enough
- do not persist dashboard counters, alerts, or authority summaries as truth tables
- monitoring due truth comes from stored monthly due dates
- approved authority visibility is stored on uploads, but external send-state is not
- prefer append-safe history tables over destructive mutation
- archive tasks instead of deleting them
- keep foreign keys explicit when the relationship is product truth

## Naming And Column Conventions

- primary keys use `id`
- foreign keys use `<table>_id` where practical
- boolean columns use `is_*`
- enum-like columns use stable uppercase values in code and DB
- soft-delete columns, when present, use `is_deleted`, `deleted_at`, `deleted_by_user_id`
- purge markers use `is_purged`, `purged_at`, `purged_by_user_id`

## Canonical Table Set

### Access And Governance

#### `users`

Purpose:

- core login identity table

Key columns:

- `id`
- `role_id`
- `full_name`
- `email`
- `phone`
- `password_hash`
- `failed_attempt_count`
- `last_failed_attempt_at`
- `is_active`
- `force_password_reset`
- `is_deleted`
- `deleted_at`
- `deleted_by_user_id`
- `created_at`
- `updated_at`

Foreign keys:

- `role_id -> roles.id`
- `deleted_by_user_id -> users.id`

Constraints:

- unique `email`

Indexes:

- `idx_users_role_id`
- `idx_users_is_active`
- `idx_users_login_lock`

#### `roles`

Purpose:

- seeded and dynamic role catalog

Key columns:

- `id`
- `role_key`
- `role_name`
- `description`
- `landing_module_key`
- `is_system_role`
- `is_active`
- `created_by_user_id`
- `created_at`
- `updated_at`

Foreign keys:

- `created_by_user_id -> users.id`

Constraints:

- unique `role_key`
- unique `role_name`

#### `permission_groups`

Purpose:

- predefined permission bundle catalog

Key columns:

- `id`
- `group_key`
- `group_name`
- `description`
- `created_at`
- `updated_at`

Constraints:

- unique `group_key`

Seeded values:

- `VIEW`
- `UPLOAD`
- `APPROVE`
- `MANAGE`

#### `role_permission_mappings`

Purpose:

- exactly one permission group per role in v1

Key columns:

- `id`
- `role_id`
- `permission_group_id`
- `created_at`
- `updated_at`

Foreign keys:

- `role_id -> roles.id`
- `permission_group_id -> permission_groups.id`

Constraints:

- unique `role_id`

#### `role_module_scopes`

Purpose:

- allowed module keys for each role

Key columns:

- `id`
- `role_id`
- `module_key`
- `created_at`
- `updated_at`

Foreign keys:

- `role_id -> roles.id`

Constraints:

- unique (`role_id`, `module_key`)

#### `audit_logs`

Purpose:

- audit history for governance actions

Key columns:

- `id`
- `actor_user_id`
- `action_type`
- `entity_type`
- `entity_id`
- `old_values_json`
- `new_values_json`
- `override_reason`
- `created_at`

Foreign keys:

- `actor_user_id -> users.id`

Indexes:

- `idx_audit_entity`
- `idx_audit_actor`
- `idx_audit_created_at`

#### `system_settings`

Purpose:

- controlled operational settings

Key columns:

- `id`
- `setting_key`
- `setting_value`
- `value_type`
- `description`
- `created_at`
- `updated_at`

Constraints:

- unique `setting_key`

Expected keys:

- `ops_phone_number`
- `self_delete_purge_days`
- `rejected_upload_cleanup_days`
- `free_media_default_expiry_days`
- `authority_whatsapp_helper_enabled`

### Green Belt Domain

#### `green_belts`

Purpose:

- legal and operational truth for belts

Key columns:

- `id`
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
- `created_at`
- `updated_at`

Enums:

- `permission_status`: `APPLIED`, `AGREEMENT_SIGNED`, `EXPIRED`
- `maintenance_mode`: `MAINTAINED`, `OUTSOURCED`
- `watering_frequency`: `DAILY`, `ALTERNATE_DAY`, `WEEKLY`, `NOT_REQUIRED`

Constraints:

- unique `belt_code`

Indexes:

- `idx_green_belts_zone`
- `idx_green_belts_permission_status`
- `idx_green_belts_maintenance_mode`

#### `belt_supervisor_assignments`

Purpose:

- historical supervisor ownership

Key columns:

- `id`
- `belt_id`
- `supervisor_user_id`
- `start_date`
- `end_date`
- `created_at`
- `updated_at`

Foreign keys:

- `belt_id -> green_belts.id`
- `supervisor_user_id -> users.id`

Indexes:

- `idx_bsa_belt_dates`
- `idx_bsa_supervisor_dates`

#### `belt_authority_assignments`

Purpose:

- authority access mapping

Key columns:

- `id`
- `belt_id`
- `authority_user_id`
- `start_date`
- `end_date`
- `created_at`
- `updated_at`

Foreign keys:

- `belt_id -> green_belts.id`
- `authority_user_id -> users.id`

Indexes:

- `idx_baa_belt_dates`
- `idx_baa_authority_dates`

#### `belt_outsourced_assignments`

Purpose:

- outsourced-belt access mapping

Key columns:

- `id`
- `belt_id`
- `outsourced_user_id`
- `start_date`
- `end_date`
- `created_at`
- `updated_at`

Foreign keys:

- `belt_id -> green_belts.id`
- `outsourced_user_id -> users.id`

Indexes:

- `idx_boa_belt_dates`
- `idx_boa_outsourced_dates`

#### `maintenance_cycles`

Purpose:

- cycle history for maintained belts

Key columns:

- `id`
- `belt_id`
- `started_by_user_id`
- `closed_by_user_id`
- `start_date`
- `end_date`
- `close_reason`
- `created_at`
- `updated_at`

Foreign keys:

- `belt_id -> green_belts.id`
- `started_by_user_id -> users.id`
- `closed_by_user_id -> users.id`

Indexes:

- `idx_cycles_belt_start`
- `idx_cycles_belt_end`

#### `watering_records`

Purpose:

- one explicit row per belt per date when action exists

Key columns:

- `id`
- `belt_id`
- `watering_date`
- `status`
- `reason_text`
- `created_by_user_id`
- `override_by_user_id`
- `override_reason`
- `created_at`
- `updated_at`

Enums:

- `status`: `DONE`, `NOT_REQUIRED`

Foreign keys:

- `belt_id -> green_belts.id`
- `created_by_user_id -> users.id`
- `override_by_user_id -> users.id`

Constraints:

- unique (`belt_id`, `watering_date`)

Indexes:

- `idx_watering_date`
- `idx_watering_belt_date`

#### `supervisor_attendance`

Purpose:

- same-day supervisor presence record

Key columns:

- `id`
- `supervisor_user_id`
- `attendance_date`
- `status`
- `created_by_user_id`
- `override_by_user_id`
- `override_reason`
- `created_at`
- `updated_at`

Enums:

- `status`: `PRESENT`, `ABSENT`

Foreign keys:

- `supervisor_user_id -> users.id`
- `created_by_user_id -> users.id`
- `override_by_user_id -> users.id`

Constraints:

- unique (`supervisor_user_id`, `attendance_date`)

#### `labour_entries`

Purpose:

- daily resource counts per belt

Key columns:

- `id`
- `belt_id`
- `entry_date`
- `labour_count`
- `gardener_count`
- `night_guard_count`
- `created_by_user_id`
- `override_by_user_id`
- `override_reason`
- `created_at`
- `updated_at`

Foreign keys:

- `belt_id -> green_belts.id`
- `created_by_user_id -> users.id`
- `override_by_user_id -> users.id`

Constraints:

- unique (`belt_id`, `entry_date`)

### Execution Resources

#### `fabrication_workers`

Purpose:

- non-login worker resource list

Key columns:

- `id`
- `worker_name`
- `skill_tag`
- `phone`
- `is_active`
- `created_at`
- `updated_at`

Indexes:

- `idx_workers_skill_tag`

#### `worker_daily_entries`

Purpose:

- universal daily truth layer for worker attendance and activity

Key columns:

- `id`
- `worker_id`
- `entry_date`
- `attendance_status`
- `activity_type`
- `task_id`
- `site_id`
- `work_plan`
- `work_update`
- `created_by_user_id`
- `created_at`
- `updated_at`

Enums:

- `attendance_status`: `PRESENT`, `ABSENT`, `HALF_DAY`
- `activity_type`: `INSTALLATION`, `MAINTENANCE`, `DRIVING`, `MONITORING`, `SUPPORT`, `OTHER`

Foreign keys:

- `worker_id -> fabrication_workers.id`
- `task_id -> tasks.id`
- `site_id -> sites.id`
- `created_by_user_id -> users.id`

Constraints:

- unique (`worker_id`, `entry_date`)

Indexes:

- `idx_wde_date`
- `idx_wde_task_id`
- `idx_wde_site_id`

#### `task_worker_assignments`

Purpose:

- fabrication-only assignment layer for task occupancy

Key columns:

- `id`
- `task_id`
- `worker_id`
- `assigned_by_user_id`
- `assigned_date`
- `release_date`
- `assignment_role`
- `created_at`
- `updated_at`

Enums:

- `assignment_role`: `PRIMARY`, `HELPER`

Foreign keys:

- `task_id -> tasks.id`
- `worker_id -> fabrication_workers.id`
- `assigned_by_user_id -> users.id`

Constraints:

- unique (`task_id`, `worker_id`, `assigned_date`)

Indexes:

- `idx_twa_task_id`
- `idx_twa_worker_id`
- `idx_twa_assigned_date`

### Advertisement And Monitoring

#### `sites`

Purpose:

- advertisement site and asset master

Key columns:

- `id`
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
- `created_at`
- `updated_at`

Enums:

- `site_category`: `GREEN_BELT`, `CITY`, `HIGHWAY`
- `lighting_type`: `LIT`, `NON_LIT`

Foreign keys:

- `green_belt_id -> green_belts.id`

Constraints:

- unique `site_code`

Indexes:

- `idx_sites_category`
- `idx_sites_green_belt_id`
- `idx_sites_route_or_group`

#### `site_monitoring_due_dates`

Purpose:

- stored monthly due truth for monitoring

Key columns:

- `id`
- `site_id`
- `due_date`
- `plan_month`
- `source_group_key`
- `created_by_user_id`
- `created_at`
- `updated_at`

Foreign keys:

- `site_id -> sites.id`
- `created_by_user_id -> users.id`

Constraints:

- unique (`site_id`, `due_date`)

Indexes:

- `idx_smdd_due_date`
- `idx_smdd_plan_month`
- `idx_smdd_source_group_key`

#### `campaigns`

Purpose:

- client campaign truth

Key columns:

- `id`
- `campaign_code`
- `client_name`
- `campaign_name`
- `start_date`
- `expected_end_date`
- `actual_end_date`
- `status`
- `created_at`
- `updated_at`

Enums:

- `status`: `ACTIVE`, `ENDED`, `CANCELLED`

Constraints:

- unique `campaign_code`

Indexes:

- `idx_campaigns_status`
- `idx_campaigns_client_name`

#### `campaign_sites`

Purpose:

- campaign-to-site linkage history

Key columns:

- `id`
- `campaign_id`
- `site_id`
- `linked_from_date`
- `linked_to_date`
- `created_at`
- `updated_at`

Foreign keys:

- `campaign_id -> campaigns.id`
- `site_id -> sites.id`

Constraints:

- unique (`campaign_id`, `site_id`, `linked_from_date`)

Indexes:

- `idx_campaign_sites_site_id`
- `idx_campaign_sites_campaign_id`

#### `free_media_records`

Purpose:

- governed free-media state history

Key columns:

- `id`
- `site_id`
- `source_type`
- `source_reference_id`
- `discovered_date`
- `confirmed_by_user_id`
- `confirmed_date`
- `status`
- `expiry_date`
- `created_at`
- `updated_at`

Enums:

- `source_type`: `MONITORING_DISCOVERY`, `CAMPAIGN_END`
- `status`: `DISCOVERED`, `CONFIRMED_ACTIVE`, `EXPIRED`, `CONSUMED`

Foreign keys:

- `site_id -> sites.id`
- `confirmed_by_user_id -> users.id`

Notes:

- when `source_type = MONITORING_DISCOVERY`, `source_reference_id` should point to a representative discovery upload row
- monitoring discovery flow may create a new discovered row or refresh the current discovered row for the same site through service logic

Indexes:

- `idx_free_media_site_id`
- `idx_free_media_status`
- `idx_free_media_expiry_date`

### Proof, Issues, Requests, And Tasks

#### `uploads`

Purpose:

- unified evidence table

Key columns:

- `id`
- `parent_type`
- `parent_id`
- `upload_type`
- `work_type`
- `is_discovery_mode`
- `file_path`
- `original_file_name`
- `mime_type`
- `file_size_bytes`
- `photo_label`
- `comment_text`
- `gps_latitude`
- `gps_longitude`
- `authority_visibility`
- `reviewed_by_user_id`
- `reviewed_at`
- `is_deleted`
- `deleted_at`
- `deleted_by_user_id`
- `is_purged`
- `purged_at`
- `purged_by_user_id`
- `created_by_user_id`
- `created_at`
- `updated_at`

Enums:

- `parent_type`: `GREEN_BELT`, `SITE`, `TASK`
- `upload_type`: `WORK`, `ISSUE`
- `photo_label`: `BEFORE_WORK`, `AFTER_WORK`, `GENERAL`
- `authority_visibility`: `HIDDEN`, `APPROVED`, `REJECTED`, `NOT_ELIGIBLE`

Foreign keys:

- `reviewed_by_user_id -> users.id`
- `deleted_by_user_id -> users.id`
- `purged_by_user_id -> users.id`
- `created_by_user_id -> users.id`

Indexes:

- `idx_uploads_parent`
- `idx_uploads_created_at`
- `idx_uploads_visibility`
- `idx_uploads_work_type`
- `idx_uploads_discovery_mode`
- `idx_uploads_deleted`
- `idx_uploads_purged`

Notes:

- issue uploads used for internal issue evidence must carry `authority_visibility = NOT_ELIGIBLE`
- `work_type` is an optional stored tag used for authority filtering and summary generation where work-context grouping matters
- parent integrity must be enforced at service level because `parent_id` is polymorphic

#### `issues`

Purpose:

- governed operational problems

Key columns:

- `id`
- `source_type`
- `source_reference_id`
- `belt_id`
- `site_id`
- `title`
- `description`
- `priority`
- `status`
- `raised_by_user_id`
- `closed_by_user_id`
- `closed_at`
- `created_at`
- `updated_at`

Enums:

- `priority`: `LOW`, `MEDIUM`, `HIGH`, `CRITICAL`
- `status`: `OPEN`, `IN_PROGRESS`, `CLOSED`

Foreign keys:

- `belt_id -> green_belts.id`
- `site_id -> sites.id`
- `raised_by_user_id -> users.id`
- `closed_by_user_id -> users.id`

Indexes:

- `idx_issues_status`
- `idx_issues_priority`
- `idx_issues_belt_id`
- `idx_issues_site_id`

#### `task_requests`

Purpose:

- pre-approval intake before real task creation

Key columns:

- `id`
- `requester_user_id`
- `request_source_role`
- `request_type`
- `client_name`
- `campaign_id`
- `site_id`
- `belt_id`
- `description`
- `status`
- `reviewed_by_user_id`
- `reviewed_at`
- `rejection_reason`
- `created_at`
- `updated_at`

Enums:

- `status`: `SUBMITTED`, `APPROVED`, `REJECTED`, `CONVERTED`

Foreign keys:

- `requester_user_id -> users.id`
- `campaign_id -> campaigns.id`
- `site_id -> sites.id`
- `belt_id -> green_belts.id`
- `reviewed_by_user_id -> users.id`

Indexes:

- `idx_task_requests_status`
- `idx_task_requests_requester`

#### `tasks`

Purpose:

- Ops-governed execution units

Key columns:

- `id`
- `request_id`
- `linked_issue_id`
- `task_source_type`
- `assigned_by_user_id`
- `assigned_lead_user_id`
- `task_category`
- `vertical_type`
- `work_description`
- `location_text`
- `priority`
- `start_date`
- `expected_close_date`
- `actual_close_date`
- `status`
- `progress_percent`
- `remark_1`
- `remark_2`
- `completion_note`
- `is_archived`
- `archived_at`
- `created_at`
- `updated_at`

Enums:

- `status`: `OPEN`, `RUNNING`, `COMPLETED`, `CANCELLED`, `ARCHIVED`
- `vertical_type`: `GREEN_BELT`, `ADVERTISEMENT`, `MONITORING`

Foreign keys:

- `request_id -> task_requests.id`
- `linked_issue_id -> issues.id`
- `assigned_by_user_id -> users.id`
- `assigned_lead_user_id -> users.id`

Constraints:

- `progress_percent` should remain between `0` and `100`
- `linked_issue_id` should be unique when not null if one issue maps to one active task in v1

Indexes:

- `idx_tasks_status`
- `idx_tasks_assigned_lead`
- `idx_tasks_request_id`
- `idx_tasks_linked_issue_id`

## Tables Explicitly Avoided In V1

Do not create these as truth tables:

- `compliance_summary`
- `authority_summary`
- `dashboard_counters`
- `alerts`
- `worker_availability_cache`

These remain derived outputs.

## Derived Output Notes

Derived from source tables rather than stored:

- green-belt health status
- watering compliance percent
- pending authority review counts
- monitoring due lists
- worker availability views
- monthly report rows
- authority summary text

## Soft Delete And Purge Coverage

Preferred support:

- `users`: soft delete
- `uploads`: soft delete plus purge markers
- `tasks`: archive rather than delete

Most other operational tables should stay append-safe or governance-controlled rather than casually deleted.

## Canonical Index Priorities

Highest-priority indexes for early implementation:

- user email and active state
- role and permission lookup
- belt code
- site code
- active assignment date-range lookup
- watering by belt/date
- uploads by parent and created date
- uploads by visibility state
- issues by status and priority
- tasks by status and assigned lead
- monitoring due dates by due date

## Migration Order

1. `roles`, `permission_groups`
2. `users`, `role_permission_mappings`, `role_module_scopes`, `audit_logs`, `system_settings`
3. `green_belts`, `belt_supervisor_assignments`, `belt_authority_assignments`, `belt_outsourced_assignments`, `maintenance_cycles`
4. `watering_records`, `supervisor_attendance`, `labour_entries`
5. `fabrication_workers`
6. `sites`, `site_monitoring_due_dates`
7. `campaigns`, `campaign_sites`, `free_media_records`
8. `task_requests`, `issues`, `tasks`
9. `worker_daily_entries`, `task_worker_assignments`
10. `uploads`

## Rewrite Rule

The old schema docs and SQL file must eventually be rewritten to match this roadmap.
Do not let the existing trimmed schema continue to define product scope.
