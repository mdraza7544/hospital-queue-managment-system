-- ==============================================================================
-- Migration Script: Add Role-Based Dashboards (Admin / Staff / Doctor)
-- Database: queue_db
-- ==============================================================================

USE queue_db;

DROP PROCEDURE IF EXISTS MigrateRolesSchema;

DELIMITER //
CREATE PROCEDURE MigrateRolesSchema()
BEGIN
    -- 1. Update staff table
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff' AND COLUMN_NAME = 'role') THEN
        ALTER TABLE staff ADD COLUMN role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff' AFTER full_name;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff' AND COLUMN_NAME = 'is_active') THEN
        ALTER TABLE staff ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff' AND COLUMN_NAME = 'last_login_at') THEN
        ALTER TABLE staff ADD COLUMN last_login_at DATETIME NULL AFTER is_active;
    END IF;

    -- Update existing admin user role
    UPDATE staff SET role = 'admin' WHERE username = 'admin';

    -- 2. Update doctors table
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'doctors' AND COLUMN_NAME = 'username') THEN
        ALTER TABLE doctors ADD COLUMN username VARCHAR(50) NULL UNIQUE AFTER specialization;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'doctors' AND COLUMN_NAME = 'password') THEN
        ALTER TABLE doctors ADD COLUMN password VARCHAR(255) NULL AFTER username;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'doctors' AND COLUMN_NAME = 'room_label') THEN
        ALTER TABLE doctors ADD COLUMN room_label VARCHAR(50) NULL AFTER password;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'doctors' AND COLUMN_NAME = 'last_login_at') THEN
        ALTER TABLE doctors ADD COLUMN last_login_at DATETIME NULL AFTER is_active;
    END IF;

    -- 3. Update queue_tickets table
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'checked_in_by_staff_id') THEN
        ALTER TABLE queue_tickets ADD COLUMN checked_in_by_staff_id INT NULL AFTER staff_id;
        ALTER TABLE queue_tickets ADD CONSTRAINT fk_checked_in_staff FOREIGN KEY (checked_in_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'referred_by_doctor_id') THEN
        ALTER TABLE queue_tickets ADD COLUMN referred_by_doctor_id INT NULL AFTER doctor_id;
        ALTER TABLE queue_tickets ADD CONSTRAINT fk_referred_by_doc FOREIGN KEY (referred_by_doctor_id) REFERENCES doctors(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'referred_at') THEN
        ALTER TABLE queue_tickets ADD COLUMN referred_at DATETIME NULL AFTER completed_at;
    END IF;

    -- Indexes on queue_tickets
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND INDEX_NAME = 'idx_doctor_status') THEN
        CREATE INDEX idx_doctor_status ON queue_tickets (doctor_id, status);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND INDEX_NAME = 'idx_dept_created') THEN
        CREATE INDEX idx_dept_created ON queue_tickets (department_id, created_at);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND INDEX_NAME = 'idx_staff_created') THEN
        CREATE INDEX idx_staff_created ON queue_tickets (checked_in_by_staff_id, created_at);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND INDEX_NAME = 'idx_doctor_completed') THEN
        CREATE INDEX idx_doctor_completed ON queue_tickets (doctor_id, completed_at);
    END IF;

    -- 4. Create user_sessions table
    CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_type ENUM('staff', 'doctor') NOT NULL,
        user_id INT NOT NULL,
        login_at DATETIME NOT NULL,
        logout_at DATETIME NULL,
        last_seen_at DATETIME NOT NULL,
        ip_address VARCHAR(45) NULL,
        INDEX idx_user_active (user_type, user_id, last_seen_at),
        INDEX idx_login_at (login_at)
    );

END //
DELIMITER ;

CALL MigrateRolesSchema();
DROP PROCEDURE IF EXISTS MigrateRolesSchema;

SELECT 'Roles migration completed successfully!' AS status;
