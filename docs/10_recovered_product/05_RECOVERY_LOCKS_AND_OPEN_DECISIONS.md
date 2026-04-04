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

## Decision Areas Still Open

### 1. Monitoring Planning Depth

Monitoring is clearly site-driven and route-aware, but the exact depth of formal planning in v1 still needs locking.

Questions:

- Is there a real Ops monitoring plan page in v1?
- Is planning optional guidance or hard assignment?

### 2. Gardener And Night Guards

These actors appear in the operational reality but were not finalized as system roles.

Options:

- out of system scope
- non-login tracked actors
- full future roles

### 3. Green Belt Master Versus Advertisement Site Master Separation

Conceptually, both belong to the broader operational asset model.
Product surfaces should still stay separated unless a later decision deliberately merges them.

### 4. Monitoring Due And Frequency Formulas

Monitoring frequency clearly matters, but the exact due and overdue formulas still need precise locking.

### 5. Export And Share Precision

The recovered product supports reporting and external sharing, but exact export behavior still needs final detail:

- report column definitions
- default filters
- WhatsApp share templates
- summary output format
