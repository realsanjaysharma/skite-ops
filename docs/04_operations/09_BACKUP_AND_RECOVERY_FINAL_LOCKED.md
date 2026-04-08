# Skyte Ops Backup And Recovery

## Purpose

This file records the active backup and recovery approach for the Skite Ops project.
It is an operations reference, not a product-scope document.

## 1. Recovery Goals

- up to one day of data loss is acceptable in v1 operations
- target recovery time: 2 to 4 hours
- zero-loss recovery is not required in the current deployment model

## 2. Database Backup Policy

- daily automated database backup at 2:00 AM server time
- backup created through hosting-compatible tooling such as `mysqldump` or equivalent panel automation
- backup stored outside the public web root
- keep last 7 daily backups
- create a monthly immutable snapshot on the first day of the month
- keep monthly snapshot for at least 30 days

Before any schema change, import, or production deployment:

- create a fresh manual database snapshot
- confirm the snapshot exists before proceeding

## 3. Upload Storage Backup Policy

Scope includes the protected upload store used by operational proof.

- weekly upload backup archive
- upload backup must run after database backup
- keep last 4 weekly upload backups
- review storage usage regularly so backups do not silently fill hosting quota

## 4. Off-Site Copy Policy

- keep at least one external copy outside the hosting account
- verify off-site accessibility weekly
- acceptable destinations include private cloud storage or encrypted offline media

## 5. Restoration Procedure

### Database

1. Create or prepare the target database.
2. Import the selected SQL backup.
3. restore environment credentials if needed.

### Upload Storage

1. Restore the protected upload directory.
2. confirm correct permissions.
3. verify application-level file access through the normal protected flow.

### Post-Restore Checks

- login works
- dashboard boot works
- upload access works
- authority-safe views open
- reports generate
- key operational records can be read

## 6. Failure Scenarios Covered

Covered:

- accidental data deletion
- server crash
- deployment mistake
- file corruption
- partial compromise where backups remain intact

Not covered:

- simultaneous loss of hosting and all off-site copies
- total compromise of both primary and backup credentials

## 7. Verification Discipline

- perform restore test in a non-production environment at least monthly
- confirm DB import works
- confirm upload access works
- confirm the application boots normally

## 8. Git Is Not Backup

Git covers code history only.
Git does not replace:

- database backup
- upload backup
- runtime data backup

## 9. Operations Rules

- do not manually delete backup files outside retention policy
- keep backup automation active
- keep off-site copy current
- take a manual DB snapshot before risky production operations

## Active Status

This file remains an active operations reference.
If backup mechanics change materially, update this file and the relevant deployment runbook together.
