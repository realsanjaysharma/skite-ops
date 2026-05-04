-- ==========================================
-- SKYTE OPS - COMPREHENSIVE TEST SEED (10 Records per Entity)
-- ==========================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Clean up existing test data (optional, but good for repeatability)
-- TRUNCATE TABLE users;
-- TRUNCATE TABLE green_belts;
-- ... (I'll skip truncation to avoid deleting system roles if they exist)

-- ==========================================
-- 1. USERS (10 Users)
-- Password for all: password123
-- ==========================================

INSERT INTO users (role_id, full_name, email, phone, password_hash, is_active)
SELECT r.id, 'Alice Ops Manager', 'alice@skyte.com', '9876543210', '$2y$10$9adPhIrX4x/pyFdeur2e/u5nhlvado0gZourTZImUgcZAEhxUtu7m', 1 FROM roles r WHERE r.role_key = 'OPS_MANAGER'
UNION ALL
SELECT r.id, 'Bob Head Supervisor', 'bob@skyte.com', '9876543211', '$2y$10$9adPhIrX4x/pyFdeur2e/u5nhlvado0gZourTZImUgcZAEhxUtu7m', 1 FROM roles r WHERE r.role_key = 'HEAD_SUPERVISOR'
UNION ALL
SELECT r.id, 'Charlie Supervisor', 'charlie@skyte.com', '9876543212', '$2y$10$9adPhIrX4x/pyFdeur2e/u5nhlvado0gZourTZImUgcZAEhxUtu7m', 1 FROM roles r WHERE r.role_key = 'GREEN_BELT_SUPERVISOR'
UNION ALL
SELECT r.id, 'David Supervisor', 'david@skyte.com', '9876543213', '$2y$10$9adPhIrX4x/pyFdeur2e/u5nhlvado0gZourTZImUgcZAEhxUtu7m', 1 FROM roles r WHERE r.role_key = 'GREEN_BELT_SUPERVISOR'
UNION ALL
SELECT r.id, 'Eve Outsourced', 'eve@partner.com', '9876543214', '$2y$10$9adPhIrX4x/pyFdeur2e/u5nhlvado0gZourTZImUgcZAEhxUtu7m', 1 FROM roles r WHERE r.role_key = 'OUTSOURCED_MAINTAINER'
UNION ALL
SELECT r.id, 'Frank Monitoring', 'frank@skyte.com', '9876543215', '$2y$10$9adPhIrX4x/pyFdeur2e/u5nhlvado0gZourTZImUgcZAEhxUtu7m', 1 FROM roles r WHERE r.role_key = 'MONITORING_TEAM'
UNION ALL
SELECT r.id, 'Grace Fabrication', 'grace@skyte.com', '9876543216', '$2y$10$9adPhIrX4x/pyFdeur2e/u5nhlvado0gZourTZImUgcZAEhxUtu7m', 1 FROM roles r WHERE r.role_key = 'FABRICATION_LEAD'
UNION ALL
SELECT r.id, 'Heidi Sales', 'heidi@skyte.com', '9876543217', '$2y$10$9adPhIrX4x/pyFdeur2e/u5nhlvado0gZourTZImUgcZAEhxUtu7m', 1 FROM roles r WHERE r.role_key = 'SALES_TEAM'
UNION ALL
SELECT r.id, 'Ivan Authority', 'ivan@gov.in', '9876543218', '$2y$10$9adPhIrX4x/pyFdeur2e/u5nhlvado0gZourTZImUgcZAEhxUtu7m', 1 FROM roles r WHERE r.role_key = 'AUTHORITY_REPRESENTATIVE'
UNION ALL
SELECT r.id, 'Mallory Management', 'mallory@skyte.com', '9876543219', '$2y$10$9adPhIrX4x/pyFdeur2e/u5nhlvado0gZourTZImUgcZAEhxUtu7m', 1 FROM roles r WHERE r.role_key = 'MANAGEMENT';

-- ==========================================
-- 2. GREEN BELTS (10 Belts)
-- ==========================================

INSERT INTO green_belts (belt_code, common_name, authority_name, zone, location_text, latitude, longitude, permission_status, maintenance_mode, watering_frequency)
VALUES
    ('GB-001', 'North Garden', 'City Council', 'North Zone', 'Main Highway Road', 28.6139, 77.2090, 'AGREEMENT_SIGNED', 'MAINTAINED', 'DAILY'),
    ('GB-002', 'South Park', 'City Council', 'South Zone', 'Near Metro Station', 28.5355, 77.2410, 'AGREEMENT_SIGNED', 'OUTSOURCED', 'ALTERNATE_DAY'),
    ('GB-003', 'East Strip', 'Forest Dept', 'East Zone', 'River Bank', 28.6280, 77.2750, 'APPLIED', 'MAINTAINED', 'WEEKLY'),
    ('GB-004', 'West Hedge', 'City Council', 'West Zone', 'Industrial Area', 28.6350, 77.1000, 'AGREEMENT_SIGNED', 'MAINTAINED', 'DAILY'),
    ('GB-005', 'Central Island', 'PWD', 'Central Zone', 'Main Roundabout', 28.6120, 77.2310, 'EXPIRED', 'OUTSOURCED', 'NOT_REQUIRED'),
    ('GB-006', 'Airport Link', 'NHAI', 'South Zone', 'Airport Road', 28.5560, 77.0850, 'AGREEMENT_SIGNED', 'MAINTAINED', 'DAILY'),
    ('GB-007', 'Metro Corridor', 'DMRC', 'West Zone', 'Under Metro Tracks', 28.6440, 77.1200, 'AGREEMENT_SIGNED', 'MAINTAINED', 'DAILY'),
    ('GB-008', 'Lake Side', 'Forest Dept', 'North Zone', 'Near City Lake', 28.7040, 77.1850, 'APPLIED', 'OUTSOURCED', 'WEEKLY'),
    ('GB-009', 'Old Town Strip', 'City Council', 'Central Zone', 'Old Fort Road', 28.6080, 77.2450, 'AGREEMENT_SIGNED', 'MAINTAINED', 'ALTERNATE_DAY'),
    ('GB-010', 'Expressway Green', 'NHAI', 'East Zone', 'Highway Mile 12', 28.5800, 77.3300, 'AGREEMENT_SIGNED', 'MAINTAINED', 'DAILY');

-- ==========================================
-- 3. ASSIGNMENTS (Supervisors/Authorities)
-- ==========================================

-- Assign supervisors to belts
INSERT INTO belt_supervisor_assignments (belt_id, supervisor_user_id, start_date)
SELECT gb.id, u.id, '2024-01-01'
FROM green_belts gb, users u
WHERE gb.belt_code IN ('GB-001', 'GB-003', 'GB-004', 'GB-006') AND u.email = 'charlie@skyte.com';

INSERT INTO belt_supervisor_assignments (belt_id, supervisor_user_id, start_date)
SELECT gb.id, u.id, '2024-01-01'
FROM green_belts gb, users u
WHERE gb.belt_code IN ('GB-007', 'GB-009', 'GB-010') AND u.email = 'david@skyte.com';

-- Assign outsourced maintainers
INSERT INTO belt_outsourced_assignments (belt_id, outsourced_user_id, start_date)
SELECT gb.id, u.id, '2024-01-01'
FROM green_belts gb, users u
WHERE gb.belt_code IN ('GB-002', 'GB-005', 'GB-008') AND u.email = 'eve@partner.com';

-- Assign authority reps
INSERT INTO belt_authority_assignments (belt_id, authority_user_id, start_date)
SELECT gb.id, u.id, '2024-01-01'
FROM green_belts gb, users u
WHERE gb.belt_code IN ('GB-001', 'GB-002', 'GB-004') AND u.email = 'ivan@gov.in';

-- ==========================================
-- 4. FABRICATION WORKERS (10 Workers)
-- ==========================================

INSERT INTO fabrication_workers (worker_name, skill_tag, phone, is_active)
VALUES
    ('Rajesh Kumar', 'Welder', '9000000001', 1),
    ('Suresh Singh', 'Painter', '9000000002', 1),
    ('Amit Sharma', 'Electrician', '9000000003', 1),
    ('Vijay Yadav', 'Helper', '9000000004', 1),
    ('Pankaj Maurya', 'Driver', '9000000005', 1),
    ('Deepak Verma', 'Welder', '9000000006', 1),
    ('Rahul Gupta', 'Painter', '9000000007', 1),
    ('Manoj Prasad', 'Helper', '9000000008', 1),
    ('Sanjay Paswan', 'Gardener', '9000000009', 1),
    ('Vikram Rathore', 'Security', '9000000010', 1);

-- ==========================================
-- 5. SITES (10 Sites)
-- ==========================================

INSERT INTO sites (site_code, location_text, site_category, green_belt_id, board_type, lighting_type, is_active)
SELECT 'SITE-001', 'NH-8 Pillar 45', 'GREEN_BELT', id, 'Unipole', 'LIT', 1 FROM green_belts WHERE belt_code = 'GB-001'
UNION ALL
SELECT 'SITE-002', 'Connaught Place Circle', 'CITY', NULL, 'Wall Wrap', 'LIT', 1
UNION ALL
SELECT 'SITE-003', 'Yamuna Expressway Exit', 'HIGHWAY', NULL, 'Gantry', 'NON_LIT', 1
UNION ALL
SELECT 'SITE-004', 'Mall Road Entrance', 'CITY', NULL, 'Backlit Board', 'LIT', 1
UNION ALL
SELECT 'SITE-005', 'Ring Road Junction', 'GREEN_BELT', id, 'Unipole', 'LIT', 1 FROM green_belts WHERE belt_code = 'GB-004'
UNION ALL
SELECT 'SITE-006', 'Tech Park Gate 1', 'CITY', NULL, 'LED Screen', 'LIT', 1
UNION ALL
SELECT 'SITE-007', 'Highway Mile 24', 'HIGHWAY', NULL, 'Hoarding', 'NON_LIT', 1
UNION ALL
SELECT 'SITE-008', 'Metro Pillar 120', 'GREEN_BELT', id, 'Unipole', 'LIT', 1 FROM green_belts WHERE belt_code = 'GB-007'
UNION ALL
SELECT 'SITE-009', 'Sector 18 Market', 'CITY', NULL, 'Wall Wrap', 'LIT', 1
UNION ALL
SELECT 'SITE-010', 'Airport Terminal 3', 'CITY', NULL, 'Internal Backlit', 'LIT', 1;

-- ==========================================
-- 6. CAMPAIGNS (10 Campaigns)
-- ==========================================

INSERT INTO campaigns (campaign_code, client_name, campaign_name, start_date, expected_end_date, status)
VALUES
    ('CAMP-24-001', 'Samsung', 'Galaxy S24 Launch', '2024-01-15', '2024-03-15', 'ACTIVE'),
    ('CAMP-24-002', 'Nike', 'Just Do It Summer', '2024-04-01', '2024-06-30', 'ACTIVE'),
    ('CAMP-24-003', 'Coca Cola', 'Share a Coke', '2024-05-01', '2024-05-31', 'ACTIVE'),
    ('CAMP-24-004', 'Tata Motors', 'Safari Adventure', '2024-02-10', '2024-04-10', 'ENDED'),
    ('CAMP-24-005', 'Zomato', 'Late Night Cravings', '2024-03-01', '2024-12-31', 'ACTIVE'),
    ('CAMP-24-006', 'HDFC Bank', 'Smart Banking', '2024-01-01', '2024-06-30', 'ACTIVE'),
    ('CAMP-24-007', 'Apple', 'iPhone 15 Pro', '2023-12-01', '2024-02-29', 'ENDED'),
    ('CAMP-24-008', 'Toyota', 'Fortuner Hybrid', '2024-06-01', '2024-08-31', 'ACTIVE'),
    ('CAMP-24-009', 'Airtel', '5G Plus Experience', '2024-02-01', '2024-05-31', 'ACTIVE'),
    ('CAMP-24-010', 'Amazon', 'Great Summer Sale', '2024-05-15', '2024-05-25', 'ACTIVE');

-- ==========================================
-- 7. CAMPAIGN-SITE MAPPINGS
-- ==========================================

INSERT INTO campaign_sites (campaign_id, site_id, linked_from_date)
SELECT c.id, s.id, c.start_date
FROM campaigns c, sites s
WHERE c.campaign_code = 'CAMP-24-001' AND s.site_code IN ('SITE-001', 'SITE-002', 'SITE-009');

INSERT INTO campaign_sites (campaign_id, site_id, linked_from_date)
SELECT c.id, s.id, c.start_date
FROM campaigns c, sites s
WHERE c.campaign_code = 'CAMP-24-005' AND s.site_code IN ('SITE-004', 'SITE-006', 'SITE-010');

-- ==========================================
-- 8. TASK REQUESTS (10 Requests)
-- ==========================================

INSERT INTO task_requests (requester_user_id, request_source_role, request_type, client_name, site_id, description, status)
SELECT u.id, 'SALES_TEAM', 'INSTALLATION', 'Samsung', s.id, 'Install new S24 skin on Unipole', 'APPROVED'
FROM users u, sites s WHERE u.email = 'heidi@skyte.com' AND s.site_code = 'SITE-001'
LIMIT 1;

INSERT INTO task_requests (requester_user_id, request_source_role, request_type, client_name, site_id, description, status)
SELECT u.id, 'SALES_TEAM', 'REMOVAL', 'Apple', s.id, 'Remove old iPhone skin', 'CONVERTED'
FROM users u, sites s WHERE u.email = 'heidi@skyte.com' AND s.site_code = 'SITE-002'
LIMIT 1;

INSERT INTO task_requests (requester_user_id, request_source_role, request_type, belt_id, description, status)
SELECT u.id, 'HEAD_SUPERVISOR', 'MAINTENANCE', gb.id, 'Repair broken fence', 'SUBMITTED'
FROM users u, green_belts gb WHERE u.email = 'bob@skyte.com' AND gb.belt_code = 'GB-003'
LIMIT 1;

-- Add 7 more dummy requests
INSERT INTO task_requests (requester_user_id, request_source_role, request_type, description, status)
SELECT id, 'OPS_MANAGER', 'OTHER', CONCAT('Misc Request ', id), 'SUBMITTED' FROM users WHERE email = 'alice@skyte.com' LIMIT 7;

-- ==========================================
-- 9. ISSUES (10 Issues)
-- ==========================================

INSERT INTO issues (source_type, belt_id, site_id, title, description, priority, status, raised_by_user_id)
SELECT 'FIELD_REPORT', id, NULL, 'Dead Plants Section A', 'Plants need urgent replacement', 'HIGH', 'OPEN', (SELECT id FROM users WHERE email = 'charlie@skyte.com') FROM green_belts WHERE belt_code = 'GB-001'
UNION ALL
SELECT 'MONITORING', NULL, id, 'Torn Flex CP Circle', 'Top right corner of flex is torn', 'MEDIUM', 'IN_PROGRESS', (SELECT id FROM users WHERE email = 'frank@skyte.com') FROM sites WHERE site_code = 'SITE-002'
UNION ALL
SELECT 'FIELD_REPORT', id, NULL, 'Watering Pump Failure', 'Main pump is making noise', 'CRITICAL', 'OPEN', (SELECT id FROM users WHERE email = 'david@skyte.com') FROM green_belts WHERE belt_code = 'GB-007';

-- Add 7 more dummy issues
INSERT INTO issues (source_type, title, description, priority, status, raised_by_user_id)
SELECT 'INTERNAL', CONCAT('Test Issue ', id), 'Automated test issue description', 'LOW', 'OPEN', (SELECT id FROM users WHERE email = 'alice@skyte.com') FROM users LIMIT 7;

-- ==========================================
-- 10. TASKS (10 Tasks)
-- ==========================================

INSERT INTO tasks (assigned_by_user_id, assigned_lead_user_id, task_category, vertical_type, work_description, location_text, priority, start_date, status)
SELECT (SELECT id FROM users WHERE email = 'alice@skyte.com'), (SELECT id FROM users WHERE email = 'grace@skyte.com'), 'INSTALLATION', 'ADVERTISEMENT', 'Install Samsung Flex', 'NH-8 Pillar 45', 'HIGH', '2024-05-01', 'RUNNING'
UNION ALL
SELECT (SELECT id FROM users WHERE email = 'alice@skyte.com'), (SELECT id FROM users WHERE email = 'grace@skyte.com'), 'MAINTENANCE', 'GREEN_BELT', 'Repair fence and replant', 'River Bank', 'MEDIUM', '2024-05-02', 'OPEN'
UNION ALL
SELECT (SELECT id FROM users WHERE email = 'alice@skyte.com'), (SELECT id FROM users WHERE email = 'grace@skyte.com'), 'MONITORING', 'MONITORING', 'Full route audit', 'City Wide', 'LOW', '2024-05-03', 'COMPLETED';

-- Add 7 more dummy tasks
INSERT INTO tasks (assigned_by_user_id, task_category, vertical_type, work_description, location_text, start_date, status)
SELECT (SELECT id FROM users WHERE email = 'alice@skyte.com'), 'OTHER', 'ADVERTISEMENT', CONCAT('Task ', id), 'Various', '2024-05-05', 'OPEN' FROM users LIMIT 7;

-- ==========================================
-- 11. WORKER ASSIGNMENTS & DAILY ENTRIES
-- ==========================================

-- Assign workers to tasks
INSERT INTO task_worker_assignments (task_id, worker_id, assigned_by_user_id, assigned_date, assignment_role)
SELECT t.id, w.id, (SELECT id FROM users WHERE email = 'alice@skyte.com'), '2024-05-01', 'PRIMARY'
FROM tasks t, fabrication_workers w
WHERE t.task_category = 'INSTALLATION' AND w.worker_name = 'Rajesh Kumar'
LIMIT 1;

-- Daily Entries
INSERT INTO worker_daily_entries (worker_id, entry_date, attendance_status, activity_type, task_id, work_update, created_by_user_id)
SELECT w.id, '2024-05-01', 'PRESENT', 'INSTALLATION', t.id, 'Started mounting the frame', (SELECT id FROM users WHERE email = 'alice@skyte.com')
FROM fabrication_workers w, tasks t
WHERE w.worker_name = 'Rajesh Kumar' AND t.task_category = 'INSTALLATION'
LIMIT 1;

SET FOREIGN_KEY_CHECKS = 1;
