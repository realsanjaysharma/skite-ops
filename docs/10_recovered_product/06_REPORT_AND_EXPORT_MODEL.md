# Recovered Report And Export Model

## Purpose

This file freezes the recovered reporting model strongly enough for schema design, query design, and CSV export implementation.

Reports are operational and calendar-month based.
They are not meant to become vague analytics dashboards with shifting formulas.

## Global Reporting Rules

- reporting uses calendar month only
- export format is CSV only in v1
- PDF generation is out of scope in v1
- archived tasks are included in historical reports
- per-user reports remain domain-scoped
- no cross-vertical report leakage is allowed
- reports are historical views for the selected month, not casual current-state snapshots

## 1. Belt Health Summary

### Purpose

High-level monthly condition overview for green belts.

### Access

- Ops
- Management

### Filters

- month (required)
- zone (optional)
- supervisor (optional, resolved through historical assignment for the selected month)

### Grouping Level

One row per belt for the selected month.

### Sorting

- zone ascending
- belt_code ascending

### Inclusion Rules

- include green belts that were relevant in the selected month
- keep hidden indicator visible where applicable
- outsourced belts may appear only if explicitly included by later filter behavior, but watering/compliance logic remains internal-maintenance-only
- compliance calculations must only use reportable internal-maintenance periods for that belt

### CSV Columns

- report_month
- belt_id
- belt_code
- belt_name
- zone
- responsible_supervisor_name
- maintenance_mode
- hidden_flag
- cycles_completed_count
- days_since_last_completion
- required_watering_days
- completed_watering_days
- watering_compliance_percent
- open_issues_count
- health_status

## 2. Supervisor Activity Report

### Purpose

Supervisor rotation and accountability report for green-belt operations.

### Access

- Ops
- Management

### Filters

- month (required)
- supervisor (optional)

### Grouping Level

One row per green-belt supervisor for the selected month.

### Sorting

- supervisor_name ascending

### Inclusion Rules

- report is limited to green-belt supervisor activity
- use historical belt assignment resolution for the selected month
- no outsourced-maintainer activity is mixed into this report
- no cross-vertical work should leak into this report

### CSV Columns

- report_month
- supervisor_id
- supervisor_name
- belts_covered_count
- cycles_completed_count
- average_cycle_duration_days
- active_days_count
- required_watering_days
- completed_watering_days
- watering_compliance_percent
- issues_raised_count

## 3. Worker Activity Report

### Purpose

Monthly fabrication-worker activity visibility based on daily entries and fabrication task assignment context.

### Access

- Ops
- Management

### Filters

- month (required)
- worker (optional)
- worker_skill_tag (optional)

### Grouping Level

One row per worker for the selected month.

### Sorting

- worker_name ascending

### Inclusion Rules

- this report is fabrication-worker focused
- worker activity comes from `worker_daily_entries`
- fabrication task context may enrich counts through `task_worker_assignment`
- no cross-vertical user activity should leak into this report
- archived tasks still count historically where relevant

### CSV Columns

- report_month
- worker_id
- worker_name
- worker_skill_tag
- present_days_count
- absent_days_count
- half_days_count
- active_days_count
- daily_entries_count
- assigned_tasks_count
- completed_tasks_count

## 4. Advertisement Operations Monthly Report

### Purpose

Monthly operational visibility for advertisement sites, monitoring coverage, campaign activity, tasks, issues, and free-media movement.

### Access

- Ops
- Management

### Filters

- month (required)
- site_category (optional)
- route_or_group (optional)
- client_or_campaign (optional)

### Grouping Level

One row per advertisement site for the selected month.

### Sorting

- site_category ascending
- site_code ascending

### Inclusion Rules

- report is site-based, not belt-based
- advertisement boards inside green belts remain advertisement assets here
- archived tasks still count historically where relevant
- monitoring values must use the stored monthly due-date schedule for that site

### CSV Columns

- report_month
- site_id
- site_code
- location_text
- site_category
- lighting_type
- green_belt_reference_code
- active_campaigns_count
- completed_campaigns_count
- installations_completed_count
- maintenance_tasks_completed_count
- open_issues_count
- monitoring_due_count
- monitoring_completed_count
- monitoring_coverage_percent
- free_media_added_count
- free_media_active_flag

## Export Notes

### File Naming

Recommended CSV filename pattern:

- `belt_health_summary_YYYY_MM.csv`
- `supervisor_activity_YYYY_MM.csv`
- `worker_activity_YYYY_MM.csv`
- `advertisement_operations_YYYY_MM.csv`

### Empty-State Rule

If filters return no rows, export should still produce a valid CSV with headers only.

### Numeric Formatting

- percentages should export as numeric values, not decorated strings
- counts should export as integers
- dates should use ISO-style `YYYY-MM-DD` where individual dates are included
