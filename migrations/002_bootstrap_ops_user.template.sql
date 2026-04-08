-- ==========================================
-- SKYTE OPS - OPTIONAL FIRST OPS USER TEMPLATE
-- ==========================================
-- This file is intentionally a template.
-- Edit the placeholder values before running it.
--
-- Steps:
-- 1. Generate a password hash in PHP, for example:
--    php -r "echo password_hash('ChangeThisStrongPassword', PASSWORD_DEFAULT), PHP_EOL;"
-- 2. Replace:
--    __OPS_FULL_NAME__
--    __OPS_EMAIL__
--    __OPS_PHONE__
--    __PASSWORD_HASH__
-- 3. Run this file after:
--    - docs/06_schema/schema_v1_full.sql
--    - migrations/001_seed_foundation.sql

INSERT INTO users (
    role_id,
    full_name,
    email,
    phone,
    password_hash,
    failed_attempt_count,
    last_failed_attempt_at,
    is_active,
    force_password_reset,
    is_deleted
)
SELECT
    r.id,
    '__OPS_FULL_NAME__',
    '__OPS_EMAIL__',
    '__OPS_PHONE__',
    '__PASSWORD_HASH__',
    0,
    NULL,
    1,
    1,
    0
FROM roles r
WHERE r.role_key = 'OPS_MANAGER'
  AND NOT EXISTS (
      SELECT 1
      FROM users u
      WHERE u.email = '__OPS_EMAIL__'
  );
