<?php
/**
 * Dashboard page for Private Clinic Patient Record System
 * Displays patient records and appointments with encrypted data decryption
 */

require_once 'config.php';
require_once 'db.php';

// Check authentication and session timeout
Database::requireAuth();

// Check if user is admin - redirect doctors to their dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'doctor') {
    header('Location: doctor_dashboard.php');
    exit();
}

// Handle success/error messages
$message = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message'], $_SESSION['message_type']);
}

// Handle timeout message
if (isset($_GET['msg']) && $_GET['msg'] === 'timeout') {
    $message = 'Your session has expired due to inactivity. Please log in again.';
    $messageType = 'warning';
}

// Handle search functionality
$searchTerm = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchTerm = Database::sanitizeInput(trim($_GET['search']));
}

// Fetch patient records with optional search filter (LIMITED TO 5)
try {
    if (!empty($searchTerm)) {
        $sql = "SELECT id, name, AES_DECRYPT(UNHEX(ic_number), ?) as ic_number, AES_DECRYPT(diagnosis, ?) as diagnosis, phone, created_at 
                FROM patients 
                WHERE name LIKE ? OR AES_DECRYPT(UNHEX(ic_number), ?) LIKE ?
                ORDER BY created_at DESC
                LIMIT 5";
        $searchParam = "%{$searchTerm}%";
        $patients = Database::fetchAll($sql, [SECURE_KEY, ENCRYPTION_KEY, $searchParam, SECURE_KEY, $searchParam]);
    } else {
        $sql = "SELECT id, name, AES_DECRYPT(UNHEX(ic_number), ?) as ic_number, AES_DECRYPT(diagnosis, ?) as diagnosis, phone, created_at 
                FROM patients 
                ORDER BY created_at DESC
                LIMIT 5";
        $patients = Database::fetchAll($sql, [SECURE_KEY, ENCRYPTION_KEY]);
    }
} catch (Exception $e) {
    $message = "Error loading patient records. Please try again.";
    $messageType = 'error';
    $patients = [];
}

// Get total patients count (for stat card)
try {
    $totalPatientsSql = "SELECT COUNT(*) as count FROM patients";
    $totalPatients = Database::fetchOne($totalPatientsSql);
    $totalPatientsCount = $totalPatients['count'] ?? 0;
} catch (Exception $e) {
    $totalPatientsCount = 0;
}

// Fetch upcoming appointments with patient names and decrypted reasons (LIMITED TO 10 - SOONEST FIRST)
try {
    $appointmentSql = "SELECT a.appointment_id, a.appointment_date, a.start_time, a.end_time, a.doctor_name, 
                              AES_DECRYPT(a.reason, ?) as reason, p.name as patient_name, AES_DECRYPT(UNHEX(p.ic_number), ?) as ic_number
                       FROM appointments a 
                       JOIN patients p ON a.patient_id = p.id 
                       WHERE a.start_time >= NOW()
                       ORDER BY a.start_time ASC
                       LIMIT 10";
    $appointments = Database::fetchAll($appointmentSql, [ENCRYPTION_KEY, SECURE_KEY]);
} catch (Exception $e) {
    error_log("Error loading appointments: " . $e->getMessage());
    $appointments = [];
}

// Get total appointments count (all upcoming appointments)
try {
    $totalAppointmentsSql = "SELECT COUNT(*) as count FROM appointments 
                            WHERE start_time >= NOW()";
    $totalAppointments = Database::fetchOne($totalAppointmentsSql);
    $totalAppointmentsCount = $totalAppointments['count'] ?? 0;
} catch (Exception $e) {
    $totalAppointmentsCount = 0;
}

// Get completed appointments count (total completed appointments for stat card)
try {
    $completedAppointmentsSql = "SELECT COUNT(*) as count FROM appointments 
                                WHERE end_time < NOW()";
    $completedAppointments = Database::fetchOne($completedAppointmentsSql);
    $completedAppointmentsCount = $completedAppointments['count'] ?? 0;
} catch (Exception $e) {
    $completedAppointmentsCount = 0;
}

// Fetch appointment history (today's completed appointments) - REFRESHES DAILY AT MIDNIGHT (LIMITED TO 10)
try {
    $historySql = "SELECT a.appointment_id, a.appointment_date, a.start_time, a.end_time, a.doctor_name, 
                          AES_DECRYPT(a.reason, ?) as reason, p.name as patient_name, AES_DECRYPT(UNHEX(p.ic_number), ?) as ic_number,
                          CASE 
                              WHEN a.end_time < NOW() THEN 'completed'
                              WHEN a.start_time <= NOW() AND a.end_time > NOW() THEN 'in_progress'
                              ELSE 'upcoming'
                          END as appointment_status
                   FROM appointments a 
                   JOIN patients p ON a.patient_id = p.id 
                   WHERE (
                       (a.end_time < NOW() AND DATE(a.end_time) = CURDATE()) OR
                       (a.start_time <= NOW() AND a.end_time > NOW() AND DATE(a.start_time) = CURDATE())
                   )
                   ORDER BY a.start_time DESC
                   LIMIT 10";
    $appointmentHistory = Database::fetchAll($historySql, [ENCRYPTION_KEY, SECURE_KEY]);
} catch (Exception $e) {
    error_log("Error loading appointment history: " . $e->getMessage());
    $appointmentHistory = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Private Clinic - Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            
            <!-- Center - Digital Clock -->
            <div class="mx-auto">
                <div class="digital-clock">
                    <div class="digital-display" id="digitalDisplay"><?php echo strtoupper(date('D, M j, Y')); ?> | <?php echo date('g:i:s A'); ?></div>
                </div>
            </div>
            
            <!-- Right side buttons -->
            <div class="navbar-nav ms-auto">
                <a href="add_patient.php" class="btn btn-success me-2">
                    <i class="bi bi-person-plus-fill me-1"></i>
                    Add Patient
                </a>
                <a href="audit_log.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-shield-alt me-1"></i>
                    Security Logs
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

    <div class="container-fluid mt-4">
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
                <?php if ($messageType !== 'success'): ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <a href="all_patients.php" class="text-decoration-none">
                    <div class="card text-white bg-primary shadow card-hover">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $totalPatientsCount; ?></h4>
                                    <p class="card-text">Total Patients</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="all_upcoming.php" class="text-decoration-none">
                    <div class="card text-white bg-info shadow card-hover">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $totalAppointmentsCount; ?></h4>
                                    <p class="card-text">Upcoming Appointments</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="all_history.php" class="text-decoration-none">
                    <div class="card text-white bg-success shadow card-hover">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $completedAppointmentsCount; ?></h4>
                                    <p class="card-text">Appointments Done</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- System Maintenance Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="m-0 font-weight-bold text-warning">
                            <i class="fas fa-tools me-2"></i>
                            System Maintenance & Disaster Recovery
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="text-dark mb-2">
                                    <i class="fas fa-database me-2 text-primary"></i>
                                    Database Backup
                                </h6>
                                <p class="text-muted mb-3">
                                    Create a complete backup of all patient records, appointments, and system data for disaster recovery purposes. 
                                    The backup includes encrypted patient data and can be used to restore the system in case of data loss.
                                </p>
                                <div class="d-flex align-items-center">
                                    <small class="text-muted me-3">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        Admin Only Access
                                    </small>
                                    <small class="text-muted me-3">
                                        <i class="fas fa-lock me-1"></i>
                                        Encrypted Data Included
                                    </small>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        Timestamped Files
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="d-grid">
                                    <button type="button" class="btn btn-warning btn-lg" id="downloadBackupBtn">
                                        <i class="fas fa-download me-2"></i>
                                        Download Database Backup (.SQL)
                                    </button>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    <i class="fas fa-info-circle me-1"></i>
                                    File will be automatically deleted from server after download
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patient Records Section -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-people-fill me-2"></i>
                                Patient Records
                                <?php if (!empty($searchTerm)): ?>
                                    <small class="text-muted">- Search results for "<?php echo htmlspecialchars($searchTerm); ?>"</small>
                                <?php endif; ?>
                            </h5>
                            <span class="badge bg-primary"><?php echo $totalPatientsCount; ?> total patients</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($patients)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-person-x text-muted" style="font-size: 4rem;"></i>
                                <h4 class="text-muted mt-3">
                                    <?php if (!empty($searchTerm)): ?>
                                        No patients found matching "<?php echo htmlspecialchars($searchTerm); ?>"
                                    <?php else: ?>
                                        No patient records found
                                    <?php endif; ?>
                                </h4>
                                <p class="text-muted">
                                    <?php if (!empty($searchTerm)): ?>
                                        Try a different search term or <a href="dashboard.php">view all patients</a>
                                    <?php else: ?>
                                        <a href="add_patient.php" class="btn btn-primary">Add your first patient</a>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped" id="patientsTable">
                                    <thead class="table-primary">
                                        <tr>
                                            <th><i class="bi bi-person me-1"></i>Name</th>
                                            <th><i class="bi bi-card-text me-1"></i>IC Number</th>
                                            <th><i class="bi bi-clipboard-pulse me-1"></i>Diagnosis</th>
                                            <th><i class="bi bi-telephone me-1"></i>Phone</th>
                                            <th><i class="bi bi-calendar me-1"></i>Date Added</th>
                                            <th><i class="bi bi-gear me-1"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="patientsTableBody">
                                        <?php foreach ($patients as $patient): ?>
                                            <tr>
                                                <td class="fw-semibold">
                                                    <a href="view_patient.php?id=<?php echo $patient['id']; ?>" 
                                                       class="text-decoration-none text-primary fw-bold"
                                                       title="View Patient Details">
                                                        <?php echo htmlspecialchars($patient['name']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <code>
                                                        <?php 
                                                        // Mask IC number - show first 10 characters, mask last 4
                                                        $icNumber = $patient['ic_number'];
                                                        if (strlen($icNumber) >= 4) {
                                                            echo htmlspecialchars(substr($icNumber, 0, -4) . 'XXXX');
                                                        } else {
                                                            echo htmlspecialchars($icNumber);
                                                        }
                                                        ?>
                                                    </code>
                                                </td>
                                                <td style="max-width: 200px;">
                                                    <?php 
                                                    $diagnosis = $patient['diagnosis'];
                                                    if ($diagnosis === null || $diagnosis === '') {
                                                        echo '<em class="text-muted">Unable to decrypt diagnosis</em>';
                                                    } else {
                                                        $cleanDiagnosis = strip_tags(html_entity_decode($diagnosis));
                                                        if (strlen($cleanDiagnosis) > 50) {
                                                            $truncated = substr($cleanDiagnosis, 0, 50) . '...';
                                                        } else {
                                                            $truncated = $cleanDiagnosis;
                                                        }
                                                        echo '<span class="diagnosis-text" title="' . htmlspecialchars($cleanDiagnosis) . '" style="cursor: default; text-decoration: none; color: inherit;">' . htmlspecialchars($truncated) . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($patient['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" 
                                                           class="btn btn-outline-primary btn-sm" 
                                                           title="Edit Patient">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-outline-danger btn-sm delete-patient" 
                                                                data-patient-id="<?php echo $patient['id']; ?>"
                                                                data-patient-name="<?php echo htmlspecialchars($patient['name']); ?>"
                                                                title="Delete Patient">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <a href="add_appointment.php?patient_id=<?php echo $patient['id']; ?>" 
                                                           class="btn btn-outline-success btn-sm" 
                                                           title="Book Appointment">
                                                            <i class="fas fa-calendar-plus"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Appointments Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="m-0 font-weight-bold text-info">
                                <i class="bi bi-calendar-event me-2"></i>
                                Upcoming Appointments
                            </h5>
                            <span class="badge bg-info"><?php echo count($appointments); ?> appointments</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                                <h5 class="text-muted mt-3">No upcoming appointments scheduled</h5>
                                <p class="text-muted">Appointments will appear here when scheduled.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="appointmentsTable">
                                    <thead class="table-info">
                                        <tr>
                                            <th><i class="bi bi-clock me-1"></i>Date & Time</th>
                                            <th><i class="fas fa-hourglass-half me-1"></i>Duration</th>
                                            <th><i class="bi bi-person me-1"></i>Patient</th>
                                            <th><i class="bi bi-card-text me-1"></i>IC Number</th>
                                            <th><i class="bi bi-person-badge me-1"></i>Doctor</th>
                                            <th><i class="bi bi-chat-text me-1"></i>Reason</th>
                                            <th><i class="bi bi-gear me-1"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="appointmentsTableBody">
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr>
                                                <td class="fw-semibold">
                                                    <i class="bi bi-calendar-date text-primary me-1"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($appointment['start_time'] ?? $appointment['appointment_date'])); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($appointment['start_time'] && $appointment['end_time']) {
                                                        $duration = (strtotime($appointment['end_time']) - strtotime($appointment['start_time'])) / 60;
                                                        echo "{$duration} min";
                                                    } else {
                                                        echo '60 min'; // Default
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="appointment_detail.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                                       class="text-primary text-decoration-none fw-bold">
                                                        <i class="bi bi-person-circle me-1"></i>
                                                        <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <code>
                                                        <?php 
                                                        // Mask IC number - show first characters, mask last 4
                                                        $icNumber = $appointment['ic_number'];
                                                        if (strlen($icNumber) >= 4) {
                                                            echo htmlspecialchars(substr($icNumber, 0, -4) . 'XXXX');
                                                        } else {
                                                            echo htmlspecialchars($icNumber);
                                                        }
                                                        ?>
                                                    </code>
                                                </td>
                                                <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                                <td style="max-width: 200px;">
                                                    <?php 
                                                    $reason = $appointment['reason'];
                                                    if ($reason === null || $reason === '') {
                                                        echo '<em class="text-muted">Unable to decrypt reason</em>';
                                                    } else {
                                                        $cleanReason = strip_tags(html_entity_decode($reason));
                                                        if (strlen($cleanReason) > 50) {
                                                            $truncated = substr($cleanReason, 0, 50) . '...';
                                                        } else {
                                                            $truncated = $cleanReason;
                                                        }
                                                        echo '<span class="reason-text" title="' . htmlspecialchars($cleanReason) . '" style="cursor: default; text-decoration: none; color: inherit;">' . htmlspecialchars($truncated) . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <button type="button" 
                                                            class="btn btn-outline-danger btn-sm cancel-appointment" 
                                                            data-appointment-id="<?php echo $appointment['appointment_id']; ?>"
                                                            data-patient-name="<?php echo htmlspecialchars($appointment['patient_name']); ?>"
                                                            title="Cancel Appointment">
                                                        <i class="bi bi-x-circle"></i> Cancel
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Appointment History Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="m-0 font-weight-bold text-secondary">
                                <i class="bi bi-clock-history me-2"></i>
                                Today's Appointment History
                            </h5>
                            <span class="badge bg-secondary"><?php echo count($appointmentHistory); ?> appointments</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointmentHistory)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-clock-history text-muted" style="font-size: 3rem;"></i>
                                <h5 class="text-muted mt-3">No recent appointment history</h5>
                                <p class="text-muted">Today's completed appointments will appear here. History resets daily at midnight.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="historyTable">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th><i class="bi bi-clock me-1"></i>Completed Time</th>
                                            <th><i class="bi bi-person me-1"></i>Patient</th>
                                            <th><i class="bi bi-person-badge me-1"></i>Doctor</th>
                                            <th><i class="bi bi-chat-text me-1"></i>Reason</th>
                                            <th><i class="bi bi-check-circle me-1"></i>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTableBody">
                                        <?php foreach ($appointmentHistory as $history): ?>
                                            <tr>
                                                <td class="fw-semibold">
                                                    <i class="bi bi-clock text-secondary me-1"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($history['end_time'])); ?>
                                                </td>
                                                <td>
                                                    <a href="appointment_detail.php?id=<?php echo $history['appointment_id']; ?>" 
                                                       class="text-primary text-decoration-none fw-bold">
                                                        <i class="bi bi-person-circle me-1"></i>
                                                        <?php echo htmlspecialchars($history['patient_name']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($history['doctor_name']); ?></td>
                                                <td style="max-width: 200px;">
                                                    <?php 
                                                    $reason = $history['reason'];
                                                    if ($reason === null || $reason === '') {
                                                        echo '<em class="text-muted">Unable to decrypt reason</em>';
                                                    } else {
                                                        $cleanReason = strip_tags(html_entity_decode($reason));
                                                        if (strlen($cleanReason) > 50) {
                                                            $truncated = substr($cleanReason, 0, 50) . '...';
                                                        } else {
                                                            $truncated = $cleanReason;
                                                        }
                                                        echo '<span class="reason-text" title="' . htmlspecialchars($cleanReason) . '" style="cursor: default; text-decoration: none; color: inherit;">' . htmlspecialchars($truncated) . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $now = time();
                                                    $startTime = strtotime($history['start_time']);
                                                    $endTime = strtotime($history['end_time']);
                                                    
                                                    if ($endTime < $now): ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-check me-1"></i>Completed
                                                        </span>
                                                    <?php elseif ($startTime <= $now && $endTime >= $now): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-play me-1"></i>In Progress
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-clock me-1"></i>Scheduled
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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
            <?php echo json_encode($_SESSION['user_id']); ?>,
            {}
        );
        
        // SweetAlert for delete confirmations
        document.addEventListener('DOMContentLoaded', function() {
            // Patient deletion
            document.querySelectorAll('.delete-patient').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const patientId = this.dataset.patientId;
                    const patientName = this.dataset.patientName;
                    
                    Swal.fire({
                        title: 'Delete Patient Record?',
                        text: `Are you sure you want to delete ${patientName}'s record? This action cannot be undone!`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = `delete_patient.php?id=${patientId}`;
                        }
                    });
                });
            });
            
            // Appointment cancellation
            document.querySelectorAll('.cancel-appointment').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const appointmentId = this.dataset.appointmentId;
                    const patientName = this.dataset.patientName;
                    
                    Swal.fire({
                        title: 'Cancel Appointment?',
                        text: `Are you sure you want to cancel ${patientName}'s appointment?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, cancel it!',
                        cancelButtonText: 'Keep appointment'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = `delete_appointment.php?id=${appointmentId}`;
                        }
                    });
                });
            });
            
            // Auto-dismiss Bootstrap success alerts after 5 seconds
            setTimeout(function() {
                const successAlerts = document.querySelectorAll('.alert-success');
                successAlerts.forEach(function(alert) {
                    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(function() {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 500);
                });
            }, 3000);
            
            // Check for upcoming appointments (within 60 minutes)
            <?php
            $upcomingReminders = [];
            $currentTime = time();
            $oneHourFromNow = $currentTime + (60 * 60); // 60 minutes from now
            
            foreach ($appointments as $appointment) {
                $appointmentTime = strtotime($appointment['start_time']);
                if ($appointmentTime > $currentTime && $appointmentTime <= $oneHourFromNow) {
                    $upcomingReminders[] = $appointment;
                }
            }
            
            if (!empty($upcomingReminders)): ?>
                // Show appointment reminder only once per session for admin
                <?php foreach ($upcomingReminders as $reminder): ?>
                const reminderKey = 'admin_<?php echo $_SESSION['user_id']; ?>_reminder_shown_<?php echo $reminder['appointment_id']; ?>';
                if (!sessionStorage.getItem(reminderKey)) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Upcoming Appointment Soon!',
                        text: 'Patient <?php echo addslashes($reminder['patient_name']); ?> is scheduled for <?php echo addslashes(date('g:i A', strtotime($reminder['start_time']))); ?>. Please prepare.',
                        timer: 10000,
                        timerProgressBar: true,
                        showConfirmButton: true,
                        confirmButtonText: 'Acknowledged'
                    }).then((result) => {
                        // Mark this reminder as shown for this specific admin
                        sessionStorage.setItem(reminderKey, 'true');
                    });
                }
                <?php endforeach; ?>
            <?php endif; ?>
            
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Update digital clock every second
            function updateDigitalClock() {
                const now = new Date();
                
                // Format date as "SUN, DEC 14, 2025"
                const dateOptions = { 
                    weekday: 'short', 
                    day: 'numeric', 
                    month: 'short', 
                    year: 'numeric'
                };
                const formattedDate = now.toLocaleDateString('en-US', dateOptions).toUpperCase();
                
                // Format time with seconds
                const timeOptions = { 
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                };
                const formattedTime = now.toLocaleTimeString('en-US', timeOptions);
                
                // Combine date and time with separator
                const displayText = `${formattedDate} | ${formattedTime}`;
                document.getElementById('digitalDisplay').textContent = displayText;
            }
            
            // Update immediately and then every second
            updateDigitalClock();
            setInterval(updateDigitalClock, 1000);
            
            // Database backup functionality
            document.getElementById('downloadBackupBtn').addEventListener('click', function() {
                // Show confirmation dialog
                Swal.fire({
                    title: 'Create Database Backup?',
                    text: 'This will create a complete backup of all patient records, appointments, and system data. The backup file will be downloaded to your computer.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-download me-2"></i>Create & Download Backup',
                    cancelButtonText: 'Cancel',
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        return new Promise((resolve) => {
                            // Show loading state
                            Swal.fire({
                                title: 'Creating Database Backup...',
                                text: 'Please wait while we generate your backup file. This may take a few moments.',
                                icon: 'info',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                showConfirmButton: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                    
                                    // Create hidden iframe to trigger download
                                    const iframe = document.createElement('iframe');
                                    iframe.style.display = 'none';
                                    iframe.src = 'backup_system.php';
                                    document.body.appendChild(iframe);
                                    
                                    // Monitor for download completion
                                    let downloadTimeout = setTimeout(() => {
                                        document.body.removeChild(iframe);
                                        resolve();
                                    }, 10000); // 10 second timeout
                                    
                                    // Listen for iframe load (indicates completion or error)
                                    iframe.onload = () => {
                                        clearTimeout(downloadTimeout);
                                        setTimeout(() => {
                                            document.body.removeChild(iframe);
                                            resolve();
                                        }, 2000); // Give time for download to start
                                    };
                                }
                            });
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show success message
                        Swal.fire({
                            title: 'Backup Created Successfully!',
                            text: 'Your database backup has been created and should be downloading now. The backup file has been automatically removed from the server for security.',
                            icon: 'success',
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'Great!'
                        });
                    }
                });
            });

        });

    </script>
</body>
</html>