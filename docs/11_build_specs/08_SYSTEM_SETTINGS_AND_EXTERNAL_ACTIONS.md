# System Settings And External Actions

## Purpose

This file defines the implementation contract for:

- configurable system settings stored in the database
- deployment or environment values that must stay outside the database
- helper actions that interact with the outside world

It exists so the app does not scatter operational switches between:

- hardcoded constants
- random controller logic
- UI-only assumptions

## Source Docs

- [07_AUTHORITY_SHARE_AND_SUMMARY_MODEL.md](C:/xampp/htdocs/skite/docs/10_recovered_product/07_AUTHORITY_SHARE_AND_SUMMARY_MODEL.md)
- [02_CANONICAL_SCHEMA_ROADMAP.md](C:/xampp/htdocs/skite/docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md)
- [04_PAGE_FIELD_AND_ACTION_SPEC.md](C:/xampp/htdocs/skite/docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md)
- [03_API_AND_ROUTE_CONTRACT.md](C:/xampp/htdocs/skite/docs/11_build_specs/03_API_AND_ROUTE_CONTRACT.md)
- [constants.php](C:/xampp/htdocs/skite/config/constants.php)

## Core Rule

External actions may help humans act faster, but they must not:

- silently mutate operational truth
- bypass approval or visibility boundaries
- create fake delivery state
- leak internal-only data

## Settings Classification Model

The system should classify settings into three buckets.

### 1. Database-Backed Operational Settings

These belong in `system_settings` because Ops may need to change them without code edits.

Examples:

- Ops phone number
- rejected upload cleanup days
- self-delete purge days
- free-media default expiry days
- authority WhatsApp helper enabled flag

### 2. Deployment Constants

These belong in code config or environment, not in the database.

Examples:

- session cookie flags
- app timezone
- base upload directory
- allowed MIME types
- upload max size hard ceiling
- login lock thresholds

### 3. Derived Runtime Values

These should not be stored as settings at all.

Examples:

- current month's authority summary text
- monitoring due list
- dashboard alert counts
- worker availability

## System Settings Table Contract

The existing schema roadmap already defines:

- `setting_key`
- `setting_value`
- `value_type`
- `description`

Recommended `value_type` vocabulary:

- `STRING`
- `INTEGER`
- `BOOLEAN`
- `JSON`

## Required Database Settings In V1

These keys should exist in seeded or first-run system settings.

### `ops_phone_number`

- type: `STRING`
- purpose: number used by Call Ops helper
- example value: `+9198XXXXXXXX`

### `self_delete_purge_days`

- type: `INTEGER`
- purpose: days after which self-deleted uploads are purged
- default value: `30`
- note: should stay aligned with [constants.php](C:/xampp/htdocs/skite/config/constants.php) until config centralization is cleaned up

### `rejected_upload_cleanup_days`

- type: `INTEGER`
- purpose: minimum rejected-upload age before cleanup eligibility
- default value: `30`

### `free_media_default_expiry_days`

- type: `INTEGER`
- purpose: default expiry window for confirmed free-media records when no explicit expiry is chosen

### `authority_whatsapp_helper_enabled`

- type: `BOOLEAN`
- purpose: allows or hides the one-click WhatsApp helper in authority view
- default value: `1`

## Recommended Optional V1.1 Settings

These are useful later but not required on day one if you want to stay lean.

### `authority_summary_include_no_work`

- type: `BOOLEAN`
- purpose: whether summary text should explicitly include belts with no completed work

### `authority_summary_date_label_prefix`

- type: `STRING`
- purpose: prefix text such as `Date`

### `default_page_limit`

- type: `INTEGER`
- purpose: list default page size

## What Must Stay In Config Or Environment

These should remain deployment-level values, not Ops-editable settings.

### Security And Session

- session cookie secure mode
- session cookie SameSite policy
- maximum login attempts
- lock duration minutes
- CSRF mechanics

### Upload Hard Limits

- allowed upload MIME types
- allowed upload extensions
- server-side maximum upload size hard ceiling

### File System Paths

- physical upload root directory
- log directory
- temp directory if introduced later

### Application Runtime

- timezone
- base application URL if needed for absolute link generation

## Current Constant Mapping Guidance

The repo currently stores several operational values in [constants.php](C:/xampp/htdocs/skite/config/constants.php).
Use this rule going forward:

- keep security and platform-hard limits in constants or env
- move Ops-adjustable business settings into `system_settings`

### Keep As Constants Or Environment

- `WATERING_CUTOFF_HOUR`
- `MAX_FAILED_LOGIN_ATTEMPTS`
- `LOGIN_LOCK_DURATION_MINUTES`
- `MAX_UPLOAD_SIZE_MB` as hard cap
- `ALLOWED_UPLOAD_EXTENSIONS`
- `ALLOWED_UPLOAD_MIME_TYPES`
- `DEFAULT_PAGE_LIMIT`
- `CYCLE_DELAY_ALERT_DAYS`
- `FREE_MEDIA_EXPIRY_ALERT_DAYS`
- `MONTH_LOCK_DAY`

### Mirror Or Eventually Migrate To `system_settings`

- `SELF_DELETED_UPLOAD_PURGE_DAYS`

Reason:

- retention thresholds are operationally adjustable and belong closer to governance settings than to hard platform config

## External Action Types

### 1. Call Ops

### 2. WhatsApp Helper Share

### 3. Download Helper Actions

### 4. Authority Summary Text Generation

All of these are helper actions only.
They must not create hidden workflow truth.

## Call Ops Contract

### Trigger Surface

- Task Detail page for assigned Fabrication Lead
- Ops may also see the same shortcut in read or control form

### Input Source

- `ops_phone_number` from `system_settings`

### Output

The backend or frontend may generate a tel-style helper target such as:

```text
tel:+9198XXXXXXXX
```

### Rules

- this is a convenience action only
- clicking Call Ops must not change task state
- no "call completed" or "call attempted" truth should be stored in v1

## Authority WhatsApp Helper Contract

### Trigger Surface

- Authority View only
- only from the currently filtered approved-proof context

### Input Requirements

- approved authority-visible uploads only
- current filter context
- generated authority summary text
- `authority_whatsapp_helper_enabled = 1`

### Output Shape

Backend helper response should contain at least:

- `message_text`
- `whatsapp_url`

Example format:

```json
{
  "success": true,
  "data": {
    "message_text": "Date: 2026-04-08\nSector 18 Belt: Watering, Cleaning",
    "whatsapp_url": "https://wa.me/?text=..."
  }
}
```

### Rules

- helper output must follow only approved filtered authority context
- must exclude:
  - hidden uploads
  - rejected uploads
  - issue uploads
  - internal notes
  - operational chatter
- generating the helper must not mark anything as externally shared
- the system does not track WhatsApp send completion in v1

## WhatsApp URL Generation Rule

Recommended v1 pattern:

```text
https://wa.me/?text=<urlencoded message>
```

The message must be URL-encoded.

Do not depend on:

- browser-only string concatenation hidden in multiple screens
- custom undocumented phone-link builders

Use one shared helper or service.

## Authority Summary Text Generation Contract

### Timing

- generated on demand from the currently filtered approved authority view
- may also be produced for end-of-day flows

### Required Inputs

- target date or date range
- filtered belts
- filtered supervisor if present
- filtered work type if present

### Output Structure

```text
Date: <date>
<Belt Name>: <work summary>
<Belt Name>: <work summary>
```

### Allowed Summary Content

- watering
- cleaning
- repair
- other authority-relevant completed work

### Forbidden Summary Content

- issue chatter
- internal comments
- rejection or visibility status
- internal operational discussion

### Summary Generation Rule

Summary text must be derived from approved work proof and its interpreted work context.
It must not be manually typed into storage as a primary truth record in v1.

## Download Helper Contract

### Authority View Downloads

- download should follow current filtered approved-proof view
- it may return:
  - filtered file list
  - zipped bundle later if introduced
- v1 does not require bundle-state persistence

### Report Downloads

- CSV downloads must follow the report formula spec
- export action must not mutate any business record

## External Action Security Rules

- all external helper endpoints require the same session and RBAC checks as the source page
- helper outputs must be filtered by record scope before message or download generation
- URL generation must never expose hidden file paths
- if a helper is disabled by setting, the endpoint should return `403` or feature-disabled error consistently

## UI Toggle Rules

The following UI elements should be conditional on settings:

- Authority WhatsApp helper button -> controlled by `authority_whatsapp_helper_enabled`
- Call Ops button -> shown only when `ops_phone_number` is present

Recommended UI behavior:

- hide the control when disabled or missing
- do not show a broken button with empty target

## Caching And Read Strategy

- settings should be loaded through one shared settings service
- low-churn settings may be cached in memory per request
- if longer-lived caching is introduced later, invalidation must happen on settings update

## Seed Requirements

First-run or migration seed should create at least:

- `ops_phone_number`
- `self_delete_purge_days`
- `rejected_upload_cleanup_days`
- `free_media_default_expiry_days`
- `authority_whatsapp_helper_enabled`

## Non-Goals In V1

- no silent WhatsApp sending
- no stored "message delivered" truth
- no per-role theme settings
- no arbitrary Ops-defined setting keys from UI
- no workflow-state mutation triggered by helper actions alone
