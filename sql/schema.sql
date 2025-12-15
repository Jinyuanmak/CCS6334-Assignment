-- Database schema for Private Clinic Patient Record System
-- Creates the database and all required tables with proper indexing and security features

CREATE DATABASE IF NOT EXISTS clinic_db;
USE clinic_db;

-- Create users table first (no dependencies)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'doctor') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create patients table (no dependencies)
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    ic_number VARCHAR(20) UNIQUE NOT NULL,
    diagnosis BLOB NOT NULL,
    phone VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create doctors table (depends on users)
CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create appointments table (depends on patients and doctors)
CREATE TABLE IF NOT EXISTS appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    appointment_date DATETIME NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    doctor_name VARCHAR(100) NOT NULL,
    doctor_id INT,
    reason BLOB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
);

-- Create message_logs table (depends on patients, appointments, and doctors)
CREATE TABLE IF NOT EXISTS message_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_type ENUM('PATIENT_UPDATE', 'PATIENT_CREATE', 'PATIENT_DELETE', 'APPOINTMENT_UPDATE') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    patient_id INT,
    appointment_id INT,
    doctor_id INT,
    change_details JSON,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    KEY idx_is_read (is_read),
    KEY idx_created_at (created_at),
    KEY idx_message_type (message_type),
    KEY idx_doctor_id (doctor_id)
);

-- Create audit_logs table (depends on users for user_id reference, but no foreign key constraint)
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    action VARCHAR(20) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_action (action),
    KEY idx_created_at (created_at),
    KEY idx_username (username)
);

-- Create login_attempts table for progressive lockout system
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(50),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    lockout_until TIMESTAMP NULL,
    attempt_count INT DEFAULT 1,
    KEY idx_ip_address (ip_address),
    KEY idx_username (username),
    KEY idx_attempt_time (attempt_time),
    KEY idx_lockout_until (lockout_until)
);

-- Create indexes for performance (only if tables exist)
CREATE INDEX IF NOT EXISTS idx_ic_number ON patients(ic_number);
CREATE INDEX IF NOT EXISTS idx_name ON patients(name);
CREATE INDEX IF NOT EXISTS idx_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_audit_logs_composite ON audit_logs (created_at DESC, action, user_id);
CREATE INDEX IF NOT EXISTS idx_appointments_patient ON appointments(patient_id);
CREATE INDEX IF NOT EXISTS idx_appointments_date ON appointments(appointment_date);

-- Additional indexes for doctor dashboard pagination performance
CREATE INDEX IF NOT EXISTS idx_appointments_doctor_time ON appointments(doctor_id, start_time);
CREATE INDEX IF NOT EXISTS idx_appointments_doctor_end_time ON appointments(doctor_id, end_time);
CREATE INDEX IF NOT EXISTS idx_patients_created_at ON patients(created_at);
CREATE INDEX IF NOT EXISTS idx_doctors_user_id ON doctors(user_id);

-- Note: Initial data seeding is handled by seed_data.php
-- This ensures secure password hashing and prevents hardcoded credentials