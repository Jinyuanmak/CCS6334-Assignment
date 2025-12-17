<?php
/**
 * Edit Patient page for Private Clinic Patient Record System
 * Handles patient record editing with encrypted diagnosis handling
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
    header('Location: dashboard.php');
    exit();
}

// Initialize variables
$errors = [];
$formData = [
    'name' => '',
    'ic_number' => '',
    'diagnosis' => '',
    'phone' => '',
    'country_code' => '+60',
    'phone_number' => ''
];

// Fetch existing patient data
try {
    $sql = "SELECT id, name, AES_DECRYPT(UNHEX(ic_number), ?) as ic_number, AES_DECRYPT(diagnosis, ?) as diagnosis, phone 
            FROM patients WHERE id = ?";
    $patient = Database::fetchOne($sql, [SECURE_KEY, ENCRYPTION_KEY, $patientId]);
    
    if (!$patient) {
        $_SESSION['message'] = 'Patient not found.';
        $_SESSION['message_type'] = 'error';
        header('Location: dashboard.php');
        exit();
    }
    
    // Pre-fill form data and split phone number
    // Default values
    $country_code = "+60"; // Default
    $local_number = "";
    
    // Extract from database value
    $db_phone = $patient['phone']; // e.g. "+60123456789"
    
    // Check known codes strictly
    $prefixes = ["+60", "+65", "+62", "+66"];
    foreach ($prefixes as $prefix) {
        if (strpos($db_phone, $prefix) === 0) {
            $country_code = $prefix;
            // CRITICAL: use strlen($prefix) to get the exact cut point. 
            // +60 is length 3, so we start cutting at index 3.
            $local_number = substr($db_phone, strlen($prefix));
            break;
        }
    }
    
    // Fallback: If no prefix matched, assume the whole thing is the local number (or handle legacy data)
    if ($local_number === "") {
        $local_number = $db_phone;
    }
    
    $formData = [
        'name' => $patient['name'],
        'ic_number' => $patient['ic_number'],
        'diagnosis' => $patient['diagnosis'] ?? '',
        'phone' => $patient['phone'],
        'country_code' => $country_code,
        'phone_number' => $local_number
    ];
    
} catch (Exception $e) {
    error_log("Error fetching patient: " . $e->getMessage());
    $_SESSION['message'] = 'Error loading patient data. Please try again.';
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input data
    $formData['name'] = ucwords(strtolower(trim(Database::sanitizeInput($_POST['name'] ?? ''))));
    $formData['ic_number'] = Database::sanitizeInput($_POST['ic_number'] ?? '');
    
    // CRITICAL FIX: Don't sanitize diagnosis - TinyMCE handles HTML content
    // Just trim and remove dangerous scripts
    $rawDiagnosis = $_POST['diagnosis'] ?? '';
    $formData['diagnosis'] = trim(strip_tags($rawDiagnosis, '<p><br><ul><ol><li><strong><b><em><i><u><span><div><h1><h2><h3><h4><h5><h6><blockquote><pre><code>'));
    
    // Combine country code and phone number
    $formData['country_code'] = Database::sanitizeInput($_POST['country_code'] ?? '+60');
    $formData['phone_number'] = Database::sanitizeInput($_POST['phone_number'] ?? '');
    $formData['phone'] = $formData['country_code'] . $formData['phone_number'];
    
    // Validate required fields
    if (empty($formData['name'])) {
        $errors['name'] = 'Patient name is required';
    }
    
    if (empty($formData['ic_number'])) {
        $errors['ic_number'] = 'IC number is required';
    } elseif (!Database::validateIC($formData['ic_number'])) {
        $errors['ic_number'] = 'IC number must be 12 digits (e.g., 123456789012)';
    }
    
    if (empty($formData['diagnosis'])) {
        $errors['diagnosis'] = 'Diagnosis is required';
    }
    
    if (empty($formData['phone'])) {
        $errors['phone'] = 'Phone number is required';
    } elseif (!Database::validatePhone($formData['phone'])) {
        $errors['phone'] = 'Phone number must be 10-15 digits';
    }
    
    // Check for duplicate IC number if it was changed
    if (empty($errors) && $formData['ic_number'] !== $patient['ic_number']) {
        try {
            $existingPatient = Database::fetchOne(
                "SELECT id FROM patients WHERE AES_DECRYPT(UNHEX(ic_number), ?) = ? AND id != ?", 
                [SECURE_KEY, $formData['ic_number'], $patientId]
            );
            
            if ($existingPatient) {
                $errors['ic_number'] = 'A patient with this IC number already exists';
                
                // Log the duplicate IC attempt
                Database::logActivity(
                    $_SESSION['user_id'], 
                    $_SESSION['username'], 
                    'UPDATE_FAILED', 
                    "Failed to update patient record: " . $formData['name'] . " - Duplicate IC number: " . $formData['ic_number']
                );
            }
        } catch (Exception $e) {
            $errors['general'] = 'Error checking IC number. Please try again.';
        }
    }
    
    // Check for duplicate phone number if it was changed
    if (empty($errors) && $formData['phone'] !== $patient['phone']) {
        try {
            $existingPatient = Database::fetchOne(
                "SELECT id FROM patients WHERE phone = ? AND id != ?", 
                [$formData['phone'], $patientId]
            );
            
            if ($existingPatient) {
                $errors['phone'] = 'This phone number is already in use by another patient.';
            }
        } catch (Exception $e) {
            $errors['general'] = 'Error checking phone number. Please try again.';
        }
    }
    
    // If no errors, update the patient record
    if (empty($errors)) {
        try {
            Database::beginTransaction();
            
            // Track changes for audit log
            $changes = [];
            if ($patient['name'] !== $formData['name']) {
                $changes[] = "Name changed from '" . $patient['name'] . "' to '" . $formData['name'] . "'";
            }
            if ($patient['ic_number'] !== $formData['ic_number']) {
                $changes[] = "IC Number changed from '" . $patient['ic_number'] . "' to '" . $formData['ic_number'] . "'";
            }
            if ($patient['diagnosis'] !== $formData['diagnosis']) {
                $changes[] = "Diagnosis updated";
            }
            if ($patient['phone'] !== $formData['phone']) {
                $changes[] = "Phone changed from '" . $patient['phone'] . "' to '" . $formData['phone'] . "'";
            }
            
            $sql = "UPDATE patients 
                    SET name = ?, ic_number = HEX(AES_ENCRYPT(?, ?)), diagnosis = AES_ENCRYPT(?, ?), phone = ?, updated_at = NOW()
                    WHERE id = ?";
            
            $result = Database::executeUpdate($sql, [
                $formData['name'],
                $formData['ic_number'],
                SECURE_KEY,
                $formData['diagnosis'],
                ENCRYPTION_KEY,
                $formData['phone'],
                $patientId
            ]);
            
            if ($result > 0) {
                Database::commit();
                
                // Log the patient update activity for audit trail
                $changeDetails = !empty($changes) 
                    ? "Updated patient " . $formData['name'] . ": " . implode(", ", $changes)
                    : "Updated patient " . $formData['name'] . " - No changes detected";
                
                Database::logActivity(
                    $_SESSION['user_id'], 
                    $_SESSION['username'], 
                    'UPDATE', 
                    $changeDetails
                );
                
                // Prepare change details for message log
                $changeDetailsArray = [];
                if ($patient['name'] !== $formData['name']) {
                    $changeDetailsArray['Name'] = [
                        'old' => $patient['name'],
                        'new' => $formData['name']
                    ];
                }
                if ($patient['ic_number'] !== $formData['ic_number']) {
                    $changeDetailsArray['IC Number'] = [
                        'old' => $patient['ic_number'],
                        'new' => $formData['ic_number']
                    ];
                }
                if ($patient['diagnosis'] !== $formData['diagnosis']) {
                    $changeDetailsArray['Diagnosis'] = [
                        'old' => 'Updated',
                        'new' => 'Updated'
                    ];
                }
                if ($patient['phone'] !== $formData['phone']) {
                    $changeDetailsArray['Phone'] = [
                        'old' => $patient['phone'],
                        'new' => $formData['phone']
                    ];
                }
                
                // Create message with change details
                $messageText = !empty($changeDetailsArray) 
                    ? "Patient {$formData['name']} record has been updated by administrator."
                    : "Patient {$formData['name']} record has been updated by administrator (no changes detected).";
                
                // Log message for doctors with change details
                Database::logMessage(
                    'PATIENT_UPDATE',
                    'Patient Record Updated',
                    $messageText,
                    $patientId,
                    null,
                    null,
                    $changeDetailsArray
                );
                
                // Set success message and redirect to dashboard
                $_SESSION['message'] = 'Patient record updated successfully!';
                $_SESSION['message_type'] = 'success';
                header('Location: dashboard.php');
                exit();
            } else {
                Database::rollback();
                $errors['general'] = 'Failed to update patient record. Please try again.';
            }
            
        } catch (Exception $e) {
            Database::rollback();
            error_log("Patient update failed: " . $e->getMessage());
            $errors['general'] = 'Database error occurred. Please try again later.';
            
            // Log the failed patient update attempt
            Database::logActivity(
                $_SESSION['user_id'], 
                $_SESSION['username'], 
                'UPDATE_FAILED', 
                "Failed to update patient record: " . $formData['name'] . " - Error: " . $e->getMessage()
            );
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient - Private Clinic</title>
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
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-person-gear me-2"></i>
                            Edit Patient Record
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo htmlspecialchars($errors['general']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">
                                            <i class="bi bi-person me-1"></i>
                                            Patient Name *
                                        </label>
                                        <?php $nameClass = 'form-control' . (isset($errors['name']) ? ' is-invalid' : ''); ?>
                                        <input type="text" 
                                               class="<?php echo $nameClass; ?>" 
                                               id="name" 
                                               name="name" 
                                               value="<?php echo htmlspecialchars($formData['name']); ?>"
                                               placeholder="Enter patient's full name"
                                               maxlength="100"
                                               style="text-transform: capitalize;"
                                               required>
                                        <?php if (isset($errors['name'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="ic_number" class="form-label">
                                            <i class="bi bi-card-text me-1"></i>
                                            IC Number *
                                        </label>
                                        <?php $icClass = 'form-control' . (isset($errors['ic_number']) ? ' is-invalid' : ''); ?>
                                        <input type="text" 
                                               class="<?php echo $icClass; ?>" 
                                               id="ic_number" 
                                               name="ic_number" 
                                               value="<?php echo htmlspecialchars($formData['ic_number']); ?>"
                                               placeholder="XXXXXX-XX-XXXX"
                                               maxlength="14"
                                               required>
                                        <?php if (isset($errors['ic_number'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['ic_number']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="diagnosis" class="form-label">
                                    <i class="bi bi-clipboard-pulse me-1"></i>
                                    Diagnosis *
                                </label>
                                <?php $diagnosisClass = 'form-control' . (isset($errors['diagnosis']) ? ' is-invalid' : ''); ?>
                                <textarea class="<?php echo $diagnosisClass; ?>" 
                                          id="diagnosis" 
                                          name="diagnosis" 
                                          placeholder="Enter patient's diagnosis"
                                          rows="4"
                                          maxlength="500"
                                          required><?php 
                                          // Output diagnosis content for TinyMCE
                                          // TinyMCE expects clean HTML, so we output it directly
                                          echo $formData['diagnosis'];
                                          ?></textarea>
                                <?php if (isset($errors['diagnosis'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['diagnosis']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="form-text">
                                    <i class="bi bi-shield-lock text-success me-1"></i>
                                    This information will be encrypted for security
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="phone_number" class="form-label">
                                    <i class="bi bi-telephone me-1"></i>
                                    Phone Number *
                                </label>
                                <div class="input-group">
                                    <select name="country_code" class="form-select" style="max-width: 100px;">
                                        <option value="+60" <?php echo ($formData['country_code'] === '+60') ? 'selected' : ''; ?>>+60</option>
                                        <option value="+65" <?php echo ($formData['country_code'] === '+65') ? 'selected' : ''; ?>>+65</option>
                                        <option value="+62" <?php echo ($formData['country_code'] === '+62') ? 'selected' : ''; ?>>+62</option>
                                        <option value="+66" <?php echo ($formData['country_code'] === '+66') ? 'selected' : ''; ?>>+66</option>
                                    </select>
                                    <?php $phoneClass = 'form-control' . (isset($errors['phone']) ? ' is-invalid' : ''); ?>
                                    <input type="tel" 
                                           class="<?php echo $phoneClass; ?>" 
                                           id="phone_number" 
                                           name="phone_number" 
                                           value="<?php echo htmlspecialchars($formData['phone_number']); ?>"
                                           placeholder="123456789"
                                           maxlength="15"
                                           required>
                                    <?php if (isset($errors['phone'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-secondary me-md-2">
                                    <i class="bi bi-x-circle me-1"></i>
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>
                                    Update Patient
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> All fields marked with * are required. The diagnosis information is encrypted for security and privacy.
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize TinyMCE for diagnosis textarea
        tinymce.init({
            selector: '#diagnosis',
            height: 300,
            resize: false,
            menubar: false,
            branding: false,
            plugins: 'lists link',
            toolbar: 'bold italic underline | bullist numlist | removeformat',
            placeholder: 'Enter patient\'s diagnosis...',
            content_style: `
                body { 
                    font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                    font-size: 14px; 
                    line-height: 1.6;
                    background-color: white !important; 
                    color: #0f172a !important;
                    margin: 0;
                    padding: 8px;
                }
                body[data-mce-placeholder]:not([data-mce-placeholder=""]):before {
                    color: #64748b !important;
                }
            `,
            setup: function (editor) {
                // Force sync to textarea on change so PHP $_POST works
                editor.on('change', function () {
                    editor.save();
                });
                
                // Apply theme on editor initialization and theme changes
                editor.on('init', function () {
                    applyTinyMCETheme(editor);
                });
                
                // Listen for theme changes
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
                            applyTinyMCETheme(editor);
                        }
                    });
                });
                observer.observe(document.documentElement, { attributes: true });
            }
        });
        
        function applyTinyMCETheme(editor) {
            const theme = document.documentElement.getAttribute('data-theme');
            const doc = editor.getDoc();
            const body = doc.body;
            
            // Always use white background for both themes
            body.style.backgroundColor = '#ffffff';
            body.style.color = '#0f172a';
        }

        // Auto-format patient name to Title Case
        document.getElementById('name').addEventListener('blur', function(e) {
            const words = this.value.toLowerCase().split(' ');
            const titleCase = words.map(word => {
                if (word.length > 0) {
                    return word.charAt(0).toUpperCase() + word.slice(1);
                }
                return word;
            }).join(' ');
            this.value = titleCase.trim();
        });

        // Auto-format IC number input with Malaysian format (XXXXXX-XX-XXXX)
        document.getElementById('ic_number').addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, ''); // Remove non-digits
            
            // Apply formatting: XXXXXX-XX-XXXX
            if (value.length > 6) {
                value = value.substring(0, 6) + '-' + value.substring(6);
            }
            if (value.length > 9) {
                value = value.substring(0, 9) + '-' + value.substring(9);
            }
            
            // Limit to 14 characters (12 digits + 2 dashes)
            if (value.length > 14) {
                value = value.substring(0, 14);
            }
            
            this.value = value;
        });

        // Auto-format phone number input (digits only)
        document.getElementById('phone_number').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, ''); // Keep digits only
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const ic = document.getElementById('ic_number').value.trim();
            const diagnosis = document.getElementById('diagnosis').value.trim();
            const phoneNumber = document.getElementById('phone_number').value.trim();

            if (!name || !ic || !diagnosis || !phoneNumber) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please fill in all required fields.',
                    confirmButtonColor: '#ffc107'
                });
                return false;
            }

            // Validate IC format (XXXXXX-XX-XXXX)
            if (ic.length !== 14 || !ic.match(/^\d{6}-\d{2}-\d{4}$/)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid IC Number',
                    text: 'IC number must be in format XXXXXX-XX-XXXX.',
                    confirmButtonColor: '#dc3545'
                });
                return false;
            }

            if (phoneNumber.length < 7 || phoneNumber.length > 15) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Phone Number',
                    text: 'Phone number must be between 7-15 digits.',
                    confirmButtonColor: '#dc3545'
                });
                return false;
            }
        });
        
        // Show validation errors with SweetAlert
        <?php if (!empty($errors) && !isset($errors['general'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Validation Errors',
            text: 'Please check the form for errors and try again.',
            confirmButtonColor: '#dc3545'
        });
        <?php endif; ?>
    </script>

    <!-- Consolidated JavaScript -->
    <script src="javascript.js"></script>
    
    <script>
        // Initialize the application
        ClinicJS.initializeApp(
            '',
            <?php echo json_encode($_SESSION['user_id']); ?>,
            {},
            <?php echo json_encode($errors); ?>
        );
        
        // Initialize page functionality
        document.addEventListener('DOMContentLoaded', function() {
            ClinicJS.initializeDiagnosisTinyMCE();
            ClinicJS.setupNameFormatting();
            ClinicJS.setupICNumberFormatting();
            ClinicJS.setupPhoneNumberFormatting();
            ClinicJS.setupPatientFormValidation();
            ClinicJS.showValidationErrors();

        });
    </script>
</body>
</html>