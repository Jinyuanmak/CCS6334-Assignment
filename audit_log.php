<?php
/**
 * Audit Log page for Private Clinic Patient Record System
 * Displays security audit trail of administrator activities
 */

require_once 'config.php';
require_once 'db.php';

// Check authentication and session timeout
Database::requireAuth();

// Check if user is admin (only admins can view audit logs)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = 'Access denied. Only administrators can view audit logs.';
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
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

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50; // Show 50 logs per page
$offset = ($page - 1) * $limit;

// Fetch audit logs with pagination
try {
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM audit_logs";
    $totalResult = Database::fetchOne($countSql);
    $totalLogs = $totalResult['total'] ?? 0;
    $totalPages = ceil($totalLogs / $limit);
    
    // Fetch logs for current page
    $sql = "SELECT user_id, username, action, description, ip_address, created_at 
            FROM audit_logs 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?";
    $auditLogs = Database::fetchAll($sql, [$limit, $offset]);
    
} catch (Exception $e) {
    error_log("Error loading audit logs: " . $e->getMessage());
    $auditLogs = [];
    $totalLogs = 0;
    $totalPages = 0;
    $message = "Error loading audit logs. Please try again.";
    $messageType = 'error';
    
    // Log the failed audit log access attempt
    Database::logActivity(
        $_SESSION['user_id'] ?? 0, 
        $_SESSION['username'] ?? 'unknown', 
        'READ_FAILED', 
        "Failed to load audit logs - Error: " . $e->getMessage()
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Audit Log - Private Clinic</title>
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

        <!-- Security Audit Log Section -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="m-0 font-weight-bold text-danger">
                                <i class="fas fa-shield-alt me-2"></i>
                                Security Audit Log
                            </h5>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-danger me-2"><?php echo number_format($totalLogs); ?> total entries</span>
                                <span class="text-muted small">Page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($auditLogs)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shield-alt text-muted" style="font-size: 4rem;"></i>
                                <h4 class="text-muted mt-3">No audit logs found</h4>
                                <p class="text-muted">Administrator activities will be logged here for security monitoring.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th><i class="bi bi-clock me-1"></i>Date & Time</th>
                                            <th><i class="bi bi-person me-1"></i>Who</th>
                                            <th><i class="bi bi-activity me-1"></i>Action</th>
                                            <th><i class="bi bi-file-text me-1"></i>Description</th>
                                            <th><i class="bi bi-globe me-1"></i>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($auditLogs as $log): ?>
                                            <tr>
                                                <td class="fw-semibold">
                                                    <i class="bi bi-calendar-date text-primary me-1"></i>
                                                    <?php echo date('M j, Y g:i:s A', strtotime($log['created_at'])); ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-person-circle text-secondary me-2"></i>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($log['username']); ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $badgeClass = 'bg-secondary';
                                                    $iconClass = 'fas fa-question';
                                                    
                                                    switch (strtoupper($log['action'])) {
                                                        case 'READ':
                                                        case 'VIEW':
                                                            $badgeClass = 'bg-info';
                                                            $iconClass = 'fas fa-eye';
                                                            break;
                                                        case 'CREATE':
                                                        case 'ADD':
                                                            $badgeClass = 'bg-success';
                                                            $iconClass = 'fas fa-plus';
                                                            break;
                                                        case 'UPDATE':
                                                        case 'EDIT':
                                                            $badgeClass = 'bg-warning';
                                                            $iconClass = 'fas fa-edit';
                                                            break;
                                                        case 'DELETE':
                                                        case 'REMOVE':
                                                            $badgeClass = 'bg-danger';
                                                            $iconClass = 'fas fa-trash';
                                                            break;
                                                        case 'SCHEDULE':
                                                        case 'APPOINTMENT':
                                                            $badgeClass = 'bg-primary';
                                                            $iconClass = 'fas fa-calendar-plus';
                                                            break;
                                                        case 'LOGIN':
                                                            $badgeClass = 'bg-success';
                                                            $iconClass = 'fas fa-sign-in-alt';
                                                            break;
                                                        case 'LOGOUT':
                                                            $badgeClass = 'bg-secondary';
                                                            $iconClass = 'fas fa-sign-out-alt';
                                                            break;
                                                        case 'LOGIN_FAILED':
                                                            $badgeClass = 'bg-danger';
                                                            $iconClass = 'fas fa-times-circle';
                                                            break;
                                                        case 'CREATE_FAILED':
                                                            $badgeClass = 'bg-danger';
                                                            $iconClass = 'fas fa-exclamation-triangle';
                                                            break;
                                                        case 'UPDATE_FAILED':
                                                            $badgeClass = 'bg-danger';
                                                            $iconClass = 'fas fa-exclamation-triangle';
                                                            break;
                                                        case 'DELETE_FAILED':
                                                            $badgeClass = 'bg-danger';
                                                            $iconClass = 'fas fa-exclamation-triangle';
                                                            break;
                                                        case 'READ_FAILED':
                                                            $badgeClass = 'bg-danger';
                                                            $iconClass = 'fas fa-exclamation-triangle';
                                                            break;
                                                        case 'SCHEDULE_FAILED':
                                                            $badgeClass = 'bg-danger';
                                                            $iconClass = 'fas fa-exclamation-triangle';
                                                            break;
                                                        case 'ACCESS_DENIED':
                                                            $badgeClass = 'bg-dark';
                                                            $iconClass = 'fas fa-ban';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?>">
                                                        <i class="<?php echo $iconClass; ?> me-1"></i>
                                                        <?php echo htmlspecialchars(strtoupper($log['action'])); ?>
                                                    </span>
                                                </td>
                                                <td style="max-width: 400px;">
                                                    <div class="text-wrap">
                                                        <?php echo htmlspecialchars($log['description']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code class="small"><?php echo htmlspecialchars($log['ip_address']); ?></code>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Audit log pagination" class="mt-4">
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
                
                <div class="alert alert-warning mt-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Security Notice:</strong> This audit log tracks all administrator activities for security and compliance purposes. 
                    All actions are logged with timestamps, user information, and IP addresses.
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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