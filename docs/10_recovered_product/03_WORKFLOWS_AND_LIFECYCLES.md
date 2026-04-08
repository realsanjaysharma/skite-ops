# Recovered Workflows And Lifecycles

## Workflow Philosophy

The intended product supports low-friction field capture, Ops-governed review, shared data reuse, and explicit lifecycle transitions.

It should behave like real operations, not like a simplified software demo.

## Green Belt Work Proof Flow

1. Supervisor works on an assigned belt.
2. Supervisor uploads proof with optional work context and optional short comment.
3. System stores location metadata for Ops review.
4. Upload becomes immediately visible internally to Ops, Head Supervisor, and Management.
5. Upload does not automatically become authority-visible.
6. Ops reviews eligible proof and governs authority visibility.
7. Approved proof becomes visible to the assigned authority representative.

GPS rule:

- GPS is stored for Ops review
- upload must not be blocked because GPS looks wrong
- no automatic mismatch threshold is required in v1

## Watering Flow

1. Supervisor marks watering for assigned belts.
2. Head Supervisor may correct or complete same-day watering across maintained belts.
3. Ops may override under governed conditions with audit.
4. If a daily `Not Required` action exists, it should support a short reason.
5. In recovered transcript logic, that reason is optional in v1 and mandatory later.
6. Watering remains separate from photo proof and should not be inferred blindly from uploads.

## Maintenance Cycle Flow

1. Head Supervisor starts a maintenance cycle.
2. Cycle remains active while work is ongoing.
3. Head Supervisor or Ops closes the cycle under governed rules.
4. Hidden or expired belt states can force controlled auto-close behavior.

## Authority-Proof Flow

1. Green-belt work proof is uploaded.
2. Ops reviews whether it is valid for authority visibility.
3. Issue uploads are excluded from authority proof.
4. Approved work proof becomes visible inside the authority portal.
5. Authorized Person access is controlled through visibility, not duplicate file creation.
6. Authority representatives filter, download, and manually share externally.
7. One-click WhatsApp sharing is helper UX with a pre-filled message; a human still presses Send.

## Authority Summary Flow

The system is intended to support date-wise and belt-wise authority summary output based on approved proof.
This is a curated operational view, not a raw dump of uploads.

Recovered locking note:

- the final in-system model is visibility control plus portal access
- external sharing itself happens outside the system
- the system does not need fake "shared externally" truth if visibility control is the real governance point
- summary output is end-of-day oriented rather than continuously final during the day
- summary content is text-only, belt-wise, and limited to authority-relevant work done

## Issue Flow

1. Issue evidence is uploaded or otherwise detected.
2. Issue enters Ops review.
3. Ops prioritizes and governs the issue lifecycle.
4. Ops may link the issue to a task if execution work is required.
5. Issue remains under Ops governance until final closure.

## Request-To-Task Flow

1. Sales, Client Servicing, or Media Planning raises a structured request.
2. Request enters the Ops queue.
3. Ops reviews the request.
4. Ops either rejects it with reason or approves it.
5. Approved request converts into task.

## Task Execution Flow

1. Ops assigns task to the execution side.
2. Fabrication or installation lead sees the task in My Tasks.
3. Lead can allocate one or more tracked fabrication workers against the task.
4. Execution happens on ground.
5. Lead uploads mandatory After Work proof and optional Before Work proof.
6. Lead can use a one-tap Call Ops shortcut when needed.
7. Lead marks work done.
8. Task enters final Ops review.
9. Ops verifies and closes.

## Worker Allocation And Availability Flow

1. Ops assigns a task to a Fabrication Lead.
2. Lead records which workers are assigned to that task.
3. Worker allocation feeds fabrication workload visibility.
4. `worker_daily_entries` remains the main daily truth layer.
5. "Who is free today" is derived using daily entries plus fabrication assignment context where needed.
6. Monthly worker activity reports are derived from the same daily and assignment history.

## Monitoring Proof Flow

1. Monitoring team selects site and context.
2. Monitoring team uploads proof and optional comment.
3. Proof becomes available internally for Ops, Sales, Client Servicing, and planning use.
4. The system does not auto-send proof to clients.
5. Commercial teams manually reuse and share the controlled proof outward.

## Monitoring Planning And Due Flow

1. Ops selects each site's monitoring due dates in advance for the month.
2. A site may have multiple due dates within the same month.
3. Ops can copy the same due-date pattern into the next month and adjust it where needed.
4. Ops can also bulk-apply the same due-date pattern across multiple selected sites or groups such as highway routes.
5. The selected dates become the operational due truth for each affected site.
6. The system can still present a suggested plan or due list, but that list must come from the stored monthly due dates rather than guessed cadence formulas.
7. Monitoring work is not blocked if Ops skips formal approval of a suggested plan.

## Role Landing Flow

After login, users land on their role-relevant surface instead of a generic landing page.

Examples:

- Ops -> Master Operations Dashboard
- Head Supervisor -> Supervisor Attendance and Watering Oversight
- Green Belt Supervisor -> Supervisor Upload
- Outsourced Maintainer -> Outsourced Upload
- Fabrication Lead -> My Tasks
- Sales -> read-only assigned task progress page
- Client Servicing -> read-only assigned task progress page
- Media Planning -> read-only assigned task progress page
- Authority Representative -> Authority View

## Free Media Discovery Flow

1. Monitoring identifies possible free or available media.
2. Discovery proof is uploaded.
3. Media Planning gains visibility into discovered opportunities.
4. Ops retains governance where business-state transitions matter.

## Campaign End To Free Media Transition Flow

1. A campaign ends.
2. The site does not automatically become free media.
3. Ops checks with business stakeholders about extension or renewal.
4. Only then does Ops confirm free-media state.

## Outsourced Belt Flow

1. Outsourced maintainer logs in.
2. Outsourced maintainer sees only assigned outsourced belts.
3. Work proof or issue proof is uploaded.
4. Ops uses the uploads for oversight.
5. Normal internal watering and compliance logic does not apply.

## Internal Read-Only Consumption Flow

### Sales And Client Servicing

- consume monitoring and client-facing proof
- filter, download, and manually share outward
- raise requests back into Ops

### Media Planning

- consume site, monitoring, and free-media visibility
- use the data for planning
- raise requests back into Ops

### Authority Representatives

- consume approved green-belt proof only
- do not approve, edit, or upload

### Management

- reads across the system in a visibility-only capacity

## Upload Review Feedback Boundary

- supervisors remain upload-focused field users
- they should not see authority review status
- they should not see rejected or hidden review outcome labels
- Ops and authority governance remain separate from supervisor-facing UX

## Rejected Upload Cleanup Flow

1. Rejected uploads remain hidden from authority access.
2. Rejected uploads stay visible to Ops for review and governance.
3. Rejected uploads become manual cleanup candidates after 30 days.
4. Ops can use a dedicated cleanup page to bulk permanently delete eligible rejected uploads while retaining minimal governance-safe metadata where required.

## Lifecycle Rules

- requests do not become tasks automatically
- upload review is separate from upload creation
- issue lifecycle is separate from task lifecycle
- execution completion is separate from final Ops closure
- authority visibility is separate from raw proof existence
- outsourced oversight is separate from maintained-belt compliance
- per-user reporting stays domain-scoped rather than uncontrolled cross-vertical visibility
