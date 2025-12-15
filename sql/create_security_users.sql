-- Database Security Enhancement: Principle of Least Privilege
-- Create specialized database users with minimal required permissions
-- Run these commands in phpMyAdmin SQL tab or MySQL command line

-- =====================================================
-- STEP 1: Create Public Access User (Read-Only)
-- =====================================================
-- This user can only SELECT data - used for login verification and public searches
-- Cannot INSERT, UPDATE, DELETE, or DROP - limits SQL injection damage

CREATE USER IF NOT EXISTS 'app_public'@'localhost' IDENTIFIED BY 'Pub1ic_R3ad_0nly_2025!';

-- Grant only SELECT permissions on specific tables needed for public operations
GRANT SELECT ON clinic_db.users TO 'app_public'@'localhost';
GRANT SELECT ON clinic_db.login_attempts TO 'app_public'@'localhost';
GRANT SELECT ON clinic_db.doctors TO 'app_public'@'localhost';

-- =====================================================
-- STEP 2: Create Admin Operations User (Read-Write)
-- =====================================================
-- This user can SELECT, INSERT, UPDATE, DELETE - used for dashboard operations
-- Cannot DROP tables or ALTER schema - prevents structural damage

CREATE USER IF NOT EXISTS 'app_admin'@'localhost' IDENTIFIED BY 'Adm1n_CRUD_S3cur3_2025!';

-- Grant CRUD permissions on all application tables
GRANT SELECT, INSERT, UPDATE, DELETE ON clinic_db.users TO 'app_admin'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinic_db.patients TO 'app_admin'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinic_db.doctors TO 'app_admin'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinic_db.appointments TO 'app_admin'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinic_db.message_logs TO 'app_admin'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinic_db.audit_logs TO 'app_admin'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinic_db.login_attempts TO 'app_admin'@'localhost';

-- =====================================================
-- STEP 3: Apply Security Policies
-- =====================================================
-- Flush privileges to ensure changes take effect immediately
FLUSH PRIVILEGES;

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================
-- Run these to verify the users were created correctly:

-- Check user creation
SELECT User, Host FROM mysql.user WHERE User IN ('app_public', 'app_admin');

-- Check permissions for public user (should only show SELECT)
SHOW GRANTS FOR 'app_public'@'localhost';

-- Check permissions for admin user (should show SELECT, INSERT, UPDATE, DELETE)
SHOW GRANTS FOR 'app_admin'@'localhost';

-- =====================================================
-- SECURITY NOTES
-- =====================================================
-- 1. app_public user CANNOT:
--    - INSERT, UPDATE, DELETE data
--    - DROP or ALTER tables
--    - CREATE new tables or databases
--    - GRANT permissions to other users
--
-- 2. app_admin user CANNOT:
--    - DROP or ALTER table structure
--    - CREATE or DROP databases
--    - GRANT permissions to other users
--    - Access mysql system tables
--
-- 3. Both users use strong passwords with:
--    - Mixed case letters
--    - Numbers and special characters
--    - Minimum 20 characters length
--    - No dictionary words