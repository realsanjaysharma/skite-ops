# 09_BACKUP_AND_RECOVERY.md

Version: Architecture Freeze v1 (Finalized) Status: Disaster Recovery
Strategy (Shared Hosting Compatible)

  ------------------------------
  1\. BACKUP STRATEGY OVERVIEW
  ------------------------------

Recovery Tolerance: - Up to 1 day of data loss acceptable. - Zero-loss
recovery not required in v1.

Target Recovery Time (RTO): - System restoration within 2--4 hours
maximum.

Selected Strategy: - Daily automated database backup. - Weekly upload +
archive storage backup. - Weekly off-site copy (Google Drive or
encrypted external storage). - Monthly restore verification test. -
Monthly immutable snapshot retained for 30 days.

  ----------------------------
  2\. DATABASE BACKUP POLICY
  ----------------------------

Frequency: - Daily automated backup at 2:00 AM (server time).

Method: - mysqldump via cPanel Cron Job. - Backup saved as .sql file.

Example: /home/account/backups/db_YYYY_MM_DD.sql

Retention: - Keep last 7 daily backups. - Delete backups older than 7
days automatically.

Immutable Snapshot Rule: - On the 1st of each month, create a monthly
snapshot. - Store separately as: db_monthly_YYYY_MM.sql - Retain for
minimum 30 days. - Monthly snapshot must not be auto-overwritten.

Storage Rules: - Stored outside public_html. - Backup folder must not be
publicly accessible.

Operational Rule: - Cron job execution manually verified once per
month. - Confirm new .sql file generated daily.

Pre-Deployment Protection Rule: - Before any schema change, bulk import,
or production deployment: - Manually generate immediate DB snapshot. -
Confirm snapshot integrity before proceeding.

  --------------------------------------------
  3\. UPLOAD + ARCHIVE STORAGE BACKUP POLICY
  --------------------------------------------

Scope Includes: - /storage/uploads/ - /storage/archive/ (if archive
feature active)

Frequency: - Weekly ZIP archive (recommended Sunday 3:00 AM).

Critical Sync Rule: - Upload backup must run AFTER daily DB backup. -
Prevents database reference mismatch.

Example: uploads_backup_YYYY_MM_DD.zip

Retention: - Keep last 4 weekly backups. - Delete older archives to
control disk usage.

Storage Capacity Monitoring: - Backup folder size reviewed monthly. -
Ensure backups do not exceed hosting quota.

  ----------------------------
  4\. OFF-SITE BACKUP POLICY
  ----------------------------

Frequency: - Weekly.

Procedure: 1. Download latest DB backup (.sql). 2. Download latest
uploads backup (.zip). 3. Upload to: - Private Google Drive folder OR -
Encrypted external hard drive.

Rule: - Off-site backup must not remain only on hosting server. - At
least one external copy required at all times. - Verify off-site copy
accessibility weekly.

  ---------------------------
  5\. RESTORATION PROCEDURE
  ---------------------------

Database Recovery: 1. Create new database in cPanel. 2. Import latest
.sql file via phpMyAdmin. 3. Update .env DB credentials if required.

Uploads Recovery: 1. Extract uploads_backup.zip into /storage/uploads/.
2. Extract archive if needed. 3. Verify folder permissions (750 or 755).
4. Confirm access through PHP controller.

Post-Recovery Validation Checklist: - Login works. - Dashboard loads. -
Upload works. - Authority access confirmed. - Reports generate
correctly. - Watering and attendance logs accessible.

  -------------------------------
  6\. FAILURE SCENARIOS COVERED
  -------------------------------

Covered: - Accidental deletion of records. - Server crash. - Hosting
suspension. - File corruption. - Partial hacking incident. - Code
deployment mistake. - Data corruption propagation (limited by monthly
immutable snapshot).

Not Covered: - Simultaneous hosting + off-site loss. - Total account
compromise including off-site credentials.

  --------------------------------
  7\. BACKUP VERIFICATION POLICY
  --------------------------------

-   Monthly restore test in LOCAL environment only.
-   Confirm:
    -   Database imports successfully.
    -   Upload files accessible.
    -   Application boots normally.
-   Log restore test date in internal operations record.

  -------------------------
  8\. GIT IS NOT A BACKUP
  -------------------------

Clarification:

-   Git stores application code only.
-   Git does NOT store:
    -   Database contents
    -   Upload files
    -   Runtime data
    -   Session data

Backup strategy is separate from Git workflow.

  -----------------------------
  9\. BACKUP DISCIPLINE RULES
  -----------------------------

-   No manual deletion of backup files outside retention policy.
-   Backup cron must remain active at all times.
-   Archive folder included in weekly backup.
-   DB backup must precede upload backup.
-   Storage usage reviewed monthly.
-   Off-site copy verified weekly.
-   Monthly immutable snapshot retained for 30 days.
-   Pre-deployment manual DB snapshot mandatory.

------------------------------------------------------------------------

STATUS

Backup system finalized and hardened. Aligned with shared hosting
constraints. Operationally serious and interview-defendable.
