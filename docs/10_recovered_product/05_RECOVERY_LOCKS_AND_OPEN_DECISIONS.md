# Recovery Locks And Open Decisions

## Purpose

This file holds two things:

- recovery clarifications that are now locked strongly enough to stop drift
- the smaller set of items that still need explicit final locking

No one should silently guess these items during implementation.

If a workflow depends on one of these decisions, lock it in docs first and only then move it into schema or code.

## Locked Recovery Clarifications

### 1. Dynamic Role Creation

Dynamic role creation is allowed.

The lock is:

- Ops may create new roles
- vertical access and module scope remain controlled
- permission assignment must use predefined permission groups
- arbitrary micro-permission toggles are not allowed
- one role maps to one permission group in v1

### 2. Role-Based Landing

Users should land on their role-relevant page after login.
Generic landing behavior is not the intended product.

### 3. Authority Visibility Model

The cleaner final transcript direction is:

- Ops governs visibility
- approved uploads become visible to the Authorized Person
- external sharing happens outside the system

The system does not need to treat "shared externally" as the primary truth when access visibility is the real control point.

### 4. Supervisor Review Feedback Boundary

Supervisors remain upload-only field users.

The lock is:

- they should not see authority review status
- they should not see rejected labels
- they should not get governance-feedback UI on normal upload pages

### 5. Daily Watering `Not Required`

Recovered transcript direction supports:

- `Not Required` as an explicit daily action when used
- short reason optional in v1
- short reason mandatory later if that flow becomes stricter

### 6. Worker Model

Recovered design lock:

- `worker_daily_entries` is the universal daily truth layer
- `task_worker_assignment` exists only for fabrication
- fabrication worker availability uses both daily work entries and fabrication assignment context

### 7. GPS Review Rule

Recovered design lock:

- GPS is stored for Ops review
- uploads are never blocked by GPS mismatch in v1
- no automatic mismatch threshold is required in v1

### 8. Rejected Upload Cleanup

Recovered design lock:

- rejected uploads are manual cleanup candidates after 30 days
- purge retains minimal metadata and purge markers

### 9. Gardener And Night Guards

Recovered design lock:

- not login roles
- tracked through separate daily resource counts

### 10. Monitoring Planning Depth

Recovered design lock:

- the system generates a suggested monitoring plan and due list
- Ops may approve it or ignore it
- monitoring work is not blocked when Ops skips formal approval
- this is a soft planning model, not hard assignment enforcement

### 11. Green Belt Master Versus Advertisement Site Master

Recovered design lock:

- Green Belt Master and Advertisement Site Master stay as separate product surfaces
- advertisement boards inside green belts are still advertisement assets, not green-belt entities
- any "unified Site / Asset Master" wording applies to advertisement sites and assets, not to merging green belts into the advertisement master

### 12. Monitoring Scheduling Model

Recovered design lock:

- Ops selects each site's monitoring due dates in advance for the month
- a site may have multiple due dates within the same month
- Ops can copy the same due-date pattern into the next month and adjust it when needed
- Ops can bulk-copy the same due-date pattern across multiple selected sites or groups
- stored monthly due dates are the operational due truth for that site

### 13. Authority WhatsApp Share Model

Recovered design lock:

- one-click Share via WhatsApp is supported from approved authority-facing views
- the system prepares a pre-filled message
- human user still presses Send
- the share helper must only use approved authority-ready content
- this does not turn the system into silent automated WhatsApp delivery

### 14. Authority Summary Output

Recovered design lock:

- authority summary is end-of-day oriented
- summary is date-wise, belt-wise, and text-only
- summary includes authority-relevant work done only
- summary excludes internal notes, issue chatter, and operational discussion
- the locked structure is captured in `07_AUTHORITY_SHARE_AND_SUMMARY_MODEL.md`

## Decision Areas Still Open

No further product-level recovery decisions are currently open.

Remaining uncertainty belongs to implementation-spec work, not product-intent recovery.
