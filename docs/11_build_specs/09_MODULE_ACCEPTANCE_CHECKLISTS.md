# Module Acceptance Checklists

## Authority Note

- Purpose: Canonical implementation-spec document.
- Authority Level: Implementation truth.
- If Conflict: This file controls implementation behavior. `docs/10_recovered_product/*` controls product meaning and scope. Repo-facing mirror docs must be updated to match, not treated as competing truth.

## Purpose

This file defines the acceptance gates for every major module in the recovered product.
A module is not "done" when its page renders or its table exists.
A module is done only when:

- schema shape matches the canonical roadmap
- route and payload behavior matches the API contract
- UI behavior matches the page spec
- lifecycle rules match the state-machine spec
- retention, report, settings, and audit effects are covered where relevant

Use this file with:

- `docs/11_build_specs/00_IMPLEMENTATION_MASTER_PLAN.md`
- `docs/11_build_specs/01_RBAC_PERMISSION_GROUP_SPEC.md`
- `docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md`
- `docs/11_build_specs/03_API_AND_ROUTE_CONTRACT.md`
- `docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md`
- `docs/11_build_specs/05_WORKFLOW_STATE_MACHINE_SPEC.md`
- `docs/11_build_specs/06_UPLOAD_STORAGE_RETENTION_SPEC.md`
- `docs/11_build_specs/07_REPORTS_ALERTS_AND_FORMULAS.md`
- `docs/11_build_specs/08_SYSTEM_SETTINGS_AND_EXTERNAL_ACTIONS.md`

## Global Acceptance Rules

These gates apply to every module.

- schema migration exists and matches the canonical schema roadmap
- controllers, services, and repositories exist for the required behavior
- routes follow the locked `index.php?route=module/action` contract
- record-scope filtering works in both list and detail views
- page behavior matches the field, filter, action, and empty-state rules
- forbidden controls are not merely disabled in UI; they are also blocked server-side
- governed mutations create audit entries where required
- no hidden lifecycle or approval shortcuts exist
- month-based reports and dashboard formulas are not re-implemented ad hoc in multiple places
- error responses use the standard JSON envelope
- manual verification covers happy path, no-data path, and access-denied path

## 1. Platform Foundation And RBAC

- login, logout, session bootstrap, and forced password reset follow the API contract
- seeded constitutional roles exist
- seeded permission groups exist
- one active permission-group mapping exists per role
- seeded role-module mappings exist and match the RBAC spec
- post-login landing resolves correctly for every seeded role
- menu generation is driven from allowed module scope
- direct URL access is blocked when module scope fails
- record-scope rules are enforced after module access succeeds
- dynamic role creation supports exactly one permission group and approved module keys only
- landing module validation rejects roles whose landing target is outside their own module scope
- user activation, deactivation, and restore flows work without breaking existing assignments
- audit logs capture user-management and role-governance mutations

## 2. Green Belt Core

- `green_belts`, `belt_supervisor_assignments`, `belt_authority_assignments`, and `maintenance_cycles` are implemented
- outsourced belt-assignment mapping exists for outsourced maintainer scoping
- `belt_code` is unique and used in list/detail views
- Green Belt Master supports create, edit, filter, and detail open behavior from the page spec
- Green Belt Detail shows legal state, configuration, assignment history, cycle history, uploads, and issues
- supervisor assignment history is date-aware and not reduced to one static field
- authority assignment history is date-aware and drives portal scope
- green belts remain separate from advertisement site master
- active-cycle uniqueness per belt is enforced
- hidden and permission-expiry state affect downstream behavior consistently
- governed cycle close behavior exists for hidden or expired belts
- relevant mutations write audit entries

## 3. Green Belt Field Operations

- Supervisor Upload supports assigned-belt selection only
- supervisor upload is low-friction and allows multi-photo submission
- GPS is captured silently when available and never blocks submission
- green-belt work uploads default to `HIDDEN`
- green-belt issue uploads default to `NOT_ELIGIBLE`
- Supervisor My Uploads shows only own recent uploads
- Supervisor My Uploads never reveals authority review status
- self-delete window is enforced at 5 minutes only
- watering uses explicit daily rows for `DONE` and `NOT_REQUIRED`
- `PENDING` is derived, not stored
- supervisor and Head Supervisor can act only on same-day watering
- Ops override path requires reason when outside normal flow
- Head Supervisor page supports attendance, watering, labour counts, and quick exceptions
- labour entries capture `labour_count`, `gardener_count`, and `night_guard_count`
- Head Supervisor can move issues from `OPEN` to `IN_PROGRESS` only within green-belt scope
- Ops-only actions remain unavailable to Head Supervisor and supervisors
- upload review queue filters and bulk review actions work for Ops
- issue uploads can never be approved for authority visibility
- audit entries exist for watering overrides, attendance overrides, and cycle overrides

## 4. Outsourced Flow

- outsourced maintainers can open only the outsourced upload surface
- outsourced maintainers can see only assigned outsourced belts
- assigned outsourced belts are enforced through explicit outsourced assignment data, not by reusing supervisor mappings
- outsourced uploads are kept separate from maintained-belt supervisor flows
- outsourced uploads default to `NOT_ELIGIBLE` for authority visibility
- outsourced flow does not expose watering, attendance, or labour controls
- outsourced activity does not count toward maintained-belt compliance dashboards
- Ops can review outsourced uploads through oversight views without mixing them into authority review

## 5. Task Requests, Tasks, And Fabrication Execution

- `task_requests` exists as a real pre-task object
- Sales, Client Servicing, and Media Planning can submit requests through the request page only
- request lifecycle supports `SUBMITTED`, `APPROVED`, `REJECTED`, and `CONVERTED`
- request approval and rejection are Ops-only
- task creation from request preserves request traceability
- tasks support the locked field set including progress and remarks
- task lifecycle supports `OPEN`, `RUNNING`, `COMPLETED`, `CANCELLED`, and `ARCHIVED`
- assigned lead can update progress and remarks only on assigned tasks
- assigned lead can mark work done only through the execution flow
- Ops performs final completion acceptance
- requester roles see read-only task progress only
- dedicated read-only task-progress routes exist and do not collide with execution-side progress-update routes
- requester roles never see task execution controls
- task cancellation and archive are Ops-only
- issue-to-task linking works without auto-closing the issue
- task detail enforces `AFTER_WORK` proof before lead completion handoff
- Call Ops helper appears only when the required setting is present
- governed task mutations create audit entries

## 6. Fabrication Worker Tracking

- `fabrication_workers`, `worker_daily_entries`, and fabrication-only `task_worker_assignments` are implemented
- worker resource list supports active and inactive workers
- one daily work entry per worker per date is enforced
- worker daily entries support attendance plus activity context
- task linkage in daily work entry is optional and works when present
- fabrication-only task-worker assignment supports occupancy planning for active tasks
- worker assignment actions are limited to Ops and the assigned Fabrication Lead
- "who is free today" is derived from worker activity plus active task assignment, not stored
- absent workers do not appear as available
- inactive workers do not appear as available
- worker activity report fields can be produced from implemented data without hand-made exceptions

## 7. Advertisement Site Master And Campaigns

- `sites`, `campaigns`, `campaign_sites`, and `free_media_records` are implemented
- Site And Asset Master supports the locked fields, filters, and validations
- advertisement sites can optionally reference a green belt, but remain separate entities
- campaign management supports create, edit, site linking, and mark-ended behavior
- campaign end does not auto-confirm free media
- Ops must explicitly confirm free-media transition
- free-media status uses governed state rather than ad hoc notes
- site detail and list queries can support monitoring plan, campaigns, and free-media views without duplicated site truth
- campaign and free-media decisions generate audit coverage where governed

## 8. Monitoring Plan And Monitoring Upload

- monitoring upload supports site selection, multi-photo upload, optional comment, and discovery mode
- monitoring upload stores GPS when available and never blocks if GPS is missing
- monitoring discovery mode creates or refreshes governed discovered free-media state
- monitoring history can filter discovery-mode uploads from stored upload metadata
- monitoring due truth is stored in `site_monitoring_due_dates`
- Ops can assign multiple due dates to one site in a month
- Ops can copy a site's due pattern into the next month
- Ops can bulk-copy a pattern across selected sites or a group like highway routes
- stored monthly due dates fully replace default cadence when a custom plan exists
- monitoring work is not blocked if Ops skips formal plan approval
- monitoring dashboard formulas correctly distinguish due today, completed today, and overdue
- qualifying completion for a due date is derived from same-day site uploads
- monitoring history pages and read-only downstream views reflect the same completion logic

## 9. Authority View And Summary

- authority record scope is driven by active belt-authority assignments
- authority users see only approved green-belt work proof
- authority users never see hidden, rejected, or `NOT_ELIGIBLE` uploads
- authority users never see issue uploads
- authority filters support date, belt, supervisor, and work type
- authority work-type filter is backed by stored upload `work_type`
- authority download uses only the currently filtered approved context
- WhatsApp helper can be generated only from approved filtered context
- helper output excludes internal notes and issue chatter
- summary text is generated on demand from approved proof
- summary text follows the locked date-wise and belt-wise format
- external share helper does not store fake "sent" or "delivered" truth
- Ops can use the same approved authority output safely without bypassing visibility rules

## 10. Reports, Alerts, And CSV Export

- report pages expose only the locked report set
- month is mandatory for report export and preview
- CSV is the only export format in v1
- exported headers match the recovered report model exactly
- report formulas reuse centralized query helpers or services
- archived tasks are included historically where required
- per-user reports remain domain-scoped
- Belt Health Summary derives watering compliance, cycle history, issue counts, and health status correctly
- Supervisor Activity report uses historical supervisor assignment, not current supervisor only
- Worker Activity report uses daily entries plus fabrication assignment data correctly
- Advertisement Operations report uses campaign, site, task, monitoring, and free-media data correctly
- dashboard cards and alert panels use the same underlying formulas as preview counts where appropriate
- empty reports export headers only and do not fail

## 11. System Settings And External Actions

- `system_settings` exists and includes the required seeded keys
- Ops can edit only allowed settings from the System Settings page
- API routes exist for settings read and update plus audit-log listing
- arbitrary key creation is not open in v1 unless deliberately enabled later
- Ops phone number is read from the settings service, not hardcoded in pages
- rejected-upload cleanup days and self-delete purge days resolve from the correct config/settings boundary
- Authority WhatsApp helper obeys the `authority_whatsapp_helper_enabled` toggle
- Call Ops helper is hidden when no Ops phone number exists
- helper endpoints enforce the same RBAC and record-scope rules as their source pages
- external helper actions do not mutate operational state by themselves
- settings reads are centralized through one settings service or equivalent helper

## 12. Rejected Upload Cleanup

- cleanup page shows only rejected uploads older than the configured threshold
- cleanup filters work by date, belt, and supervisor
- purge removes physical file content only
- purge retains minimal metadata row and sets purge markers
- purged uploads do not leak broken file links into UI
- non-eligible, approved, and non-rejected uploads do not appear in cleanup
- purge action is Ops-only
- purge action writes audit history

## Completion Rule

No module should be marked complete in project tracking until:

- its checklist items are satisfied
- its critical legacy doc targets have been rewritten or explicitly mapped to the new source of truth
- manual verification confirms role boundaries and edge cases
- any known deviation is written down as an explicit v1 deferral, not left as silent drift
