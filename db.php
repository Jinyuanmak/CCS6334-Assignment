<?php
/**
 * Database connection utilities for Private Clinic Patient Record System
 * Provides PDO connection and prepared statement utilities
 */

require_once 'config.php';

class Database {
    // Connection pools for different privilege levels
    private static $publicConnection = null;    // Read-only connection
    private static $adminConnection = null;     // Read-write connection
    private static $rootConnection = null;      // Full privileges (backup only)
    
    /**
     * Get read-only database connection for public operations
     * Used for: login verification, public searches, read-only operations
     * Permissions: SELECT only - cannot INSERT, UPDATE, DELETE, or DROP
     * @return PDO Database connection with minimal privileges
     * @throws Exception If connection fails
     */
    public static function getPublicConnection() {
        if (self::$publicConnection === null) {
            try {
                // Set PHP timezone to Malaysia Time
                date_default_timezone_set('Asia/Kuala_Lumpur');
                
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                // Try to connect with public user first, fallback to root if it doesn't exist
                try {
                    self::$publicConnection = new PDO($dsn, DB_PUBLIC_USER, DB_PUBLIC_PASS, $options);
                } catch (PDOException $e) {
                    // Fallback to root user if public user doesn't exist
                    error_log("Public user connection failed, falling back to root: " . $e->getMessage());
                    self::$publicConnection = new PDO($dsn, DB_ROOT_USER, DB_ROOT_PASS, $options);
                }
                
                // Set MySQL timezone to Malaysia Time (GMT+8)
                self::$publicConnection->exec("SET time_zone = '+08:00'");
                
            } catch (PDOException $e) {
                error_log("Public database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed. Please try again later.");
            }
        }
        
        return self::$publicConnection;
    }
    
    /**
     * Get read-write database connection for admin operations
     * Used for: dashboard operations, CRUD operations, data modifications
     * Permissions: SELECT, INSERT, UPDATE, DELETE - cannot DROP or ALTER schema
     * @return PDO Database connection with CRUD privileges
     * @throws Exception If connection fails
     */
    public static function getAdminConnection() {
        if (self::$adminConnection === null) {
            try {
                // Set PHP timezone to Malaysia Time
                date_default_timezone_set('Asia/Kuala_Lumpur');
                
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                // Try to connect with admin user first, fallback to root if it doesn't exist
                try {
                    self::$adminConnection = new PDO($dsn, DB_ADMIN_USER, DB_ADMIN_PASS, $options);
                } catch (PDOException $e) {
                    // Fallback to root user if admin user doesn't exist
                    error_log("Admin user connection failed, falling back to root: " . $e->getMessage());
                    self::$adminConnection = new PDO($dsn, DB_ROOT_USER, DB_ROOT_PASS, $options);
                }
                
                // Set MySQL timezone to Malaysia Time (GMT+8)
                self::$adminConnection->exec("SET time_zone = '+08:00'");
                
            } catch (PDOException $e) {
                error_log("Admin database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed. Please try again later.");
            }
        }
        
        return self::$adminConnection;
    }
    
    /**
     * Get root database connection for system operations
     * Used for: backup operations, system maintenance, schema changes
     * Permissions: Full database privileges
     * @return PDO Database connection with full privileges
     * @throws Exception If connection fails
     */
    public static function getRootConnection() {
        if (self::$rootConnection === null) {
            try {
                // Set PHP timezone to Malaysia Time
                date_default_timezone_set('Asia/Kuala_Lumpur');
                
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                self::$rootConnection = new PDO($dsn, DB_ROOT_USER, DB_ROOT_PASS, $options);
                
                // Set MySQL timezone to Malaysia Time (GMT+8)
                self::$rootConnection->exec("SET time_zone = '+08:00'");
                
            } catch (PDOException $e) {
                error_log("Root database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed. Please try again later.");
            }
        }
        
        return self::$rootConnection;
    }
    
    /**
     * Legacy method for backward compatibility
     * Routes to admin connection by default
     * @return PDO Database connection
     * @throws Exception If connection fails
     */
    public static function getConnection() {
        return self::getAdminConnection();
    }
    
    /**
     * Execute a prepared statement with parameters using specified connection type
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for the query
     * @param string $connectionType Connection type: 'public', 'admin', or 'root'
     * @return PDOStatement Executed statement
     * @throws Exception If query execution fails
     */
    public static function executeQuery($sql, $params = [], $connectionType = 'admin') {
        try {
            // Select appropriate connection based on operation type
            switch ($connectionType) {
                case 'public':
                    $connection = self::getPublicConnection();
                    break;
                case 'root':
                    $connection = self::getRootConnection();
                    break;
                case 'admin':
                default:
                    $connection = self::getAdminConnection();
                    break;
            }
            
            $stmt = $connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query execution failed: " . $e->getMessage());
            throw new Exception("Database operation failed. Please try again later.");
        }
    }
    
    /**
     * Execute a SELECT query and return all results
     * @param string $sql SQL SELECT query
     * @param array $params Parameters for the query
     * @param string $connectionType Connection type: 'public' for read-only, 'admin' for read-write
     * @return array Query results
     */
    public static function fetchAll($sql, $params = [], $connectionType = 'admin') {
        $stmt = self::executeQuery($sql, $params, $connectionType);
        return $stmt->fetchAll();
    }
    
    /**
     * Execute a SELECT query and return single result
     * @param string $sql SQL SELECT query
     * @param array $params Parameters for the query
     * @param string $connectionType Connection type: 'public' for read-only, 'admin' for read-write
     * @return array|false Single row result or false if not found
     */
    public static function fetchOne($sql, $params = [], $connectionType = 'admin') {
        $stmt = self::executeQuery($sql, $params, $connectionType);
        return $stmt->fetch();
    }
    
    /**
     * Execute an INSERT, UPDATE, or DELETE query (always uses admin connection)
     * @param string $sql SQL query
     * @param array $params Parameters for the query
     * @return int Number of affected rows
     */
    public static function executeUpdate($sql, $params = []) {
        $stmt = self::executeQuery($sql, $params, 'admin');
        return $stmt->rowCount();
    }
    
    /**
     * Get the last inserted ID
     * @return string Last insert ID
     */
    public static function getLastInsertId() {
        return self::getConnection()->lastInsertId();
    }
    
    /**
     * Begin a database transaction
     */
    public static function beginTransaction() {
        self::getConnection()->beginTransaction();
    }
    
    /**
     * Commit a database transaction
     */
    public static function commit() {
        self::getConnection()->commit();
    }
    
    /**
     * Rollback a database transaction
     */
    public static function rollback() {
        self::getConnection()->rollBack();
    }
    
    /**
     * Sanitize input data to prevent XSS and other attacks
     * @param string $data Input data to sanitize
     * @return string Sanitized data
     */
    public static function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        
        // Remove or neutralize JavaScript protocols
        $data = preg_replace('/javascript:/i', 'blocked:', $data);
        $data = preg_replace('/vbscript:/i', 'blocked:', $data);
        $data = preg_replace('/data:/i', 'blocked:', $data);
        
        // Remove event handlers
        $data = preg_replace('/on\w+\s*=/i', 'blocked=', $data);
        
        // HTML encode special characters
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        
        return $data;
    }
    
    /**
     * Validate IC number format (Malaysian IC format)
     * @param string $ic IC number to validate
     * @return bool True if valid, false otherwise
     */
    public static function validateIC($ic) {
        // Check if it matches the formatted pattern XXXXXX-XX-XXXX
        if (preg_match('/^\d{6}-\d{2}-\d{4}$/', $ic)) {
            return true;
        }
        
        // Also accept unformatted 12 digits for backward compatibility
        $ic = preg_replace('/[\s-]/', '', $ic);
        return preg_match('/^\d{12}$/', $ic);
    }
    
    /**
     * Validate phone number format
     * @param string $phone Phone number to validate
     * @return bool True if valid, false otherwise
     */
    public static function validatePhone($phone) {
        // Remove any spaces, dashes, or plus signs
        $phone = preg_replace('/[\s\-\+]/', '', $phone);
        
        // Check if it's 10-15 digits (Malaysian phone numbers)
        return preg_match('/^\d{10,15}$/', $phone);
    }
    
    /**
     * Check session timeout and handle inactivity
     * Implements 15-minute inactivity timeout
     * @return bool True if session is valid, false if timed out
     */
    public static function checkSessionTimeout() {
        // Check if user is authenticated
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            return false;
        }
        
        // Check for last activity timestamp
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
            return true;
        }
        
        $currentTime = time();
        $lastActivity = $_SESSION['last_activity'];
        $timeoutDuration = 900; // 15 minutes in seconds
        
        // Check if session has timed out
        if (($currentTime - $lastActivity) > $timeoutDuration) {
            // Session timed out - destroy session and redirect
            session_destroy();
            header('Location: index.php?msg=timeout');
            exit();
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = $currentTime;
        return true;
    }
    
    /**
     * Require authentication and check session timeout
     * Call this at the beginning of protected pages
     */
    public static function requireAuth() {
        session_start();
        
        if (!self::checkSessionTimeout()) {
            // Log unauthorized access attempt
            try {
                $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                
                self::logActivity(
                    0, // No user ID for unauthorized access
                    'anonymous', 
                    'ACCESS_DENIED', 
                    "Unauthorized access attempt to: " . $requestUri . " - User Agent: " . substr($userAgent, 0, 100) . " - IP: " . $ip
                );
            } catch (Exception $e) {
                error_log("Failed to log unauthorized access: " . $e->getMessage());
            }
            
            header('Location: index.php');
            exit();
        }
    }
    
    /**
     * Log administrator activities for security audit trail
     * @param int $user_id User ID performing the action
     * @param string $username Username performing the action
     * @param string $action Action type (READ, CREATE, UPDATE, DELETE, SCHEDULE, etc.)
     * @param string $description Detailed description of the action
     * @return bool True if logged successfully, false otherwise
     */
    public static function logActivity($user_id, $username, $action, $description) {
        try {
            // Capture user's IP address
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            
            // Handle cases where IP might be forwarded through proxy
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
                $ip_address = $_SERVER['HTTP_X_REAL_IP'];
            }
            
            // Insert audit log entry - uses admin connection for INSERT
            $sql = "INSERT INTO audit_logs (user_id, username, action, description, ip_address, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            $result = self::executeQuery($sql, [
                $user_id,
                $username,
                $action,
                $description,
                $ip_address
            ], 'admin');
            
            return $result->rowCount() > 0;
            
        } catch (Exception $e) {
            // Log the error but don't break the main functionality
            error_log("Audit logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log messages for doctor notifications
     * @param string $messageType Type of message (PATIENT_UPDATE, PATIENT_CREATE, etc.)
     * @param string $title Message title
     * @param string $message Message content
     * @param int|null $patientId Patient ID if applicable
     * @param int|null $appointmentId Appointment ID if applicable
     * @param int|null $doctorId Doctor ID if applicable (for targeted messages)
     * @param array|null $changeDetails Array of changes (old_value => new_value)
     * @return bool True if logged successfully, false otherwise
     */
    public static function logMessage($messageType, $title, $message, $patientId = null, $appointmentId = null, $doctorId = null, $changeDetails = null) {
        try {
            // If no specific doctor is provided, send to all doctors who have appointments with this patient
            if ($doctorId === null && $patientId !== null) {
                // Get all doctors who have appointments with this patient
                $doctorSql = "SELECT DISTINCT doctor_id FROM appointments WHERE patient_id = ? AND doctor_id IS NOT NULL";
                $doctors = self::fetchAll($doctorSql, [$patientId]);
                
                $changeDetailsJson = $changeDetails ? json_encode($changeDetails) : null;
                
                if (empty($doctors)) {
                    // If no doctors found, create a general message (no doctor_id)
                    $sql = "INSERT INTO message_logs (message_type, title, message, patient_id, appointment_id, change_details, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())";
                    
                    $result = self::executeUpdate($sql, [
                        $messageType,
                        $title,
                        $message,
                        $patientId,
                        $appointmentId,
                        $changeDetailsJson
                    ]);
                    
                    return $result > 0;
                } else {
                    // Create a message for each doctor who has appointments with this patient
                    $success = true;
                    foreach ($doctors as $doctor) {
                        $sql = "INSERT INTO message_logs (message_type, title, message, patient_id, appointment_id, doctor_id, change_details, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                        
                        $result = self::executeUpdate($sql, [
                            $messageType,
                            $title,
                            $message,
                            $patientId,
                            $appointmentId,
                            $doctor['doctor_id'],
                            $changeDetailsJson
                        ]);
                        
                        if (!$result) {
                            $success = false;
                        }
                    }
                    return $success;
                }
            } else {
                // Create message for specific doctor or general message
                $changeDetailsJson = $changeDetails ? json_encode($changeDetails) : null;
                
                $sql = "INSERT INTO message_logs (message_type, title, message, patient_id, appointment_id, doctor_id, change_details, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $result = self::executeUpdate($sql, [
                    $messageType,
                    $title,
                    $message,
                    $patientId,
                    $appointmentId,
                    $doctorId,
                    $changeDetailsJson
                ]);
                
                return $result > 0;
            }
            
        } catch (Exception $e) {
            // Log the error but don't break the main functionality
            error_log("Message logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread message count for specific doctor
     * @param int|null $doctorId Doctor ID to get count for
     * @return int Number of unread messages
     */
    public static function getUnreadMessageCount($doctorId = null) {
        try {
            if ($doctorId === null) {
                // Get general messages (no specific doctor)
                $sql = "SELECT COUNT(*) as count FROM message_logs WHERE is_read = FALSE AND doctor_id IS NULL";
                $result = self::fetchOne($sql, [], 'admin');
            } else {
                // Get messages for specific doctor or general messages
                $sql = "SELECT COUNT(*) as count FROM message_logs WHERE is_read = FALSE AND (doctor_id = ? OR doctor_id IS NULL)";
                $result = self::fetchOne($sql, [$doctorId], 'admin');
            }
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Error getting unread message count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if IP address is currently locked out
     * @param string $ipAddress IP address to check
     * @return array|false Lockout info or false if not locked
     */
    public static function checkLockout($ipAddress) {
        try {
            $sql = "SELECT lockout_until, attempt_count FROM login_attempts 
                    WHERE ip_address = ? AND lockout_until > NOW() 
                    ORDER BY attempt_time DESC LIMIT 1";
            $result = self::fetchOne($sql, [$ipAddress], 'public');
            return $result ?: false;
        } catch (Exception $e) {
            error_log("Error checking lockout: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if username is currently locked out
     * @param string $username Username to check
     * @return array|false Lockout info or false if not locked
     */
    public static function checkUsernameLockout($username) {
        try {
            $sql = "SELECT lockout_until, attempt_count FROM login_attempts 
                    WHERE username = ? AND lockout_until > NOW() 
                    ORDER BY attempt_time DESC LIMIT 1";
            $result = self::fetchOne($sql, [$username], 'public');
            return $result ?: false;
        } catch (Exception $e) {
            error_log("Error checking username lockout: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record username-based login attempt and calculate lockout if needed
     * @param string $ipAddress IP address (for logging)
     * @param string $username Username attempted
     * @param bool $success Whether login was successful
     * @return array Lockout information
     */
    public static function recordUsernameLoginAttempt($ipAddress, $username, $success) {
        try {
            // Clean up old attempts (older than 1 hour) - uses admin connection for DELETE
            $cleanupSql = "DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            self::executeQuery($cleanupSql, [], 'admin');
            
            if ($success) {
                // Clear all failed attempts for this username on successful login
                $clearSql = "DELETE FROM login_attempts WHERE username = ? AND success = FALSE";
                self::executeQuery($clearSql, [$username], 'admin');
                
                // Record successful login
                $insertSql = "INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, TRUE)";
                self::executeQuery($insertSql, [$ipAddress, $username], 'admin');
                
                return ['locked' => false, 'lockout_until' => null, 'attempts' => 0];
            } else {
                // Count recent failed attempts for this username - uses public connection for SELECT
                $countSql = "SELECT COUNT(*) as count FROM login_attempts 
                            WHERE username = ? AND success = FALSE AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
                $countResult = self::fetchOne($countSql, [$username], 'public');
                $failedAttempts = ($countResult['count'] ?? 0) + 1; // +1 for current attempt
                
                // Calculate lockout duration based on attempt count
                $lockoutMinutes = 0;
                switch ($failedAttempts) {
                    case 5:
                        $lockoutMinutes = 1;
                        break;
                    case 6:
                        $lockoutMinutes = 5;
                        break;
                    case 7:
                        $lockoutMinutes = 10;
                        break;
                    case 8:
                        $lockoutMinutes = 15;
                        break;
                    case 9:
                        $lockoutMinutes = 30;
                        break;
                    case 10:
                    default:
                        if ($failedAttempts >= 10) {
                            $lockoutMinutes = 60; // 1 hour maximum
                        }
                        break;
                }
                
                $lockoutUntil = null;
                if ($lockoutMinutes > 0) {
                    $lockoutUntil = date('Y-m-d H:i:s', time() + ($lockoutMinutes * 60));
                }
                
                // Record failed attempt - uses admin connection for INSERT
                $insertSql = "INSERT INTO login_attempts (ip_address, username, success, lockout_until, attempt_count) 
                             VALUES (?, ?, FALSE, ?, ?)";
                self::executeQuery($insertSql, [$ipAddress, $username, $lockoutUntil, $failedAttempts], 'admin');
                
                return [
                    'locked' => $lockoutMinutes > 0,
                    'lockout_until' => $lockoutUntil,
                    'attempts' => $failedAttempts,
                    'lockout_minutes' => $lockoutMinutes
                ];
            }
        } catch (Exception $e) {
            error_log("Error recording username login attempt: " . $e->getMessage());
            return ['locked' => false, 'lockout_until' => null, 'attempts' => 0];
        }
    }
    
    /**
     * Record login attempt and calculate lockout if needed
     * @param string $ipAddress IP address
     * @param string $username Username attempted
     * @param bool $success Whether login was successful
     * @return array Lockout information
     */
    public static function recordLoginAttempt($ipAddress, $username, $success) {
        try {
            // Clean up old attempts (older than 1 hour)
            $cleanupSql = "DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            self::executeUpdate($cleanupSql);
            
            if ($success) {
                // Clear all failed attempts for this IP on successful login
                $clearSql = "DELETE FROM login_attempts WHERE ip_address = ? AND success = FALSE";
                self::executeUpdate($clearSql, [$ipAddress]);
                
                // Record successful login
                $insertSql = "INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, TRUE)";
                self::executeUpdate($insertSql, [$ipAddress, $username]);
                
                return ['locked' => false, 'lockout_until' => null, 'attempts' => 0];
            } else {
                // Count recent failed attempts from this IP
                $countSql = "SELECT COUNT(*) as count FROM login_attempts 
                            WHERE ip_address = ? AND success = FALSE AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
                $countResult = self::fetchOne($countSql, [$ipAddress], 'public');
                $failedAttempts = ($countResult['count'] ?? 0) + 1; // +1 for current attempt
                
                // Calculate lockout duration based on attempt count
                $lockoutMinutes = 0;
                switch ($failedAttempts) {
                    case 5:
                        $lockoutMinutes = 1;
                        break;
                    case 6:
                        $lockoutMinutes = 5;
                        break;
                    case 7:
                        $lockoutMinutes = 10;
                        break;
                    case 8:
                        $lockoutMinutes = 15;
                        break;
                    case 9:
                        $lockoutMinutes = 30;
                        break;
                    case 10:
                    default:
                        if ($failedAttempts >= 10) {
                            $lockoutMinutes = 60; // 1 hour maximum
                        }
                        break;
                }
                
                $lockoutUntil = null;
                if ($lockoutMinutes > 0) {
                    $lockoutUntil = date('Y-m-d H:i:s', time() + ($lockoutMinutes * 60));
                }
                
                // Record failed attempt
                $insertSql = "INSERT INTO login_attempts (ip_address, username, success, lockout_until, attempt_count) 
                             VALUES (?, ?, FALSE, ?, ?)";
                self::executeUpdate($insertSql, [$ipAddress, $username, $lockoutUntil, $failedAttempts]);
                
                return [
                    'locked' => $lockoutMinutes > 0,
                    'lockout_until' => $lockoutUntil,
                    'attempts' => $failedAttempts,
                    'lockout_minutes' => $lockoutMinutes
                ];
            }
        } catch (Exception $e) {
            error_log("Error recording login attempt: " . $e->getMessage());
            return ['locked' => false, 'lockout_until' => null, 'attempts' => 0];
        }
    }
}
?>