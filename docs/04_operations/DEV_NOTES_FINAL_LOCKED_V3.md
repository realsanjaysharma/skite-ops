# DEV_NOTES.md

Version: Architecture Freeze v3 (Fully Hardened + Operational Safety)
Status: Coding, Governance & Debug Discipline (FINAL LOCK)

  -------------
  1\. PURPOSE
  -------------

Defines strict development discipline for the Skyte Operations System.

This system is: - Solo-developed - Long-term maintained -
Audit-sensitive - Legally sensitive (authority reporting) - Upload-heavy
(600+ photos daily) - Interview-defendable

Code must favor clarity, auditability, predictability, and operational
safety over cleverness.

  ---------------------------------
  2\. CORE DEVELOPMENT PHILOSOPHY
  ---------------------------------

1.  Clarity over optimization.
2.  Predictable structure over shortcuts.
3.  Explicit logic over hidden behavior.
4.  Separation of concerns enforced.
5.  Every non-obvious rule must be commented.
6.  No magic numbers.
7.  No silent overrides.
8.  All governance rules traceable to Decisions Log.

If future-you cannot understand it in 6 months, the implementation is
wrong.

  ---------------------------------------------------
  3\. PROJECT STRUCTURE (Shared Hosting Compatible)
  ---------------------------------------------------

/ index.php

/app /controllers /services /helpers /middleware

/config database.php app.php constants.php

/storage /uploads /archive /logs

/migrations 001_initial_schema.sql 002_add_sites_table.sql

  -------------------------------------------
  4\. LAYER RESPONSIBILITY RULE (MANDATORY)
  -------------------------------------------

Controllers: - Handle HTTP request/response only. - Handle request
validation (format, required fields). - Call service layer. - Must not
contain business logic. - Never contain SQL queries.

Services: - Contain ALL business logic. - Enforce Decisions Log rules.
- Handle business validation and domain rules. - Must not contain raw
SQL queries. - Must not perform role-based access checks.

Repositories: - Contain ALL database queries. - Perform data access
only. - Must not contain business logic.

Authorization (RBAC) must be enforced at the middleware layer before
reaching controllers.

Views: - Display only. - No business logic. - No direct DB access.

  -------------------------
  5\. COMMENTING STANDARD
  -------------------------

Every file must include header block. Every function must include
docblock. Every governance rule must reference Decision ID.

  --------------------------
  6\. SOFT DELETE STANDARD
  --------------------------

All soft-deletable tables must include:

is_deleted (boolean default false) deleted_at (datetime nullable)
deleted_by (user_id nullable)

Hard delete allowed only for: - Scheduled purge - Admin maintenance

  ----------------------------
  7\. DATE & TIME DISCIPLINE
  ----------------------------

All date logic must use:

SERVER TIME ONLY. Database date comparison. No frontend-only validation.

Supervisors: - Can mark watering only for CURRENT_DATE.

Ops: - Can perform override on locked records. - Must log override_reason.

  ----------------------------------
  8\. CONSTANTS CONFIGURATION RULE
  ----------------------------------

All operational rules must use constants.php.

Example:

WATERING_CUTOFF_HOUR = 20 FREE_MEDIA_EXPIRY_DAYS = 90
UPLOAD_RETENTION_DAYS = 30 MAX_UPLOAD_SIZE_MB = 5

Never hardcode values in services.

  ------------------------------
  9\. AUDIT MIDDLEWARE PATTERN
  ------------------------------

All override and governance actions must:

Capture: - override_by - override_reason - override_timestamp

Audit middleware must log: - Critical status changes - Override usage -
Permission violations

Override Reason Enforcement:
- override_reason is supported at schema and audit level.
- Mandatory reason enforcement belongs in service layer when override flows are implemented.

  -------------------------
  10\. LOGGING DISCIPLINE
  -------------------------

Log file: /storage/logs/app.log

Never log: - Passwords - Tokens - Raw file paths

  ---------------------
  11\. ERROR HANDLING
  ---------------------

Production: - No raw PHP errors - Generic user-safe message - Detailed
internal logging

All DB operations must use try/catch.

  -----------------------------------------------
  12\. TRANSACTION DISCIPLINE (NEW -- CRITICAL)
  -----------------------------------------------

All multi-table state changes must use database transactions.

Examples requiring transaction:

-   Issue linked to Task
-   Task completion auto-closes Issue
-   Attendance update + related recalculation
-   Archive operation affecting multiple tables

Rule:

Begin transaction Execute all operations Commit only if all succeed
Rollback on failure

No partial writes allowed.

  ----------------------------------------
  13\. FILE UPLOAD SAFETY STANDARD (NEW)
  ----------------------------------------

Because system is upload-heavy:

Mandatory rules:

-   Validate MIME type (image/jpeg, image/png, image/webp)
-   Enforce MAX_UPLOAD_SIZE_MB
-   Generate unique filename (UUID or timestamp-based)
-   Never overwrite existing file
-   Store relative path in DB (not full server path)
-   Scan file extension and MIME both

Upload folder structure should be date-based:

/uploads/YYYY/MM/

  ----------------------------
  14\. PAGINATION RULE (NEW)
  ----------------------------

Upload-heavy pages MUST use server-side pagination.

Never load: - Full month uploads at once - Full belt history without
limit - Full worker history without limit

Standard:

Default limit: 50 per page.

  -------------------------------
  15\. MIGRATION ALIGNMENT RULE
  -------------------------------

Before using new field in code:

1.  Confirm migration file exists.
2.  Confirm schema_version updated.
3.  Confirm staging tested.

  ---------------------------------
  16\. HTTP METHOD ALIGNMENT NOTE
  ---------------------------------

Current controllers may use POST for operations that should map to
PUT/DELETE. This will be aligned in a later refactor phase to comply
with Decision 039.

  ------------------------------------
  16\. BUSINESS LOGIC ALIGNMENT RULE
  ------------------------------------

If rule exists in Decisions Log, it must exist in service layer
explicitly.

  ----------------------
  17\. EDIT LOCK RULES
  ----------------------

Month-based records:

"Past-month records are locked by default.
Only Ops role can perform override on locked records.
All overrides must require a reason and must be recorded in audit_logs.
Overrides are action-specific and do not unlock the entire month or dataset."

  ---------------------------
  18\. DEBUGGING DISCIPLINE
  ---------------------------

When debugging:

1.  Reproduce locally.
2.  Check logs.
3.  Verify schema_version.
4.  Confirm migration applied.
5.  Confirm permissions.
6.  Confirm date-lock logic.

Never debug directly in production.

  -------------------------------
  19\. DOCUMENTATION DISCIPLINE
  -------------------------------

Any schema or structural change requires update of:

05_DATA_AND_FLOW_NOTES.md 06_DECISIONS_LOG.md 04_PAGE_CATALOG.md

  ------------------------------------------------------------
  20\. FINAL RULE

  If unsure:

  Simplify. Document. Reference decision. Never improvise
  silently.
  ------------------------------------------------------------

STATUS

Development discipline fully hardened. Ready for Backend Implementation
Phase.
