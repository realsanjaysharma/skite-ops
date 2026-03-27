-- ==========================================
-- MIGRATION 003 - Fix sites table ID type
-- ==========================================
--
-- Controlled application rule:
-- Apply only when the live sites.id column is still INT-based.
-- BOOLEAN alignment for is_active is intentionally skipped here because
-- MySQL BOOLEAN is stored as TINYINT(1) anyway.
-- ==========================================

ALTER TABLE sites
    MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

INSERT INTO system_meta (id, schema_version)
VALUES (1, 3)
ON DUPLICATE KEY UPDATE schema_version = VALUES(schema_version);

INSERT INTO schema_migrations (filename)
SELECT '003_fix_sites_table.sql'
WHERE NOT EXISTS (
    SELECT 1 FROM schema_migrations WHERE filename = '003_fix_sites_table.sql'
);
