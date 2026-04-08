# Skyte Ops Decisions Log

## Purpose

This file records the repo-facing decision set after product recovery.
It is intentionally shorter and cleaner than the pre-recovery log and should now mirror the recovered canon plus build specs.

Primary references:

- `docs/10_recovered_product/`
- `docs/11_build_specs/`

## Section 1: Product Constitution

### Decision 001 - Canonical Authority Shift

`docs/10_recovered_product` is the canonical product truth.
`docs/11_build_specs` is the canonical implementation-spec layer.
Legacy docs must mirror those layers rather than compete with them.

### Decision 002 - Product Scope

Skite Ops is a multi-domain internal operations platform covering:

- green belts
- advertisement sites and campaigns
- monitoring
- request-to-task execution
- authority proof governance
- commercial and management read models

### Decision 003 - Separate Masters

Green Belt Master and Advertisement Site Master remain separate surfaces and separate entity types.
Advertisement sites may reference green belts, but green belts are not advertisement assets.

## Section 2: Role And Governance Decisions

### Decision 004 - Ops Final Authority

Ops remains the final governance authority for approvals, assignments, closures, mappings, and overrides.

### Decision 005 - Role-Specific Landing

Login landing is role-specific, not generic.

### Decision 006 - Dynamic Role Constraint

Dynamic roles are allowed only through predefined permission groups and approved module scopes.
One role maps to one permission group in v1.

### Decision 007 - Supervisor Review Boundary

Green Belt Supervisors must not see authority review state or rejection outcome on their uploads.

### Decision 008 - Outsourced Separation

Outsourced belts remain outside internal watering, labour, and attendance compliance logic.

## Section 3: Green Belt Operations Decisions

### Decision 009 - Watering State Model

Watering uses explicit daily records for `DONE` and `NOT_REQUIRED`.
`PENDING` is derived from the absence of a row.

### Decision 010 - Watering Authority

Supervisors and Head Supervisor act on same-day watering only.
Ops override requires reason and audit.

### Decision 011 - Maintenance Cycle Model

Maintenance cycles are governed state with one active cycle per belt.
Hidden or expired belts can force controlled cycle closure.

### Decision 012 - Gardener And Night Guard Tracking

Gardener and night guards are not login roles.
They are tracked through separate daily counts in labour entries.

## Section 4: Upload, Issue, And Authority Decisions

### Decision 013 - Upload Parent Model

Uploads use the real parent context of `GREEN_BELT`, `SITE`, or `TASK`.
`parent_type = ISSUE` is not part of the recovered model.

### Decision 014 - Authority Visibility Model

Only approved green-belt work proof becomes authority-visible.
Issue uploads and other non-authority uploads are not authority-eligible.

### Decision 015 - Authority Share Helper

One-click WhatsApp helper sharing is allowed from approved authority context, but the system does not track fake send-state in v1.

### Decision 016 - Rejected Upload Cleanup

Rejected uploads become cleanup candidates after 30 days.
Purge removes file content but retains minimal metadata and purge markers.

### Decision 017 - GPS Handling

GPS is stored for Ops review only.
It must not block upload and does not use automatic mismatch logic in v1.

## Section 5: Request, Task, And Worker Decisions

### Decision 018 - Request Before Task

Sales, Client Servicing, and Media Planning create task requests.
Ops approves or rejects them before task creation.

### Decision 019 - Issue And Task Separation

Issues and tasks remain separate objects.
Task completion does not auto-close the issue.

### Decision 020 - Task Completion Proof

Task execution requires mandatory `AFTER_WORK` proof before completion handoff.
`BEFORE_WORK` proof is optional.

### Decision 021 - Worker Model

`worker_daily_entries` is the universal daily truth layer.
`task_worker_assignments` exists only for fabrication task occupancy.

## Section 6: Monitoring And Planning Decisions

### Decision 022 - Monitoring Plan Model

Monitoring planning is soft Ops-approved planning, not hard blocking assignment.

### Decision 023 - Monthly Due Truth

Monitoring due truth comes from Ops-selected monthly due dates per site.
Those dates can be copied forward and bulk-applied across sites or groups.

### Decision 024 - Custom Schedule Override

When a custom site schedule is chosen, it replaces the site's default cadence.

## Section 7: Reporting And Technical Decisions

### Decision 025 - Report Scope

Reports are calendar-month only and CSV-only in v1.
Per-user reports remain domain-scoped.

### Decision 026 - Technical Constraint

The product must remain practical for PHP, MySQL, and shared hosting.
Controllers handle HTTP concerns, services own business rules, repositories own data access, and RBAC stays in middleware.

## Change Rule

Any future structural change should update:

- the recovered canon where product intent changes
- the build-spec layer where implementation contract changes
- this repo-facing log where legacy mirrors need synchronized summary
