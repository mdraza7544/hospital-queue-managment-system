-- ==============================================================================
-- Migration Script: Convert STI Queueing System to Hospital Queueing System
-- Database: queue_db
-- ==============================================================================

USE queue_db;

-- 1. Update existing departments to preserve foreign keys
UPDATE departments SET name = 'Cardiology', prefix = 'CAR', window_number = 1 WHERE id = 1;
UPDATE departments SET name = 'Neurology', prefix = 'NEU', window_number = 2 WHERE id = 2;
UPDATE departments SET name = 'Orthopedics', prefix = 'ORT', window_number = 3 WHERE id = 3;
UPDATE departments SET name = 'Pediatrics', prefix = 'PED', window_number = 4 WHERE id = 4;

-- Insert remaining hospital departments
INSERT INTO departments (id, name, prefix, window_number) VALUES
(5, 'Gynecology', 'GYN', 5),
(6, 'ENT', 'ENT', 6),
(7, 'Ophthalmology', 'EYE', 7),
(8, 'Dermatology', 'DER', 8),
(9, 'General Medicine', 'GEN', 9),
(10, 'Admin', 'ADM', 10)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name), 
    prefix = VALUES(prefix), 
    window_number = VALUES(window_number);

-- 2. Ensure default admin user belongs to Admin department (ID 10)
UPDATE staff 
SET department_id = (SELECT id FROM departments WHERE name = 'Admin' LIMIT 1) 
WHERE username = 'admin';

-- 3. Create doctors table
CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    department_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Seed sample doctors
INSERT INTO doctors (full_name, specialization, department_id, is_active)
SELECT 'Dr. John Smith', 'Cardiologist', d.id, 1 
FROM departments d 
WHERE d.name = 'Cardiology' AND NOT EXISTS (SELECT 1 FROM doctors WHERE full_name = 'Dr. John Smith');

INSERT INTO doctors (full_name, specialization, department_id, is_active)
SELECT 'Dr. Sarah Jenkins', 'Interventional Cardiology', d.id, 1 
FROM departments d 
WHERE d.name = 'Cardiology' AND NOT EXISTS (SELECT 1 FROM doctors WHERE full_name = 'Dr. Sarah Jenkins');

INSERT INTO doctors (full_name, specialization, department_id, is_active)
SELECT 'Dr. Emily Chen', 'Neurologist', d.id, 1 
FROM departments d 
WHERE d.name = 'Neurology' AND NOT EXISTS (SELECT 1 FROM doctors WHERE full_name = 'Dr. Emily Chen');

INSERT INTO doctors (full_name, specialization, department_id, is_active)
SELECT 'Dr. Michael Davis', 'Orthopedic Surgeon', d.id, 1 
FROM departments d 
WHERE d.name = 'Orthopedics' AND NOT EXISTS (SELECT 1 FROM doctors WHERE full_name = 'Dr. Michael Davis');

INSERT INTO doctors (full_name, specialization, department_id, is_active)
SELECT 'Dr. Lisa Wong', 'Pediatrician', d.id, 1 
FROM departments d 
WHERE d.name = 'Pediatrics' AND NOT EXISTS (SELECT 1 FROM doctors WHERE full_name = 'Dr. Lisa Wong');

INSERT INTO doctors (full_name, specialization, department_id, is_active)
SELECT 'Dr. Robert Taylor', 'General Physician', d.id, 1 
FROM departments d 
WHERE d.name = 'General Medicine' AND NOT EXISTS (SELECT 1 FROM doctors WHERE full_name = 'Dr. Robert Taylor');

-- 4. Idempotent migration procedure for queue_tickets table
DROP PROCEDURE IF EXISTS MigrateQueueTickets;

DELIMITER //
CREATE PROCEDURE MigrateQueueTickets()
BEGIN
    -- Rename student_name to patient_name
    IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'student_name') THEN
        ALTER TABLE queue_tickets CHANGE COLUMN student_name patient_name VARCHAR(100) NOT NULL;
    END IF;

    -- Rename student_number to appointment_number
    IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'student_number') THEN
        ALTER TABLE queue_tickets CHANGE COLUMN student_number appointment_number VARCHAR(50) NULL;
    END IF;

    -- Add age
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'age') THEN
        ALTER TABLE queue_tickets ADD COLUMN age TINYINT UNSIGNED NULL AFTER appointment_number;
    END IF;

    -- Add gender
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'gender') THEN
        ALTER TABLE queue_tickets ADD COLUMN gender ENUM('Male', 'Female', 'Other') NULL AFTER age;
    END IF;

    -- Add weight_kg
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'weight_kg') THEN
        ALTER TABLE queue_tickets ADD COLUMN weight_kg DECIMAL(5,2) NULL AFTER gender;
    END IF;

    -- Add bp
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'bp') THEN
        ALTER TABLE queue_tickets ADD COLUMN bp VARCHAR(10) NULL AFTER weight_kg;
    END IF;

    -- Add doctor_id
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'doctor_id') THEN
        ALTER TABLE queue_tickets ADD COLUMN doctor_id INT NULL AFTER department_id;
        ALTER TABLE queue_tickets ADD CONSTRAINT fk_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL;
    END IF;

    -- Add referred_from_ticket_id
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'referred_from_ticket_id') THEN
        ALTER TABLE queue_tickets ADD COLUMN referred_from_ticket_id INT NULL AFTER doctor_id;
        ALTER TABLE queue_tickets ADD CONSTRAINT fk_referred_from FOREIGN KEY (referred_from_ticket_id) REFERENCES queue_tickets(id) ON DELETE SET NULL;
    END IF;

    -- Rename transferred_to_department_id to referred_to_department_id, or create if missing
    IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'transferred_to_department_id') THEN
        ALTER TABLE queue_tickets CHANGE COLUMN transferred_to_department_id referred_to_department_id INT NULL;
    ELSEIF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'referred_to_department_id') THEN
        ALTER TABLE queue_tickets ADD COLUMN referred_to_department_id INT NULL AFTER staff_id;
        ALTER TABLE queue_tickets ADD CONSTRAINT fk_referred_dept FOREIGN KEY (referred_to_department_id) REFERENCES departments(id) ON DELETE SET NULL;
    END IF;

    -- Add referred_to_doctor_id
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'referred_to_doctor_id') THEN
        ALTER TABLE queue_tickets ADD COLUMN referred_to_doctor_id INT NULL AFTER referred_to_department_id;
        ALTER TABLE queue_tickets ADD CONSTRAINT fk_referred_to_doc FOREIGN KEY (referred_to_doctor_id) REFERENCES doctors(id) ON DELETE SET NULL;
    END IF;

    -- Add referral_notes
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND COLUMN_NAME = 'referral_notes') THEN
        ALTER TABLE queue_tickets ADD COLUMN referral_notes TEXT NULL AFTER referred_to_doctor_id;
    END IF;

    -- Safely update status ENUM to include 'referred' and map old statuses
    ALTER TABLE queue_tickets MODIFY COLUMN status ENUM('waiting', 'serving', 'completed', 'cancelled', 'no-show', 'skipped', 'transferred', 'referred') DEFAULT 'waiting';
    UPDATE queue_tickets SET status = 'referred' WHERE status = 'transferred';
    UPDATE queue_tickets SET status = 'cancelled' WHERE status = 'skipped';
    ALTER TABLE queue_tickets MODIFY COLUMN status ENUM('waiting', 'serving', 'completed', 'referred', 'no-show', 'cancelled') DEFAULT 'waiting';

    -- Add indexes
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND INDEX_NAME = 'idx_appointment_date') THEN
        CREATE INDEX idx_appointment_date ON queue_tickets (appointment_number, created_at);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_tickets' AND INDEX_NAME = 'idx_dept_status') THEN
        CREATE INDEX idx_dept_status ON queue_tickets (department_id, status);
    END IF;
END //
DELIMITER ;

CALL MigrateQueueTickets();
DROP PROCEDURE IF EXISTS MigrateQueueTickets;

SELECT 'Hospital migration completed successfully!' AS status;
