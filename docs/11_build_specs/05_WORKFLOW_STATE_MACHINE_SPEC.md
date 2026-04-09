# Workflow State Machine Spec

## Authority Note

- Purpose: Canonical implementation-spec document.
- Authority Level: Implementation truth.
- If Conflict: This file controls implementation behavior. `docs/10_recovered_product/*` controls product meaning and scope. Repo-facing mirror docs must be updated to match, not treated as competing truth.

## Purpose

This file freezes exact status models and allowed transitions for the main governed operational objects.
It exists so controllers, services, and UI do not invent different lifecycle logic.

## Source Docs

- `docs/10_recovered_product/03_WORKFLOWS_AND_LIFECYCLES.md`
- `docs/10_recovered_product/02_DOMAIN_AND_ENTITY_MODEL.md`
- `docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md`
- `docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md`

## Global Lifecycle Rules

- no hidden state changes
- no silent approval shortcuts
- every governed transition should be attributable to an actor or controlled maintenance process
- issue lifecycle is separate from task lifecycle
- upload existence lifecycle is separate from authority-visibility lifecycle
- derived dashboard or report states must not write back into operational truth

## 1. Upload Record Lifecycle

This state machine governs whether a physical upload still exists as a usable file.
It is separate from authority visibility.

### States

- `ACTIVE`
- `SOFT_DELETED`
- `PURGED`

### Persistence Shape

- `ACTIVE`: `is_deleted = 0`, `is_purged = 0`
- `SOFT_DELETED`: `is_deleted = 1`, `is_purged = 0`
- `PURGED`: `is_purged = 1`, file removed, minimal metadata retained

### Allowed Transitions

| From | To | Actor | Conditions | Required Side Effects |
|---|---|---|---|---|
| `ACTIVE` | `SOFT_DELETED` | upload creator on self-delete-capable page | within self-delete window, record not already purged | set `is_deleted`, `deleted_at`, `deleted_by_user_id`; hide from normal user-facing lists |
| `SOFT_DELETED` | `PURGED` | maintenance cleanup process | `deleted_at` older than self-delete purge threshold | remove file, set `is_purged`, `purged_at`; retain metadata row |
| `ACTIVE` | `PURGED` | Ops cleanup action | upload is rejected and older than rejected-upload cleanup threshold | remove file, set `is_purged`, `purged_at`, `purged_by_user_id`; retain metadata row |

### Not Allowed In V1

- `SOFT_DELETED -> ACTIVE`
- `PURGED -> ACTIVE`
- direct user-side permanent delete

## 2. Upload Authority Visibility Lifecycle

This state machine governs whether a work upload is visible in the authority portal.
It applies only to uploads where authority visibility matters.

### States

- `HIDDEN`
- `APPROVED`
- `REJECTED`
- `NOT_ELIGIBLE`

### Initial State Rules

- green-belt work proof starts as `HIDDEN`
- green-belt issue proof starts as `NOT_ELIGIBLE`
- monitoring proof starts as `NOT_ELIGIBLE`
- task proof starts as `NOT_ELIGIBLE`
- outsourced uploads start as `NOT_ELIGIBLE`

### Allowed Transitions

| From | To | Actor | Conditions | Required Side Effects |
|---|---|---|---|---|
| create | `HIDDEN` | system | green-belt work proof created | upload appears in Ops review queue |
| create | `NOT_ELIGIBLE` | system | upload is issue proof or non-authority domain proof | upload excluded from authority review queue |
| `HIDDEN` | `APPROVED` | Ops | eligible work proof only | set `reviewed_by_user_id`, `reviewed_at`; proof becomes visible to assigned authority rep |
| `HIDDEN` | `REJECTED` | Ops | eligible work proof only | set `reviewed_by_user_id`, `reviewed_at`; proof stays internal |
| `APPROVED` | `REJECTED` | Ops | re-review needed | update review metadata and remove authority visibility |
| `REJECTED` | `APPROVED` | Ops | re-review needed | update review metadata and make proof authority-visible |
| `APPROVED` | `HIDDEN` | Ops | temporary withdrawal or review correction | remove authority visibility without marking final rejection |
| `REJECTED` | `HIDDEN` | Ops | review reset | return upload to neutral hidden state |

### Not Allowed In V1

- `NOT_ELIGIBLE -> APPROVED`
- authority user changing visibility
- supervisor seeing visibility outcome

## 3. Watering Daily Record Model

Watering uses explicit rows plus one implicit derived state.

### States

- `DONE`
- `NOT_REQUIRED`
- `PENDING` derived from absence of a row for that belt and date

### Persistence Shape

- `DONE` and `NOT_REQUIRED` are stored rows in `watering_records`
- `PENDING` is not stored and must be derived

### Allowed Transitions

| From | To | Actor | Conditions | Required Side Effects |
|---|---|---|---|---|
| `PENDING` | `DONE` | assigned supervisor | same day, assigned belt | create watering row |
| `PENDING` | `DONE` | Head Supervisor | same day, maintained belt | create watering row |
| `PENDING` | `DONE` | Ops | override path | create row and audit reason if used as override |
| `PENDING` | `NOT_REQUIRED` | assigned supervisor | same day, assigned belt | create watering row with optional reason in v1 |
| `PENDING` | `NOT_REQUIRED` | Head Supervisor | same day, maintained belt | create watering row with optional reason in v1 |
| `PENDING` | `NOT_REQUIRED` | Ops | override path | create row and audit reason |
| `DONE` | `NOT_REQUIRED` | Ops | governed correction only | update row, retain audit |
| `NOT_REQUIRED` | `DONE` | Ops | governed correction only | update row, retain audit |

### Not Allowed In V1

- casual backdating by supervisors
- hidden stored `PENDING` rows

## 4. Issue Lifecycle

### States

- `OPEN`
- `IN_PROGRESS`
- `CLOSED`

### Initial State

- newly created issues start as `OPEN`

### Allowed Transitions

| From | To | Actor | Conditions | Required Side Effects |
|---|---|---|---|---|
| create | `OPEN` | system | issue created from upload or Ops input | issue enters issue list |
| `OPEN` | `IN_PROGRESS` | Head Supervisor | green-belt oversight scope only | lifecycle history and audit |
| `OPEN` | `IN_PROGRESS` | Ops | any allowed issue | lifecycle history and audit |
| `IN_PROGRESS` | `CLOSED` | Ops | issue resolved or accepted as closed | set close metadata |

### Side Behaviors

- task creation or linking may occur while issue is `OPEN` or `IN_PROGRESS`
- task completion does not auto-close the issue

### Not Allowed In V1

- Head Supervisor closing issues
- issue reopen flow

## 5. Task Request Lifecycle

### States

- `SUBMITTED`
- `APPROVED`
- `REJECTED`
- `CONVERTED`

### Initial State

- newly submitted requests start as `SUBMITTED`

### Allowed Transitions

| From | To | Actor | Conditions | Required Side Effects |
|---|---|---|---|---|
| create | `SUBMITTED` | Sales, Client Servicing, Media Planning, or other allowed requester | request page validation passes | request enters Ops queue |
| `SUBMITTED` | `APPROVED` | Ops | request accepted for execution | review metadata stored |
| `SUBMITTED` | `REJECTED` | Ops | request declined | review metadata and rejection reason stored |
| `APPROVED` | `CONVERTED` | Ops | task created from request | created task references request |

### Not Allowed In V1

- requester-side approval
- `REJECTED -> SUBMITTED`
- direct `SUBMITTED -> CONVERTED` without passing Ops review logic

## 6. Task Lifecycle

### States

- `OPEN`
- `RUNNING`
- `COMPLETED`
- `CANCELLED`
- `ARCHIVED`

### Meaning

- `OPEN`: task exists but execution has not been accepted as started
- `RUNNING`: execution is underway or completion proof has been submitted but Ops has not yet accepted final completion
- `COMPLETED`: Ops has accepted final completion proof
- `CANCELLED`: Ops stopped the task
- `ARCHIVED`: historical storage state after completion or cancellation

### Allowed Transitions

| From | To | Actor | Conditions | Required Side Effects |
|---|---|---|---|---|
| create | `OPEN` | Ops | task created directly or from request | task visible to assigned lead if assigned |
| `OPEN` | `RUNNING` | Ops | task starts operationally | lifecycle history |
| `OPEN` | `RUNNING` | assigned Fabrication Lead | execution starts on ground | lifecycle history |
| `RUNNING` | `COMPLETED` | Ops | required proof present and accepted | set completion metadata through normal task fields |
| `OPEN` | `CANCELLED` | Ops | task should not proceed | lifecycle history |
| `RUNNING` | `CANCELLED` | Ops | task halted after start | lifecycle history |
| `COMPLETED` | `ARCHIVED` | Ops | archival decision | set archive metadata |
| `CANCELLED` | `ARCHIVED` | Ops | archival decision | set archive metadata |

### Execution Notes

- assigned lead may upload proof, update progress, and record worker allocation
- assigned lead's "work done" action does not create a new final status in v1
- with current v1 enums, lead-side completion submission is represented inside `RUNNING`
- Ops review is what moves the task to `COMPLETED`

### Not Allowed In V1

- requester-side task status changes
- unassigned lead performing execution actions
- `COMPLETED -> RUNNING`
- `CANCELLED -> RUNNING`

## 7. Maintenance Cycle Lifecycle

### Logical States

- `ACTIVE`
- `CLOSED`

### Persistence Shape

- `ACTIVE`: `end_date IS NULL`
- `CLOSED`: `end_date IS NOT NULL`

### Allowed Transitions

| From | To | Actor | Conditions | Required Side Effects |
|---|---|---|---|---|
| create | `ACTIVE` | Head Supervisor | maintained belt, no active cycle exists | set `started_by_user_id`, `start_date` |
| create | `ACTIVE` | Ops | governed creation | set `started_by_user_id`, `start_date` |
| `ACTIVE` | `CLOSED` | Head Supervisor | normal cycle close | set `closed_by_user_id`, `end_date`, `close_reason` |
| `ACTIVE` | `CLOSED` | Ops | governed close | set `closed_by_user_id`, `end_date`, `close_reason` |
| `ACTIVE` | `CLOSED` | controlled service action | belt becomes hidden or permission expires while cycle is open | system-driven close reason plus audit trail |

### Not Allowed In V1

- reopening a closed cycle
- multiple active cycles per belt

## 8. Monitoring Due-Date Evaluation Model

Monitoring schedule truth is stored as due-date rows, not as a persisted state machine.

### Stored Truth

- `site_monitoring_due_dates` rows

### Derived Views

- `UPCOMING`
- `DUE_TODAY`
- `OVERDUE`
- `COMPLETED_FOR_DATE`

### Derived Evaluation Rule

- `UPCOMING`: due date is in the future
- `DUE_TODAY`: due date equals today and no qualifying monitoring proof exists for that site/date
- `OVERDUE`: due date is before today and no qualifying monitoring proof exists for that site/date
- `COMPLETED_FOR_DATE`: qualifying monitoring proof exists for that site/date

### Important Rule

- due status must be derived from stored due dates plus monitoring proof
- no separate persisted `monitoring_status` table should be created in v1

## 9. Free Media State Lifecycle

### States

- `DISCOVERED`
- `CONFIRMED_ACTIVE`
- `EXPIRED`
- `CONSUMED`

### Allowed Transitions

| From | To | Actor | Conditions | Required Side Effects |
|---|---|---|---|---|
| create | `DISCOVERED` | system or Ops | discovery proof or campaign-end origin exists | record source context |
| `DISCOVERED` | `CONFIRMED_ACTIVE` | Ops | business confirmation completed | set confirmation metadata |
| `CONFIRMED_ACTIVE` | `EXPIRED` | Ops or controlled maintenance logic | expiry reached or stale state confirmed | update expiry state |
| `CONFIRMED_ACTIVE` | `CONSUMED` | Ops | site no longer available because it is reused or sold | update final state |

### Discovery Source Rule

- when monitoring upload is submitted with discovery mode enabled, service logic must create or refresh a `DISCOVERED` free-media row for that site
- `source_reference_id` should point to a representative discovery upload row when the source is monitoring discovery

### Not Allowed In V1

- auto-confirming free media on campaign end

## Audit Expectations

These transitions must create audit entries:

- upload authority visibility changes
- task request approval or rejection
- task cancellation, completion, or archive
- cycle auto-close or override close
- watering overrides by Ops
- rejected upload purge
