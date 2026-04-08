# Page Field And Action Spec

## Purpose

This file locks page-level functional behavior so UI, controllers, services, and schema do not drift.
It does not define visual design.
It does define:

- who can open a page
- which records appear there
- fields and filters
- main actions
- validations
- empty-state behavior

## Global Page Rules

- dashboards are summary and navigation surfaces, not overloaded action pages
- record visibility always follows role scope first, then page behavior
- field-entry pages must stay low-friction
- review states hidden from a role must not leak through table columns, badges, or empty-state hints
- table columns listed here are the required minimum, not the maximum possible future set
- if a role cannot mutate a record, its detail view must be visually read-only

## 1. Master Operations Dashboard

### Access

- `OPS_MANAGER`

### Landing

- default landing for Ops

### Required Summary Cards

- green-belt operational status
- monitoring due and completed pressure
- open tasks by status
- open issues by priority
- pending authority upload review
- campaigns ending soon
- free-media pressure

### Actions

- open domain dashboards
- open upload review
- open task management
- open issue management
- open monitoring plan

### Rules

- no direct mutation from dashboard cards
- counts must be derived, not hardcoded or manually maintained

## 2. Green Belt Dashboard

### Access

- `OPS_MANAGER`
- `HEAD_SUPERVISOR`

### Shows

- maintained belts needing attention
- same-day watering visibility
- active cycles
- open green-belt issues
- upload activity

### Filters

- zone
- supervisor
- maintenance mode

## 3. Advertisement Dashboard

### Access

- `OPS_MANAGER`
- `MANAGEMENT`

### Shows

- open advertisement tasks
- campaigns ending soon
- sites with open issues
- free-media counts

## 4. Monitoring Dashboard

### Access

- `OPS_MANAGER`
- `MANAGEMENT`

### Shows

- due today count
- completed today count
- overdue due-date count
- free-media discovery activity

## 5. Management Dashboard

### Access

- `MANAGEMENT`

### Landing

- default landing for Management

### Shows

- read-only cross-domain summaries
- dashboard cards only
- links into allowed read-only dashboards, summary drilldowns, and reports

### Rules

- no mutation controls

## 6. Green Belt Master

### Access

- `OPS_MANAGER`

### Layout

- list view with filters
- create/edit form

### Filters

- zone
- permission status
- maintenance mode
- hidden flag
- supervisor

### Required Table Columns

- belt_code
- common_name
- authority_name
- zone
- permission_status
- maintenance_mode
- is_hidden

### Form Fields

- belt_code
- common_name
- authority_name
- zone
- location_text
- latitude
- longitude
- permission_start_date
- permission_end_date
- permission_status
- maintenance_mode
- watering_frequency
- is_hidden

### Actions

- create belt
- edit belt
- open detail

### Validations

- `belt_code` required and unique
- `common_name` required
- `permission_status` required
- if coordinates are captured, both latitude and longitude should be present

## 7. Green Belt Detail

### Access

- `OPS_MANAGER`
- `HEAD_SUPERVISOR` in scoped form

### Required Sections

- identity and legal state
- configuration
- supervisor assignment history
- authority assignment history
- watering history
- maintenance cycle history
- uploads
- issues

### Actions

- start cycle
- close cycle
- open issue
- open upload review from relevant upload set

### Rules

- Head Supervisor must not see Ops-only governance controls

## 8. Supervisor Upload

### Access

- `GREEN_BELT_SUPERVISOR`

### Landing

- default landing for green-belt supervisors

### Form Fields

- assigned belt selector
- one or more photos
- upload type
- optional work type when `upload_type = WORK`
- optional comment
- optional issue yes/no helper if UI keeps it separate from upload type

### Actions

- submit upload

### Validations

- only currently assigned belts allowed
- at least one photo required
- GPS metadata captured silently when available
- authority review result must not be shown after submission

### Empty State

- if no belts are assigned, show a clear no-assignment message and no upload action

## 9. Supervisor My Uploads

### Access

- `GREEN_BELT_SUPERVISOR`

### Shows

- own recent uploads only

### Required Columns

- created_at
- belt_name
- upload_type
- comment preview

### Actions

- soft delete within self-delete window only

### Rules

- no approval badge
- no rejected badge
- no authority-visibility status

## 10. Outsourced Upload

### Access

- `OUTSOURCED_MAINTAINER`

### Landing

- default landing for outsourced maintainer

### Form Fields

- assigned outsourced belt selector
- one or more photos
- upload type
- optional work type when `upload_type = WORK`
- optional comment

### Actions

- submit upload

### Validations

- only assigned outsourced belts allowed
- watering controls must not appear

## 11. Supervisor Attendance And Watering Oversight

### Access

- `HEAD_SUPERVISOR`
- `OPS_MANAGER` in broader form

### Landing

- default landing for Head Supervisor

### Required Sections

- same-day supervisor attendance grid
- same-day watering grid for maintained belts
- labour entry panel
- quick exceptions list

### Attendance Grid Fields

- supervisor_name
- attendance_status
- marked_by
- marked_at

### Watering Grid Fields

- belt_code
- belt_name
- supervisor_name
- watering_status
- reason_text
- marked_by
- marked_at

### Actions

- mark attendance
- mark same-day watering
- mark `NOT_REQUIRED`
- enter labour counts
- open related cycle controls

### Validations

- Head Supervisor can only act on current-day records
- Ops overrides require reason text
- `NOT_REQUIRED` reason is optional in v1

## 12. Maintenance Cycle Controls

### Access

- `HEAD_SUPERVISOR`
- `OPS_MANAGER`

### Shows

- active cycles
- recently closed cycles

### Required Columns

- belt_code
- belt_name
- start_date
- open_days_count
- started_by

### Actions

- start cycle
- close cycle

### Validations

- only one active cycle per belt at a time

## 13. Upload Review

### Access

- `OPS_MANAGER`

### Shows

- green-belt review queue

### Filters

- date
- belt
- supervisor
- upload type
- authority visibility state

### Required Columns

- thumbnail
- upload_id
- created_at
- belt_name
- supervisor_name
- upload_type
- authority_visibility

### Actions

- approve
- reject
- bulk approve
- bulk reject

### Rules

- issue uploads cannot become `APPROVED`
- rejected state remains internal

## 14. Issue Management

### Access

- `OPS_MANAGER`
- `HEAD_SUPERVISOR` with limited transitions

### Filters

- status
- priority
- source type
- belt
- site

### Required Columns

- issue_id
- title
- priority
- status
- source_type
- belt_or_site_reference
- linked_task_id

### Actions

- `OPEN -> IN_PROGRESS`
- create task
- link task
- close issue

### Rules

- Head Supervisor may move `OPEN -> IN_PROGRESS`
- only Ops may close

## 15. Authority View

### Access

- `AUTHORITY_REPRESENTATIVE`

### Landing

- default landing for authority representatives

### Filters

- date
- belt
- supervisor
- work type

### Required Sections

- present-day approved proof
- filtered history
- date-wise summary block

### Required Columns Or Tiles

- upload date
- belt name
- supervisor name
- work type from stored upload metadata
- proof thumbnail

### Actions

- download filtered view
- one-click WhatsApp helper share

### Rules

- approved work proof only
- no issue uploads
- no hidden or rejected proof
- no internal notes
- work-type filtering must use stored upload `work_type`, not comment parsing

## 16. Site And Asset Master

### Access

- `OPS_MANAGER`

### Filters

- site_category
- lighting_type
- is_active
- route_or_group
- green_belt_reference

### Required Columns

- site_code
- location_text
- site_category
- lighting_type
- route_or_group
- green_belt_reference
- is_active

### Form Fields

- site_code
- location_text
- site_category
- green_belt_id
- route_or_group
- ownership_name
- board_type
- lighting_type
- latitude
- longitude
- is_active

### Actions

- create site
- edit site
- open campaign management
- open monitoring plan

### Validations

- `site_code` required and unique
- `site_category` required
- `lighting_type` required

## 17. Campaign Management

### Access

- `OPS_MANAGER`

### Filters

- status
- client_name
- site_category

### Required Columns

- campaign_code
- client_name
- campaign_name
- status
- start_date
- expected_end_date
- linked_sites_count

### Form Fields

- campaign_code
- client_name
- campaign_name
- start_date
- expected_end_date
- status
- linked site selection

### Actions

- create campaign
- edit campaign
- link sites
- mark ended
- confirm free-media transition

### Rules

- ending a campaign must not automatically create confirmed free media

## 18. Monitoring Upload

### Access

- `MONITORING_TEAM`

### Landing

- default landing for monitoring team

### Form Fields

- site selector
- one or more photos
- optional comment
- discovery mode toggle

### Actions

- submit upload
- open navigation helper

### Validations

- site required
- at least one photo required
- discovery mode must create or refresh governed discovered free-media state, not just a photo row
- GPS metadata stored when available

## 19. Monitoring Plan

### Access

- `OPS_MANAGER`

### Required Views

- month selector
- site list
- monthly calendar picker
- bulk-apply panel

### Filters

- month
- site_category
- route_or_group
- lighting_type

### Required Columns

- site_code
- location_text
- site_category
- lighting_type
- route_or_group
- selected_due_dates_count

### Actions

- select multiple due dates for one site
- clear selected due dates for one site
- copy same pattern into next month
- bulk-copy pattern across selected sites
- bulk-copy pattern across a group
- mark plan approved if Ops chooses to use approval flow

### Rules

- stored monthly due dates are the operational due truth
- monitoring work must not be blocked if formal plan approval is skipped

## 20. Monitoring History

### Access

- `OPS_MANAGER`
- `MONITORING_TEAM`
- scoped read access for commercial roles if exposed through their own pages

### Filters

- date range
- site
- site_category
- client_or_campaign
- discovery mode

### Required Columns

- created_at
- site_code
- location_text
- comment preview
- upload count

### Rules

- discovery-mode filtering must use stored upload discovery metadata, not inferred comments

## 21. Free And Available Media Page

### Access

- `OPS_MANAGER`
- `MEDIA_PLANNING`
- optional read access for Sales and Client Servicing if later enabled

### Filters

- site_category
- route_or_group
- status
- expiry window

### Required Columns

- site_code
- location_text
- source_type
- status
- discovered_date
- confirmed_date
- expiry_date

### Actions

- open site
- raise request

### Rules

- only confirmed active rows should be treated as current free media

## 22. Raise Request Page

### Access

- `SALES_TEAM`
- `CLIENT_SERVICING`
- `MEDIA_PLANNING`

### Form Fields

- request_type
- client_name
- campaign
- site
- belt
- description

### Actions

- submit request

### Validations

- description required
- at least one operational context field must be present

## 23. Task Management

### Access

- `OPS_MANAGER`

### Filters

- status
- priority
- vertical_type
- assigned_lead
- client_name
- campaign

### Required Columns

- task_id
- work_description
- vertical_type
- assigned_lead
- status
- progress_percent
- expected_close_date

### Actions

- create task
- convert request to task
- assign lead
- reassign lead
- archive task
- open task detail

### Rules

- task creation from a request should retain request traceability

## 24. Task Detail

### Access

- `OPS_MANAGER`
- assigned `FABRICATION_LEAD`

### Required Sections

- task metadata
- request or issue context
- proof history
- worker allocation
- progress and remarks

### Required Fields

- work_description
- location_text
- assigned_by
- assigned_lead
- priority
- start_date
- expected_close_date
- status
- progress_percent
- remark_1
- remark_2
- completion_note

### Actions

- upload `AFTER_WORK` proof
- upload `BEFORE_WORK` proof
- record worker allocation
- mark work done
- use Call Ops helper

### Validations

- `AFTER_WORK` proof required before completion
- only assigned lead may perform execution actions
- progress must remain `0-100`

## 25. Fabrication Lead My Tasks

### Access

- `FABRICATION_LEAD`

### Landing

- default landing for fabrication lead

### Required Columns

- task_id
- work_description
- location_text
- priority
- status
- progress_percent
- expected_close_date

### Actions

- open task detail
- open worker allocation

## 26. Assigned Task Progress Page

### Access

- `SALES_TEAM`
- `CLIENT_SERVICING`
- `MEDIA_PLANNING`

### Landing

- default landing for these roles

### Filters

- status
- client
- campaign
- site
- date range

### Required Columns

- task_id
- work_description
- location_text
- assigned_lead
- status
- progress_percent
- expected_close_date

### Actions

- open read-only task detail
- open request intake

### Rules

- page is read-only for task execution
- no task edit, assignment, or completion controls

## 27. User Management

### Access

- `OPS_MANAGER`

### Required Columns

- full_name
- email
- role_name
- is_active
- force_password_reset

### Actions

- create user
- deactivate user
- reactivate user
- reset password

## 28. Access And Mapping Control

### Access

- `OPS_MANAGER`

### Required Mapping Surfaces

- belt to supervisor
- belt to authority representative
- belt to outsourced maintainer
- role to allowed modules

### Actions

- create assignment
- close assignment
- edit future assignment

## 29. Audit Log Viewer

### Access

- `OPS_MANAGER`

### Filters

- actor
- entity_type
- date range
- action_type

### Required Columns

- created_at
- actor_name
- action_type
- entity_type
- entity_id
- override_reason

## 30. Reports

### Access

- `OPS_MANAGER`
- `MANAGEMENT`

### Required Report Options

- Belt Health Summary
- Supervisor Activity
- Worker Activity
- Advertisement Operations Monthly

### Common Filters

- month required
- domain-specific optional filters

### Actions

- preview row count
- export CSV

### Rules

- calendar month only
- CSV only in v1
- archived tasks included historically where relevant

## 31. System Settings

### Access

- `OPS_MANAGER`

### Required Setting Areas

- Ops phone number
- upload retention values
- approved helper toggles
- free-media expiry defaults

### Actions

- edit allowed setting values

### Rules

- arbitrary key creation should not be open in v1 unless explicitly enabled later

## 32. Rejected Uploads Cleanup

### Access

- `OPS_MANAGER`

### Filters

- date range
- belt
- supervisor

### Required Columns

- upload_id
- created_at
- belt_name
- supervisor_name
- rejection_age_days

### Actions

- bulk purge eligible rejected uploads

### Rules

- only rejected uploads older than cleanup threshold appear
- purge keeps minimal metadata and purge markers
