-- ==========================================
-- SKYTE OPS - FOUNDATION SEED
-- ==========================================
-- Run this after:
--   docs/06_schema/schema_v1_full.sql
--
-- Purpose:
-- - seed constitutional roles
-- - seed permission groups
-- - seed role-to-permission mappings
-- - seed role module scopes
-- - seed required system settings

-- ==========================================
-- PERMISSION GROUPS
-- ==========================================

INSERT INTO permission_groups (group_key, group_name, description)
VALUES
    ('VIEW', 'View', 'Read-first capability bundle'),
    ('UPLOAD', 'Upload', 'Field-entry capability bundle'),
    ('APPROVE', 'Approve', 'Governance approval capability bundle'),
    ('MANAGE', 'Manage', 'Full management capability bundle')
ON DUPLICATE KEY UPDATE
    group_name = VALUES(group_name),
    description = VALUES(description),
    updated_at = CURRENT_TIMESTAMP;

-- ==========================================
-- CONSTITUTIONAL ROLES
-- ==========================================

INSERT INTO roles (role_key, role_name, description, landing_module_key, is_system_role, is_active)
VALUES
    ('OPS_MANAGER', 'Ops Manager', 'Primary governance and control role', 'dashboard.master_ops', 1, 1),
    ('HEAD_SUPERVISOR', 'Head Supervisor', 'Green belt oversight and attendance/watering control role', 'green_belt.watering_oversight', 1, 1),
    ('GREEN_BELT_SUPERVISOR', 'Green Belt Supervisor', 'Internal field supervisor upload role', 'green_belt.supervisor_upload', 1, 1),
    ('OUTSOURCED_MAINTAINER', 'Outsourced Maintainer', 'Outsourced belt upload role', 'green_belt.outsourced_upload', 1, 1),
    ('MONITORING_TEAM', 'Monitoring Team', 'Monitoring upload and history role', 'monitoring.upload', 1, 1),
    ('FABRICATION_LEAD', 'Fabrication Lead', 'Execution lead role for tasks and workers', 'task.my_tasks', 1, 1),
    ('SALES_TEAM', 'Sales Team', 'Read-first commercial request and progress role', 'task.progress_read', 1, 1),
    ('CLIENT_SERVICING', 'Client Servicing', 'Read-first client servicing request and progress role', 'task.progress_read', 1, 1),
    ('MEDIA_PLANNING', 'Media Planning', 'Read-first planning and inventory role', 'task.progress_read', 1, 1),
    ('AUTHORITY_REPRESENTATIVE', 'Authority Representative', 'Read-only authority proof consumer role', 'green_belt.authority_view', 1, 1),
    ('MANAGEMENT', 'Management', 'Read-only management oversight role', 'dashboard.management', 1, 1)
ON DUPLICATE KEY UPDATE
    role_name = VALUES(role_name),
    description = VALUES(description),
    landing_module_key = VALUES(landing_module_key),
    is_system_role = VALUES(is_system_role),
    is_active = VALUES(is_active),
    updated_at = CURRENT_TIMESTAMP;

-- ==========================================
-- ROLE TO PERMISSION GROUP MAPPINGS
-- ==========================================

INSERT INTO role_permission_mappings (role_id, permission_group_id)
SELECT r.id, pg.id
FROM roles r
JOIN permission_groups pg ON pg.group_key = 'MANAGE'
WHERE r.role_key = 'OPS_MANAGER'
ON DUPLICATE KEY UPDATE
    permission_group_id = (SELECT id FROM permission_groups WHERE group_key = 'MANAGE'),
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO role_permission_mappings (role_id, permission_group_id)
SELECT r.id, pg.id
FROM roles r
JOIN permission_groups pg ON pg.group_key = 'MANAGE'
WHERE r.role_key = 'HEAD_SUPERVISOR'
ON DUPLICATE KEY UPDATE
    permission_group_id = (SELECT id FROM permission_groups WHERE group_key = 'MANAGE'),
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO role_permission_mappings (role_id, permission_group_id)
SELECT r.id, pg.id
FROM roles r
JOIN permission_groups pg ON pg.group_key = 'UPLOAD'
WHERE r.role_key IN ('GREEN_BELT_SUPERVISOR', 'OUTSOURCED_MAINTAINER', 'MONITORING_TEAM', 'FABRICATION_LEAD')
ON DUPLICATE KEY UPDATE
    permission_group_id = (SELECT id FROM permission_groups WHERE group_key = 'UPLOAD'),
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO role_permission_mappings (role_id, permission_group_id)
SELECT r.id, pg.id
FROM roles r
JOIN permission_groups pg ON pg.group_key = 'VIEW'
WHERE r.role_key IN ('SALES_TEAM', 'CLIENT_SERVICING', 'MEDIA_PLANNING', 'AUTHORITY_REPRESENTATIVE', 'MANAGEMENT')
ON DUPLICATE KEY UPDATE
    permission_group_id = (SELECT id FROM permission_groups WHERE group_key = 'VIEW'),
    updated_at = CURRENT_TIMESTAMP;

-- ==========================================
-- ROLE MODULE SCOPES
-- ==========================================

INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, modules.module_key
FROM roles r
JOIN (
    SELECT 'dashboard.master_ops' AS module_key
    UNION ALL SELECT 'dashboard.green_belt'
    UNION ALL SELECT 'dashboard.advertisement'
    UNION ALL SELECT 'dashboard.monitoring'
    UNION ALL SELECT 'dashboard.management'
    UNION ALL SELECT 'green_belt.master'
    UNION ALL SELECT 'green_belt.detail'
    UNION ALL SELECT 'green_belt.supervisor_upload'
    UNION ALL SELECT 'green_belt.my_uploads'
    UNION ALL SELECT 'green_belt.outsourced_upload'
    UNION ALL SELECT 'green_belt.watering_oversight'
    UNION ALL SELECT 'green_belt.maintenance_cycles'
    UNION ALL SELECT 'green_belt.supervisor_attendance'
    UNION ALL SELECT 'green_belt.labour_entries'
    UNION ALL SELECT 'green_belt.upload_review'
    UNION ALL SELECT 'green_belt.issue_management'
    UNION ALL SELECT 'green_belt.authority_view'
    UNION ALL SELECT 'advertisement.site_master'
    UNION ALL SELECT 'advertisement.campaign_management'
    UNION ALL SELECT 'monitoring.upload'
    UNION ALL SELECT 'monitoring.plan'
    UNION ALL SELECT 'monitoring.history'
    UNION ALL SELECT 'media.free_media_inventory'
    UNION ALL SELECT 'task.request_intake'
    UNION ALL SELECT 'task.progress_read'
    UNION ALL SELECT 'task.management'
    UNION ALL SELECT 'task.detail'
    UNION ALL SELECT 'task.my_tasks'
    UNION ALL SELECT 'task.worker_allocation'
    UNION ALL SELECT 'governance.user_management'
    UNION ALL SELECT 'governance.access_mappings'
    UNION ALL SELECT 'governance.audit_logs'
    UNION ALL SELECT 'governance.rejected_upload_cleanup'
    UNION ALL SELECT 'reports.monthly'
    UNION ALL SELECT 'settings.system'
) modules
WHERE r.role_key = 'OPS_MANAGER'
ON DUPLICATE KEY UPDATE
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, modules.module_key
FROM roles r
JOIN (
    SELECT 'dashboard.green_belt' AS module_key
    UNION ALL SELECT 'green_belt.detail'
    UNION ALL SELECT 'green_belt.watering_oversight'
    UNION ALL SELECT 'green_belt.maintenance_cycles'
    UNION ALL SELECT 'green_belt.supervisor_attendance'
    UNION ALL SELECT 'green_belt.labour_entries'
    UNION ALL SELECT 'green_belt.issue_management'
) modules
WHERE r.role_key = 'HEAD_SUPERVISOR'
ON DUPLICATE KEY UPDATE
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, modules.module_key
FROM roles r
JOIN (
    SELECT 'green_belt.supervisor_upload' AS module_key
    UNION ALL SELECT 'green_belt.my_uploads'
) modules
WHERE r.role_key = 'GREEN_BELT_SUPERVISOR'
ON DUPLICATE KEY UPDATE
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, 'green_belt.outsourced_upload'
FROM roles r
WHERE r.role_key = 'OUTSOURCED_MAINTAINER'
ON DUPLICATE KEY UPDATE
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, modules.module_key
FROM roles r
JOIN (
    SELECT 'monitoring.upload' AS module_key
    UNION ALL SELECT 'monitoring.history'
) modules
WHERE r.role_key = 'MONITORING_TEAM'
ON DUPLICATE KEY UPDATE
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, modules.module_key
FROM roles r
JOIN (
    SELECT 'task.my_tasks' AS module_key
    UNION ALL SELECT 'task.detail'
    UNION ALL SELECT 'task.worker_allocation'
) modules
WHERE r.role_key = 'FABRICATION_LEAD'
ON DUPLICATE KEY UPDATE
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, modules.module_key
FROM roles r
JOIN (
    SELECT 'task.progress_read' AS module_key
    UNION ALL SELECT 'task.request_intake'
    UNION ALL SELECT 'monitoring.history'
    UNION ALL SELECT 'media.free_media_inventory'
) modules
WHERE r.role_key IN ('SALES_TEAM', 'CLIENT_SERVICING', 'MEDIA_PLANNING')
ON DUPLICATE KEY UPDATE
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, 'green_belt.authority_view'
FROM roles r
WHERE r.role_key = 'AUTHORITY_REPRESENTATIVE'
ON DUPLICATE KEY UPDATE
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, modules.module_key
FROM roles r
JOIN (
    SELECT 'dashboard.advertisement' AS module_key
    UNION ALL SELECT 'dashboard.monitoring'
    UNION ALL SELECT 'dashboard.management'
    UNION ALL SELECT 'reports.monthly'
) modules
WHERE r.role_key = 'MANAGEMENT'
ON DUPLICATE KEY UPDATE
    updated_at = CURRENT_TIMESTAMP;

-- ==========================================
-- REQUIRED SYSTEM SETTINGS
-- ==========================================

INSERT INTO system_settings (setting_key, setting_value, value_type, description)
VALUES
    ('ops_phone_number', '+910000000000', 'STRING', 'Update this to the real Ops phone number before using Call Ops'),
    ('self_delete_purge_days', '30', 'INTEGER', 'Days after which self-deleted uploads are purge-eligible'),
    ('rejected_upload_cleanup_days', '30', 'INTEGER', 'Minimum rejected-upload age before cleanup eligibility'),
    ('free_media_default_expiry_days', '90', 'INTEGER', 'Default expiry window for confirmed free-media records'),
    ('authority_whatsapp_helper_enabled', '1', 'BOOLEAN', 'Controls whether authority WhatsApp helper actions are shown')
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    value_type = VALUES(value_type),
    description = VALUES(description),
    updated_at = CURRENT_TIMESTAMP;
