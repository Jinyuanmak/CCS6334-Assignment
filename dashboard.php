<?php
/**
 * Dashboard page for Private Clinic Patient Record System
 * Displays patient records and appointments with encrypted data decryption
 */

require_once 'config.php';
require_once 'db.php';
require_once 'appointment_analytics.php';

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

// Get appointment analytics data for Weekly Workload chart (default to weekly view)
try {
    $analyticsData = AppointmentAnalyticsService::getAnalyticsData('weekly');
    $chartLabels = $analyticsData['json_labels'];
    $chartCounts = $analyticsData['json_counts'];
    $analyticsError = isset($analyticsData['is_fallback']) && $analyticsData['is_fallback'];
} catch (Exception $e) {
    error_log("Error loading appointment analytics: " . $e->getMessage());
    // Fallback data for error cases
    $chartLabels = json_encode(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
    $chartCounts = json_encode([0, 0, 0, 0, 0, 0, 0]);
    $analyticsError = true;
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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        <!-- Analytics and System Maintenance Section - Side by Side -->
        <div class="row mb-4">
            <!-- Weekly Workload Analytics - Left Side -->
            <div class="col-lg-8">
                <div class="card shadow h-100">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                            <h5 class="m-0 font-weight-bold text-primary flex-grow-1">
                                <i class="fas fa-chart-line me-2"></i>
                                Appointment Trends
                                <?php if ($analyticsError): ?>
                                    <small class="text-muted ms-2 d-block d-sm-inline">
                                        <i class="fas fa-exclamation-triangle text-warning"></i>
                                        Data temporarily unavailable
                                    </small>
                                <?php endif; ?>
                            </h5>
                            <div class="btn-group btn-group-sm flex-shrink-0" role="group" aria-label="Chart view toggle">
                                <button type="button" class="btn btn-outline-primary active" id="weeklyViewBtn" aria-pressed="true">
                                    <i class="fas fa-calendar-week me-1 d-none d-md-inline"></i>
                                    Weekly
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="monthlyViewBtn" aria-pressed="false">
                                    <i class="fas fa-calendar-alt me-1 d-none d-md-inline"></i>
                                    Monthly
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($analyticsError): ?>
                            <div class="alert alert-warning mb-3" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Analytics Temporarily Unavailable</strong><br>
                                Unable to load appointment analytics data. Please try refreshing the page or contact support if the issue persists.
                            </div>
                        <?php endif; ?>
                        <div class="chart-container" role="img" aria-labelledby="chart-title" aria-describedby="chart-description">
                            <div id="chart-title" class="visually-hidden">Weekly Workload Line Chart</div>
                            <div id="chart-description" class="visually-hidden">
                                A line chart showing appointment trends for the next 7 days with smooth curves. 
                                Use Tab to navigate to the data table below for detailed information.
                            </div>
                            <canvas id="workloadChart" 
                                    role="img" 
                                    aria-label="Weekly appointment workload line chart showing appointment trends for the next 7 days"
                                    tabindex="0">
                                <p>Your browser does not support the canvas element. Please see the data table below for appointment information.</p>
                            </canvas>
                            <!-- Accessible data table for screen readers -->
                            <div id="chart-data-table" class="visually-hidden" aria-live="polite">
                                <table class="table" role="table" aria-label="Weekly appointment data">
                                    <caption>Appointment counts for the next 7 days</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Day</th>
                                            <th scope="col">Appointments</th>
                                        </tr>
                                    </thead>
                                    <tbody id="chart-data-tbody">
                                        <!-- Data will be populated by JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Maintenance - Right Side -->
            <div class="col-lg-4">
                <div class="card shadow h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="m-0 font-weight-bold text-warning">
                            <i class="fas fa-tools me-2"></i>
                            System Maintenance
                        </h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h6 class="text-dark mb-2">
                            <i class="fas fa-database me-2 text-primary"></i>
                            Database Backup
                        </h6>
                        <p class="text-muted mb-3">
                            Create a complete backup of all patient records, appointments, and system data for disaster recovery.
                        </p>
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">
                                <i class="fas fa-shield-alt me-1"></i>
                                Admin Only Access
                            </small>
                            <small class="text-muted d-block mb-1">
                                <i class="fas fa-lock me-1"></i>
                                Encrypted Data Included
                            </small>
                            <small class="text-muted d-block">
                                <i class="fas fa-clock me-1"></i>
                                Timestamped Files
                            </small>
                        </div>
                        <div class="mt-auto">
                            <div class="d-grid">
                                <button type="button" class="btn btn-warning btn-lg" id="downloadBackupBtn">
                                    <i class="fas fa-download me-2"></i>
                                    Download Backup
                                </button>
                            </div>
                            <small class="text-muted mt-2 d-block text-center">
                                <i class="fas fa-info-circle me-1"></i>
                                Auto-deleted after download
                            </small>
                        </div>
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
        
        // Chart rendering and dashboard initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure Chart.js is loaded before initializing
            if (typeof Chart === 'undefined') {
                console.error('Chart.js library not loaded');
                return;
            }
            // Initialize Weekly Workload Chart with enhanced configuration
            const ctx = document.getElementById('workloadChart').getContext('2d');
            
            // Validate PHP JSON data before chart initialization
            const jsonLabels = <?php echo $chartLabels; ?>;
            const jsonCounts = <?php echo $chartCounts; ?>;
            
            // Ensure data is valid arrays
            if (!Array.isArray(jsonLabels) || !Array.isArray(jsonCounts)) {
                console.error('Invalid chart data format');
                throw new Error('Chart data must be arrays');
            }
            
            if (jsonLabels.length !== jsonCounts.length) {
                console.error('Chart data arrays have mismatched lengths');
                throw new Error('Labels and counts arrays must have equal length');
            }
            
            // Chart.js configuration object with line chart type and premium styling
            const chartConfig = {
                type: 'line',
                data: {
                    labels: jsonLabels, // Use validated JSON data
                    datasets: [{
                        label: 'Appointments',
                        data: jsonCounts, // Use validated JSON data
                        borderColor: '#3b82f6', // Soft blue color for line
                        backgroundColor: 'rgba(59, 130, 246, 0.1)', // Semi-transparent background fill
                        borderWidth: 2,
                        fill: true, // Add fill property with semi-transparent background
                        tension: 0.4, // Smooth curves between data points
                        pointRadius: 4, // Circular markers radius
                        pointBackgroundColor: '#3b82f6', // Soft blue color for points
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true, // Responsive behavior configured
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            // Enhanced tooltip interactions
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            borderColor: '#3b82f6',
                            borderWidth: 1,
                            cornerRadius: 6,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return `${context.parsed.y} appointment${context.parsed.y !== 1 ? 's' : ''}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                color: '#6b7280'
                            },
                            grid: {
                                color: '#e5e7eb'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#6b7280'
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    // Enhanced interaction configuration
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    // Animation configuration for smooth rendering and view transitions
                    animation: {
                        duration: 750,
                        easing: 'easeInOutQuart'
                    },
                    // Enhanced animation for data updates (view switching)
                    animations: {
                        tension: {
                            duration: 750,
                            easing: 'easeInOutQuart',
                            from: 0.4,
                            to: 0.4
                        },
                        x: {
                            duration: 750,
                            easing: 'easeInOutQuart'
                        },
                        y: {
                            duration: 750,
                            easing: 'easeInOutQuart'
                        }
                    }
                }
            };
            
            // Initialize Chart.js with processed appointment data
            try {
                const workloadChart = new Chart(ctx, chartConfig);
                
                // Store chart reference for potential future updates
                window.workloadChart = workloadChart;
                
                // Populate accessible data table for screen readers
                populateAccessibleDataTable(jsonLabels, jsonCounts);
                
                // Add keyboard navigation support
                addChartKeyboardNavigation(workloadChart, jsonLabels, jsonCounts);
                
                console.log('Weekly Workload chart initialized successfully');
            } catch (error) {
                console.error('Failed to initialize Weekly Workload chart:', error);
                
                // Fallback: Display error message in chart area and populate data table
                const chartContainer = document.getElementById('workloadChart').parentElement;
                chartContainer.innerHTML = `
                    <div class="alert alert-warning text-center" role="alert" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Chart Unavailable</strong><br>
                        Unable to render the weekly workload chart. Please refresh the page.
                    </div>
                `;
                
                // Still populate the accessible data table even if chart fails
                populateAccessibleDataTable(jsonLabels, jsonCounts);
            }
            
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
            
            /**
             * Populate accessible data table for screen readers
             */
            function populateAccessibleDataTable(labels, counts) {
                const tbody = document.getElementById('chart-data-tbody');
                if (!tbody) return;
                
                tbody.innerHTML = '';
                
                for (let i = 0; i < labels.length; i++) {
                    const row = document.createElement('tr');
                    const dayCell = document.createElement('td');
                    const countCell = document.createElement('td');
                    
                    dayCell.textContent = labels[i];
                    countCell.textContent = counts[i] + (counts[i] === 1 ? ' appointment' : ' appointments');
                    
                    row.appendChild(dayCell);
                    row.appendChild(countCell);
                    tbody.appendChild(row);
                }
            }
            
            /**
             * Add keyboard navigation support to chart
             */
            function addChartKeyboardNavigation(chart, labels, counts) {
                const canvas = document.getElementById('workloadChart');
                let currentIndex = 0;
                
                canvas.addEventListener('keydown', function(event) {
                    switch(event.key) {
                        case 'ArrowRight':
                        case 'ArrowDown':
                            event.preventDefault();
                            currentIndex = Math.min(currentIndex + 1, labels.length - 1);
                            announceDataPoint(labels[currentIndex], counts[currentIndex]);
                            highlightDataPoint(chart, currentIndex);
                            break;
                            
                        case 'ArrowLeft':
                        case 'ArrowUp':
                            event.preventDefault();
                            currentIndex = Math.max(currentIndex - 1, 0);
                            announceDataPoint(labels[currentIndex], counts[currentIndex]);
                            highlightDataPoint(chart, currentIndex);
                            break;
                            
                        case 'Home':
                            event.preventDefault();
                            currentIndex = 0;
                            announceDataPoint(labels[currentIndex], counts[currentIndex]);
                            highlightDataPoint(chart, currentIndex);
                            break;
                            
                        case 'End':
                            event.preventDefault();
                            currentIndex = labels.length - 1;
                            announceDataPoint(labels[currentIndex], counts[currentIndex]);
                            highlightDataPoint(chart, currentIndex);
                            break;
                            
                        case 'Enter':
                        case ' ':
                            event.preventDefault();
                            announceChartSummary(labels, counts);
                            break;
                    }
                });
                
                // Announce initial focus
                canvas.addEventListener('focus', function() {
                    announceDataPoint(labels[currentIndex], counts[currentIndex]);
                });
            }
            
            /**
             * Announce data point for screen readers
             */
            function announceDataPoint(label, count) {
                const announcement = `${label}: ${count} ${count === 1 ? 'appointment' : 'appointments'}`;
                
                // Create or update live region for screen reader announcements
                let liveRegion = document.getElementById('chart-live-region');
                if (!liveRegion) {
                    liveRegion = document.createElement('div');
                    liveRegion.id = 'chart-live-region';
                    liveRegion.setAttribute('aria-live', 'polite');
                    liveRegion.setAttribute('aria-atomic', 'true');
                    liveRegion.className = 'visually-hidden';
                    document.body.appendChild(liveRegion);
                }
                
                liveRegion.textContent = announcement;
            }
            
            /**
             * Announce chart summary
             */
            function announceChartSummary(labels, counts) {
                const total = counts.reduce((sum, count) => sum + count, 0);
                const max = Math.max(...counts);
                const maxDay = labels[counts.indexOf(max)];
                
                const summary = `Chart summary: Total ${total} appointments across 7 days. Busiest day is ${maxDay} with ${max} appointments.`;
                
                let liveRegion = document.getElementById('chart-live-region');
                if (!liveRegion) {
                    liveRegion = document.createElement('div');
                    liveRegion.id = 'chart-live-region';
                    liveRegion.setAttribute('aria-live', 'polite');
                    liveRegion.setAttribute('aria-atomic', 'true');
                    liveRegion.className = 'visually-hidden';
                    document.body.appendChild(liveRegion);
                }
                
                liveRegion.textContent = summary;
            }
            
            /**
             * Highlight data point visually (for users who can see)
             */
            function highlightDataPoint(chart, index) {
                // Reset all point colors
                const dataset = chart.data.datasets[0];
                dataset.pointBackgroundColor = Array(dataset.data.length).fill('#3b82f6');
                dataset.pointRadius = Array(dataset.data.length).fill(4);
                
                // Highlight current point
                dataset.pointBackgroundColor[index] = '#1d4ed8'; // Darker blue for highlight
                dataset.pointRadius[index] = 6; // Larger radius for highlight
                
                chart.update('none'); // Update without animation for better accessibility
            }
            
            /**
             * ViewToggleController - Manages switching between weekly and monthly views
             */
            class ViewToggleController {
                constructor(chartInstance) {
                    this.chart = chartInstance;
                    this.currentView = 'weekly'; // Default to weekly view
                    this.isLoading = false;
                    
                    this.initializeEventListeners();
                    this.highlightActiveButton('weekly');
                }
                
                /**
                 * Initialize event listeners for toggle buttons
                 */
                initializeEventListeners() {
                    const weeklyBtn = document.getElementById('weeklyViewBtn');
                    const monthlyBtn = document.getElementById('monthlyViewBtn');
                    
                    if (weeklyBtn) {
                        weeklyBtn.addEventListener('click', () => this.switchView('weekly'));
                    }
                    
                    if (monthlyBtn) {
                        monthlyBtn.addEventListener('click', () => this.switchView('monthly'));
                    }
                }
                
                /**
                 * Switch between weekly and monthly views
                 * @param {string} viewType - 'weekly' or 'monthly'
                 */
                async switchView(viewType) {
                    if (this.isLoading || this.currentView === viewType) {
                        return; // Prevent multiple requests or switching to same view
                    }
                    
                    this.isLoading = true;
                    this.highlightActiveButton(viewType);
                    
                    try {
                        const data = await this.fetchAppointmentData(viewType);
                        
                        if (data.success) {
                            this.updateChart(data.labels, data.counts);
                            this.currentView = viewType;
                            
                            // Update accessible data table
                            populateAccessibleDataTable(data.labels, data.counts);
                        } else {
                            console.error('Failed to fetch appointment data:', data.error);
                            this.showErrorMessage('Unable to load ' + viewType + ' view data');
                        }
                    } catch (error) {
                        console.error('Error switching view:', error);
                        this.showErrorMessage('Network error while loading ' + viewType + ' view');
                    } finally {
                        this.isLoading = false;
                    }
                }
                
                /**
                 * Fetch appointment data from backend
                 * @param {string} viewType - View type ('weekly' or 'monthly')
                 * @returns {Promise<Object>} Response data
                 */
                async fetchAppointmentData(viewType) {
                    const response = await fetch(`data.php?mode=chart&view=${viewType}`, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    return await response.json();
                }
                
                /**
                 * Update chart with new data
                 * @param {Array} labels - New labels array
                 * @param {Array} data - New data array
                 */
                updateChart(labels, data) {
                    if (!this.chart || !labels || !data) {
                        console.error('Invalid chart or data for update');
                        return;
                    }
                    
                    // Update chart data
                    this.chart.data.labels = labels;
                    this.chart.data.datasets[0].data = data;
                    
                    // Animate the update smoothly with proper duration and easing
                    this.chart.update({
                        duration: 750,
                        easing: 'easeInOutQuart'
                    });
                }
                
                /**
                 * Highlight the active button and remove highlight from inactive button
                 * @param {string} viewType - 'weekly' or 'monthly'
                 */
                highlightActiveButton(viewType) {
                    const weeklyBtn = document.getElementById('weeklyViewBtn');
                    const monthlyBtn = document.getElementById('monthlyViewBtn');
                    
                    if (weeklyBtn && monthlyBtn) {
                        // Remove active class from both buttons and update aria-pressed
                        weeklyBtn.classList.remove('active');
                        weeklyBtn.setAttribute('aria-pressed', 'false');
                        monthlyBtn.classList.remove('active');
                        monthlyBtn.setAttribute('aria-pressed', 'false');
                        
                        // Add active class to selected button and update aria-pressed
                        if (viewType === 'weekly') {
                            weeklyBtn.classList.add('active');
                            weeklyBtn.setAttribute('aria-pressed', 'true');
                        } else {
                            monthlyBtn.classList.add('active');
                            monthlyBtn.setAttribute('aria-pressed', 'true');
                        }
                    }
                }
                
                /**
                 * Show error message to user
                 * @param {string} message - Error message to display
                 */
                showErrorMessage(message) {
                    // Create a temporary alert
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-warning alert-dismissible fade show mt-2';
                    alertDiv.innerHTML = `
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    
                    // Insert after the chart card header
                    const cardBody = document.querySelector('.chart-container').parentElement;
                    cardBody.insertBefore(alertDiv, cardBody.firstChild);
                    
                    // Auto-dismiss after 5 seconds
                    setTimeout(() => {
                        if (alertDiv.parentNode) {
                            alertDiv.remove();
                        }
                    }, 5000);
                }
            }
            
            // Initialize ViewToggleController after chart is created
            if (window.workloadChart) {
                window.viewToggleController = new ViewToggleController(window.workloadChart);
            }

        });

    </script>
</body>
</html>