<?php

/**
 * constants.php
 *
 * Purpose:
 * Centralises all operational rules that use fixed values.
 * No magic numbers are allowed anywhere else in the codebase.
 *
 * Architecture Rule:
 * All services and controllers reference these constants.
 * Never hardcode these values inline.
 *
 * Governance Reference: Decision 065 (08_DECISIONS_ADDENDUM_V1.md)
 */

// ==========================================
// TIMEZONE
// ==========================================

// All date/time logic uses IST (UTC+5:30).
// This is also enforced at DB level via PDO init command in database.php.
date_default_timezone_set('Asia/Kolkata');


// ==========================================
// SCHEMA VERSION
// ==========================================

// Must match schema_version in system_meta table.
// Update this when a new migration is applied.
define('SCHEMA_VERSION', 3);


// ==========================================
// WATERING COMPLIANCE
// ==========================================

// Hour (24h, IST) after which supervisors cannot mark watering for today.
// Governance Reference: Decision 059, DEV_NOTES section 8
define('WATERING_CUTOFF_HOUR', 20);


// ==========================================
// LOGIN PROTECTION
// ==========================================

// Maximum consecutive failed login attempts before account is locked.
// Governance Reference: Decision 008 (SECURITY doc), AuthService
define('MAX_FAILED_LOGIN_ATTEMPTS', 5);

// Duration in minutes the account remains locked after threshold is hit.
define('LOGIN_LOCK_DURATION_MINUTES', 15);


// ==========================================
// UPLOAD RULES
// ==========================================

// Minutes after upload creation within which a supervisor may self-delete.
// Governance Reference: Decision 063, Decision 019 (DECISIONS_LOG)
define('UPLOAD_SELF_DELETE_WINDOW_MINUTES', 5);

// Maximum allowed upload file size in megabytes.
// Governance Reference: SECURITY doc section 5
define('MAX_UPLOAD_SIZE_MB', 5);

// Allowed file extensions for uploads (checked alongside MIME type).
define('ALLOWED_UPLOAD_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// Allowed MIME types for uploads (server-side finfo check, not client-declared).
define('ALLOWED_UPLOAD_MIME_TYPES', ['image/jpeg', 'image/png', 'image/webp']);


// ==========================================
// DATA RETENTION
// ==========================================

// Days after which a supervisor self-deleted upload is eligible for hard purge.
// Governance Reference: Decision 020 (DECISIONS_LOG)
define('SELF_DELETED_UPLOAD_PURGE_DAYS', 30);


// ==========================================
// PAGINATION
// ==========================================

// Default number of records returned per page on list endpoints.
// Governance Reference: DEV_NOTES section 14
define('DEFAULT_PAGE_LIMIT', 50);


// ==========================================
// MAINTENANCE CYCLE
// ==========================================

// Days a maintenance cycle can remain open before an alert is triggered.
// Governance Reference: DATA_AND_FLOW section 4
define('CYCLE_DELAY_ALERT_DAYS', 4);


// ==========================================
// FREE MEDIA (FUTURE MODULE — values locked now)
// ==========================================

// Days before free media expiry when an alert should appear on dashboard.
// Governance Reference: DATA_AND_FLOW section (future)
define('FREE_MEDIA_EXPIRY_ALERT_DAYS', 7);


// ==========================================
// MONTH LOCK
// ==========================================

// Day of month on which the previous month's records become locked.
// Records from any month other than the current calendar month are locked.
// This constant is informational — lock logic uses date comparison, not this value.
// Governance Reference: Decision 032 (DECISIONS_LOG)
define('MONTH_LOCK_DAY', 1);
