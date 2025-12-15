<?php
/**
 * Patient deletion handler for Private Clinic Patient Record System
 * Securely deletes patient records with authentication and validation checks
 */

require_once 'config.php';
require_once 'db.php';

// Check authentication and session timeout
Database::requireAuth();

// Initialize message variables
$message = '';
$messageType = 'error';

try {
    // Validate that patient ID is provided and is numeric
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception("Invalid patient ID provided.");
    }
    
    $patientId = (int)$_GET['id'];
    
    // Verify that the patient exists before attempting deletion
    $checkSql = "SELECT id, name FROM patients WHERE id = ?";
    $patient = Database::fetchOne($checkSql, [$patientId]);
    
    if (!$patient) {
        throw new Exception("Patient record not found.");
    }
    
    // Begin transaction for safe deletion
    Database::beginTransaction();
    
    try {
        // Delete the patient record (this will permanently remove all data including encrypted diagnosis)
        $deleteSql = "DELETE FROM patients WHERE id = ?";
        $affectedRows = Database::executeUpdate($deleteSql, [$patientId]);
        
        if ($affectedRows === 0) {
            throw new Exception("Failed to delete patient record.");
        }
        
        // Commit the transaction
        Database::commit();
        
        // Log the patient deletion activity for audit trail
        Database::logActivity(
            $_SESSION['user_id'], 
            $_SESSION['username'], 
            'DELETE', 
            "Deleted patient record: " . $patient['name']
        );
        
        // Set success message
        $message = "Patient record for " . htmlspecialchars($patient['name']) . " has been successfully deleted.";
        $messageType = 'success';
        
    } catch (Exception $e) {
        // Rollback transaction on error
        Database::rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log the error securely without exposing sensitive information
    error_log("Patient deletion error: " . $e->getMessage() . " - User: " . ($_SESSION['username'] ?? 'unknown'));
    
    // Log the failed deletion attempt
    Database::logActivity(
        $_SESSION['user_id'] ?? 0, 
        $_SESSION['username'] ?? 'unknown', 
        'DELETE_FAILED', 
        "Failed to delete patient record - Error: " . $e->getMessage()
    );
    
    // Set user-friendly error message
    $message = $e->getMessage();
    $messageType = 'error';
}

// Store message in session for display on dashboard
$_SESSION['message'] = $message;
$_SESSION['message_type'] = $messageType;

// Redirect back to dashboard
header('Location: dashboard.php');
exit();