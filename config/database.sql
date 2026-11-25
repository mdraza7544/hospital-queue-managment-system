-- Fresh Install Database Schema for Hospital Queueing System
CREATE DATABASE IF NOT EXISTS queue_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE queue_db;

-- Drop existing tables in reverse dependency order if setting up fresh
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS user_sessions;
DROP TABLE IF EXISTS queue_tickets;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS doctors;
DROP TABLE IF EXISTS departments;
SET FOREIGN_KEY_CHECKS = 1;

-- Table: departments
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    prefix VARCHAR(10) NOT NULL,
    window_number INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: doctors
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    username VARCHAR(50) NULL UNIQUE,
    password VARCHAR(255) NULL,
    room_label VARCHAR(50) NULL,
    department_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Table: staff
CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL,
    department_id INT,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- Table: queue_tickets
CREATE TABLE queue_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(20) NOT NULL UNIQUE,
    patient_name VARCHAR(100) NOT NULL,
    appointment_number VARCHAR(50) NULL,
    age TINYINT UNSIGNED NULL,
    gender ENUM('Male', 'Female', 'Other') NULL,
    weight_kg DECIMAL(5,2) NULL,
    bp VARCHAR(10) NULL,
    department_id INT NOT NULL,
    doctor_id INT NULL,
    referred_by_doctor_id INT NULL,
    referred_from_ticket_id INT NULL,
    is_priority BOOLEAN DEFAULT FALSE,
    status ENUM('waiting', 'serving', 'completed', 'referred', 'no-show', 'cancelled') DEFAULT 'waiting',
    called_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    referred_at DATETIME NULL,
    staff_id INT NULL,
    checked_in_by_staff_id INT NULL,
    referred_to_department_id INT NULL,
    referred_to_doctor_id INT NULL,
    referral_notes TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
    FOREIGN KEY (referred_by_doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
    FOREIGN KEY (referred_from_ticket_id) REFERENCES queue_tickets(id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (checked_in_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (referred_to_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (referred_to_doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
);

-- Table: user_sessions (Attendance & Live status)
CREATE TABLE user_sessions (
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

-- Table: attendance (Duty Attendance Self Check-In / Check-Out)
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('staff', 'doctor') NOT NULL,
    user_id INT NOT NULL,
    work_date DATE NOT NULL,
    check_in_at DATETIME NOT NULL,
    check_out_at DATETIME NULL,
    auto_closed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    open_user_key VARCHAR(50) GENERATED ALWAYS AS (
        IF(check_out_at IS NULL, CONCAT(user_type, '_', user_id), NULL)
    ) VIRTUAL,
    UNIQUE KEY uq_user_open_duty (open_user_key),
    INDEX idx_user_workdate (user_type, user_id, work_date),
    INDEX idx_open_records (check_out_at)
);

-- Indexes for performance
CREATE INDEX idx_appointment_date ON queue_tickets (appointment_number, created_at);
CREATE INDEX idx_dept_status ON queue_tickets (department_id, status);
CREATE INDEX idx_created_at ON queue_tickets (created_at);
CREATE INDEX idx_status ON queue_tickets (status);
CREATE INDEX idx_doctor_status ON queue_tickets (doctor_id, status);
CREATE INDEX idx_dept_created ON queue_tickets (department_id, created_at);
CREATE INDEX idx_staff_created ON queue_tickets (checked_in_by_staff_id, created_at);
CREATE INDEX idx_doctor_completed ON queue_tickets (doctor_id, completed_at);

-- Seed Hospital Departments
INSERT INTO departments (id, name, prefix, window_number) VALUES
(1, 'Cardiology', 'CAR', 1),
(2, 'Neurology', 'NEU', 2),
(3, 'Orthopedics', 'ORT', 3),
(4, 'Pediatrics', 'PED', 4),
(5, 'Gynecology', 'GYN', 5),
(6, 'ENT', 'ENT', 6),
(7, 'Ophthalmology', 'EYE', 7),
(8, 'Dermatology', 'DER', 8),
(9, 'General Medicine', 'GEN', 9),
(10, 'Admin', 'ADM', 10);

-- Default Admin & Staff Accounts
-- Password for admin: admin123
-- Password for admission & cardio.staff: admission123 / password123
INSERT INTO staff (id, username, password, full_name, role, is_active, department_id) VALUES
(1, 'admin', '$2y$10$jpQlgHHCk.KoZ8NXxbBOT.SNM4wRITF.iahN2bN.4SDXCfWWStMzW', 'System Administrator', 'admin', 1, 10),
(2, 'admission', '$2y$10$RlAOMRtTKo5Kyh9kWVv/9.vM.5.fRFVjfpRNrTn2xnt4POK.4Q7eq', 'Admission Desk Staff', 'staff', 1, 2),
(3, 'cardio.staff', '$2y$10$KI0BLZCAkhLZ8OqXEpeIiO9JYO.7FgbZBjPlPWNB8U1cn3sFY6A6S', 'Cardiology Desk Staff', 'staff', 1, 1);

-- Default Doctors with Login Credentials
-- Password for all seed doctors: password123
INSERT INTO doctors (id, full_name, specialization, username, password, room_label, department_id, is_active) VALUES
(1, 'Dr. Alan Grant', 'Interventional Cardiology', 'dr.grant', '$2y$10$KI0BLZCAkhLZ8OqXEpeIiO9JYO.7FgbZBjPlPWNB8U1cn3sFY6A6S', 'Clinic 101', 1, 1),
(2, 'Dr. Sarah Jenkins', 'Cardiologist', 'dr.jenkins', '$2y$10$KI0BLZCAkhLZ8OqXEpeIiO9JYO.7FgbZBjPlPWNB8U1cn3sFY6A6S', 'Clinic 102', 1, 1),
(3, 'Dr. Emily Chen', 'Neurologist', 'dr.chen', '$2y$10$KI0BLZCAkhLZ8OqXEpeIiO9JYO.7FgbZBjPlPWNB8U1cn3sFY6A6S', 'Clinic 201', 2, 1),
(4, 'Dr. Michael Davis', 'Orthopedic Surgeon', 'dr.davis', '$2y$10$KI0BLZCAkhLZ8OqXEpeIiO9JYO.7FgbZBjPlPWNB8U1cn3sFY6A6S', 'Clinic 301', 3, 1),
(5, 'Dr. Lisa Wong', 'Pediatrician', 'dr.wong', '$2y$10$KI0BLZCAkhLZ8OqXEpeIiO9JYO.7FgbZBjPlPWNB8U1cn3sFY6A6S', 'Clinic 401', 4, 1),
(6, 'Dr. Robert Taylor', 'General Physician', 'dr.taylor', '$2y$10$KI0BLZCAkhLZ8OqXEpeIiO9JYO.7FgbZBjPlPWNB8U1cn3sFY6A6S', 'Clinic 901', 9, 1);
