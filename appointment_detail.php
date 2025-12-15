<?php
/**
 * Appointment Detail page for Private Clinic Patient Record System
 * Shows detailed information about completed appointments
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
    
    // Redirect based on user role
    if ($_SESSION['role'] === 'doctor') {
        header('Location: doctor_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

// Fetch appointment details with patient and diagnosis information
try {
    $sql = "SELECT a.appointment_id, a.appointment_date, a.start_time, a.end_time, a.doctor_name,
                   AES_DECRYPT(a.reason, ?) as reason,
                   p.id as patient_id, p.name as patient_name, 
                   AES_DECRYPT(UNHEX(p.ic_number), ?) as ic_number,
                   AES_DECRYPT(p.diagnosis, ?) as diagnosis,
                   p.phone as patient_phone,
                   a.created_at as appointment_created
            FROM appointments a 
            JOIN patients p ON a.patient_id = p.id 
            WHERE a.appointment_id = ?";
    
    $appointment = Database::fetchOne($sql, [ENCRYPTION_KEY, SECURE_KEY, ENCRYPTION_KEY, $appointmentId]);
    
    if (!$appointment) {
        $_SESSION['message'] = 'Appointment not found.';
        $_SESSION['message_type'] = 'error';
        
        // Redirect based on user role
        if ($_SESSION['role'] === 'doctor') {
            header('Location: doctor_dashboard.php');
        } else {
            header('Location: dashboard.php');
        }
        exit();
    }
    
    // Calculate appointment duration
    $startTime = new DateTime($appointment['start_time']);
    $endTime = new DateTime($appointment['end_time']);
    $duration = $startTime->diff($endTime);
    $durationMinutes = ($duration->h * 60) + $duration->i;
    
    // Check if appointment is completed
    $isCompleted = strtotime($appointment['end_time']) < time();
    
    // Log the appointment detail view activity for audit trail
    Database::logActivity(
        $_SESSION['user_id'], 
        $_SESSION['username'], 
        'READ', 
        "Viewed appointment details for patient: " . $appointment['patient_name'] . " with Dr. " . $appointment['doctor_name']
    );
    
} catch (Exception $e) {
    error_log("Error fetching appointment details: " . $e->getMessage());
    $_SESSION['message'] = 'Error loading appointment details. Please try again.';
    $_SESSION['message_type'] = 'error';
    
    // Log the failed appointment detail view attempt
    Database::logActivity(
        $_SESSION['user_id'], 
        $_SESSION['username'], 
        'READ_FAILED', 
        "Failed to view appointment details - Appointment ID: " . $appointmentId . " - Error: " . $e->getMessage()
    );
    
    // Redirect based on user role
    if ($_SESSION['role'] === 'doctor') {
        header('Location: doctor_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

// Determine back button destination based on user role
$backUrl = ($_SESSION['role'] === 'doctor') ? 'doctor_dashboard.php' : 'dashboard.php';
$backText = ($_SESSION['role'] === 'doctor') ? 'Back to Doctor Dashboard' : 'Back to Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - Private Clinic</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <style>
        .appointment-detail-hero {
            background: linear-gradient(135deg, var(--primary-500) 0%, var(--secondary-500) 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .appointment-detail-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .appointment-detail-hero .container {
            position: relative;
            z-index: 1;
        }
        
        .appointment-detail-hero h1 {
            color: white !important;
        }
        
        .appointment-detail-hero .lead {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        .info-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 1rem;
            overflow: hidden;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .info-card .card-header {
            border: none;
            font-weight: 600;
            padding: 1.5rem;
        }
        
        .info-card .card-body {
            padding: 2rem;
        }
        
        .info-item {
            background: rgba(248, 250, 252, 0.8);
            border: 1px solid rgba(226, 232, 240, 0.5);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.2s ease;
        }
        
        .info-item:hover {
            background: rgba(240, 249, 255, 0.9);
            border-color: rgba(14, 165, 233, 0.2);
            transform: translateX(5px);
        }
        
        .info-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray-800);
            line-height: 1.5;
        }
        
        .status-badge {
            font-size: 1rem;
            padding: 0.75rem 1.5rem;
            border-radius: 2rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .timeline-item {
            text-align: center;
            padding: 1.5rem;
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .timeline-item:hover {
            border-color: var(--primary-300);
            transform: translateY(-3px);
        }
        
        .timeline-badge {
            font-size: 1.1rem;
            padding: 0.75rem 1.25rem;
            border-radius: 2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .medical-content {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.8);
            border-radius: 1rem;
            padding: 2rem;
            min-height: 150px;
            line-height: 1.6;
            font-size: 1rem;
        }
        
        .action-buttons {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow-lg);
            margin-top: 2rem;
        }
        
        .btn-enhanced {
            padding: 0.75rem 2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold" href="<?php echo $backUrl; ?>">
                <i class="bi bi-hospital me-2"></i>
                Private Clinic<?php echo ($_SESSION['role'] === 'doctor') ? ' - Doctor Portal' : ''; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a href="<?php echo $backUrl; ?>" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-1"></i>
                    <?php echo $backText; ?>
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="appointment-detail-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold mb-3">
                        <i class="bi bi-calendar-check me-3"></i>
                        Appointment Details
                    </h1>
                    <p class="lead mb-0">
                        Comprehensive appointment information for <?php echo htmlspecialchars($appointment['patient_name']); ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <?php if ($isCompleted): ?>
                        <span class="status-badge bg-success text-white">
                            <i class="fas fa-check-circle"></i>Completed
                        </span>
                    <?php else: ?>
                        <span class="status-badge bg-warning text-dark">
                            <i class="fas fa-clock"></i>In Progress
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-11">
                <!-- Main Information Cards -->
                <div class="row mb-4">
                    <!-- Patient Information -->
                    <div class="col-lg-6 mb-4">
                        <div class="info-card card shadow-lg h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-person-fill me-2"></i>Patient Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="info-item">
                                    <div class="info-label">
                                        <i class="bi bi-person me-2"></i>Patient Name
                                    </div>
                                    <div class="info-value">
                                        <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">
                                        <i class="bi bi-card-text me-2"></i>IC Number
                                    </div>
                                    <div class="info-value">
                                        <code class="fs-6 bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($appointment['ic_number']); ?></code>
                                    </div>
                                </div>
                                
                                <div class="info-item mb-0">
                                    <div class="info-label">
                                        <i class="bi bi-telephone me-2"></i>Phone Number
                                    </div>
                                    <div class="info-value">
                                        <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Appointment Information -->
                    <div class="col-lg-6 mb-4">
                        <div class="info-card card shadow-lg h-100">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-calendar-event me-2"></i>Appointment Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="info-item">
                                    <div class="info-label">
                                        <i class="bi bi-person-badge me-2"></i>Doctor Name
                                    </div>
                                    <div class="info-value">
                                        <strong><?php echo htmlspecialchars($appointment['doctor_name']); ?></strong>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">
                                        <i class="bi bi-calendar-date me-2"></i>Appointment Date & Time
                                    </div>
                                    <div class="info-value">
                                        <strong><?php echo date('l, M j, Y', strtotime($appointment['start_time'])); ?> | <?php echo date('g:i A', strtotime($appointment['start_time'])); ?> - <?php echo date('g:i A', strtotime($appointment['end_time'])); ?></strong>
                                    </div>
                                </div>
                                
                                <div class="info-item mb-0">
                                    <div class="info-label">
                                        <i class="bi bi-hourglass-split me-2"></i>Duration
                                    </div>
                                    <div class="info-value">
                                        <span class="badge bg-info fs-6"><?php echo $durationMinutes; ?> minutes</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Medical Information -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-4">
                        <div class="info-card card shadow-lg h-100">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-clipboard-pulse me-2"></i>Patient Diagnosis
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="medical-content">
                                    <?php 
                                    if ($appointment['diagnosis'] === null || $appointment['diagnosis'] === '') {
                                        echo '<em class="text-muted">Unable to decrypt diagnosis or no diagnosis recorded</em>';
                                    } else {
                                        // Decode HTML entities and allow safe HTML tags for rich text formatting
                                        $decodedDiagnosis = html_entity_decode($appointment['diagnosis'], ENT_QUOTES, 'UTF-8');
                                        // Allow comprehensive HTML tags for TinyMCE formatting (including colors, styles)
                                        $allowedTags = '<p><br><ul><ol><li><strong><b><em><i><u><span><div><h1><h2><h3><h4><h5><h6><blockquote><pre><code>';
                                        $cleanDiagnosis = strip_tags($decodedDiagnosis, $allowedTags);
                                        // Output the HTML content directly (not escaped) so it renders properly
                                        echo $cleanDiagnosis;
                                    }
                                    ?>
                                </div>
                                <div class="mt-3 p-3 bg-light rounded">
                                    <small class="text-muted">
                                        <i class="bi bi-shield-lock text-success me-2"></i>
                                        This medical information is encrypted for security and privacy protection
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="info-card card shadow-lg h-100">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-chat-text me-2"></i>Appointment Reason
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="medical-content">
                                    <?php 
                                    if ($appointment['reason'] === null || $appointment['reason'] === '') {
                                        echo '<em class="text-muted">Unable to decrypt appointment reason or no reason recorded</em>';
                                    } else {
                                        // Decode HTML entities and allow safe HTML tags for rich text formatting
                                        $decodedReason = html_entity_decode($appointment['reason'], ENT_QUOTES, 'UTF-8');
                                        // Allow comprehensive HTML tags for TinyMCE formatting (including colors, styles)
                                        $allowedTags = '<p><br><ul><ol><li><strong><b><em><i><u><span><div><h1><h2><h3><h4><h5><h6><blockquote><pre><code>';
                                        $cleanReason = strip_tags($decodedReason, $allowedTags);
                                        // Output the HTML content directly (not escaped) so it renders properly
                                        echo $cleanReason;
                                    }
                                    ?>
                                </div>
                                <div class="mt-3 p-3 bg-light rounded">
                                    <small class="text-muted">
                                        <i class="bi bi-shield-lock text-success me-2"></i>
                                        This appointment information is encrypted for security and privacy protection
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Appointment Timeline -->
                <div class="info-card card shadow-lg mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>Appointment Timeline
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="timeline-item">
                                    <div class="timeline-badge bg-primary text-white">
                                        <i class="bi bi-calendar-plus"></i>Scheduled
                                    </div>
                                    <div class="fw-semibold text-dark">
                                        <?php echo date('M j, Y', strtotime($appointment['appointment_created'])); ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?php echo date('g:i A', strtotime($appointment['appointment_created'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="timeline-item">
                                    <div class="timeline-badge bg-info text-white">
                                        <i class="bi bi-play-circle"></i>Started
                                    </div>
                                    <div class="fw-semibold text-dark">
                                        <?php echo date('M j, Y', strtotime($appointment['start_time'])); ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?php echo date('g:i A', strtotime($appointment['start_time'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="timeline-item">
                                    <?php if ($isCompleted): ?>
                                        <div class="timeline-badge bg-success text-white">
                                            <i class="bi bi-check-circle"></i>Completed
                                        </div>
                                        <div class="fw-semibold text-dark">
                                            <?php echo date('M j, Y', strtotime($appointment['end_time'])); ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?php echo date('g:i A', strtotime($appointment['end_time'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="timeline-badge bg-warning text-dark">
                                            <i class="bi bi-clock"></i>In Progress
                                        </div>
                                        <div class="fw-semibold text-dark">
                                            Expected End
                                        </div>
                                        <div class="small text-muted">
                                            <?php echo date('g:i A', strtotime($appointment['end_time'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <a href="<?php echo $backUrl; ?>" class="btn btn-secondary btn-enhanced">
                            <i class="bi bi-arrow-left"></i>
                            <?php echo $backText; ?>
                        </a>
                        
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="view_patient.php?id=<?php echo $appointment['patient_id']; ?>" class="btn btn-info btn-enhanced">
                                <i class="bi bi-person-lines-fill"></i>
                                View Full Patient Record
                            </a>
                            
                            <?php if ($_SESSION['role'] === 'admin' && !$isCompleted): ?>
                                <a href="edit_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-warning btn-enhanced">
                                    <i class="bi bi-pencil-square"></i>
                                    Edit Appointment
                                </a>
                            <?php endif; ?>
                        </div>
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

        });
    </script>
</body>
</html>