<?php
/**
 * Delete Appointment handler for Private Clinic Patient Record System
 * Handles secure appointment cancellation
 */

require_once 'config.php';
require_once 'db.php';

// Check authentication and session timeout
Database::requireAuth();

// Get appointment ID from URL
$appointmentId = $_GET['id'] ?? '';

if (empty($appointmentId) || !is_numeric($appointmentId)) {
    $_SESSION['message'] = 'Invalid appointment ID.';
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
    exit();
}

try {
    // Check if appointment exists and get details for logging
    $checkSql = "SELECT a.appointment_id, a.appointment_date, a.start_time, a.doctor_name, p.name as patient_name 
                 FROM appointments a 
                 JOIN patients p ON a.patient_id = p.id 
                 WHERE a.appointment_id = ?";
    $appointment = Database::fetchOne($checkSql, [$appointmentId]);
    
    if (!$appointment) {
        $_SESSION['message'] = 'Appointment not found.';
        $_SESSION['message_type'] = 'error';
        header('Location: dashboard.php');
        exit();
    }
    
    // Delete the appointment
    $deleteSql = "DELETE FROM appointments WHERE appointment_id = ?";
    $rowsAffected = Database::executeUpdate($deleteSql, [$appointmentId]);
    
    if ($rowsAffected > 0) {
        // Log the appointment cancellation activity for audit trail
        Database::logActivity(
            $_SESSION['user_id'], 
            $_SESSION['username'], 
            'DELETE', 
            "Cancelled appointment for patient " . $appointment['patient_name'] . " with Dr. " . $appointment['doctor_name'] . " scheduled for " . date('M j, Y g:i A', strtotime($appointment['start_time']))
        );
        
        $_SESSION['message'] = 'Appointment cancelled successfully.';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Failed to cancel appointment. Please try again.';
        $_SESSION['message_type'] = 'error';
    }
    
} catch (Exception $e) {
    error_log("Error deleting appointment: " . $e->getMessage());
    $_SESSION['message'] = 'Error cancelling appointment. Please try again.';
    $_SESSION['message_type'] = 'error';
    
    // Log the failed appointment cancellation attempt
    Database::logActivity(
        $_SESSION['user_id'], 
        $_SESSION['username'], 
        'DELETE_FAILED', 
        "Failed to cancel appointment - Error: " . $e->getMessage()
    );
}

header('Location: dashboard.php');
exit();
?>