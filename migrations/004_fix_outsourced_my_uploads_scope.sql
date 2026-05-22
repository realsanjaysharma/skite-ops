-- Migration 004: Add green_belt.my_uploads to OUTSOURCED_MAINTAINER role scope
--
-- Root cause: 001_seed_foundation.sql only granted OUTSOURCED_MAINTAINER access
-- to green_belt.outsourced_upload, omitting green_belt.my_uploads. This meant
-- outsourced users could upload but could not view or manage their own submissions.
-- GREEN_BELT_SUPERVISOR already had both scopes correctly.
--
-- Run this on any install that was seeded before this fix was applied.

INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, 'green_belt.my_uploads'
FROM roles r
WHERE r.role_key = 'OUTSOURCED_MAINTAINER'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
