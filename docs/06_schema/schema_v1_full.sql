-- ==========================================
-- SKYTE OPS - CANONICAL SCHEMA BASELINE
-- ==========================================
-- This file is the repo-facing executable schema target rewritten from:
-- - docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md
-- - docs/11_build_specs/05_WORKFLOW_STATE_MACHINE_SPEC.md
-- - docs/11_build_specs/06_UPLOAD_STORAGE_RETENTION_SPEC.md
--
-- It is a canonical target baseline and has not been runtime-validated in this rewrite pass.

SET NAMES utf8mb4;

-- ==========================================
-- ACCESS AND GOVERNANCE
-- ==========================================

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(100) NOT NULL,
    role_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    landing_module_key VARCHAR(120) NOT NULL,
    is_system_role TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_role_key (role_key),
    UNIQUE KEY uq_roles_role_name (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE permission_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_key VARCHAR(50) NOT NULL,
    group_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permission_groups_group_key (group_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    failed_attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_failed_attempt_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    force_password_reset TINYINT(1) NOT NULL DEFAULT 0,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL,
    deleted_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role_id FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_users_deleted_by_user_id FOREIGN KEY (deleted_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_id (role_id),
    KEY idx_users_is_active (is_active),
    KEY idx_users_login_lock (failed_attempt_count, last_failed_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE roles
    ADD CONSTRAINT fk_roles_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE role_permission_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    permission_group_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rpm_role_id FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rpm_permission_group_id FOREIGN KEY (permission_group_id) REFERENCES permission_groups(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_rpm_role_id (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE role_module_scopes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    module_key VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rms_role_id FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY uq_rms_role_module (role_id, module_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    old_values_json LONGTEXT NULL,
    new_values_json LONGTEXT NULL,
    override_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_actor_user_id FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_actor (actor_user_id),
    KEY idx_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE system_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value LONGTEXT NOT NULL,
    value_type ENUM('STRING','INTEGER','BOOLEAN','JSON') NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_system_settings_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- GREEN BELT DOMAIN
-- ==========================================

CREATE TABLE green_belts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    belt_code VARCHAR(100) NOT NULL,
    common_name VARCHAR(150) NOT NULL,
    authority_name VARCHAR(150) NOT NULL,
    zone VARCHAR(150) NULL,
    location_text VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    permission_start_date DATE NULL,
    permission_end_date DATE NULL,
    permission_status ENUM('APPLIED','AGREEMENT_SIGNED','EXPIRED') NOT NULL,
    maintenance_mode ENUM('MAINTAINED','OUTSOURCED') NOT NULL,
    watering_frequency ENUM('DAILY','ALTERNATE_DAY','WEEKLY','NOT_REQUIRED') NOT NULL,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_green_belts_belt_code (belt_code),
    KEY idx_green_belts_zone (zone),
    KEY idx_green_belts_permission_status (permission_status),
    KEY idx_green_belts_maintenance_mode (maintenance_mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE belt_supervisor_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    belt_id BIGINT UNSIGNED NOT NULL,
    supervisor_user_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bsa_belt_id FOREIGN KEY (belt_id) REFERENCES green_belts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_bsa_supervisor_user_id FOREIGN KEY (supervisor_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    KEY idx_bsa_belt_dates (belt_id, start_date, end_date),
    KEY idx_bsa_supervisor_dates (supervisor_user_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE belt_authority_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    belt_id BIGINT UNSIGNED NOT NULL,
    authority_user_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_baa_belt_id FOREIGN KEY (belt_id) REFERENCES green_belts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_baa_authority_user_id FOREIGN KEY (authority_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    KEY idx_baa_belt_dates (belt_id, start_date, end_date),
    KEY idx_baa_authority_dates (authority_user_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE belt_outsourced_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    belt_id BIGINT UNSIGNED NOT NULL,
    outsourced_user_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_boa_belt_id FOREIGN KEY (belt_id) REFERENCES green_belts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_boa_outsourced_user_id FOREIGN KEY (outsourced_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    KEY idx_boa_belt_dates (belt_id, start_date, end_date),
    KEY idx_boa_outsourced_dates (outsourced_user_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE maintenance_cycles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    belt_id BIGINT UNSIGNED NOT NULL,
    started_by_user_id BIGINT UNSIGNED NOT NULL,
    closed_by_user_id BIGINT UNSIGNED NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    close_reason VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_maintenance_cycles_belt_id FOREIGN KEY (belt_id) REFERENCES green_belts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_cycles_started_by_user_id FOREIGN KEY (started_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_cycles_closed_by_user_id FOREIGN KEY (closed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_cycles_belt_start (belt_id, start_date),
    KEY idx_cycles_belt_end (belt_id, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE watering_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    belt_id BIGINT UNSIGNED NOT NULL,
    watering_date DATE NOT NULL,
    status ENUM('DONE','NOT_REQUIRED') NOT NULL,
    reason_text TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    override_by_user_id BIGINT UNSIGNED NULL,
    override_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_watering_records_belt_id FOREIGN KEY (belt_id) REFERENCES green_belts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_watering_records_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_watering_records_override_by_user_id FOREIGN KEY (override_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_watering_records_belt_date (belt_id, watering_date),
    KEY idx_watering_date (watering_date),
    KEY idx_watering_belt_date (belt_id, watering_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE supervisor_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supervisor_user_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('PRESENT','ABSENT') NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    override_by_user_id BIGINT UNSIGNED NULL,
    override_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_supervisor_attendance_supervisor_user_id FOREIGN KEY (supervisor_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_supervisor_attendance_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_supervisor_attendance_override_by_user_id FOREIGN KEY (override_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_supervisor_attendance_user_date (supervisor_user_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE labour_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    belt_id BIGINT UNSIGNED NOT NULL,
    entry_date DATE NOT NULL,
    labour_count INT UNSIGNED NOT NULL DEFAULT 0,
    gardener_count INT UNSIGNED NOT NULL DEFAULT 0,
    night_guard_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    override_by_user_id BIGINT UNSIGNED NULL,
    override_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_labour_entries_belt_id FOREIGN KEY (belt_id) REFERENCES green_belts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_labour_entries_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_labour_entries_override_by_user_id FOREIGN KEY (override_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_labour_entries_belt_date (belt_id, entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- EXECUTION RESOURCES
-- ==========================================

CREATE TABLE fabrication_workers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    worker_name VARCHAR(150) NOT NULL,
    skill_tag VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_workers_skill_tag (skill_tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- ADVERTISEMENT AND MONITORING
-- ==========================================

CREATE TABLE sites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_code VARCHAR(100) NOT NULL,
    location_text VARCHAR(255) NOT NULL,
    site_category ENUM('GREEN_BELT','CITY','HIGHWAY') NOT NULL,
    green_belt_id BIGINT UNSIGNED NULL,
    route_or_group VARCHAR(150) NULL,
    ownership_name VARCHAR(150) NULL,
    board_type VARCHAR(100) NULL,
    lighting_type ENUM('LIT','NON_LIT') NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sites_green_belt_id FOREIGN KEY (green_belt_id) REFERENCES green_belts(id) ON DELETE SET NULL,
    UNIQUE KEY uq_sites_site_code (site_code),
    KEY idx_sites_category (site_category),
    KEY idx_sites_green_belt_id (green_belt_id),
    KEY idx_sites_route_or_group (route_or_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE site_monitoring_due_dates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    plan_month DATE NOT NULL,
    source_group_key VARCHAR(100) NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_smdd_site_id FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    CONSTRAINT fk_smdd_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_smdd_site_due_date (site_id, due_date),
    KEY idx_smdd_due_date (due_date),
    KEY idx_smdd_plan_month (plan_month),
    KEY idx_smdd_source_group_key (source_group_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE campaigns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_code VARCHAR(100) NOT NULL,
    client_name VARCHAR(150) NOT NULL,
    campaign_name VARCHAR(150) NOT NULL,
    start_date DATE NOT NULL,
    expected_end_date DATE NOT NULL,
    actual_end_date DATE NULL,
    status ENUM('ACTIVE','ENDED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_campaigns_campaign_code (campaign_code),
    KEY idx_campaigns_status (status),
    KEY idx_campaigns_client_name (client_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE campaign_sites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    site_id BIGINT UNSIGNED NOT NULL,
    linked_from_date DATE NOT NULL,
    linked_to_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_campaign_sites_campaign_id FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_sites_site_id FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    UNIQUE KEY uq_campaign_sites_history (campaign_id, site_id, linked_from_date),
    KEY idx_campaign_sites_site_id (site_id),
    KEY idx_campaign_sites_campaign_id (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE free_media_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    source_type ENUM('MONITORING_DISCOVERY','CAMPAIGN_END') NOT NULL,
    source_reference_id BIGINT UNSIGNED NULL,
    discovered_date DATE NOT NULL,
    confirmed_by_user_id BIGINT UNSIGNED NULL,
    confirmed_date DATE NULL,
    status ENUM('DISCOVERED','CONFIRMED_ACTIVE','EXPIRED','CONSUMED') NOT NULL DEFAULT 'DISCOVERED',
    expiry_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_free_media_records_site_id FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    CONSTRAINT fk_free_media_records_confirmed_by_user_id FOREIGN KEY (confirmed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_free_media_site_id (site_id),
    KEY idx_free_media_status (status),
    KEY idx_free_media_source (source_type, source_reference_id),
    KEY idx_free_media_expiry_date (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- REQUESTS, ISSUES, TASKS, AND WORKERS
-- ==========================================

CREATE TABLE task_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_user_id BIGINT UNSIGNED NOT NULL,
    request_source_role VARCHAR(100) NOT NULL,
    request_type VARCHAR(100) NOT NULL,
    client_name VARCHAR(150) NULL,
    campaign_id BIGINT UNSIGNED NULL,
    site_id BIGINT UNSIGNED NULL,
    belt_id BIGINT UNSIGNED NULL,
    description TEXT NOT NULL,
    status ENUM('SUBMITTED','APPROVED','REJECTED','CONVERTED') NOT NULL DEFAULT 'SUBMITTED',
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    rejection_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_task_requests_requester_user_id FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_task_requests_campaign_id FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
    CONSTRAINT fk_task_requests_site_id FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL,
    CONSTRAINT fk_task_requests_belt_id FOREIGN KEY (belt_id) REFERENCES green_belts(id) ON DELETE SET NULL,
    CONSTRAINT fk_task_requests_reviewed_by_user_id FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_task_requests_status (status),
    KEY idx_task_requests_requester (requester_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE issues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_type VARCHAR(100) NOT NULL,
    source_reference_id BIGINT UNSIGNED NULL,
    belt_id BIGINT UNSIGNED NULL,
    site_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    priority ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'MEDIUM',
    status ENUM('OPEN','IN_PROGRESS','CLOSED') NOT NULL DEFAULT 'OPEN',
    raised_by_user_id BIGINT UNSIGNED NOT NULL,
    closed_by_user_id BIGINT UNSIGNED NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_issues_belt_id FOREIGN KEY (belt_id) REFERENCES green_belts(id) ON DELETE SET NULL,
    CONSTRAINT fk_issues_site_id FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL,
    CONSTRAINT fk_issues_raised_by_user_id FOREIGN KEY (raised_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_issues_closed_by_user_id FOREIGN KEY (closed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_issues_status (status),
    KEY idx_issues_priority (priority),
    KEY idx_issues_belt_id (belt_id),
    KEY idx_issues_site_id (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NULL,
    linked_issue_id BIGINT UNSIGNED NULL,
    task_source_type VARCHAR(100) NOT NULL,
    assigned_by_user_id BIGINT UNSIGNED NOT NULL,
    assigned_lead_user_id BIGINT UNSIGNED NULL,
    task_category VARCHAR(100) NOT NULL,
    vertical_type ENUM('GREEN_BELT','ADVERTISEMENT','MONITORING') NOT NULL,
    work_description TEXT NOT NULL,
    location_text VARCHAR(255) NOT NULL,
    priority ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'MEDIUM',
    start_date DATE NOT NULL,
    expected_close_date DATE NULL,
    actual_close_date DATE NULL,
    status ENUM('OPEN','RUNNING','COMPLETED','CANCELLED','ARCHIVED') NOT NULL DEFAULT 'OPEN',
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    remark_1 TEXT NULL,
    remark_2 TEXT NULL,
    completion_note TEXT NULL,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    archived_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tasks_request_id FOREIGN KEY (request_id) REFERENCES task_requests(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_linked_issue_id FOREIGN KEY (linked_issue_id) REFERENCES issues(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_assigned_by_user_id FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tasks_assigned_lead_user_id FOREIGN KEY (assigned_lead_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_tasks_progress_percent CHECK (progress_percent <= 100),
    UNIQUE KEY uq_tasks_linked_issue_id (linked_issue_id),
    KEY idx_tasks_status (status),
    KEY idx_tasks_assigned_lead (assigned_lead_user_id),
    KEY idx_tasks_request_id (request_id),
    KEY idx_tasks_linked_issue_id (linked_issue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE worker_daily_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    worker_id BIGINT UNSIGNED NOT NULL,
    entry_date DATE NOT NULL,
    attendance_status ENUM('PRESENT','ABSENT','HALF_DAY') NOT NULL,
    activity_type ENUM('INSTALLATION','MAINTENANCE','DRIVING','MONITORING','SUPPORT','OTHER') NOT NULL,
    task_id BIGINT UNSIGNED NULL,
    site_id BIGINT UNSIGNED NULL,
    work_plan TEXT NULL,
    work_update TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_worker_daily_entries_worker_id FOREIGN KEY (worker_id) REFERENCES fabrication_workers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_worker_daily_entries_task_id FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    CONSTRAINT fk_worker_daily_entries_site_id FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL,
    CONSTRAINT fk_worker_daily_entries_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_worker_daily_entries_worker_date (worker_id, entry_date),
    KEY idx_wde_date (entry_date),
    KEY idx_wde_task_id (task_id),
    KEY idx_wde_site_id (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE task_worker_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NOT NULL,
    worker_id BIGINT UNSIGNED NOT NULL,
    assigned_by_user_id BIGINT UNSIGNED NOT NULL,
    assigned_date DATE NOT NULL,
    release_date DATE NULL,
    assignment_role ENUM('PRIMARY','HELPER') NOT NULL DEFAULT 'HELPER',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_task_worker_assignments_task_id FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_task_worker_assignments_worker_id FOREIGN KEY (worker_id) REFERENCES fabrication_workers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_task_worker_assignments_assigned_by_user_id FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_task_worker_assignments_history (task_id, worker_id, assigned_date),
    KEY idx_twa_task_id (task_id),
    KEY idx_twa_worker_id (worker_id),
    KEY idx_twa_assigned_date (assigned_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE uploads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_type ENUM('GREEN_BELT','SITE','TASK') NOT NULL,
    parent_id BIGINT UNSIGNED NOT NULL,
    upload_type ENUM('WORK','ISSUE') NOT NULL,
    work_type VARCHAR(100) NULL,
    is_discovery_mode TINYINT(1) NOT NULL DEFAULT 0,
    file_path VARCHAR(255) NULL,
    original_file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size_bytes BIGINT UNSIGNED NOT NULL,
    photo_label ENUM('BEFORE_WORK','AFTER_WORK','GENERAL') NOT NULL DEFAULT 'GENERAL',
    comment_text TEXT NULL,
    gps_latitude DECIMAL(10,7) NULL,
    gps_longitude DECIMAL(10,7) NULL,
    authority_visibility ENUM('HIDDEN','APPROVED','REJECTED','NOT_ELIGIBLE') NOT NULL,
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL,
    deleted_by_user_id BIGINT UNSIGNED NULL,
    is_purged TINYINT(1) NOT NULL DEFAULT 0,
    purged_at DATETIME NULL,
    purged_by_user_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_uploads_reviewed_by_user_id FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_uploads_deleted_by_user_id FOREIGN KEY (deleted_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_uploads_purged_by_user_id FOREIGN KEY (purged_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_uploads_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    KEY idx_uploads_parent (parent_type, parent_id),
    KEY idx_uploads_created_at (created_at),
    KEY idx_uploads_visibility (authority_visibility),
    KEY idx_uploads_work_type (work_type),
    KEY idx_uploads_discovery_mode (is_discovery_mode),
    KEY idx_uploads_deleted (is_deleted),
    KEY idx_uploads_purged (is_purged)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
