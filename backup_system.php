<?php
/**
 * Database Backup System for Private Clinic Patient Record System
 * Provides disaster recovery capabilities with secure admin-only access
 */

require_once 'config.php';
require_once 'db.php';

// Ensure user is authenticated and is an admin
Database::requireAuth();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = 'Access denied. Database backup is restricted to administrators only.';
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
    exit();
}

// Get client IP for audit logging
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

try {
    // Generate timestamp-based filename
    $timestamp = date('Y_m_d_H_i_s');
    $filename = "clinic_backup_{$timestamp}.sql";
    $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
    
    // Database connection parameters - using root credentials for backup operations
    $host = DB_HOST;
    $database = DB_NAME;
    $username = DB_ROOT_USER;
    $password = DB_ROOT_PASS;
    
    // Construct mysqldump command for XAMPP environment
    $mysqldumpPath = 'mysqldump'; // Assumes mysqldump is in PATH (XAMPP default)
    
    // Alternative paths for different XAMPP installations
    $possiblePaths = [
        'mysqldump',
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        '/Applications/XAMPP/xamppfiles/bin/mysqldump',
        '/opt/lampp/bin/mysqldump'
    ];
    
    $mysqldumpCommand = null;
    foreach ($possiblePaths as $path) {
        if (is_executable($path) || shell_exec("which $path 2>/dev/null")) {
            $mysqldumpCommand = $path;
            break;
        }
    }
    
    if (!$mysqldumpCommand) {
        throw new Exception('mysqldump command not found. Please ensure MySQL tools are installed and accessible.');
    }
    
    // Build secure mysqldump command with comprehensive options
    $command = sprintf(
        '%s --host=%s --user=%s --password=%s --single-transaction --routines --triggers --complete-insert --extended-insert --comments --dump-date --lock-tables=false %s > %s 2>&1',
        escapeshellarg($mysqldumpCommand),
        escapeshellarg($host),
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg($database),
        escapeshellarg($tempPath)
    );
    
    // Execute backup command
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        $errorOutput = implode("\n", $output);
        throw new Exception("Database backup failed. Error code: {$returnCode}. Output: {$errorOutput}");
    }
    
    // Verify backup file was created and has content
    if (!file_exists($tempPath) || filesize($tempPath) < 1000) {
        throw new Exception('Backup file was not created properly or is too small.');
    }
    
    // Log successful backup creation
    Database::logActivity(
        $_SESSION['user_id'],
        $_SESSION['username'],
        'BACKUP_CREATE',
        "Database backup created successfully: {$filename} (" . number_format(filesize($tempPath)) . " bytes)"
    );
    
    // Set headers for file download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tempPath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output file contents and clean up
    if (readfile($tempPath)) {
        // Successfully sent file, now clean up
        unlink($tempPath);
        
        // Log successful backup download
        Database::logActivity(
            $_SESSION['user_id'],
            $_SESSION['username'],
            'BACKUP_DOWNLOAD',
            "Database backup downloaded successfully: {$filename}"
        );
        
        exit();
    } else {
        throw new Exception('Failed to send backup file to browser.');
    }
    
} catch (Exception $e) {
    // Clean up temp file if it exists
    if (isset($tempPath) && file_exists($tempPath)) {
        unlink($tempPath);
    }
    
    // Log backup failure
    Database::logActivity(
        $_SESSION['user_id'],
        $_SESSION['username'],
        'BACKUP_FAILED',
        "Database backup failed: " . $e->getMessage()
    );
    
    // Set error message and redirect
    $_SESSION['message'] = 'Database backup failed: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
    exit();
}
?>