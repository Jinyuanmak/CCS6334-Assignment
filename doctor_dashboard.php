<?php
/**
 * Doctor Dashboard page for Private Clinic Patient Record System
 * Displays doctor-specific appointments and patient records
 */

require_once 'config.php';
require_once 'db.php';

// Check authentication and session timeout
Database::requireAuth();

// Check if user is a doctor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['message'] = 'Access denied. This page is for doctors only.';
    $_SESSION['message_type'] = 'error';
    header('Location: index.php');
    exit();
}

// Get doctor information using the logged-in user_id
$doctorInfo = null;
$doctorId = null;

try {
    // First get the doctor ID using the user_id from session
    $sql = "SELECT d.* FROM doctors d 
            JOIN users u ON d.user_id = u.id 
            WHERE u.id = ?";
    $doctorInfo = Database::fetchOne($sql, [$_SESSION['user_id']]);
    
    if ($doctorInfo) {
        $doctorId = $doctorInfo['id'];
    }
} catch (Exception $e) {
    error_log("Error loading doctor info: " . $e->getMessage());
}

if (!$doctorInfo || !$doctorId) {
    $_SESSION['message'] = 'Doctor profile not found. Please contact administrator.';
    $_SESSION['message_type'] = 'error';
    header('Location: index.php');
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

// Get unread message count for notification badge (doctor-specific)
$unreadMessageCount = Database::getUnreadMessageCount($doctorId);

// DEBUG: Add temporary debug info (remove after testing)
if (isset($_GET['debug'])) {
    echo "<!-- DEBUG INFO: Doctor ID: $doctorId, Unread Count: $unreadMessageCount -->";
}

// Handle timeout message
if (isset($_GET['msg']) && $_GET['msg'] === 'timeout') {
    $message = 'Your session has expired due to inactivity. Please log in again.';
    $messageType = 'warning';
}

// Add cache-busting headers to ensure fresh data
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Pagination setup for appointments
$appointmentPage = isset($_GET['appointment_page']) ? max(1, intval($_GET['appointment_page'])) : 1;
$appointmentLimit = 5; // 5 appointments per page
$appointmentOffset = ($appointmentPage - 1) * $appointmentLimit;

// Fetch doctor's appointments (in-progress, today, and future appointments) with pagination
try {
    // Get total count for appointments pagination
    $appointmentCountSql = "SELECT COUNT(*) as total FROM appointments a 
                           JOIN patients p ON a.patient_id = p.id 
                           WHERE a.doctor_id = ? AND a.end_time > NOW()";
    $appointmentTotalResult = Database::fetchOne($appointmentCountSql, [$doctorId]);
    $totalAppointments = $appointmentTotalResult['total'] ?? 0;
    $appointmentTotalPages = ceil($totalAppointments / $appointmentLimit);
    
    // Fetch appointments for current page
    $appointmentSql = "SELECT a.appointment_id, a.appointment_date, a.start_time, a.end_time,
                              AES_DECRYPT(a.reason, ?) as reason, p.name as patient_name, AES_DECRYPT(UNHEX(p.ic_number), ?) as ic_number,
                              CASE 
                                  WHEN a.end_time < NOW() THEN 'completed'
                                  WHEN a.start_time <= NOW() AND a.end_time > NOW() THEN 'in_progress'
                                  ELSE 'upcoming'
                              END as appointment_status
                       FROM appointments a 
                       JOIN patients p ON a.patient_id = p.id 
                       WHERE a.doctor_id = ? AND a.end_time > NOW()
                       ORDER BY a.start_time ASC
                       LIMIT ? OFFSET ?";
    $appointments = Database::fetchAll($appointmentSql, [ENCRYPTION_KEY, SECURE_KEY, $doctorId, $appointmentLimit, $appointmentOffset]);
} catch (Exception $e) {
    error_log("Error loading doctor appointments: " . $e->getMessage());
    $appointments = [];
    $totalAppointments = 0;
    $appointmentTotalPages = 0;
}

// Pagination setup for patients
$patientPage = isset($_GET['patient_page']) ? max(1, intval($_GET['patient_page'])) : 1;
$patientLimit = 10; // 10 patients per page
$patientOffset = ($patientPage - 1) * $patientLimit;

// Fetch doctor's patients (patients who have appointments with this doctor) with pagination
try {
    // Get total count for patients pagination
    $patientCountSql = "SELECT COUNT(DISTINCT p.id) as total
                       FROM patients p 
                       JOIN appointments a ON p.id = a.patient_id 
                       WHERE a.doctor_id = ?";
    $patientTotalResult = Database::fetchOne($patientCountSql, [$doctorId]);
    $totalPatients = $patientTotalResult['total'] ?? 0;
    $patientTotalPages = ceil($totalPatients / $patientLimit);
    
    // Fetch patients for current page
    $patientSql = "SELECT DISTINCT p.id, p.name, AES_DECRYPT(UNHEX(p.ic_number), ?) as ic_number, p.phone, p.created_at,
                          AES_DECRYPT(p.diagnosis, ?) as diagnosis,
                          (SELECT appointment_id FROM appointments WHERE patient_id = p.id AND doctor_id = ? ORDER BY start_time DESC LIMIT 1) as latest_appointment_id
                   FROM patients p 
                   JOIN appointments a ON p.id = a.patient_id 
                   WHERE a.doctor_id = ?
                   ORDER BY p.created_at DESC
                   LIMIT ? OFFSET ?";
    $patients = Database::fetchAll($patientSql, [SECURE_KEY, ENCRYPTION_KEY, $doctorId, $doctorId, $patientLimit, $patientOffset]);
} catch (Exception $e) {
    error_log("Error loading doctor patients: " . $e->getMessage());
    $patients = [];
    $totalPatients = 0;
    $patientTotalPages = 0;
}

// Get today's appointments count
try {
    $todayAppointmentsSql = "SELECT COUNT(*) as count FROM appointments 
                            WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE()";
    $todayAppointments = Database::fetchOne($todayAppointmentsSql, [$doctorId]);
    $todayCount = $todayAppointments['count'] ?? 0;
} catch (Exception $e) {
    $todayCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Private Clinic - Doctor Dashboard</title>
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
            <a class="navbar-brand text-white fw-bold" href="doctor_dashboard.php">
                <i class="bi bi-hospital me-2"></i>
                Private Clinic - Doctor Portal
            </a>
            
            <!-- Center - Digital Clock -->
            <div class="mx-auto">
                <div class="digital-clock">
                    <div class="digital-display" id="digitalDisplay"><?php echo strtoupper(date('D, M j, Y')); ?> | <?php echo date('g:i:s A'); ?></div>
                </div>
            </div>
            
            <!-- Right side buttons -->
            <div class="navbar-nav ms-auto">
                <a href="message_log.php" class="btn btn-outline-light me-2 position-relative">
                    <i class="fas fa-envelope me-1"></i>
                    Messages
                    <?php if ($unreadMessageCount > 0): ?>
                        <span class="position-absolute badge bg-danger" style="top: -8px; right: -8px; z-index: 1070;">
                            <?php echo $unreadMessageCount; ?>
                            <span class="visually-hidden">unread messages</span>
                        </span>
                    <?php endif; ?>
                </a>
                <span class="navbar-text text-white me-3">
                    <i class="fas fa-user-md me-1"></i>
                    <?php echo htmlspecialchars($doctorInfo['name']); ?>
                </span>
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

        <!-- Doctor Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-info shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo count($appointments); ?></h4>
                                <p class="card-text">My Appointments</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-check fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $todayCount; ?></h4>
                                <p class="card-text">Today's Appointments</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-day fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-primary shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $totalPatients; ?></h4>
                                <p class="card-text">My Patients</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Schedule Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <h5 class="m-0 font-weight-bold text-info me-3">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    My Schedule
                                </h5>
                                <!-- Sort & Filter Toolbar -->
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-sort me-1"></i>Sort
                                    </button>
                                    <ul class="dropdown-menu" id="appointmentSortOptions">
                                        <li><a class="dropdown-item" href="#" data-sort="date-newest">Date: Newest First</a></li>
                                        <li><a class="dropdown-item" href="#" data-sort="date-oldest">Date: Oldest First</a></li>
                                        <li><a class="dropdown-item" href="#" data-sort="name-az">Name: A-Z</a></li>
                                        <li><a class="dropdown-item" href="#" data-sort="name-za">Name: Z-A</a></li>
                                    </ul>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="appointmentFilterToggle">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-info"><?php echo number_format($totalAppointments); ?> total appointments</span>
                                <span class="text-muted small">Page <?php echo $appointmentPage; ?> of <?php echo max(1, $appointmentTotalPages); ?></span>
                            </div>
                        </div>
                        <!-- Tag-Based Filter (Initially Hidden) -->
                        <div class="mt-2" id="appointmentFilterRow" style="display: none;">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-control d-flex align-items-center flex-wrap" id="appointmentTagContainer" onclick="document.getElementById('appointmentTagInput').focus()" style="min-height: 38px; cursor: text;">
                                        <input type="text" id="appointmentTagInput" style="border:none; outline:none; background:transparent; min-width:100px; flex-grow:1;" placeholder="Type & Enter...">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="clearAppointmentTags">
                                        <i class="fas fa-times me-1"></i>Clear All Tags
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times text-muted" style="font-size: 3rem;"></i>
                                <h5 class="text-muted mt-3">No appointments found for this doctor</h5>
                                <p class="text-muted">Your appointments will appear here when scheduled by the admin.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-info">
                                        <tr>
                                            <th><i class="fas fa-clock me-1"></i>Date & Time</th>
                                            <th><i class="fas fa-hourglass-half me-1"></i>Duration</th>
                                            <th><i class="fas fa-user me-1"></i>Patient</th>
                                            <th><i class="fas fa-id-card me-1"></i>IC Number</th>
                                            <th><i class="fas fa-notes-medical me-1"></i>Reason</th>
                                            <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                            <th><i class="bi bi-gear me-1"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="appointmentsTable">
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr data-name="<?php echo strtoupper(htmlspecialchars($appointment['patient_name'])); ?>" 
                                                data-date="<?php echo $appointment['start_time']; ?>">
                                                <td class="fw-semibold">
                                                    <i class="fas fa-calendar-day text-info me-1"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($appointment['start_time'])); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $duration = (strtotime($appointment['end_time']) - strtotime($appointment['start_time'])) / 60;
                                                    echo "{$duration} min";
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
                                                    <?php 
                                                    $now = time();
                                                    $startTime = strtotime($appointment['start_time']);
                                                    $endTime = strtotime($appointment['end_time']);
                                                    
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
                                                            <i class="fas fa-clock me-1"></i>Upcoming
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Get patient ID from appointment
                                                    $patientId = null;
                                                    try {
                                                        $patientQuery = Database::fetchOne("SELECT id FROM patients WHERE AES_DECRYPT(UNHEX(ic_number), ?) = ?", [SECURE_KEY, $appointment['ic_number']]);
                                                        $patientId = $patientQuery['id'] ?? null;
                                                    } catch (Exception $e) {
                                                        error_log("Error finding patient ID: " . $e->getMessage());
                                                    }
                                                    ?>
                                                    <a href="appointment_detail.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                                       class="btn btn-info btn-sm" 
                                                       title="View Appointment Details">
                                                        <i class="fas fa-calendar-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Appointment Pagination -->
                            <?php if ($appointmentTotalPages > 1): ?>
                                <nav aria-label="My schedule pagination" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <!-- Previous Page -->
                                        <?php if ($appointmentPage > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?appointment_page=<?php echo $appointmentPage - 1; ?><?php echo isset($_GET['patient_page']) ? '&patient_page=' . $_GET['patient_page'] : ''; ?>">
                                                    <i class="bi bi-chevron-left"></i> Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <!-- Page Numbers -->
                                        <?php
                                        $startPage = max(1, $appointmentPage - 2);
                                        $endPage = min($appointmentTotalPages, $appointmentPage + 2);
                                        
                                        for ($i = $startPage; $i <= $endPage; $i++):
                                        ?>
                                            <li class="page-item <?php echo ($i === $appointmentPage) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?appointment_page=<?php echo $i; ?><?php echo isset($_GET['patient_page']) ? '&patient_page=' . $_GET['patient_page'] : ''; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <!-- Next Page -->
                                        <?php if ($appointmentPage < $appointmentTotalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?appointment_page=<?php echo $appointmentPage + 1; ?><?php echo isset($_GET['patient_page']) ? '&patient_page=' . $_GET['patient_page'] : ''; ?>">
                                                    Next <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Patients Section -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <h5 class="m-0 font-weight-bold text-primary me-3">
                                    <i class="fas fa-users me-2"></i>
                                    My Patient Records
                                </h5>
                                <!-- Sort & Filter Toolbar -->
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-sort me-1"></i>Sort
                                    </button>
                                    <ul class="dropdown-menu" id="patientSortOptions">
                                        <li><a class="dropdown-item" href="#" data-sort="date-newest">Date: Newest First</a></li>
                                        <li><a class="dropdown-item" href="#" data-sort="date-oldest">Date: Oldest First</a></li>
                                        <li><a class="dropdown-item" href="#" data-sort="name-az">Name: A-Z</a></li>
                                        <li><a class="dropdown-item" href="#" data-sort="name-za">Name: Z-A</a></li>
                                    </ul>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="patientFilterToggle">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-primary"><?php echo number_format($totalPatients); ?> total patients</span>
                                <span class="text-muted small">Page <?php echo $patientPage; ?> of <?php echo max(1, $patientTotalPages); ?></span>
                            </div>
                        </div>
                        <!-- Tag-Based Filter (Initially Hidden) -->
                        <div class="mt-2" id="patientFilterRow" style="display: none;">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-control d-flex align-items-center flex-wrap" id="patientTagContainer" onclick="document.getElementById('patientTagInput').focus()" style="min-height: 38px; cursor: text;">
                                        <input type="text" id="patientTagInput" style="border:none; outline:none; background:transparent; min-width:100px; flex-grow:1;" placeholder="Type & Enter...">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="clearPatientTags">
                                        <i class="fas fa-times me-1"></i>Clear All Tags
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($patients)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-user-friends text-muted" style="font-size: 4rem;"></i>
                                <h4 class="text-muted mt-3">No patient records found</h4>
                                <p class="text-muted">Patients who have appointments with you will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead class="table-primary">
                                        <tr>
                                            <th><i class="fas fa-user me-1"></i>Name</th>
                                            <th><i class="fas fa-id-card me-1"></i>IC Number</th>
                                            <th><i class="fas fa-notes-medical me-1"></i>Diagnosis</th>
                                            <th><i class="fas fa-phone me-1"></i>Phone</th>
                                            <th><i class="fas fa-calendar me-1"></i>First Visit</th>
                                            <th><i class="bi bi-gear me-1"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="patientsTable">
                                        <?php foreach ($patients as $patient): ?>
                                            <tr data-name="<?php echo strtoupper(htmlspecialchars($patient['name'])); ?>" 
                                                data-date="<?php echo $patient['created_at']; ?>">
                                                <td class="fw-semibold">
                                                    <?php if (!empty($patient['latest_appointment_id'])): ?>
                                                        <a href="appointment_detail.php?id=<?php echo $patient['latest_appointment_id']; ?>" 
                                                           class="text-primary text-decoration-none fw-bold">
                                                            <i class="bi bi-person-circle me-1"></i>
                                                            <?php echo htmlspecialchars($patient['name']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <i class="bi bi-person-circle me-1"></i>
                                                        <?php echo htmlspecialchars($patient['name']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <code>
                                                        <?php 
                                                        // Mask IC number - show first 8 characters, mask last 4
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
                                                    <?php if (!empty($patient['latest_appointment_id'])): ?>
                                                        <a href="appointment_detail.php?id=<?php echo $patient['latest_appointment_id']; ?>" 
                                                           class="btn btn-info btn-sm" 
                                                           title="View Latest Appointment Details">
                                                            <i class="fas fa-calendar-alt"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">No appointments</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Patient Pagination -->
                            <?php if ($patientTotalPages > 1): ?>
                                <nav aria-label="My patients pagination" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <!-- Previous Page -->
                                        <?php if ($patientPage > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?patient_page=<?php echo $patientPage - 1; ?><?php echo isset($_GET['appointment_page']) ? '&appointment_page=' . $_GET['appointment_page'] : ''; ?>">
                                                    <i class="bi bi-chevron-left"></i> Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <!-- Page Numbers -->
                                        <?php
                                        $startPage = max(1, $patientPage - 2);
                                        $endPage = min($patientTotalPages, $patientPage + 2);
                                        
                                        for ($i = $startPage; $i <= $endPage; $i++):
                                        ?>
                                            <li class="page-item <?php echo ($i === $patientPage) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?patient_page=<?php echo $i; ?><?php echo isset($_GET['appointment_page']) ? '&appointment_page=' . $_GET['appointment_page'] : ''; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <!-- Next Page -->
                                        <?php if ($patientPage < $patientTotalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?patient_page=<?php echo $patientPage + 1; ?><?php echo isset($_GET['appointment_page']) ? '&appointment_page=' . $_GET['appointment_page'] : ''; ?>">
                                                    Next <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
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
        // Initialize the application (simplified to avoid extension conflicts)
        try {
            if (typeof ClinicJS !== 'undefined') {
                ClinicJS.initializeApp(
                    '',
                    <?php echo json_encode($_SESSION['user_id']); ?>,
                    {}
                );
            }
        } catch (error) {
            console.log('ClinicJS initialization skipped:', error.message);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
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
            ?>
            

            
            <?php if (!empty($upcomingReminders)): ?>
                // Show appointment reminder only once per session per doctor
                <?php foreach ($upcomingReminders as $reminder): ?>
                const reminderKey = 'doctor_<?php echo $doctorId; ?>_reminder_shown_<?php echo $reminder['appointment_id']; ?>';
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
                        // Mark this reminder as shown for this specific doctor
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
            
            // Function to update message count badge
            function updateMessageCount() {
                // Use a more robust fetch with timeout
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout
                
                fetch('message_log.php?ajax=count', {
                    signal: controller.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    clearTimeout(timeoutId);
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const messageLink = document.querySelector('a[href="message_log.php"]');
                    if (!messageLink) return;
                    
                    const existingBadge = messageLink.querySelector('.badge');
                    
                    if (data.count > 0) {
                        if (!existingBadge) {
                            // Create new badge with inline positioning
                            const badge = document.createElement('span');
                            badge.className = 'position-absolute badge bg-danger';
                            badge.style.cssText = 'top: -8px; right: -8px; z-index: 1070;';
                            badge.innerHTML = data.count + '<span class="visually-hidden">unread messages</span>';
                            messageLink.appendChild(badge);
                        } else {
                            // Update existing badge
                            existingBadge.innerHTML = data.count + '<span class="visually-hidden">unread messages</span>';
                        }
                    } else if (existingBadge) {
                        // Remove badge if no unread messages
                        existingBadge.remove();
                    }
                })
                .catch(error => {
                    clearTimeout(timeoutId);
                    if (error.name !== 'AbortError') {
                        console.log('Message count update failed:', error);
                    }
                });
            }
            
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
            
            // Update message count every 30 seconds
            updateMessageCount(); // Initial update
            setInterval(updateMessageCount, 30000);
            
            // Debug: Log badge status and test badge positioning
            <?php if (isset($_GET['debug'])): ?>
            console.log('Debug: Doctor ID = <?php echo $doctorId; ?>, Unread Count = <?php echo $unreadMessageCount; ?>');
            const messageLink = document.querySelector('a[href="message_log.php"]');
            const badge = messageLink ? messageLink.querySelector('.badge') : null;
            console.log('Debug: Message link found =', !!messageLink);
            console.log('Debug: Badge found =', !!badge);
            if (badge) {
                console.log('Debug: Badge text =', badge.textContent.trim());
                const styles = window.getComputedStyle(badge);
                console.log('Debug: Badge styles =', {
                    position: styles.position,
                    top: styles.top,
                    right: styles.right,
                    zIndex: styles.zIndex,
                    display: styles.display,
                    visibility: styles.visibility,
                    backgroundColor: styles.backgroundColor,
                    color: styles.color
                });
                const rect = badge.getBoundingClientRect();
                console.log('Debug: Badge bounds =', rect);
            }
            if (messageLink) {
                const linkStyles = window.getComputedStyle(messageLink);
                console.log('Debug: Message link styles =', {
                    position: linkStyles.position,
                    overflow: linkStyles.overflow
                });
            }
            <?php endif; ?>
            
            // Sort and Filter functionality
            initializeSortAndFilter();
        });
        
        function initializeSortAndFilter() {
            // Global variables for tags
            window.appointmentTags = [];
            window.patientTags = [];
            
            // Appointment Filter Toggle
            document.getElementById('appointmentFilterToggle').addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent event bubbling
                const filterRow = document.getElementById('appointmentFilterRow');
                const filterInput = document.getElementById('appointmentTagInput');
                
                if (filterRow.style.display === 'none') {
                    filterRow.style.display = 'block';
                    filterInput.focus();
                } else {
                    filterRow.style.display = 'none';
                }
            });
            
            // Patient Filter Toggle
            document.getElementById('patientFilterToggle').addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent event bubbling
                const filterRow = document.getElementById('patientFilterRow');
                const filterInput = document.getElementById('patientTagInput');
                
                if (filterRow.style.display === 'none') {
                    filterRow.style.display = 'block';
                    filterInput.focus();
                } else {
                    filterRow.style.display = 'none';
                }
            });
            
            // Outside click detection for appointment filter
            document.addEventListener('click', function(e) {
                const appointmentFilterRow = document.getElementById('appointmentFilterRow');
                const appointmentFilterToggle = document.getElementById('appointmentFilterToggle');
                const appointmentTagContainer = document.getElementById('appointmentTagContainer');
                const clearAppointmentTagsBtn = document.getElementById('clearAppointmentTags');
                
                // Check if click is outside the filter components
                if (appointmentFilterRow && appointmentFilterRow.style.display === 'block') {
                    if (!appointmentFilterRow.contains(e.target) && 
                        !appointmentFilterToggle.contains(e.target) &&
                        e.target !== appointmentFilterToggle &&
                        e.target !== appointmentTagContainer &&
                        e.target !== clearAppointmentTagsBtn) {
                        appointmentFilterRow.style.display = 'none';
                    }
                }
            });
            
            // Outside click detection for patient filter
            document.addEventListener('click', function(e) {
                const patientFilterRow = document.getElementById('patientFilterRow');
                const patientFilterToggle = document.getElementById('patientFilterToggle');
                const patientTagContainer = document.getElementById('patientTagContainer');
                const clearPatientTagsBtn = document.getElementById('clearPatientTags');
                
                // Check if click is outside the filter components
                if (patientFilterRow && patientFilterRow.style.display === 'block') {
                    if (!patientFilterRow.contains(e.target) && 
                        !patientFilterToggle.contains(e.target) &&
                        e.target !== patientFilterToggle &&
                        e.target !== patientTagContainer &&
                        e.target !== clearPatientTagsBtn) {
                        patientFilterRow.style.display = 'none';
                    }
                }
            });
            
            // Prevent filter panels from closing when clicking inside them
            document.getElementById('appointmentFilterRow').addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            document.getElementById('patientFilterRow').addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            // Tag input event listeners
            document.getElementById('appointmentTagInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addAppointmentTag(this.value.trim());
                    this.value = '';
                }
            });
            
            document.getElementById('patientTagInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addPatientTag(this.value.trim());
                    this.value = '';
                }
            });
            
            // Clear tags event listeners
            document.getElementById('clearAppointmentTags').addEventListener('click', clearAppointmentTags);
            document.getElementById('clearPatientTags').addEventListener('click', clearPatientTags);
            
            // Sort event listeners
            document.querySelectorAll('#appointmentSortOptions a').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    sortList('appointments', this.dataset.sort);
                });
            });
            
            document.querySelectorAll('#patientSortOptions a').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    sortList('patients', this.dataset.sort);
                });
            });
        }
        
        // Tag Management Functions
        function addAppointmentTag(tagText) {
            const cleanTag = tagText.trim();
            if (cleanTag && !window.appointmentTags.includes(cleanTag.toLowerCase())) {
                window.appointmentTags.push(cleanTag.toLowerCase());
                renderAppointmentTags();
                filterAppointments();
            }
        }
        
        function addPatientTag(tagText) {
            const cleanTag = tagText.trim();
            if (cleanTag && !window.patientTags.includes(cleanTag.toLowerCase())) {
                window.patientTags.push(cleanTag.toLowerCase());
                renderPatientTags();
                filterPatients();
            }
        }
        
        function removeAppointmentTag(tagText) {
            const index = window.appointmentTags.indexOf(tagText);
            if (index > -1) {
                window.appointmentTags.splice(index, 1);
                renderAppointmentTags();
                filterAppointments();
            }
        }
        
        function removePatientTag(tagText) {
            const index = window.patientTags.indexOf(tagText);
            if (index > -1) {
                window.patientTags.splice(index, 1);
                renderPatientTags();
                filterPatients();
            }
        }
        
        function clearAppointmentTags() {
            window.appointmentTags = [];
            renderAppointmentTags();
            filterAppointments();
        }
        
        function clearPatientTags() {
            window.patientTags = [];
            renderPatientTags();
            filterPatients();
        }
        
        function renderAppointmentTags() {
            const container = document.getElementById('appointmentTagContainer');
            const input = document.getElementById('appointmentTagInput');
            
            // Remove existing tags (but keep the input)
            const existingTags = container.querySelectorAll('.badge');
            existingTags.forEach(tag => tag.remove());
            
            // Add tags before the input
            window.appointmentTags.forEach(function(tag) {
                const tagElement = document.createElement('span');
                tagElement.className = 'badge bg-primary me-1 my-1';
                tagElement.innerHTML = `${tag} <i class="fas fa-times ms-1" style="cursor: pointer;"></i>`;
                tagElement.querySelector('i').addEventListener('click', function() {
                    removeAppointmentTag(tag);
                });
                container.insertBefore(tagElement, input);
            });
        }
        
        function renderPatientTags() {
            const container = document.getElementById('patientTagContainer');
            const input = document.getElementById('patientTagInput');
            
            // Remove existing tags (but keep the input)
            const existingTags = container.querySelectorAll('.badge');
            existingTags.forEach(tag => tag.remove());
            
            // Add tags before the input
            window.patientTags.forEach(function(tag) {
                const tagElement = document.createElement('span');
                tagElement.className = 'badge bg-primary me-1 my-1';
                tagElement.innerHTML = `${tag} <i class="fas fa-times ms-1" style="cursor: pointer;"></i>`;
                tagElement.querySelector('i').addEventListener('click', function() {
                    removePatientTag(tag);
                });
                container.insertBefore(tagElement, input);
            });
        }
        
        // Filter Functions with Tag Logic (OR Logic) - Case Insensitive
        function filterAppointments() {
            const tbody = document.getElementById('appointmentsTable');
            if (!tbody) return;
            
            const rows = tbody.getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                let shouldShow = true;
                
                if (window.appointmentTags.length > 0) {
                    // Check if row contains AT LEAST ONE tag (OR logic) - Case insensitive
                    const rowText = row.innerText.toLowerCase();
                    shouldShow = false; // Start with false, set to true if any tag matches
                    
                    for (let tag of window.appointmentTags) {
                        const tagText = tag.toLowerCase();
                        if (rowText.includes(tagText)) {
                            shouldShow = true;
                            break; // Found a match, no need to check other tags
                        }
                    }
                }
                
                row.style.display = shouldShow ? '' : 'none';
            }
        }
        
        function filterPatients() {
            const tbody = document.getElementById('patientsTable');
            if (!tbody) return;
            
            const rows = tbody.getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                let shouldShow = true;
                
                if (window.patientTags.length > 0) {
                    // Check if row contains AT LEAST ONE tag (OR logic) - Case insensitive
                    const rowText = row.innerText.toLowerCase();
                    shouldShow = false; // Start with false, set to true if any tag matches
                    
                    for (let tag of window.patientTags) {
                        const tagText = tag.toLowerCase();
                        if (rowText.includes(tagText)) {
                            shouldShow = true;
                            break; // Found a match, no need to check other tags
                        }
                    }
                }
                
                row.style.display = shouldShow ? '' : 'none';
            }
        }
        
        // Sorting Function
        function sortList(tableType, criteria) {
            const tbody = document.getElementById(tableType === 'appointments' ? 'appointmentsTable' : 'patientsTable');
            if (!tbody) return;
            
            const rows = Array.from(tbody.getElementsByTagName('tr'));
            
            rows.sort(function(a, b) {
                switch(criteria) {
                    case 'date-newest':
                        const dateA = new Date(a.dataset.date);
                        const dateB = new Date(b.dataset.date);
                        return dateB - dateA;
                    case 'date-oldest':
                        const dateA2 = new Date(a.dataset.date);
                        const dateB2 = new Date(b.dataset.date);
                        return dateA2 - dateB2;
                    case 'name-az':
                        return a.dataset.name.localeCompare(b.dataset.name);
                    case 'name-za':
                        return b.dataset.name.localeCompare(a.dataset.name);
                    default:
                        return 0;
                }
            });
            
            rows.forEach(function(row) {
                tbody.appendChild(row);
            });
            

        }
        
    </script>
</body>
</html>