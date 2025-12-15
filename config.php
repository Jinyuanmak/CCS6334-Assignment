<?php
/**
 * Configuration file for Private Clinic Patient Record System
 * Contains database connection parameters and encryption key
 */

// Database configuration - Principle of Least Privilege Implementation
define('DB_HOST', 'localhost');
define('DB_NAME', 'clinic_db');

// Public/Read-Only Database User (for login, search, public operations)
// Permissions: SELECT only - cannot INSERT, UPDATE, DELETE, or DROP
define('DB_PUBLIC_USER', 'app_public');
define('DB_PUBLIC_PASS', 'Pub1ic_R3ad_0nly_2025!');

// Admin/Read-Write Database User (for dashboard, CRUD operations)
// Permissions: SELECT, INSERT, UPDATE, DELETE - cannot DROP or ALTER schema
define('DB_ADMIN_USER', 'app_admin');
define('DB_ADMIN_PASS', 'Adm1n_CRUD_S3cur3_2025!');

// Legacy root user (for backup operations and system maintenance)
define('DB_ROOT_USER', 'root');
define('DB_ROOT_PASS', '');

// Encryption keys for AES encryption of sensitive medical data
define('ENCRYPTION_KEY', 'MySecureClinicKey2025');  // For diagnosis encryption
define('SECURE_KEY', 'YourSecretKey123');           // For IC number encryption

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS in production

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone setting
date_default_timezone_set('Asia/Kuala_Lumpur');
?>