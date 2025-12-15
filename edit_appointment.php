<?php
/**
 * Edit Appointment page for Private Clinic Patient Record System
 * Allows editing of appointment details with proper validation and security
 */

require_once 'config.php';
require_once 'db.php';

// Check authentication and session timeout
Database::requireAuth();

// Check if user is admin (only admins can edit appointments)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = 'Access denied. Only administrators can edit appointments.';
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
    exit();
}

// Get appointment ID from URL
$appointmentId = $_GET['id'] ?? '';

if (empty($appointmentId) || !is_numeric($appointmentId)) {
    $_SESSION['message'] = 'Invalid appointment ID.';
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
    exit();
}

// Initialize variables
$errors = [];
$message = '';
$messageType = '';
$formData = [];

// Handle success/error messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message'], $_SESSION['message_type']);
}

// Fetch all doctors for the datalist
$doctors = [];
try {
    $sql = "SELECT name, specialization FROM doctors ORDER BY name ASC";
    $doctors = Database::fetchAll($sql);
} catch (Exception $e) {
    error_log("Error loading doctors: " . $e->getMessage());
    // Continue without doctors list if there's an error
}

// Fetch appointment details
try {
    $sql = "SELECT a.appointment_id, a.patient_id, a.appointment_date, a.start_time, a.end_time, 
                   a.doctor_name, AES_DECRYPT(a.reason, ?) as reason,
                   p.name as patient_name, AES_DECRYPT(UNHEX(p.ic_number), ?) as ic_number,
                   AES_DECRYPT(p.diagnosis, ?) as diagnosis, p.phone as patient_phone
            FROM appointments a 
            JOIN patients p ON a.patient_id = p.id 
            WHERE a.appointment_id = ?";
    
    $appointment = Database::fetchOne($sql, [ENCRYPTION_KEY, SECURE_KEY, ENCRYPTION_KEY, $appointmentId]);
    
    if (!$appointment) {
        $_SESSION['message'] = 'Appointment not found.';
        $_SESSION['message_type'] = 'error';
        header('Location: dashboard.php');
        exit();
    }
    
    // Populate form data
    $formData = [
        'appointment_id' => $appointment['appointment_id'],
        'patient_id' => $appointment['patient_id'],
        'patient_name' => $appointment['patient_name'],
        'ic_number' => $appointment['ic_number'],
        'patient_phone' => $appointment['patient_phone'],
        'appointment_date' => date('Y-m-d', strtotime($appointment['start_time'])),
        'start_time' => date('H:i', strtotime($appointment['start_time'])),
        'end_time' => date('H:i', strtotime($appointment['end_time'])),
        'doctor_name' => $appointment['doctor_name'],
        'reason' => $appointment['reason'] ?? ''
    ];
    
} catch (Exception $e) {
    error_log("Error fetching appointment details: " . $e->getMessage());
    $_SESSION['message'] = 'Error loading appointment details. Please try again.';
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get and sanitize form data
        $appointmentDate = Database::sanitizeInput($_POST['appointment_date'] ?? '');
        $startTime = Database::sanitizeInput($_POST['start_time'] ?? '');
        $endTime = Database::sanitizeInput($_POST['end_time'] ?? '');
        $doctorName = Database::sanitizeInput($_POST['doctor_name'] ?? '');
        $reason = Database::sanitizeInput($_POST['reason'] ?? '');
        
        // Validation
        if (empty($appointmentDate)) {
            $errors['appointment_date'] = 'Appointment date is required.';
        }
        
        if (empty($startTime)) {
            $errors['start_time'] = 'Start time is required.';
        }
        
        if (empty($endTime)) {
            $errors['end_time'] = 'End time is required.';
        }
        
        if (empty($doctorName)) {
            $errors['doctor_name'] = 'Doctor name is required.';
        } else {
            // Validate that the doctor exists in the database
            try {
                $cleanDoctorName = trim($doctorName);
                if (strpos($cleanDoctorName, ' - ') !== false) {
                    $cleanDoctorName = trim(explode(' - ', $cleanDoctorName)[0]);
                }
                
                $doctorExists = Database::fetchOne("SELECT id FROM doctors WHERE name = ? LIMIT 1", [$cleanDoctorName]);
                if (!$doctorExists) {
                    // Try partial match
                    $doctorExists = Database::fetchOne("SELECT id FROM doctors WHERE name LIKE ? LIMIT 1", ['%' . $cleanDoctorName . '%']);
                    if (!$doctorExists) {
                        $errors['doctor_name'] = 'Selected doctor not found in database. Please choose from the available doctors.';
                    }
                }
            } catch (Exception $e) {
                error_log("Error validating doctor: " . $e->getMessage());
                $errors['doctor_name'] = 'Error validating doctor. Please try again.';
            }
        }
        
        if (empty($reason)) {
            $errors['reason'] = 'Appointment reason is required.';
        }
        
        // Validate date and time logic
        if (!empty($appointmentDate) && !empty($startTime) && !empty($endTime)) {
            $startDateTime = $appointmentDate . ' ' . $startTime;
            $endDateTime = $appointmentDate . ' ' . $endTime;
            
            if (strtotime($endDateTime) <= strtotime($startDateTime)) {
                $errors['end_time'] = 'End time must be after start time.';
            } else {
                // Validate appointment duration (must be 30, 60, 90, or 120 minutes)
                $durationMinutes = (strtotime($endDateTime) - strtotime($startDateTime)) / 60;
                $allowedDurations = [30, 60, 90, 120];
                
                if (!in_array($durationMinutes, $allowedDurations)) {
                    $errors['end_time'] = 'Appointment duration must be exactly 30, 60, 90, or 120 minutes. Current duration: ' . $durationMinutes . ' minutes.';
                }
            }
            
            // Check if appointment is in the past (only for future appointments)
            if (strtotime($startDateTime) < time()) {
                $errors['appointment_date'] = 'Cannot schedule appointments in the past.';
            }
        }
        
        // If no errors, update the appointment
        if (empty($errors)) {
            Database::beginTransaction();
            
            try {
                // Update appointment
                $updateSql = "UPDATE appointments SET 
                             appointment_date = ?, 
                             start_time = ?, 
                             end_time = ?, 
                             doctor_name = ?, 
                             reason = AES_ENCRYPT(?, ?)
                             WHERE appointment_id = ?";
                
                $startDateTime = $appointmentDate . ' ' . $startTime;
                $endDateTime = $appointmentDate . ' ' . $endTime;
                
                Database::executeUpdate($updateSql, [
                    $appointmentDate,
                    $startDateTime,
                    $endDateTime,
                    $doctorName,
                    $reason,
                    ENCRYPTION_KEY,
                    $appointmentId
                ]);
                
                // Log the appointment update activity
                Database::logActivity(
                    $_SESSION['user_id'], 
                    $_SESSION['username'], 
                    'UPDATE', 
                    "Updated appointment for patient: " . $appointment['patient_name'] . " with Dr. " . $doctorName
                );
                
                // Track changes for detailed message
                $changes = [];
                $changeDescriptions = [];
                
                // Check what changed
                if ($appointmentDate !== date('Y-m-d', strtotime($appointment['start_time']))) {
                    $changes['date'] = [
                        'old' => date('M j, Y', strtotime($appointment['start_time'])),
                        'new' => date('M j, Y', strtotime($appointmentDate))
                    ];
                    $changeDescriptions[] = "Date changed from " . $changes['date']['old'] . " to " . $changes['date']['new'];
                }
                
                if (date('H:i', strtotime($startDateTime)) !== date('H:i', strtotime($appointment['start_time']))) {
                    $changes['start_time'] = [
                        'old' => date('g:i A', strtotime($appointment['start_time'])),
                        'new' => date('g:i A', strtotime($startDateTime))
                    ];
                    $changeDescriptions[] = "Start time changed from " . $changes['start_time']['old'] . " to " . $changes['start_time']['new'];
                }
                
                if (date('H:i', strtotime($endDateTime)) !== date('H:i', strtotime($appointment['end_time']))) {
                    $changes['end_time'] = [
                        'old' => date('g:i A', strtotime($appointment['end_time'])),
                        'new' => date('g:i A', strtotime($endDateTime))
                    ];
                    $changeDescriptions[] = "End time changed from " . $changes['end_time']['old'] . " to " . $changes['end_time']['new'];
                }
                
                if ($doctorName !== $appointment['doctor_name']) {
                    $changes['doctor'] = [
                        'old' => $appointment['doctor_name'],
                        'new' => $doctorName
                    ];
                    $changeDescriptions[] = "Doctor changed from " . $changes['doctor']['old'] . " to " . $changes['doctor']['new'];
                }
                
                // Compare reason (decode both for comparison)
                $oldReason = html_entity_decode($appointment['reason'] ?? '', ENT_QUOTES, 'UTF-8');
                $oldReasonClean = strip_tags($oldReason);
                if (trim($reason) !== trim($oldReasonClean)) {
                    $changes['reason'] = [
                        'old' => $oldReasonClean,
                        'new' => $reason
                    ];
                    $changeDescriptions[] = "Reason changed from \"" . (strlen($oldReasonClean) > 50 ? substr($oldReasonClean, 0, 50) . "..." : $oldReasonClean) . "\" to \"" . (strlen($reason) > 50 ? substr($reason, 0, 50) . "..." : $reason) . "\"";
                }
                
                // Get doctor ID for the appointment
                $doctorIdForMessage = null;
                try {
                    // Clean doctor name (remove specialization if included)
                    $cleanDoctorName = trim($doctorName);
                    if (strpos($cleanDoctorName, ' - ') !== false) {
                        $cleanDoctorName = trim(explode(' - ', $cleanDoctorName)[0]);
                    }
                    
                    // Try exact match first
                    $doctorQuery = Database::fetchOne("SELECT id FROM doctors WHERE name = ? LIMIT 1", [$cleanDoctorName]);
                    
                    // If no exact match, try partial match
                    if (!$doctorQuery) {
                        $doctorQuery = Database::fetchOne("SELECT id FROM doctors WHERE name LIKE ? LIMIT 1", ['%' . $cleanDoctorName . '%']);
                    }
                    
                    $doctorIdForMessage = $doctorQuery['id'] ?? null;
                    
                    // Update the doctor name to use the clean version
                    $doctorName = $cleanDoctorName;
                } catch (Exception $e) {
                    error_log("Error finding doctor ID for message: " . $e->getMessage());
                }
                
                // Create detailed message
                $detailedMessage = "Your appointment with patient " . $appointment['patient_name'] . " has been updated by administrator.";
                
                // Log message for the specific doctor with change details
                Database::logMessage(
                    'APPOINTMENT_UPDATE',
                    'Appointment Updated',
                    $detailedMessage,
                    $appointment['patient_id'],
                    $appointmentId,
                    $doctorIdForMessage,
                    $changes
                );
                
                Database::commit();
                
                $_SESSION['message'] = 'Appointment updated successfully!';
                $_SESSION['message_type'] = 'success';
                header('Location: appointment_detail.php?id=' . $appointmentId);
                exit();
                
            } catch (Exception $e) {
                Database::rollback();
                error_log("Error updating appointment: " . $e->getMessage());
                
                // Log the failed appointment update
                Database::logActivity(
                    $_SESSION['user_id'], 
                    $_SESSION['username'], 
                    'UPDATE_FAILED', 
                    "Failed to update appointment - Appointment ID: " . $appointmentId . " - Error: " . $e->getMessage()
                );
                
                $errors['general'] = 'Failed to update appointment. Please try again.';
            }
        }
        
        // Update form data with submitted values
        $formData = array_merge($formData, [
            'appointment_date' => $appointmentDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'doctor_name' => $doctorName,
            'reason' => $reason
        ]);
        
    } catch (Exception $e) {
        error_log("Error processing appointment update: " . $e->getMessage());
        $errors['general'] = 'An error occurred while processing your request. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Appointment - Private Clinic</title>
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
            
            <!-- Right side buttons -->
            <div class="navbar-nav ms-auto">
                <a href="appointment_detail.php?id=<?php echo $appointmentId; ?>" class="btn btn-outline-light me-2">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Appointment
                </a>
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars(ucwords($_SESSION['username'], ".")); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($message): 
            $alertClass = 'alert ';
            if ($messageType === 'error') {
                $alertClass .= 'alert-danger';
            } elseif ($messageType === 'warning') {
                $alertClass .= 'alert-warning';
            } elseif ($messageType === 'success') {
                $alertClass .= 'alert-success';
            } else {
                $alertClass .= 'alert-info';
            }
            $alertClass .= ' alert-dismissible fade show';
        ?>
            <div class="<?php echo $alertClass; ?>" role="alert">
                <?php 
                    $iconClass = 'bi ';
                    if ($messageType === 'error' || $messageType === 'warning') {
                        $iconClass .= 'bi-exclamation-triangle-fill';
                    } elseif ($messageType === 'success') {
                        $iconClass .= 'bi-check-circle-fill';
                    } else {
                        $iconClass .= 'bi-info-circle-fill';
                    }
                    $iconClass .= ' me-2';
                ?>
                <i class="<?php echo $iconClass; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Edit Appointment Form -->
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0">
                            <i class="bi bi-pencil-square me-2"></i>
                            Edit Appointment Details
                        </h4>
                        <small>Modify appointment information for <?php echo htmlspecialchars($formData['patient_name']); ?></small>
                    </div>
                    <div class="card-body">
                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo htmlspecialchars($errors['general']); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="row">
                                <!-- Patient Information (Read-only) -->
                                <div class="col-md-6">
                                    <h5 class="text-primary mb-3">
                                        <i class="bi bi-person-fill me-2"></i>Patient Information
                                    </h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-person me-1"></i>Patient Name
                                        </label>
                                        <div class="form-control-plaintext bg-light p-3 rounded border">
                                            <strong><?php echo htmlspecialchars($formData['patient_name']); ?></strong>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-card-text me-1"></i>IC Number
                                        </label>
                                        <div class="form-control-plaintext bg-light p-3 rounded border">
                                            <code><?php echo htmlspecialchars($formData['ic_number']); ?></code>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-telephone me-1"></i>Phone Number
                                        </label>
                                        <div class="form-control-plaintext bg-light p-3 rounded border">
                                            <?php echo htmlspecialchars($formData['patient_phone']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Editable Appointment Information -->
                                <div class="col-md-6">
                                    <h5 class="text-warning mb-3">
                                        <i class="bi bi-calendar-event me-2"></i>Edit Appointment Details
                                    </h5>
                                    
                                    <div class="mb-3">
                                        <label for="appointment_date" class="form-label fw-bold">
                                            <i class="bi bi-calendar-date me-1"></i>Appointment Date *
                                        </label>
                                        <input type="date" 
                                               class="form-control <?php echo isset($errors['appointment_date']) ? 'is-invalid' : ''; ?>" 
                                               id="appointment_date" 
                                               name="appointment_date" 
                                               value="<?php echo htmlspecialchars($formData['appointment_date']); ?>" 
                                               required>
                                        <?php if (isset($errors['appointment_date'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['appointment_date']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="duration" class="form-label fw-bold">
                                            <i class="bi bi-hourglass-split me-1"></i>Appointment Duration *
                                        </label>
                                        <select class="form-control" id="duration" name="duration">
                                            <option value="30">30 minutes</option>
                                            <option value="60" selected>60 minutes</option>
                                            <option value="90">90 minutes</option>
                                            <option value="120">120 minutes</option>
                                        </select>
                                        <div class="form-text">
                                            <i class="bi bi-info-circle me-1"></i>
                                            End time will be automatically calculated based on start time and duration
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="start_time" class="form-label fw-bold">
                                                    <i class="bi bi-clock me-1"></i>Start Time *
                                                </label>
                                                <input type="time" 
                                                       class="form-control <?php echo isset($errors['start_time']) ? 'is-invalid' : ''; ?>" 
                                                       id="start_time" 
                                                       name="start_time" 
                                                       value="<?php echo htmlspecialchars($formData['start_time']); ?>" 
                                                       required>
                                                <?php if (isset($errors['start_time'])): ?>
                                                    <div class="invalid-feedback">
                                                        <?php echo htmlspecialchars($errors['start_time']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="end_time" class="form-label fw-bold">
                                                    <i class="bi bi-clock-fill me-1"></i>End Time *
                                                </label>
                                                <input type="time" 
                                                       class="form-control <?php echo isset($errors['end_time']) ? 'is-invalid' : ''; ?>" 
                                                       id="end_time" 
                                                       name="end_time" 
                                                       value="<?php echo htmlspecialchars($formData['end_time']); ?>" 
                                                       readonly
                                                       required>
                                                <?php if (isset($errors['end_time'])): ?>
                                                    <div class="invalid-feedback">
                                                        <?php echo htmlspecialchars($errors['end_time']); ?>
                                                    </div>
                                                <?php endif; ?>

                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="doctor_name" class="form-label fw-bold">
                                            <i class="bi bi-person-badge me-1"></i>Doctor Name *
                                        </label>
                                        <input type="text" 
                                               class="form-control <?php echo isset($errors['doctor_name']) ? 'is-invalid' : ''; ?>" 
                                               id="doctor_name" 
                                               name="doctor_name" 
                                               value="<?php echo htmlspecialchars($formData['doctor_name']); ?>" 
                                               list="doctorOptions"
                                               placeholder="Type to search doctors..." 
                                               maxlength="100" 
                                               required>
                                        <datalist id="doctorOptions">
                                            <?php foreach ($doctors as $doctor): ?>
                                                <option value="<?php echo htmlspecialchars($doctor['name']); ?>">
                                                    <?php echo htmlspecialchars($doctor['name'] . ' - ' . $doctor['specialization']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </datalist>
                                        <?php if (isset($errors['doctor_name'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['doctor_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="form-text">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Start typing to see available doctors from the database
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Appointment Reason -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h5 class="text-info mb-3">
                                        <i class="bi bi-chat-text me-2"></i>Appointment Reason
                                    </h5>
                                    
                                    <div class="mb-3">
                                        <label for="reason" class="form-label fw-bold">
                                            <i class="bi bi-file-text me-1"></i>Reason for Appointment *
                                        </label>
                                        <textarea class="form-control <?php echo isset($errors['reason']) ? 'is-invalid' : ''; ?>" 
                                                  id="reason" 
                                                  name="reason" 
                                                  rows="4" 
                                                  placeholder="Enter the reason for this appointment" 
                                                  maxlength="500" 
                                                  required><?php 
                                                  // Handle TinyMCE content properly for form validation errors
                                                  if (!empty($formData['reason'])) {
                                                      $reason = $formData['reason'];
                                                      
                                                      // If this is from form submission (POST), preserve the content as-is
                                                      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                                                          // Form validation failed - preserve user input without double-encoding
                                                          if ($reason !== '' && strip_tags($reason) === $reason) {
                                                              // Plain text - safe to escape
                                                              echo htmlspecialchars($reason);
                                                          } else {
                                                              // Contains HTML - output directly for TinyMCE
                                                              echo $reason;
                                                          }
                                                      } else {
                                                          // Initial page load - decode database content for TinyMCE
                                                          $decodedReason = html_entity_decode($reason, ENT_QUOTES, 'UTF-8');
                                                          $allowedTags = '<p><br><ul><ol><li><strong><b><em><i><u><span><div><h1><h2><h3><h4><h5><h6><blockquote><pre><code>';
                                                          $cleanReason = strip_tags($decodedReason, $allowedTags);
                                                          echo $cleanReason;
                                                      }
                                                  }
                                                  ?></textarea>
                                        <?php if (isset($errors['reason'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['reason']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="form-text">
                                            <i class="bi bi-shield-lock text-success me-1"></i>
                                            This information will be encrypted for security and privacy
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-between mt-4">
                                <a href="appointment_detail.php?id=<?php echo $appointmentId; ?>" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-1"></i>
                                    Cancel & Go Back
                                </a>
                                
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-check-circle me-1"></i>
                                    Update Appointment
                                </button>
                            </div>
                        </form>
                    </div>
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
            ClinicJS.initializeEditAppointmentTinyMCE();
            ClinicJS.setupAppointmentDurationCalculation();
            ClinicJS.setupEditAppointmentFormValidation();
            
            // Enhanced doctor selection functionality
            const doctorInput = document.getElementById('doctor_name');
            if (doctorInput) {
                // Add visual feedback when doctor is selected from datalist
                doctorInput.addEventListener('input', function() {
                    const datalist = document.getElementById('doctorOptions');
                    const options = datalist.querySelectorAll('option');
                    let isValidDoctor = false;
                    
                    for (let option of options) {
                        if (option.value === this.value) {
                            isValidDoctor = true;
                            break;
                        }
                    }
                    
                    // Add visual feedback
                    if (isValidDoctor && this.value.length > 0) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else if (this.value.length > 0) {
                        this.classList.remove('is-valid');
                        // Don't add is-invalid while typing, only on form submit
                    } else {
                        this.classList.remove('is-valid', 'is-invalid');
                    }
                });
                
                // Clean up doctor name on blur (remove specialization if present)
                doctorInput.addEventListener('blur', function() {
                    if (this.value.includes(' - ')) {
                        this.value = this.value.split(' - ')[0].trim();
                    }
                });
            }
        });
    </script>
</body>
</html>