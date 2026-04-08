# Upload Storage And Retention Spec

## Purpose

This file defines the implementation contract for upload storage, metadata, validation, and retention behavior.
It exists so upload handling stays consistent across:

- supervisor uploads
- outsourced uploads
- monitoring uploads
- task proof uploads
- authority visibility review
- cleanup and purge logic

## Source Docs

- `docs/10_recovered_product/02_DOMAIN_AND_ENTITY_MODEL.md`
- `docs/10_recovered_product/03_WORKFLOWS_AND_LIFECYCLES.md`
- `docs/10_recovered_product/07_AUTHORITY_SHARE_AND_SUMMARY_MODEL.md`
- `docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md`
- `docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md`
- `docs/11_build_specs/05_WORKFLOW_STATE_MACHINE_SPEC.md`

## Locked Product Rules

- GPS is stored for Ops review only
- GPS must not block upload in v1
- task completion proof uses `AFTER_WORK` required and `BEFORE_WORK` optional
- supervisor self-delete window is 5 minutes
- rejected uploads become cleanup candidates after 30 days
- rejected-upload purge retains minimal metadata and purge markers
- issue uploads are excluded from authority-ready output
- approved authority visibility is access control, not duplicate file storage

## Core Storage Rules

- one physical file equals one row in `uploads`
- a multi-photo submission creates multiple upload rows with shared context
- the database stores a relative path, not a full filesystem path and not a public URL
- original client filename is stored separately from server storage path
- server-side filename must be collision-safe and must not trust the client filename
- `parent_type` must never be `ISSUE` in v1
- issue evidence is represented by `upload_type = ISSUE` plus the real parent context

## Parent Context Matrix

| Operational Use | `parent_type` | `upload_type` | Default `authority_visibility` | Notes |
|---|---|---|---|---|
| supervisor work proof | `GREEN_BELT` | `WORK` | `HIDDEN` | eligible for Ops authority review |
| supervisor issue proof | `GREEN_BELT` | `ISSUE` | `NOT_ELIGIBLE` | internal issue evidence only |
| outsourced work proof | `GREEN_BELT` | `WORK` | `NOT_ELIGIBLE` | oversight only, not authority flow |
| outsourced issue proof | `GREEN_BELT` | `ISSUE` | `NOT_ELIGIBLE` | oversight only |
| monitoring proof | `SITE` | `WORK` | `NOT_ELIGIBLE` | client/support consumption only |
| free-media discovery proof | `SITE` | `WORK` | `NOT_ELIGIBLE` | discovery context handled elsewhere |
| task before-work proof | `TASK` | `WORK` | `NOT_ELIGIBLE` | `photo_label = BEFORE_WORK` |
| task after-work proof | `TASK` | `WORK` | `NOT_ELIGIBLE` | `photo_label = AFTER_WORK` |

## Required Upload Metadata

Every upload row must capture at least:

- `parent_type`
- `parent_id`
- `upload_type`
- `file_path`
- `original_file_name`
- `mime_type`
- `file_size_bytes`
- `photo_label`
- `comment_text`
- `gps_latitude`
- `gps_longitude`
- `authority_visibility`
- `created_by_user_id`
- `created_at`

Review and deletion metadata should be populated only when relevant:

- `reviewed_by_user_id`
- `reviewed_at`
- `deleted_by_user_id`
- `deleted_at`
- `purged_by_user_id`
- `purged_at`

## Physical Storage Layout

Recommended v1 relative storage pattern:

```text
uploads/<parent_type>/<YYYY>/<MM>/<generated_file_name>.<ext>
```

Examples:

```text
uploads/green_belt/2026/04/gb_4812_01f3c8a2.jpg
uploads/site/2026/04/site_9044_0c91f7ab.webp
uploads/task/2026/04/task_2210_9a22c001.jpg
```

### Storage Path Rules

- `parent_type` folder should be lowercase in storage paths
- generated filename should include stable context plus a random suffix
- do not expose user-supplied filename directly in server storage path
- if a file is purged, `file_path` should be nulled or replaced with a controlled tombstone token consistently across the app

## File Validation Rules

Recommended v1 accepted MIME types:

- `image/jpeg`
- `image/png`
- `image/webp`

Recommended v1 defaults:

- maximum `10` files per submission
- maximum `10 MB` per file

Implementation note:

- if these values later move into `system_settings`, code should still apply server-side validation using the configured values

## GPS Capture Rules

- capture GPS when the browser or client supplies it
- store `gps_latitude` and `gps_longitude` when available
- missing GPS must not block upload
- no automatic mismatch threshold or auto-reject logic in v1
- Ops may later review GPS visually against known parent coordinates

## Photo Label Rules

### Non-Task Uploads

- default `photo_label = GENERAL`

### Task Uploads

- `BEFORE_WORK` is optional
- `AFTER_WORK` is required before task completion
- task completion must fail validation if no `AFTER_WORK` proof exists for that task submission flow

## Upload Creation Rules By Surface

### Supervisor Upload

- creates `GREEN_BELT` parent uploads
- work proof defaults to `authority_visibility = HIDDEN`
- issue proof defaults to `authority_visibility = NOT_ELIGIBLE`
- no review-state feedback shown to supervisor

### Outsourced Upload

- creates `GREEN_BELT` parent uploads
- all outsourced uploads default to `NOT_ELIGIBLE` for authority flow

### Monitoring Upload

- creates `SITE` parent uploads
- always defaults to `NOT_ELIGIBLE`

### Task Detail Upload

- creates `TASK` parent uploads
- always defaults to `NOT_ELIGIBLE`
- validates `AFTER_WORK` before lead completion handoff

## Retention Tiers

### 1. Approved Green-Belt Work Uploads

- keep permanently in v1
- no cleanup page eligibility
- remain available for authority portal access and historical evidence

### 2. Rejected Green-Belt Work Uploads

- remain stored and internal after rejection
- visible to Ops for review
- become manual cleanup candidates after `30` days from rejection or review date
- file can be purged by Ops from the cleanup page
- metadata row remains after purge

### 3. Self-Deleted Uploads

- creator may soft-delete only within the 5-minute self-delete window on pages that expose self-delete
- file remains until maintenance cleanup
- cleanup process purges file after `30` days from `deleted_at`
- metadata row remains after purge

### 4. Issue Uploads

- treated as permanent evidence in v1
- excluded from authority output
- not part of rejected-upload cleanup flow

### 5. Monitoring, Task, And Other Operational Uploads

- kept as operational evidence in v1
- no auto-purge path defined by default
- any later cleanup policy should be introduced explicitly, not silently inherited

## Self-Delete Rules

- self-delete is creator-only
- self-delete is allowed only on designated "My Uploads" style pages
- self-delete is limited to the configured 5-minute window
- self-delete must set `is_deleted = 1`
- soft-deleted uploads must disappear from normal user-facing lists
- self-delete must not bypass audit-safe metadata

Recommended v1 limitation:

- do not expose self-delete for issue uploads

## Rejected Upload Cleanup Rules

Eligibility for Ops cleanup page:

- `authority_visibility = REJECTED`
- `is_purged = 0`
- not already soft-deleted
- older than `30` days from review or rejection decision

Cleanup action behavior:

- remove physical file
- retain metadata row
- set `is_purged = 1`
- set `purged_at`
- set `purged_by_user_id`

## Minimal Metadata To Retain After Purge

The metadata row should continue to preserve:

- `id`
- `parent_type`
- `parent_id`
- `upload_type`
- `photo_label`
- `original_file_name`
- `mime_type`
- `created_by_user_id`
- `created_at`
- `reviewed_by_user_id`
- `reviewed_at`
- `authority_visibility`
- `is_deleted`
- `deleted_at`
- `deleted_by_user_id`
- `is_purged`
- `purged_at`
- `purged_by_user_id`

The file body itself should be gone.

## Security Rules

- never allow executable file types
- validate MIME type server-side, not by extension only
- sanitize any original filename before display
- do not build public URLs directly from unsanitized user data
- do not trust client-side size checks as sufficient

## Query Rules

- authority portal queries must use only `APPROVED` green-belt work uploads
- supervisor-facing lists must exclude deleted uploads and must not reveal review outcome
- monitoring and task pages should ignore authority visibility unless explicitly debugging

## Non-Goals In V1

- no duplicate storage of authority bundles
- no automatic WhatsApp send-state tracking
- no AI-based photo classification
- no GPS-based blocking or automatic fraud scoring
