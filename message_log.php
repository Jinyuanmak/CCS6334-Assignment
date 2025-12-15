<?php
/**
 * Message Log page for Private Clinic Patient Record System
 * Shows notifications for doctors about patient updates
 */

require_once 'config.php';
require_once 'db.php';

// Check authentication and session timeout
Database::requireAuth();

// Check if user is doctor (only doctors can view message logs)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['message'] = 'Access denied. Only doctors can view message logs.';
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
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

// Handle AJAX request for message count
if (isset($_GET['ajax']) && $_GET['ajax'] === 'count') {
    header('Content-Type: application/json');
    $count = Database::getUnreadMessageCount($doctorId);
    echo json_encode(['count' => $count]);
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

// Handle mark as read action
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    try {
        $messageId = intval($_GET['mark_read']);
        $sql = "UPDATE message_logs SET is_read = TRUE WHERE id = ?";
        Database::executeUpdate($sql, [$messageId]);
        
        $_SESSION['message'] = 'Message marked as read.';
        $_SESSION['message_type'] = 'success';
        header('Location: message_log.php');
        exit();
    } catch (Exception $e) {
        error_log("Error marking message as read: " . $e->getMessage());
    }
}

// Handle mark all as read action
if (isset($_GET['mark_all_read'])) {
    try {
        $sql = "UPDATE message_logs SET is_read = TRUE WHERE is_read = FALSE AND (doctor_id = ? OR doctor_id IS NULL)";
        Database::executeUpdate($sql, [$doctorId]);
        
        $_SESSION['message'] = 'All messages marked as read.';
        $_SESSION['message_type'] = 'success';
        header('Location: message_log.php');
        exit();
    } catch (Exception $e) {
        error_log("Error marking all messages as read: " . $e->getMessage());
    }
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20; // Show 20 messages per page
$offset = ($page - 1) * $limit;

// Fetch message logs with pagination (doctor-specific)
try {
    // Get total count for pagination (messages for this doctor or general messages)
    $countSql = "SELECT COUNT(*) as total FROM message_logs WHERE doctor_id = ? OR doctor_id IS NULL";
    $totalResult = Database::fetchOne($countSql, [$doctorId]);
    $totalMessages = $totalResult['total'] ?? 0;
    $totalPages = ceil($totalMessages / $limit);
    
    // Get unread count for this doctor
    $unreadCount = Database::getUnreadMessageCount($doctorId);
    
    // Fetch messages for current page (messages for this doctor or general messages)
    $sql = "SELECT ml.id, ml.message_type, ml.title, ml.message, ml.patient_id, 
                   ml.appointment_id, ml.is_read, ml.created_at, ml.change_details,
                   p.name as patient_name
            FROM message_logs ml
            LEFT JOIN patients p ON ml.patient_id = p.id
            WHERE ml.doctor_id = ? OR ml.doctor_id IS NULL
            ORDER BY ml.created_at DESC, ml.is_read ASC
            LIMIT ? OFFSET ?";
    $messageLogs = Database::fetchAll($sql, [$doctorId, $limit, $offset]);
    
} catch (Exception $e) {
    error_log("Error loading message logs: " . $e->getMessage());
    $messageLogs = [];
    $totalMessages = 0;
    $totalPages = 0;
    $unreadCount = 0;
    $message = "Error loading message logs. Please try again.";
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Log - Private Clinic</title>
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
            <a class="navbar-brand text-white fw-bold" href="doctor_dashboard.php">
                <i class="bi bi-hospital me-2"></i>
                Private Clinic - Doctor Portal
            </a>
            
            <!-- Right side buttons -->
            <div class="navbar-nav ms-auto">
                <a href="doctor_dashboard.php" class="btn btn-outline-light me-2">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Dashboard
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

        <!-- Message Log Section -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="m-0 font-weight-bold text-info">
                                <i class="fas fa-envelope me-2"></i>
                                Message Log
                                <?php if ($unreadCount > 0): ?>
                                    <span class="badge bg-danger ms-2"><?php echo $unreadCount; ?> unread</span>
                                <?php endif; ?>
                            </h5>
                            <div class="d-flex align-items-center">
                                <?php if ($unreadCount > 0): ?>
                                    <a href="?mark_all_read=1" class="btn btn-sm btn-outline-success me-2">
                                        <i class="bi bi-check-all me-1"></i>
                                        Mark All Read
                                    </a>
                                <?php endif; ?>
                                <span class="badge bg-info me-2"><?php echo number_format($totalMessages); ?> total messages</span>
                                <span class="text-muted small">Page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($messageLogs)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-envelope text-muted" style="font-size: 4rem;"></i>
                                <h4 class="text-muted mt-3">No messages found</h4>
                                <p class="text-muted">Patient update notifications will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($messageLogs as $log): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100 message-log-card <?php echo $log['is_read'] ? 'border-secondary' : 'border-danger'; ?>">
                                            <div class="card-header <?php echo $log['is_read'] ? 'bg-light' : 'bg-danger text-white'; ?> py-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="fw-bold">
                                                        <?php
                                                        $iconClass = 'fas fa-info-circle';
                                                        switch ($log['message_type']) {
                                                            case 'PATIENT_UPDATE':
                                                                $iconClass = 'fas fa-user-edit';
                                                                break;
                                                            case 'PATIENT_CREATE':
                                                                $iconClass = 'fas fa-user-plus';
                                                                break;
                                                            case 'PATIENT_DELETE':
                                                                $iconClass = 'fas fa-user-minus';
                                                                break;
                                                            case 'APPOINTMENT_UPDATE':
                                                                $iconClass = 'fas fa-calendar-edit';
                                                                break;
                                                        }
                                                        ?>
                                                        <i class="<?php echo $iconClass; ?> me-1"></i>
                                                        <?php echo str_replace('_', ' ', $log['message_type']); ?>
                                                    </small>
                                                    <?php if (!$log['is_read']): ?>
                                                        <span class="badge bg-light text-danger">NEW</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="card-body message-content">
                                                <h6 class="card-title message-title"><?php echo htmlspecialchars($log['title']); ?></h6>
                                                <p class="card-text message-text"><?php echo nl2br(htmlspecialchars($log['message'])); ?></p>
                                                
                                                <?php if (!empty($log['change_details'])): ?>
                                                    <?php 
                                                    $changeDetails = json_decode($log['change_details'], true);
                                                    if ($changeDetails && is_array($changeDetails)):
                                                    ?>
                                                        <div class="mt-2 p-2 bg-light rounded">
                                                            <small class="text-muted fw-bold">
                                                                <i class="fas fa-exchange-alt me-1"></i>Change Details:
                                                            </small>
                                                            <div class="mt-1">
                                                                <?php foreach ($changeDetails as $field => $change): ?>
                                                                    <div class="small mb-1">
                                                                        <strong><?php echo ucfirst($field); ?>:</strong>
                                                                        <span class="text-danger"><?php 
                                                                            // Decode HTML entities for clean display
                                                                            $oldValue = html_entity_decode($change['old'], ENT_QUOTES, 'UTF-8');
                                                                            $cleanOldValue = strip_tags($oldValue);
                                                                            echo htmlspecialchars($cleanOldValue); 
                                                                        ?></span>
                                                                        <i class="fas fa-arrow-right mx-1"></i>
                                                                        <span class="text-success"><?php 
                                                                            // Decode HTML entities for clean display
                                                                            $newValue = html_entity_decode($change['new'], ENT_QUOTES, 'UTF-8');
                                                                            $cleanNewValue = strip_tags($newValue);
                                                                            echo htmlspecialchars($cleanNewValue); 
                                                                        ?></span>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <?php if ($log['patient_name']): ?>
                                                    <div class="mb-2">
                                                        <small class="text-muted">
                                                            <i class="bi bi-person me-1"></i>
                                                            Patient: <strong><?php echo htmlspecialchars($log['patient_name']); ?></strong>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock me-1"></i>
                                                        <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                                    </small>
                                                    
                                                    <div>
                                                        <?php if ($log['patient_id']): ?>
                                                            <a href="view_patient.php?id=<?php echo $log['patient_id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary me-1">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($log['appointment_id']): ?>
                                                            <a href="appointment_detail.php?id=<?php echo $log['appointment_id']; ?>" 
                                                               class="btn btn-sm btn-outline-info me-1">
                                                                <i class="bi bi-calendar"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!$log['is_read']): ?>
                                                            <a href="?mark_read=<?php echo $log['id']; ?>" 
                                                               class="btn btn-sm btn-outline-success">
                                                                <i class="bi bi-check"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Message log pagination" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <!-- Previous Page -->
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page - 1); ?>">
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
                                                <a class="page-link" href="?page=<?php echo ($page + 1); ?>">
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
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Message Log:</strong> This page shows notifications about patient updates and changes. 
                    New messages will appear with a red badge. Click the buttons to view patient details or mark messages as read.
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