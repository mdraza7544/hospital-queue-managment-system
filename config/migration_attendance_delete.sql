-- Migration: Add Duty Attendance Table and Soft Delete Columns
USE queue_db;

-- 1. Create Duty Attendance Table
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('staff', 'doctor') NOT NULL,
    user_id INT NOT NULL,
    work_date DATE NOT NULL,
    check_in_at DATETIME NOT NULL,
    check_out_at DATETIME NULL,
    auto_closed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Unique virtual column: guarantees at most one open duty check-in per user at DB level
    open_user_key VARCHAR(50) GENERATED ALWAYS AS (
        IF(check_out_at IS NULL, CONCAT(user_type, '_', user_id), NULL)
    ) VIRTUAL,
    UNIQUE KEY uq_user_open_duty (open_user_key),
    INDEX idx_user_workdate (user_type, user_id, work_date),
    INDEX idx_open_records (check_out_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add Soft Delete Columns to staff
ALTER TABLE staff 
    ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;

-- 3. Add Soft Delete Columns to doctors
ALTER TABLE doctors 
    ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
