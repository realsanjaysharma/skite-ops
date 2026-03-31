# Recovered Workflows And Lifecycles

## Workflow Philosophy

The intended product supports low-friction field capture, Ops-governed review, shared data reuse, and explicit lifecycle transitions.

It should behave like real operations, not like a simplified software demo.

## Green Belt Work Proof Flow

1. Supervisor works on an assigned belt.
2. Supervisor uploads proof with optional work context and optional short comment.
3. Upload becomes immediately visible internally to Ops, Head Supervisor, and Management.
4. Upload does not automatically become authority-visible.
5. Ops reviews eligible proof and governs authority visibility.
6. Approved proof becomes visible to the assigned authority representative.

## Watering Flow

1. Supervisor marks watering for assigned belts.
2. Head Supervisor may correct or complete same-day watering across maintained belts.
3. Ops may override under governed conditions with audit.
4. Watering remains separate from photo proof and should not be inferred blindly from uploads.

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
5. Authority representatives filter, download, and manually share externally.

## Authority Summary Flow

The system is intended to support date-wise and belt-wise authority summary output based on approved proof.
This is a curated operational view, not a raw dump of uploads.

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
3. Execution happens on ground.
4. Lead uploads completion proof and marks work done.
5. Task enters final Ops review.
6. Ops verifies and closes.

## Monitoring Proof Flow

1. Monitoring team selects site and context.
2. Monitoring team uploads proof and optional comment.
3. Proof becomes available internally for Ops, Sales, Client Servicing, and planning use.
4. The system does not auto-send proof to clients.
5. Commercial teams manually reuse and share the controlled proof outward.

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

## Lifecycle Rules

- requests do not become tasks automatically
- upload review is separate from upload creation
- issue lifecycle is separate from task lifecycle
- execution completion is separate from final Ops closure
- authority visibility is separate from raw proof existence
- outsourced oversight is separate from maintained-belt compliance
