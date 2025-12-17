<?php
/**
 * Add Appointment page for Private Clinic Patient Record System
 * Handles appointment creation with encrypted reason field
 */

require_once 'config.php';
require_once 'db.php';

// Check authentication and session timeout
Database::requireAuth();

$message = '';
$messageType = '';
$patients = [];
$doctors = [];
// Determine locked patient ID - check POST first (form error), then GET (from dashboard)
$selectedPatientId = $_POST['patient_id'] ?? $_GET['patient_id'] ?? '';
$lockedPatient = null;

// If patient_id is provided, get the patient details and lock the selection
if (!empty($selectedPatientId)) {
    try {
        $lockedPatient = Database::fetchOne("SELECT id, name, ic_number FROM patients WHERE id = ?", [$selectedPatientId]);
        if (!$lockedPatient) {
            $selectedPatientId = ''; // Reset if patient not found
        }
    } catch (Exception $e) {
        error_log("Error loading locked patient: " . $e->getMessage());
        $selectedPatientId = '';
    }
}

// Fetch all patients for the dropdown (only if not locked)
if (!$lockedPatient) {
    try {
        $sql = "SELECT id, name, ic_number FROM patients ORDER BY name ASC";
        $patients = Database::fetchAll($sql);
    } catch (Exception $e) {
        $message = "Error loading patient list. Please try again.";
        $messageType = 'error';
        $patients = [];
    }
} else {
    $patients = []; // No need to load all patients if one is locked
}

// Fetch all doctors for the datalist
try {
    $sql = "SELECT name, specialization FROM doctors ORDER BY name ASC";
    $doctors = Database::fetchAll($sql);
} catch (Exception $e) {
    error_log("Error loading doctors: " . $e->getMessage());
    // Continue without doctors list if there's an error
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = Database::sanitizeInput($_POST['patient_id'] ?? '');
    $appointmentDate = Database::sanitizeInput($_POST['appointment_date'] ?? '');
    $appointmentTime = Database::sanitizeInput($_POST['appointment_time'] ?? '');
    $doctorName = Database::sanitizeInput($_POST['doctor_name'] ?? '');
    $reason = Database::sanitizeInput($_POST['reason'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($patientId)) {
        $errors[] = "Please select a patient.";
    }
    
    if (empty($appointmentDate)) {
        $errors[] = "Please select an appointment date.";
    }
    
    if (empty($appointmentTime)) {
        $errors[] = "Please select an appointment time.";
    }
    
    if (empty($doctorName)) {
        $errors[] = "Please enter the doctor's name.";
    }
    
    if (empty($reason)) {
        $errors[] = "Please enter the reason for the appointment.";
    }
    
    // Validate date is not in the past
    if (!empty($appointmentDate) && !empty($appointmentTime)) {
        $appointmentDateTime = "{$appointmentDate} {$appointmentTime}";
        if (strtotime($appointmentDateTime) < time()) {
            $errors[] = "Appointment date and time cannot be in the past.";
        }
    }
    
    // Duration field (add this to form later)
    $duration = $_POST['duration'] ?? 60; // Default 60 minutes
    
    // Conflict Detection Logic
    if (!empty($appointmentDate) && !empty($appointmentTime) && !empty($doctorName) && !empty($patientId)) {
        $startTime = "{$appointmentDate} {$appointmentTime}";
        $endTime = date('Y-m-d H:i:s', strtotime($startTime) + ($duration * 60));
        
        try {
            // Check Doctor Availability
            $doctorConflictSql = "SELECT COUNT(*) as count FROM appointments 
                                 WHERE doctor_name = ? 
                                 AND ((start_time <= ? AND end_time > ?) 
                                      OR (start_time < ? AND end_time >= ?)
                                      OR (start_time >= ? AND start_time < ?))";
            
            $doctorConflict = Database::fetchOne($doctorConflictSql, [
                $doctorName, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime
            ]);
            
            if ($doctorConflict['count'] > 0) {
                $errors[] = "Time Clash! This Doctor is already busy at that time.";
            }
            
            // Check Patient Availability
            $patientConflictSql = "SELECT COUNT(*) as count FROM appointments 
                                  WHERE patient_id = ? 
                                  AND ((start_time <= ? AND end_time > ?) 
                                       OR (start_time < ? AND end_time >= ?)
                                       OR (start_time >= ? AND start_time < ?))";
            
            $patientConflict = Database::fetchOne($patientConflictSql, [
                $patientId, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime
            ]);
            
            if ($patientConflict['count'] > 0) {
                $errors[] = "Time Clash! This Patient already has an appointment at that time.";
            }
            
        } catch (Exception $e) {
            error_log("Error checking appointment conflicts: " . $e->getMessage());
            $errors[] = "Error validating appointment time. Please try again.";
        }
    }
    
    // Validate patient exists
    if (!empty($patientId)) {
        try {
            $checkPatient = Database::fetchOne("SELECT id FROM patients WHERE id = ?", [$patientId]);
            if (!$checkPatient) {
                $errors[] = "Selected patient does not exist.";
            }
        } catch (Exception $e) {
            $errors[] = "Error validating patient selection.";
        }
    }
    
    if (empty($errors)) {
        try {
            // Combine date and time
            $appointmentDateTime = "{$appointmentDate} {$appointmentTime}";
            $startTime = $appointmentDateTime;
            $endTime = date('Y-m-d H:i:s', strtotime($startTime) + ($duration * 60));
            
            // Get doctor ID - CRITICAL FIX: Proper doctor name matching
            $doctorId = null;
            try {
                // Extract just the doctor name from the form input (remove specialization if present)
                $cleanDoctorName = $doctorName;
                if (strpos($doctorName, '(') !== false) {
                    $cleanDoctorName = trim(substr($doctorName, 0, strpos($doctorName, '(')));
                }
                
                // Try exact match first
                $doctorQuery = Database::fetchOne("SELECT id FROM doctors WHERE name = ? LIMIT 1", [$cleanDoctorName]);
                
                // If no exact match, try partial match
                if (!$doctorQuery) {
                    $doctorQuery = Database::fetchOne("SELECT id FROM doctors WHERE name LIKE ? LIMIT 1", ['%' . $cleanDoctorName . '%']);
                }
                
                if ($doctorQuery) {
                    $doctorId = $doctorQuery['id'];
                } else {
                    error_log("Doctor not found in database: " . $doctorName . " (cleaned: " . $cleanDoctorName . ")");
                }
            } catch (Exception $e) {
                error_log("Error finding doctor ID: " . $e->getMessage());
                // Continue without doctor ID if not found
            }
            
            // Insert appointment with encrypted reason and time slots
            $sql = "INSERT INTO appointments (patient_id, appointment_date, start_time, end_time, doctor_name, doctor_id, reason) 
                    VALUES (?, ?, ?, ?, ?, ?, AES_ENCRYPT(?, ?))";
            
            Database::executeUpdate($sql, [
                $patientId,
                $appointmentDateTime,
                $startTime,
                $endTime,
                $doctorName,
                $doctorId,
                $reason,
                ENCRYPTION_KEY
            ]);
            
            // Log the appointment scheduling activity for audit trail
            Database::logActivity(
                $_SESSION['user_id'], 
                $_SESSION['username'], 
                'SCHEDULE', 
                "Scheduled appointment with Doctor: " . $doctorName . " on " . $appointmentDate . " at " . $appointmentTime
            );
            
            $_SESSION['message'] = 'Appointment scheduled successfully!';
            $_SESSION['message_type'] = 'success';
            $_SESSION['auto_dismiss'] = true;
            
            header('Location: dashboard.php');
            exit();
            
        } catch (Exception $e) {
            error_log("Error creating appointment: " . $e->getMessage());
            error_log("SQL Error Details: " . print_r($e, true));
            
            // Show more detailed error in development mode
            if (defined('SHOW_DETAILED_ERRORS') && SHOW_DETAILED_ERRORS) {
                $message = "Error scheduling appointment: " . $e->getMessage();
            } else {
                $message = "Error scheduling appointment. Please try again. If the problem persists, contact support.";
            }
            $messageType = 'error';
            
            // Log the failed appointment creation attempt
            Database::logActivity(
                $_SESSION['user_id'], 
                $_SESSION['username'], 
                'SCHEDULE_FAILED', 
                "Failed to schedule appointment with Doctor: " . $doctorName . " on " . $appointmentDate . " - Error: " . $e->getMessage()
            );
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
        
        // Log appointment scheduling failures
        Database::logActivity(
            $_SESSION['user_id'], 
            $_SESSION['username'], 
            'SCHEDULE_FAILED', 
            "Failed to schedule appointment with Doctor: " . $doctorName . " on " . $appointmentDate . " - Validation errors: " . implode(', ', $errors)
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Private Clinic - Schedule Appointment</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/6jh4lhkc4nzm1va5vf86pgbobw5tdtusn1ymf1u07ei8n5a7/tinymce/8/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-light">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold" href="dashboard.php">
                <i class="bi bi-hospital me-2"></i>
                Private Clinic
            </a>
            <div class="navbar-nav ms-auto">
                <a href="dashboard.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-calendar-plus me-2"></i>
                            Schedule New Appointment
                        </h4>
                    </div>
                    <div class="card-body">

                        <?php if ($message): 
                            $alertClass = 'alert ';
                            if ($messageType === 'error') {
                                $alertClass .= 'alert-danger';
                            } else {
                                $alertClass .= 'alert-info';
                            }
                            $alertClass .= ' alert-dismissible fade show';
                        ?>
                            <div class="<?php echo $alertClass; ?>" role="alert">
                                <i class="bi bi-<?php echo $messageType === 'error' ? 'exclamation-triangle-fill' : 'info-circle-fill'; ?> me-2"></i>
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="add_appointment.php" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="patient_id" class="form-label">
                                    <i class="bi bi-person me-1"></i>
                                    Patient *
                                </label>
                                <?php if ($lockedPatient): ?>
                                    <!-- Locked patient - show as disabled field with hidden input -->
                                    <input type="text" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($lockedPatient['name']); ?>" 
                                           disabled>
                                    <input type="hidden" name="patient_id" value="<?php echo $lockedPatient['id']; ?>">
                                    <div class="form-text">
                                        <i class="bi bi-lock text-warning me-1"></i>
                                        Patient is locked for this appointment booking
                                    </div>
                                <?php else: ?>
                                    <!-- Normal patient selection -->
                                    <select class="form-select" id="patient_id" name="patient_id" required>
                                        <option value="">Select a patient</option>
                                        <?php foreach ($patients as $patient): ?>
                                            <option value="<?php echo $patient['id']; ?>" 
                                                    <?php echo ($selectedPatientId == $patient['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($patient['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="appointment_date" class="form-label">
                                            <i class="bi bi-calendar me-1"></i>
                                            Appointment Date *
                                        </label>
                                        <input type="date" 
                                               class="form-control"
                                               id="appointment_date" 
                                               name="appointment_date" 
                                               required
                                               min="<?php echo date('Y-m-d'); ?>"
                                               value="<?php echo isset($_POST['appointment_date']) ? htmlspecialchars($_POST['appointment_date']) : ''; ?>">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="appointment_time" class="form-label">
                                            <i class="bi bi-clock me-1"></i>
                                            Appointment Time *
                                        </label>
                                        <input type="time" 
                                               class="form-control"
                                               id="appointment_time" 
                                               name="appointment_time" 
                                               required
                                               value="<?php echo isset($_POST['appointment_time']) ? htmlspecialchars($_POST['appointment_time']) : ''; ?>">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="duration" class="form-label">
                                            <i class="bi bi-hourglass-split me-1"></i>
                                            Duration (minutes) *
                                        </label>
                                        <select class="form-select" id="duration" name="duration" required>
                                            <option value="30" <?php echo (isset($_POST['duration']) && $_POST['duration'] == '30') ? 'selected' : ''; ?>>30 minutes</option>
                                            <option value="60" <?php echo (!isset($_POST['duration']) || $_POST['duration'] == '60') ? 'selected' : ''; ?>>60 minutes</option>
                                            <option value="90" <?php echo (isset($_POST['duration']) && $_POST['duration'] == '90') ? 'selected' : ''; ?>>90 minutes</option>
                                            <option value="120" <?php echo (isset($_POST['duration']) && $_POST['duration'] == '120') ? 'selected' : ''; ?>>120 minutes</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="doctor_name" class="form-label">
                                    <i class="bi bi-person-badge me-1"></i>
                                    Doctor Name *
                                </label>
                                <input type="text" 
                                       class="form-control"
                                       id="doctor_name" 
                                       name="doctor_name" 
                                       list="doctorOptions"
                                       required 
                                       maxlength="100"
                                       placeholder="Type to search doctors..."
                                       value="<?php echo isset($_POST['doctor_name']) ? htmlspecialchars($_POST['doctor_name']) : ''; ?>">
                                <datalist id="doctorOptions">
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?php echo htmlspecialchars($doctor['name'] . ' (' . $doctor['specialization'] . ')'); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="mb-4">
                                <label for="reason" class="form-label">
                                    <i class="bi bi-chat-text me-1"></i>
                                    Reason for Appointment *
                                </label>
                                <textarea class="form-control"
                                          id="reason" 
                                          name="reason" 
                                          required 
                                          rows="4"
                                          placeholder="Enter the reason for this appointment"><?php 
                                          // Handle TinyMCE content properly for form validation errors
                                          if (isset($_POST['reason'])) {
                                              $reason = $_POST['reason'];
                                              // For TinyMCE, preserve HTML content without double-encoding
                                              if ($reason !== '' && strip_tags($reason) === $reason) {
                                                  // Plain text - safe to escape
                                                  echo htmlspecialchars($reason);
                                              } else {
                                                  // Contains HTML - output directly for TinyMCE
                                                  echo $reason;
                                              }
                                          }
                                          ?></textarea>
                                <div class="form-text">
                                    <i class="bi bi-shield-lock text-success me-1"></i>
                                    This information will be encrypted for security
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-secondary me-md-2">
                                    <i class="bi bi-x-circle me-1"></i>
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-info">
                                    <i class="bi bi-calendar-plus me-1"></i>
                                    Schedule Appointment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> All fields marked with * are required. The appointment reason will be encrypted for privacy and security.
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Consolidated JavaScript -->
    <script src="javascript.js"></script>
    
    <script>
        // Initialize the application
        ClinicJS.initializeApp(
            '',
            <?php echo json_encode($_SESSION['user_id']); ?>,
            {}
        );
        
        // Initialize page functionality
        document.addEventListener('DOMContentLoaded', function() {
            ClinicJS.initializeReasonTinyMCE();
            ClinicJS.setupAppointmentFormValidation();

        });
    </script>
</body>
</html>