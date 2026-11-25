-- ==============================================================================
-- ⚠️ DANGER: HOSPITAL QUEUEING SYSTEM DATA RESET SCRIPT
-- ==============================================================================
-- WARNING: This will permanently delete all queue tickets, patient history,
-- doctors, and user attendance logs.
-- ALL STAFF ACCOUNTS (including Admin) and DEPARTMENTS ARE PRESERVED.
--
-- TAKE A FULL BACKUP BEFORE RUNNING THIS SCRIPT:
-- C:\xampp\mysql\bin\mysqldump.exe -P 3307 -u root queue_db > backup_before_reset.sql
-- ==============================================================================

USE queue_db;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Clear queue tickets and history
TRUNCATE TABLE queue_tickets;

-- 2. Clear attendance sessions
TRUNCATE TABLE user_sessions;

-- 3. Clear doctors (fresh doctors with credentials will be created via Admin Dashboard)
TRUNCATE TABLE doctors;

SET FOREIGN_KEY_CHECKS = 1;

-- Notice: 'staff' and 'departments' are 100% preserved.
SELECT 'Data reset completed successfully. Staff and departments preserved.' AS status;
