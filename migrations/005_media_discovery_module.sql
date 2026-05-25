-- Migration: Add monitoring.discovery module_key for MONITORING_TEAM role
-- Date: 2026-05-24
-- Feature: Media Discovery page

INSERT INTO role_module_scopes (role_id, module_key)
SELECT id, 'monitoring.discovery' FROM roles WHERE role_key = 'MONITORING_TEAM'
ON DUPLICATE KEY UPDATE module_key = module_key;
