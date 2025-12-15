<?php
/**
 * View Patient Details page for Private Clinic Patient Record System
 * Read-only view of patient information accessible by both admin and doctor
 */

require_once 'config.php';
require_once 'db.php';

// Check authentication and session timeout
Database::requireAuth();

// Get patient ID from URL
$patientId = $_GET['id'] ?? '';

if (empty($patientId) || !is_numeric($patientId)) {
    $_SESSION['message'] = 'Invalid patient ID.';
    $_SESSION['message_type'] = 'error';
    
    // Redirect based on user role
    if ($_SESSION['role'] === 'doctor') {
        header('Location: doctor_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

// Fetch patient data with decrypted diagnosis
try {
    $sql = "SELECT id, name, AES_DECRYPT(UNHEX(ic_number), ?) as ic_number, AES_DECRYPT(diagnosis, ?) as diagnosis, phone, created_at, updated_at
            FROM patients WHERE id = ?";
    $patient = Database::fetchOne($sql, [SECURE_KEY, ENCRYPTION_KEY, $patientId]);
    
    if (!$patient) {
        $_SESSION['message'] = 'Patient not found.';
        $_SESSION['message_type'] = 'error';
        
        // Redirect based on user role
        if ($_SESSION['role'] === 'doctor') {
            header('Location: doctor_dashboard.php');
        } else {
            header('Location: dashboard.php');
        }
        exit();
    }
    
    // Log the patient view activity for audit trail
    Database::logActivity(
        $_SESSION['user_id'], 
        $_SESSION['username'], 
        'READ', 
        "Viewed details of patient: " . $patient['name']
    );
    
} catch (Exception $e) {
    error_log("Error fetching patient: " . $e->getMessage());
    $_SESSION['message'] = 'Error loading patient data. Please try again.';
    $_SESSION['message_type'] = 'error';
    
    // Log the failed patient view attempt
    Database::logActivity(
        $_SESSION['user_id'], 
        $_SESSION['username'], 
        'READ_FAILED', 
        "Failed to view patient details - Patient ID: " . $patientId . " - Error: " . $e->getMessage()
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
    <title>View Patient Details - Private Clinic</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
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

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-person-lines-fill me-2"></i>
                            Patient Details - Read Only
                        </h4>
                        <small>Viewing patient information</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-primary">
                                        <i class="bi bi-person me-1"></i>
                                        Patient Name
                                    </label>
                                    <div class="form-control-plaintext bg-light p-3 rounded border">
                                        <?php echo htmlspecialchars($patient['name']); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-primary">
                                        <i class="bi bi-card-text me-1"></i>
                                        IC Number
                                    </label>
                                    <div class="form-control-plaintext bg-light p-3 rounded border">
                                        <code class="fs-6"><?php echo htmlspecialchars($patient['ic_number']); ?></code>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-primary">
                                <i class="bi bi-telephone me-1"></i>
                                Phone Number
                            </label>
                            <div class="form-control-plaintext bg-light p-3 rounded border">
                                <?php echo htmlspecialchars($patient['phone']); ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-primary">
                                <i class="bi bi-clipboard-pulse me-1"></i>
                                Diagnosis
                            </label>
                            <div class="form-control-plaintext bg-light p-3 rounded border" style="min-height: 120px;">
                                <?php 
                                if ($patient['diagnosis'] === null || $patient['diagnosis'] === '') {
                                    echo '<em class="text-muted">Unable to decrypt diagnosis or no diagnosis recorded</em>';
                                } else {
                                    // Decode HTML entities and allow safe HTML tags for rich text formatting
                                    $decodedDiagnosis = html_entity_decode($patient['diagnosis'], ENT_QUOTES, 'UTF-8');
                                    // Allow comprehensive HTML tags for TinyMCE formatting (including colors, styles)
                                    $allowedTags = '<p><br><ul><ol><li><strong><b><em><i><u><span><div><h1><h2><h3><h4><h5><h6><blockquote><pre><code>';
                                    $cleanDiagnosis = strip_tags($decodedDiagnosis, $allowedTags);
                                    // Output the HTML content directly (not escaped) so it renders properly
                                    echo $cleanDiagnosis;
                                }
                                ?>
                            </div>
                            <div class="form-text">
                                <i class="bi bi-shield-lock text-success me-1"></i>
                                This information is encrypted for security and privacy
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-secondary">
                                        <i class="bi bi-calendar-plus me-1"></i>
                                        Date Added
                                    </label>
                                    <div class="form-control-plaintext bg-light p-2 rounded border">
                                        <small><?php echo date('M j, Y g:i A', strtotime($patient['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-secondary">
                                        <i class="bi bi-calendar-check me-1"></i>
                                        Last Updated
                                    </label>
                                    <div class="form-control-plaintext bg-light p-2 rounded border">
                                        <small>
                                            <?php 
                                            if ($patient['updated_at']) {
                                                echo date('M j, Y g:i A', strtotime($patient['updated_at']));
                                            } else {
                                                echo 'Never updated';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-between mt-4">
                            <a href="<?php echo $backUrl; ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i>
                                <?php echo $backText; ?>
                            </a>
                            
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                                    <i class="bi bi-pencil-square me-1"></i>
                                    Edit Patient
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