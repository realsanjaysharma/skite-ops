# Skyte Ops Requirements Context

## Authority Note

- Purpose: Repo-facing mirror for onboarding and navigation.
- Authority Level: Mirror only.
- If Conflict: `docs/10_recovered_product/*` wins on product meaning and `docs/11_build_specs/*` wins on implementation behavior.

## Purpose

This file records the real-world operating context the product must respect.
It has been rewritten from the recovered transcript truth and should now be read as a synchronized legacy mirror, not as a separate source of product scope.

Primary references:

- `docs/10_recovered_product/00_FINAL_PRODUCT_BEHAVIOR_MODEL.md`
- `docs/10_recovered_product/03_WORKFLOWS_AND_LIFECYCLES.md`
- `docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md`

## Business Reality

Skite operates green-belt maintenance and outdoor advertising at the same time.
Monitoring, fabrication, commercial coordination, and authority-facing proof all overlap with those operations.

The system must therefore support:

- legal and operational green-belt management
- advertisement site and campaign management
- monitoring proof collection and planning
- fabrication and installation execution
- authority-ready proof visibility
- client and planning-facing read models

## Green Belt Ground Reality

### Team Shape

- Green Belt Supervisors perform field work and upload proof
- one Head Supervisor oversees same-day watering, attendance, labour, and cycle handling
- outsourced maintainers handle some belts outside internal compliance logic
- daily labour is tracked as counts, not as login users
- gardener and night guards are tracked as separate daily counts, not login roles

### Belt Work Pattern

Typical green-belt work includes:

- watering
- cleaning
- trimming and routine upkeep
- seasonal or need-based maintenance
- issue and damage response

Watering is separate from photo proof and should not be inferred only from uploads.

### Evidence Pattern

Field evidence is noisy and imperfect.
Supervisors need low-friction uploads with minimal required inputs.
They must not be forced into heavy tagging or approval-aware workflows.

Supervisors:

- can upload work proof or issue proof
- can mark same-day watering on assigned belts
- can see their own recent uploads
- cannot see authority review outcome or rejection badges

## Head Supervisor Reality

Head Supervisor is operationally important but still below Ops.

The role exists to:

- mark supervisor attendance
- oversee same-day watering across maintained belts
- enter labour counts
- start and close maintenance cycles
- move green-belt issues from `OPEN` to `IN_PROGRESS`

The role does not approve uploads, close issues, or govern advertisement modules.

## Outsourced Belt Reality

Outsourced belts remain legally relevant but operationally separate from internal compliance.

For outsourced belts:

- uploads and issue reporting exist
- oversight exists
- watering compliance does not
- internal attendance and labour logic do not

## Advertisement And Task Reality

Advertisement execution work is task-driven.
Tasks may come from requests, issues, monitoring findings, client needs, or Ops decisions.

The field reality still resembles an Excel-style execution model:

- work description
- assigned by
- assigned lead
- progress percentage
- remarks
- completion note

Fabrication workers do not receive normal system logins.

The product tracks worker reality through:

- `worker_daily_entries` as the universal daily truth layer
- fabrication-only `task_worker_assignments` for task occupancy and "who is free today" visibility

## Monitoring Reality

Monitoring is site-based and operationally planned, but it should not become a rigid blocking workflow.

Recovered behavior now locks this model:

- Ops selects monthly due dates in advance for each site
- a site can have multiple due dates in a month
- the same pattern can be copied next month
- the same pattern can be bulk-applied across multiple sites or groups such as highway routes
- stored due dates are the real due truth
- monitoring can still continue even if Ops skips formal approval of the suggested plan

## Authority Reporting Reality

Authority reporting is governed visibility, not duplicate data storage.

Recovered rules:

- authority representatives see only approved green-belt work proof
- issue uploads never enter authority-ready output
- authority users filter by date, belt, supervisor, and work type
- download and WhatsApp helper sharing are supported
- the system prepares safe output, but humans still send it

## Commercial And Planning Reality

Sales, Client Servicing, and Media Planning are not execution-control roles.

They need:

- monitoring and proof visibility
- free-media visibility
- request submission to Ops
- read-only progress views for tasks linked to their requests, campaigns, or clients

## Management Reality

Management wants:

- broad visibility
- monthly reporting
- clean summaries
- no operational UI complexity

Management does not need mutation power.

## Product Design Implications

The system must remain:

- mobile-practical
- low-friction for field users
- audit-safe for governance
- explicit in status and lifecycle logic
- realistic about imperfect evidence
- careful not to over-automate decisions that Ops should still govern
