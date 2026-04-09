# Reports, Alerts, And Formulas

## Authority Note

- Purpose: Canonical implementation-spec document.
- Authority Level: Implementation truth.
- If Conflict: This file controls implementation behavior. `docs/10_recovered_product/*` controls product meaning and scope. Repo-facing mirror docs must be updated to match, not treated as competing truth.

## Purpose

This file defines the implementation formulas used by reports, dashboards, due lists, and alert surfaces.
It exists so reporting and attention logic are computed consistently across:

- dashboards
- list views
- CSV exports
- internal alert panels

## Source Docs

- `docs/10_recovered_product/06_REPORT_AND_EXPORT_MODEL.md`
- `docs/10_recovered_product/03_WORKFLOWS_AND_LIFECYCLES.md`
- `docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md`
- `docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md`
- `docs/11_build_specs/05_WORKFLOW_STATE_MACHINE_SPEC.md`

## Global Reporting Rules

- report month uses calendar-month boundaries only
- monthly reports are historical views, not live-state snapshots
- CSV is the only export format in v1
- archived tasks remain included historically
- per-user reports remain domain-scoped
- derived counts must never be stored back into operational truth tables

## Core Date Helpers

For a selected report month:

- `month_start` = first calendar date of the selected month
- `month_end` = last calendar date of the selected month
- `report_today` = current server date only for live dashboard and alert views, not monthly historical reports

For historical monthly reports:

- use `month_end` when a "days since" formula needs an anchor date
- if the selected month is the current month, it is still treated as a report month ending on `month_end`, not "today"

## Qualifying Row Definitions

### Qualifying Monitoring Completion For A Due Date

A site due date counts as completed when:

- there is at least one `uploads` row with:
  - `parent_type = SITE`
  - `parent_id = site_id`
  - `is_deleted = 0`
  - `is_purged = 0`
  - `DATE(created_at) = due_date`

### Qualifying Watering Completion For A Date

A watering day counts as completed when:

- there is a `watering_records` row with:
  - matching `belt_id`
  - matching `watering_date`
  - `status = DONE`

### Reportable Green-Belt Date

A belt-date is reportable for internal watering and compliance only when:

- belt is in internal maintenance scope for that date
- belt is not hidden on that date
- permission is not expired on that date

Implementation note:

- where date-level hidden or permission history is not fully versioned, v1 should use the best available month-consistent interpretation and keep the logic centralized in one service

## Watering Scheduler Formulas

These formulas are reused from the earlier explicit architecture notes and remain compatible with the recovered model.

### `DAILY`

- watering is expected every reportable calendar day

### `ALTERNATE_DAY`

- watering is required when `days_since_last_watered >= 2`

### `WEEKLY`

- watering is required when `days_since_last_watered >= 7`

### `NOT_REQUIRED`

- no scheduled watering expectation exists

### Daily Explicit `NOT_REQUIRED`

If a specific day has an explicit `watering_records.status = NOT_REQUIRED` row:

- that day must not count as a missed watering day
- that day must be removed from `required_watering_days`

## Dashboard Metric Formulas

### Master Operations Dashboard

#### `green_belt_operational_count`

Count of green belts currently relevant to operations:

- belt is not hidden
- permission not expired

#### `pending_authority_review_count`

Count of uploads where:

- `parent_type = GREEN_BELT`
- `upload_type = WORK`
- `authority_visibility = HIDDEN`
- `is_deleted = 0`
- `is_purged = 0`

#### `open_task_count`

Count of tasks where:

- `status IN (OPEN, RUNNING)`
- `is_archived = 0`

#### `open_issue_count`

Count of issues where:

- `status IN (OPEN, IN_PROGRESS)`

#### `campaign_ending_soon_count`

Count of campaigns where:

- `status = ACTIVE`
- `expected_end_date >= report_today`
- `expected_end_date <= report_today + 7 days`

#### `free_media_active_count`

Count of free-media records where:

- `status = CONFIRMED_ACTIVE`
- `expiry_date IS NULL OR expiry_date >= report_today`

### Green Belt Dashboard

#### `active_cycle_count`

Count of maintenance cycles where:

- `end_date IS NULL`

#### `same_day_watering_pending_count`

Count of maintained, reportable belts for `report_today` where:

- no `watering_records` row exists for `report_today`

#### `open_green_belt_issue_count`

Count of issues where:

- `belt_id IS NOT NULL`
- `status IN (OPEN, IN_PROGRESS)`

### Monitoring Dashboard

#### `due_today_count`

Count of `site_monitoring_due_dates` rows where:

- `due_date = report_today`
- no qualifying monitoring completion exists for that due date

#### `completed_today_count`

Count of `site_monitoring_due_dates` rows where:

- `due_date = report_today`
- qualifying monitoring completion exists

#### `overdue_due_date_count`

Count of `site_monitoring_due_dates` rows where:

- `due_date < report_today`
- no qualifying monitoring completion exists

## Alert Logic

Alerts are derived and grouped by category.
They should not be stored as first-class truth rows in v1.

### Green Belt Alert Categories

#### `attendance_missing`

Triggered when:

- a reportable supervisor has no attendance record for `report_today`

#### `watering_missing`

Triggered when:

- a maintained, reportable belt has no watering row for `report_today`
- and the day is expected under the watering scheduler

#### `cycle_delay`

Triggered when:

- a maintenance cycle is active
- `days_open > 4`

This threshold comes from the earlier explicit architecture lock and remains compatible with the recovered model.

#### `expiry_warning`

Triggered when:

- `permission_end_date IS NOT NULL`
- `permission_end_date >= report_today`
- `permission_end_date <= report_today + 7 days`

### Monitoring And Advertisement Alert Categories

#### `monitoring_overdue`

Triggered when:

- a monitoring due date exists in the past
- and no qualifying completion exists

#### `high_priority_tasks`

Triggered when:

- task `status IN (OPEN, RUNNING)`
- `priority IN (HIGH, CRITICAL)`

#### `campaign_end_review_pending`

Triggered when:

- campaign has ended
- and no governed free-media confirmation decision has been recorded yet

## Worker Availability Logic

### Purpose

The "who is free today" view is derived, not stored.

### Worker Availability Inputs

- `fabrication_workers`
- `worker_daily_entries`
- `task_worker_assignments`
- `tasks`

### `available_today` Rule

A fabrication worker counts as available today when:

- worker `is_active = 1`
- there is a `worker_daily_entries` row for `report_today`
- `attendance_status IN (PRESENT, HALF_DAY)`
- there is no active fabrication assignment row for that worker where:
  - `assigned_date <= report_today`
  - `release_date IS NULL OR release_date >= report_today`
  - linked task `status IN (OPEN, RUNNING)`

### `occupied_today` Rule

A fabrication worker counts as occupied today when:

- worker has an active fabrication assignment linked to an `OPEN` or `RUNNING` task for `report_today`

### `not_available_today` Rule

A fabrication worker is not available when:

- no daily entry exists for `report_today`
- or `attendance_status = ABSENT`
- or worker is inactive

## Report Field Formulas

Exact CSV columns are frozen in `docs/10_recovered_product/06_REPORT_AND_EXPORT_MODEL.md`.
This section defines how calculated fields should be derived.

### 1. Belt Health Summary

#### `cycles_completed_count`

Count of `maintenance_cycles` where:

- `belt_id = current belt`
- `end_date BETWEEN month_start AND month_end`

#### `days_since_last_completion`

If the belt has a completed cycle on or before `month_end`:

- `month_end - MAX(end_date)`

Otherwise:

- null

#### `required_watering_days`

Count of dates in the selected month where:

- the date is reportable for the belt
- watering is expected under the watering scheduler
- there is no explicit `NOT_REQUIRED` row for that date

#### `completed_watering_days`

Count of selected-month dates where:

- the date is reportable for the belt
- a `watering_records.status = DONE` row exists

#### `watering_compliance_percent`

If `required_watering_days > 0`:

- `(completed_watering_days / required_watering_days) * 100`

Else:

- `100`

#### `open_issues_count`

Count of issues where:

- `belt_id = current belt`
- `status IN (OPEN, IN_PROGRESS)`

#### `health_status`

Recommended v1 precedence:

- `RISK` if:
  - permission is expired by `month_end`, or
  - any open `CRITICAL` issue exists, or
  - any active cycle remains open at `month_end`
- `WARNING` if:
  - any open non-critical issue exists, or
  - `watering_compliance_percent < 100`
- `HEALTHY` otherwise

### 2. Supervisor Activity Report

#### `belts_covered_count`

Count of distinct belts assigned to that supervisor for any overlapping period in the selected month.

#### `cycles_completed_count`

Count of completed cycles where:

- the supervisor was the responsible belt supervisor on the cycle close date

#### `average_cycle_duration_days`

Average of:

- `end_date - start_date`

for completed cycles attributed to that supervisor in the selected month

#### `active_days_count`

Count of `supervisor_attendance` rows where:

- `supervisor_user_id = current supervisor`
- `attendance_date BETWEEN month_start AND month_end`
- `status = PRESENT`

#### `required_watering_days`

Sum of reportable required watering days for belts historically attributed to that supervisor on those dates.

#### `completed_watering_days`

Sum of completed watering days for belts historically attributed to that supervisor on those dates.

#### `watering_compliance_percent`

If `required_watering_days > 0`:

- `(completed_watering_days / required_watering_days) * 100`

Else:

- `100`

#### `issues_raised_count`

Count of issues raised in the selected month by that supervisor.

### 3. Worker Activity Report

#### `present_days_count`

Count of `worker_daily_entries` rows where:

- `attendance_status = PRESENT`

#### `absent_days_count`

Count of `worker_daily_entries` rows where:

- `attendance_status = ABSENT`

#### `half_days_count`

Count of `worker_daily_entries` rows where:

- `attendance_status = HALF_DAY`

#### `active_days_count`

Count of selected-month `worker_daily_entries` rows where:

- `attendance_status IN (PRESENT, HALF_DAY)`

#### `daily_entries_count`

Count of all selected-month `worker_daily_entries` rows for that worker.

#### `assigned_tasks_count`

Count of distinct tasks linked through `task_worker_assignments` where:

- assignment overlaps the selected month

#### `completed_tasks_count`

Count of distinct tasks linked through `task_worker_assignments` where:

- task status is `COMPLETED` or `ARCHIVED`
- and `actual_close_date BETWEEN month_start AND month_end`

### 4. Advertisement Operations Monthly Report

#### `active_campaigns_count`

Count of campaigns linked to the site where the campaign overlaps the selected month and remains active during that overlap.

#### `completed_campaigns_count`

Count of campaigns linked to the site where:

- `actual_end_date BETWEEN month_start AND month_end`

#### `installations_completed_count`

Count of site-linked tasks where:

- `vertical_type = ADVERTISEMENT`
- task category is installation-type under final category vocabulary
- `status IN (COMPLETED, ARCHIVED)`
- `actual_close_date BETWEEN month_start AND month_end`

#### `maintenance_tasks_completed_count`

Count of site-linked tasks where:

- `vertical_type = ADVERTISEMENT`
- task category is maintenance or repair-type under final category vocabulary
- `status IN (COMPLETED, ARCHIVED)`
- `actual_close_date BETWEEN month_start AND month_end`

#### `open_issues_count`

Count of issues where:

- `site_id = current site`
- `status IN (OPEN, IN_PROGRESS)`

#### `monitoring_due_count`

Count of `site_monitoring_due_dates` rows where:

- `site_id = current site`
- `due_date BETWEEN month_start AND month_end`

#### `monitoring_completed_count`

Count of selected-month site due dates where:

- a qualifying monitoring completion exists for the same `site_id` and `due_date`

#### `monitoring_coverage_percent`

If `monitoring_due_count > 0`:

- `(monitoring_completed_count / monitoring_due_count) * 100`

Else:

- `100`

#### `free_media_added_count`

Count of `free_media_records` rows for the site where:

- `created_at BETWEEN month_start AND month_end`

#### `free_media_active_flag`

Set to `1` when the site has at least one `free_media_records` row in `CONFIRMED_ACTIVE` status that remains active at `month_end`.
Otherwise set to `0`.

## Export Rules

### File Names

Recommended export file names:

- `belt_health_summary_YYYY_MM.csv`
- `supervisor_activity_YYYY_MM.csv`
- `worker_activity_YYYY_MM.csv`
- `advertisement_operations_YYYY_MM.csv`

### Empty Reports

If no rows match:

- export headers only
- do not fail export

### Numeric Formatting

- counts export as integers
- percentages export as numeric values without `%`
- dates export in `YYYY-MM-DD`

## Query Consistency Rule

All dashboard cards, alert counts, preview counts, and CSV exports must reuse centralized query helpers or service formulas.
Do not re-implement the same formula independently in multiple controllers.
