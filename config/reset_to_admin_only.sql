-- Hospital Queueing System - Reset to Admin-Only State
-- WARNING: This will permanently delete all queue tickets, clinical consultations, 
-- doctor accounts, non-admin staff accounts, duty attendance records, and login sessions.
-- Clinical departments and the primary administrator account (admin / admin123) are preserved.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Truncate all transaction and clinical queue records
TRUNCATE TABLE queue_tickets;

-- 2. Truncate all attendance and session records
TRUNCATE TABLE attendance;
TRUNCATE TABLE user_sessions;

-- 3. Remove all doctor accounts and reset auto-increment
TRUNCATE TABLE doctors;

-- 4. Delete all non-admin staff members
DELETE FROM staff WHERE username != 'admin';

-- 5. Ensure the default administrator account exists and is active
INSERT INTO staff (id, username, password, full_name, role, is_active, is_deleted, department_id, created_at)
VALUES (
    1, 
    'admin', 
    '$2y$10$D2mFDAE3Os6dWAWuY54GP.E9D3jGs8r8Bu.Gkocn.IjahXPnKBbnK', -- verified bcrypt hash of 'admin123'
    'Hospital Administrator', 
    'admin', 
    1, 
    0, 
    10, 
    NOW()
)
ON DUPLICATE KEY UPDATE 
    password = '$2y$10$D2mFDAE3Os6dWAWuY54GP.E9D3jGs8r8Bu.Gkocn.IjahXPnKBbnK',
    full_name = 'Hospital Administrator',
    role = 'admin',
    is_active = 1,
    is_deleted = 0,
    department_id = 10;

-- Reset staff auto-increment
ALTER TABLE staff AUTO_INCREMENT = 2;

SET FOREIGN_KEY_CHECKS = 1;
