# 10_GIT_WORKFLOW.md

Version: Architecture Freeze v1 (Fully Disciplined -- Finalized) Status:
Development & Deployment Governance (Solo Maintainer Model)

  -------------
  1\. PURPOSE
  -------------

Defines strict source control, schema evolution, deployment, and
rollback discipline for the Skyte Operations System.

Assumptions: - Single primary developer - Private GitHub repository -
Shared hosting (cPanel) - Staging subdomain available - No direct
production edits allowed

  --------------------------
  2\. REPOSITORY STRUCTURE
  --------------------------

Repository: Private GitHub

Branches:

-   main → Production-ready only
-   develop → Integration branch
-   feature/\* → New features
-   hotfix/\* → Emergency production fixes

Branch Rules: - No direct commits to main. - All work must originate
from feature or hotfix branch. - Hotfix must be merged back into develop
after release.

  -----------------------------------
  3\. .GITIGNORE POLICY (MANDATORY)
  -----------------------------------

The repository must include a .gitignore file containing:

.env /storage/uploads/ /storage/archive/ /backups/ /vendor/ (if Composer
used) /node_modules/ (if frontend tools added)

Never commit: - Database dumps - Uploaded files - Backup files -
Credentials - Local config overrides

  ---------------------
  4\. BRANCHING MODEL
  ---------------------

Feature Workflow:

1.  git checkout -b feature/feature-name
2.  Develop locally (XAMPP)
3.  Test thoroughly
4.  Merge into develop
5.  Validate integration
6.  Merge into main
7.  Tag release

Hotfix Workflow:

1.  git checkout -b hotfix/bug-name
2.  Fix locally
3.  Test locally
4.  Merge into main
5.  Tag patch release
6.  Merge hotfix back into develop

  -----------------------
  5\. COMMIT DISCIPLINE
  -----------------------

Rules: - Small logical commits only - No mixed unrelated changes - Clear
descriptive messages

Format:

\[VERTICAL\] Short Description

Examples: \[GB\] Add watering frequency enum \[SECURITY\] Implement CSRF
validation \[REPORT\] Add monthly CSV export

  -------------------------
  6\. VERSIONING STRATEGY
  -------------------------

Version Format: vMajor.Minor.Patch

Major: - Schema change - Structural architecture change

Minor: - New feature added

Patch: - Bug fix only

Every production deployment must be tagged.

  --------------------------------------------------
  7\. DATABASE MIGRATION & SCHEMA VERSION TRACKING
  --------------------------------------------------

Migration Directory (MANDATORY):

-   Create /migrations/ directory in repository.

-   Each schema change must have migration file.

-   Naming convention:

    001_add_watering_frequency.sql 002_add_attendance_table.sql
    003_modify_task_status.sql

Rules:

-   Migration number must increment sequentially.
-   schema_version in system_meta must match latest migration number.
-   No manual structural DB changes in production.
-   All migrations documented in 06_DECISIONS_LOG.md.
-   Migration files never deleted --- only new migrations added.

Before any schema change:

1.  Manual DB backup required.
2.  Add new migration file.
3.  Apply locally.
4.  Update schema_version.
5.  Test locally.
6.  Deploy to staging first.

  ------------------------------
  8\. STAGING ENVIRONMENT RULE
  ------------------------------

Staging must:

-   Mirror production PHP version.
-   Mirror database structure.
-   Use separate DB credentials.
-   Not share production data.

Rules:

-   All releases tested on staging first.
-   No testing directly in production.
-   Only after staging validation → production deploy.

  ------------------------------------------
  9\. DEPLOYMENT WORKFLOW (Shared Hosting)
  ------------------------------------------

Deployment Method (Locked):

-   Release ZIP upload OR
-   cPanel Git Version Control

One method must be chosen and used consistently. Never mix deployment
methods.

Standard Deployment:

1.  Merge to main.
2.  Tag release.
3.  Deploy to staging.
4.  Run release validation checklist.
5.  Deploy to production.
6.  Monitor system 24 hours.

  -----------------------------------------
  10\. PRODUCTION CONFIGURATION LOCK RULE
  -----------------------------------------

-   Production .env changes must be documented.
-   Any production config change must be mirrored in staging.
-   No undocumented environment variable changes.
-   Never change production config without backup.

  -----------------------------------
  11\. RELEASE VALIDATION CHECKLIST
  -----------------------------------

Before marking release stable:

-   Login works
-   Role access correct
-   Upload functioning
-   Watering logic verified
-   Attendance logic verified
-   Reports generate
-   No PHP warnings/errors
-   Backup cron still active
-   schema_version matches migration count

  ----------------------------------
  12\. EMERGENCY ROLLBACK STRATEGY
  ----------------------------------

If deployment fails:

1.  Revert to previous tagged version.
2.  Restore latest DB backup if needed.
3.  Ensure rollback does not overwrite backup files.
4.  Validate system.
5.  Create new hotfix branch.
6.  Document issue in decisions log.

  ----------------------------
  13\. SOLO MAINTAINER RULES
  ----------------------------

-   Never deploy untagged code.
-   Never edit production via File Manager.
-   Never skip backup before schema change.
-   Always update MD files when structural changes occur.
-   Keep Git history readable.
-   No undocumented migrations.
-   No silent production config changes.

------------------------------------------------------------------------

STATUS

Git governance fully hardened. Migration discipline enforced. Production
configuration lock active. Ready for Backend Implementation Phase.
