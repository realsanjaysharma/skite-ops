# Recovery Locks And Open Decisions

## Purpose

These items were discussed in the transcripts but are not equally frozen.
They must be explicitly locked before the repo is fully rewritten around the recovered product.

## Decision Areas To Lock

### 1. Monitoring Planning Depth

Monitoring is clearly site-driven and route-aware, but the exact depth of formal planning in v1 still needs locking.

Questions:

- Is there a real Ops monitoring plan page in v1?
- Is planning optional guidance or hard assignment?

### 2. Authority Visibility Versus External Share State

The recovered model clearly supports approved authority visibility.
It is still not fully locked whether the system should separately track external sharing state.

### 3. Daily Watering `Not Required` Behavior

This affects:

- data model
- UI
- compliance logic
- audit expectations

Questions:

- Is `Not Required` a daily state?
- Is a reason mandatory?
- How should it affect reports?

### 4. Gardener And Night Guards

These actors appear in the operational reality but were not finalized as system roles.

Options:

- out of system scope
- non-login tracked actors
- full future roles

### 5. Fixed Roles Versus Dynamic Role Creation

The recovered product strongly suggests a fixed constitutional role set.
This must be explicitly locked against open-ended dynamic role creation unless intentionally changed.

### 6. Green Belt Master Versus Advertisement Site Master Separation

Conceptually, both belong to the broader operational asset model.
Product surfaces should still stay separated unless a later decision deliberately merges them.

### 7. Monitoring Due And Frequency Formulas

Monitoring frequency clearly matters, but the exact due and overdue formulas still need precise locking.

### 8. Rejected Upload Feedback To Supervisors

The governance model is clear, but feedback visibility to supervisors is not fully frozen.

Questions:

- should supervisors see rejection state?
- should they only see a minimal "not approved" style result?

### 9. Export And Share Precision

The recovered product supports reporting and external sharing, but exact export behavior still needs final detail:

- report column definitions
- default filters
- WhatsApp share templates
- summary output format

## Recovery Rule

No one should silently guess these items during implementation.

If a workflow depends on one of these decisions, lock it in docs first and only then move it into schema or code.
