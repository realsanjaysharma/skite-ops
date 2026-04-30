-- ============================================================
-- Phase 9: New V1 Surfaces — Role Module Scope Seed
-- Run on live skite_ops database.
-- ============================================================

-- Alert Panel for OPS_MANAGER
INSERT INTO role_module_scopes (role_id, module_key)
SELECT id, 'governance.alert_panel' FROM roles WHERE role_key = 'OPS_MANAGER'
ON DUPLICATE KEY UPDATE module_key = module_key;

-- Worker Daily Entry for FABRICATION_LEAD
INSERT INTO role_module_scopes (role_id, module_key)
SELECT id, 'task.worker_daily_entry' FROM roles WHERE role_key = 'FABRICATION_LEAD'
ON DUPLICATE KEY UPDATE module_key = module_key;

-- Client Media Library for SALES_TEAM
INSERT INTO role_module_scopes (role_id, module_key)
SELECT id, 'commercial.client_media_library' FROM roles WHERE role_key = 'SALES_TEAM'
ON DUPLICATE KEY UPDATE module_key = module_key;

-- Client Media Library for CLIENT_SERVICING
INSERT INTO role_module_scopes (role_id, module_key)
SELECT id, 'commercial.client_media_library' FROM roles WHERE role_key = 'CLIENT_SERVICING'
ON DUPLICATE KEY UPDATE module_key = module_key;

-- Media Planning Inventory for MEDIA_PLANNING
INSERT INTO role_module_scopes (role_id, module_key)
SELECT id, 'commercial.media_planning_inventory' FROM roles WHERE role_key = 'MEDIA_PLANNING'
ON DUPLICATE KEY UPDATE module_key = module_key;
