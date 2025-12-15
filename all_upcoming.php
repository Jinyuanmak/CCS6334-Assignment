<?php
/**
 * All Upcoming Appointments page for Private Clinic Patient Record System
 * Displays all upcoming appointments without limits
 */

require_once 'config.php';
require_once 'db.php';

// Check authentication and session timeout
Database::requireAuth();

// Handle success/error messages
$message = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message'], $_SESSION['message_type']);
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20; // Show 20 appointments per page
$offset = ($page - 1) * $limit;

// Fetch upcoming appointments with pagination
try {
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM appointments a 
                 JOIN patients p ON a.patient_id = p.id 
                 WHERE a.start_time >= NOW()";
    $totalResult = Database::fetchOne($countSql);
    $totalAppointments = $totalResult['total'] ?? 0;
    $totalPages = ceil($totalAppointments / $limit);
    
    // Fetch appointments for current page
    $appointmentSql = "SELECT a.appointment_id, a.appointment_date, a.start_time, a.end_time, a.doctor_name, 
                              AES_DECRYPT(a.reason, ?) as reason, p.name as patient_name, AES_DECRYPT(UNHEX(p.ic_number), ?) as ic_number
                       FROM appointments a 
                       JOIN patients p ON a.patient_id = p.id 
                       WHERE a.start_time >= NOW()
                       ORDER BY a.start_time ASC
                       LIMIT ? OFFSET ?";
    $appointments = Database::fetchAll($appointmentSql, [ENCRYPTION_KEY, SECURE_KEY, $limit, $offset]);
} catch (Exception $e) {
    error_log("Error loading appointments: " . $e->getMessage());
    $appointments = [];
    $totalAppointments = 0;
    $totalPages = 0;
    $message = "Error loading appointments. Please try again.";
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Upcoming Appointments - Private Clinic</title>
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
            
            <!-- Right side buttons -->
            <div class="navbar-nav ms-auto">
                <a href="dashboard.php" class="btn btn-outline-light me-2">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Dashboard
                </a>
                <a href="add_patient.php" class="btn btn-success me-2">
                    <i class="bi bi-person-plus-fill me-1"></i>
                    Add Patient
                </a>
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
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
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Upcoming Appointments Section -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="m-0 font-weight-bold text-info">
                                <i class="bi bi-calendar-event me-2"></i>
                                All Upcoming Appointments
                            </h5>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-info"><?php echo number_format($totalAppointments); ?> total appointments</span>
                                <span class="text-muted small">Page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 4rem;"></i>
                                <h4 class="text-muted mt-3">No upcoming appointments scheduled</h4>
                                <p class="text-muted">Appointments will appear here when scheduled.</p>
                                <a href="add_appointment.php" class="btn btn-info">
                                    <i class="bi bi-calendar-plus me-1"></i>
                                    Schedule New Appointment
                                </a>
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
                                    <tbody>
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
                                                       class="text-decoration-none text-primary fw-bold" 
                                                       title="View Appointment Details">
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
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Upcoming appointments pagination" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <!-- Previous Page -->
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                                    <i class="bi bi-chevron-left"></i> Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <!-- Page Numbers -->
                                        <?php
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);
                                        
                                        for ($i = $startPage; $i <= $endPage; $i++):
                                        ?>
                                            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <!-- Next Page -->
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">
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

    <script>
        // SweetAlert for appointment cancellation
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>

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

        });
    </script>
</body>
</html>