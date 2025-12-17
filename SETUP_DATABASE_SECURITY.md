# Database Security Setup Instructions

## Overview
This guide will help you set up enhanced database security for the Private Clinic Patient Record System using the **Principle of Least Privilege**. The system uses multiple database users with different permission levels to minimize security risks.

## Security Architecture

### Three-Tier Database User System
1. **`app_public`** - Read-only access for authentication and public operations
2. **`app_admin`** - CRUD operations for dashboard and patient management
3. **`root`** - Full privileges for system maintenance and backups only

## Prerequisites
- MySQL 8.0 or higher
- Administrative access to your MySQL server
- phpMyAdmin or MySQL command line access

## Step 1: Create Security Users

### Option A: Using phpMyAdmin (Recommended)
1. Open phpMyAdmin in your web browser
2. Click on the **"SQL"** tab at the top
3. Copy and paste the following SQL commands:

```sql
-- Create read-only user for public operations (login, search)
CREATE USER IF NOT EXISTS 'app_public'@'localhost' IDENTIFIED BY 'Pub1ic_R3ad_0nly_2025!';

-- Create admin user for CRUD operations
CREATE USER IF NOT EXISTS 'app_admin'@'localhost' IDENTIFIED BY 'Adm1n_CRUD_S3cur3_2025!';

-- Grant minimal permissions to public user (SELECT only)
GRANT SELECT ON clinic_db.users TO 'app_public'@'localhost';
GRANT SELECT ON clinic_db.patients TO 'app_public'@'localhost';
GRANT SELECT ON clinic_db.appointments TO 'app_public'@'localhost';
GRANT SELECT ON clinic_db.doctors TO 'app_public'@'localhost';
GRANT SELECT ON clinic_db.login_attempts TO 'app_public'@'localhost';

-- Grant CRUD permissions to admin user (no DROP or ALTER)
GRANT SELECT, INSERT, UPDATE, DELETE ON clinic_db.* TO 'app_admin'@'localhost';

-- Apply the changes
FLUSH PRIVILEGES;
```

4. Click **"Go"** to execute the commands

### Option B: Using MySQL Command Line
```bash
mysql -u root -p
```

Then paste the same SQL commands from Option A.

## Step 2: Update Configuration (Already Done)
The `config.php` file is already configured with the security users:

```php
// Public/Read-Only Database User
define('DB_PUBLIC_USER', 'app_public');
define('DB_PUBLIC_PASS', 'Pub1ic_R3ad_0nly_2025!');

// Admin/Read-Write Database User  
define('DB_ADMIN_USER', 'app_admin');
define('DB_ADMIN_PASS', 'Adm1n_CRUD_S3cur3_2025!');
```

## Step 3: Verify Setup

### Test Database Connections
Create a test file `test_connections.php`:

```php
<?php
require_once 'config.php';
require_once 'db.php';

echo "<h2>Database Connection Test</h2>";

// Test public connection
try {
    $publicConn = Database::getPublicConnection();
    echo "<p style='color: green;'>✅ Public connection: SUCCESS</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Public connection: " . $e->getMessage() . "</p>";
}

// Test admin connection
try {
    $adminConn = Database::getAdminConnection();
    echo "<p style='color: green;'>✅ Admin connection: SUCCESS</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Admin connection: " . $e->getMessage() . "</p>";
}

// Test root connection
try {
    $rootConn = Database::getRootConnection();
    echo "<p style='color: green;'>✅ Root connection: SUCCESS</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Root connection: " . $e->getMessage() . "</p>";
}
?>
```

### Verify User Permissions
Run this SQL query in phpMyAdmin to check user permissions:

```sql
-- Check public user permissions
SHOW GRANTS FOR 'app_public'@'localhost';

-- Check admin user permissions  
SHOW GRANTS FOR 'app_admin'@'localhost';
```

## Step 4: Test System Functionality

1. **Login Test**: Try logging into the system at `index.php`
2. **Dashboard Test**: Verify the dashboard loads patient records
3. **Patient Management**: Test adding/editing patients
4. **Appointment System**: Test scheduling appointments

## Security Benefits

### ✅ What This Setup Provides:
- **Principle of Least Privilege**: Each operation uses minimal required permissions
- **SQL Injection Mitigation**: Login operations cannot modify data even if compromised
- **Defense in Depth**: Multiple security layers
- **Audit Trail**: Clear separation of operations for logging
- **Damage Limitation**: Compromised connections have limited capabilities

### 🔒 Permission Breakdown:
- **Public User**: Can only SELECT (read) data - cannot INSERT, UPDATE, DELETE, or DROP
- **Admin User**: Can perform CRUD operations but cannot DROP tables or ALTER schema
- **Root User**: Full access reserved for backups and system maintenance only

## Troubleshooting

### Common Issues:

#### Issue 1: "Access denied for user 'app_public'"
**Solution**: Ensure the user was created with the correct password and permissions were granted.

#### Issue 2: "Unknown database 'clinic_db'"
**Solution**: Make sure the database exists and the user has access to it.

#### Issue 3: "Connection failed"
**Solution**: Check that MySQL is running and the credentials in `config.php` match the created users.

#### Issue 4: System still works but uses root user
**Solution**: This is normal! The system has automatic fallback to root user if security users don't exist.

### Fallback System
The system includes automatic fallback logic:
- If `app_public` user fails → Falls back to `root` user
- If `app_admin` user fails → Falls back to `root` user
- This ensures the system always works, even without security users

## Production Recommendations

### 🔐 For Production Deployment:

1. **Change Default Passwords**: Generate strong, unique passwords
```sql
ALTER USER 'app_public'@'localhost' IDENTIFIED BY 'your_strong_password_here';
ALTER USER 'app_admin'@'localhost' IDENTIFIED BY 'your_strong_password_here';
```

2. **Restrict Host Access**: Limit connections to specific hosts
```sql
-- Instead of 'localhost', use specific IP addresses
CREATE USER 'app_public'@'192.168.1.100' IDENTIFIED BY 'password';
```

3. **Enable SSL**: Force encrypted connections
```sql
ALTER USER 'app_public'@'localhost' REQUIRE SSL;
ALTER USER 'app_admin'@'localhost' REQUIRE SSL;
```

4. **Regular Audits**: Monitor user activity and permissions
```sql
-- Check current connections
SHOW PROCESSLIST;

-- Review user permissions
SELECT user, host FROM mysql.user;
```

## Maintenance

### Regular Tasks:
- Review user permissions quarterly
- Monitor failed login attempts in `audit_logs` table
- Update passwords annually
- Check for unused database users

### Backup Considerations:
- Only `root` user should perform database backups
- Security users should not have BACKUP privileges
- Test backup restoration regularly

## Support

If you encounter issues:
1. Check MySQL error logs
2. Verify user permissions with `SHOW GRANTS`
3. Test connections with the test script above
4. Review the `audit_logs` table for security events

---

**🏥 This security setup ensures your Private Clinic Patient Record System follows industry best practices for database security while maintaining full functionality.**