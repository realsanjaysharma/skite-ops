# EXECUTION BEHAVIOR LOCK (V1)

## Purpose
This document defines **final execution-level decisions** required before implementation.

It does NOT redefine architecture or schema.

It removes ambiguity in:
- Behavior
- Interactions
- Operational rules

This document is binding for implementation.

---

# 1. MONITORING SCOPE (V1 LOCK)

Monitoring in v1 is defined as:

- Site-based uploads (primary)
- Task-assisted work (optional)

### Rules:
- Monitoring uses `workers`, `worker_attendance`, and `worker_daily_entries`
- No separate monitoring attendance system
- No "assigned sites" mapping in v1
- Monitoring is NOT pre-assigned - it is activity-driven

Worker Daily Entry Rule:
- Entry allowed only if worker attendance exists for that date

---

# 2. SITE ACCESS RULES

### Roles:

**Ops**
- Full control (create/edit/manage sites)

**Monitoring Team**
- View sites
- Upload monitoring proof

**Fabrication Lead**
- Access sites through tasks only

**Other Roles (Management / Sales / Planning)**
- View-only (as per role scope)

View-only scope includes:
- Site master data (basic details)
- Role-visible monitoring uploads (read-only)
- Dashboard summaries

View-only does NOT include:
- Upload creation
- Task interaction
- Issue management

---

# 3. ISSUE ↔ TASK INTERACTION (LOCKED)

### Rule:
- Issue may optionally link to one task (1:1)

### Behavior:
- Task completion → DOES NOT auto-close issue
- Task completion → does NOT change issue status

Issue status transitions:
- OPEN → IN_PROGRESS when Ops starts handling OR task is linked

"Resolution attempted" is NOT a stored state.

It is derived dynamically in UI:
- If issue has linked task AND task.status = COMPLETED
→ UI shows: "Resolution Attempted"

Issue status remains:
- OPEN or IN_PROGRESS until manually closed by Ops

### Closure:
- Issue must be CLOSED manually by Ops

### Edge Cases:
- Cancelled task → issue remains OPEN
- Manually closed issue → linked task remains unchanged

---

# 4. MAINTENANCE CYCLE EDGE RULES

### Core Principle:
Maintenance cycle is governance tracking, NOT a hard dependency.

### Rules:
- Watering allowed WITHOUT active cycle
- Uploads allowed WITHOUT active cycle
- Labour entries allowed WITHOUT active cycle

### Cycle Auto-Close:
Cycle auto-closes when:
- Belt becomes hidden
- Permission becomes expired

### Restart:
- New cycle can start anytime after closure

---

# 5. UPLOAD APPROVAL RULE

### Approval:
- Required only for authority visibility

### Behavior:
- Default = HIDDEN
- Ops can:
  - Approve
  - Reject

### Reversal:
- Approval is REVERSIBLE
- All changes must be audit logged

### Scope:
- Applies only to Green Belt uploads (authority-facing)

Upload API:
- Single unified upload endpoint
- Differentiated by:
  - upload_type (WORK / ISSUE)
  - parent_type

---

# 6. USER LIFECYCLE RULES

### Soft Delete:
- Users are soft-deleted only

### Restore:
- Users CAN be restored

User Restore Behavior:
- Only Ops can restore users
- Restored user:
  - is_active = TRUE
  - is_deleted = FALSE
- force_password_reset = TRUE on restore

### Role Change:
- Ops can change role anytime

### Password Policy:
- force_password_reset = TRUE on:
  - Manual reset by Ops
  - User restore

- force_password_reset = FALSE on user creation

### Session Handling:
- Deactivation does NOT force logout immediately (v1)

---

# 7. REPORT SPECIFICATION (V1 MINIMUM LOCK)

Each report must define:

- Fixed columns
- Grouping level
- Date filter
- Inclusion rules

### Rules:
- Reports are generated from current + historical data
- Soft-deleted records excluded
- Archived tasks are excluded by default.

Reports must support a filter:
- include_archived = TRUE / FALSE

Only when include_archived = TRUE:
- archived tasks are included in report output

---

# 8. DASHBOARD METRIC DEFINITIONS

### Overdue (General Principle):
Expected activity - Actual activity

### Examples:

**Watering Compliance**
= Required watering days - Completed watering logs

**Worker Activity**
= Number of daily entries per worker

**Belt Activity**
= Number of uploads + labour entries per belt

### Rule:
- Metrics must be computed dynamically
- No pre-stored aggregates

---

# 9. IMPERFECT EVIDENCE HANDLING (V1)

- No upload does NOT guarantee no work performed.
- No watering log does NOT guarantee watering was not done.
- System alerts are advisory signals, not absolute truth.
- Ops may override or ignore alerts based on ground reality.
- Overrides and ignored alerts must be explainable and may be audited.
- Compliance should be treated as best-effort inference, not strict enforcement.

---

# 10. OVERRIDE LOGGING RULE

All override actions must include:
- override_by
- override_reason
- audit log entry

---

# FINAL NOTE

This document:
- Does NOT introduce new schema
- Does NOT modify architecture
- Does NOT add new entities

It only removes execution ambiguity.

All implementation must comply with:
- DATA_AND_FLOW
- DECISIONS_LOG
- NON_NEGOTIABLES
- SCHEMA

---

# STATUS
Execution behavior: LOCKED
Ready for implementation
