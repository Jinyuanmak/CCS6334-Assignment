<?php
/**
 * All Patients page for Private Clinic Patient Record System
 * Displays all patient records without limits
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

// Handle search functionality
$searchTerm = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchTerm = Database::sanitizeInput(trim($_GET['search']));
}

// Fetch all patient records with optional search filter (NO LIMIT)
try {
    if (!empty($searchTerm)) {
        $sql = "SELECT id, name, AES_DECRYPT(UNHEX(ic_number), ?) as ic_number, AES_DECRYPT(diagnosis, ?) as diagnosis, phone, created_at 
                FROM patients 
                WHERE name LIKE ? OR AES_DECRYPT(UNHEX(ic_number), ?) LIKE ?
                ORDER BY created_at DESC";
        $searchParam = "%{$searchTerm}%";
        $patients = Database::fetchAll($sql, [SECURE_KEY, ENCRYPTION_KEY, $searchParam, SECURE_KEY, $searchParam]);
    } else {
        $sql = "SELECT id, name, AES_DECRYPT(UNHEX(ic_number), ?) as ic_number, AES_DECRYPT(diagnosis, ?) as diagnosis, phone, created_at 
                FROM patients 
                ORDER BY created_at DESC";
        $patients = Database::fetchAll($sql, [SECURE_KEY, ENCRYPTION_KEY]);
    }
} catch (Exception $e) {
    $message = "Error loading patient records. Please try again.";
    $messageType = 'error';
    $patients = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Patients - Private Clinic</title>
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
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Live Search Bar -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body py-2">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input class="form-control border-start-0" 
                                   type="search" 
                                   id="liveSearch"
                                   placeholder="Type to search patients instantly..." 
                                   aria-label="Live Search">
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
                                All Patient Records
                                <?php if (!empty($searchTerm)): ?>
                                    <small class="text-muted">- Search results for "<?php echo htmlspecialchars($searchTerm); ?>"</small>
                                <?php endif; ?>
                            </h5>
                            <span class="badge bg-primary"><?php echo count($patients); ?> patients</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Always render the table structure for JavaScript to work -->
                        <div class="table-responsive">
                            <table class="table table-hover table-striped" id="allPatientsTable">
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
                                    <?php if (empty($patients)): ?>
                                        <tr id="noResultsRow">
                                            <td colspan="6" class="text-center py-5">
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
                                                        Try a different search term or <a href="all_patients.php">view all patients</a>
                                                    <?php else: ?>
                                                        <a href="add_patient.php" class="btn btn-primary">Add your first patient</a>
                                                    <?php endif; ?>
                                                </p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
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
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize live search functionality
            initializeLiveSearch();
            
            // Initialize delete confirmations
            initializeDeleteConfirmations();
            
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
        
        function initializeLiveSearch() {

            
            // Get elements with debugging
            const searchInput = document.getElementById('liveSearch');
            const table = document.getElementById('allPatientsTable');
            
            if (!searchInput || !table) {
                return;
            }
            
            // Add event listener for live search
            searchInput.addEventListener('keyup', function(event) {
                const filter = this.value.toLowerCase().trim();
                
                const tbody = table.querySelector('tbody');
                if (!tbody) {
                    return;
                }
                
                const rows = tbody.getElementsByTagName('tr');
                
                let visibleCount = 0;
                let hiddenCount = 0;
                
                // Filter rows (skip header by starting from 0 since we're using tbody)
                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    
                    // Skip the "no results" row if it exists
                    if (row.id === 'noResultsRow') {
                        continue;
                    }
                    
                    const rowText = row.textContent.toLowerCase();
                    
                    if (filter === '' || rowText.includes(filter)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                        hiddenCount++;
                    }
                }
                

                
                // Show/hide "no results" message for live search
                const noResultsRow = document.getElementById('noResultsRow');
                if (noResultsRow) {
                    if (filter !== '' && visibleCount === 0) {
                        // Show custom "no search results" message
                        noResultsRow.innerHTML = `
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                                <h4 class="text-muted mt-3">No patients found matching "${filter}"</h4>
                                <p class="text-muted">Try a different search term or <button class="btn btn-link p-0" onclick="document.getElementById('liveSearch').value=''; document.getElementById('liveSearch').dispatchEvent(new Event('keyup'));">clear search</button></p>
                            </td>
                        `;
                        noResultsRow.style.display = '';
                    } else if (filter === '' && <?php echo empty($patients) ? 'true' : 'false'; ?>) {
                        // Show original "no patients" message when no filter and no patients
                        noResultsRow.innerHTML = `
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-person-x text-muted" style="font-size: 4rem;"></i>
                                <h4 class="text-muted mt-3">No patient records found</h4>
                                <p class="text-muted"><a href="add_patient.php" class="btn btn-primary">Add your first patient</a></p>
                            </td>
                        `;
                        noResultsRow.style.display = '';
                    } else {
                        noResultsRow.style.display = 'none';
                    }
                }
            });
            
            // Add input event for real-time feedback
            searchInput.addEventListener('input', function() {
                // Real-time search feedback
            });
        }
        
        function initializeDeleteConfirmations() {
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
            

        }
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