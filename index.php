<?php
/**
 * Login page for Private Clinic Patient Record System
 * Handles secure user authentication with database verification and audit logging
 */

require_once 'config.php';
require_once 'db.php';

// Start session
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$attempt_message = '';
$lockout_until = '';
$show_timer = false;

// Get client IP address for logging
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = Database::sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // Don't sanitize password as it may contain special chars
    $clientIP = getClientIP();
    
    $loginSuccess = false;
    
    // Check if username is currently locked out
    $lockoutInfo = Database::checkUsernameLockout($username);
    if ($lockoutInfo) {
        $lockoutTime = new DateTime($lockoutInfo['lockout_until']);
        $now = new DateTime();
        $remainingTime = $lockoutTime->diff($now);
        
        $error_message = "Account temporarily locked due to multiple failed login attempts.";
        $lockout_until = $lockoutInfo['lockout_until'];
        $show_timer = true;
    } else {
        try {
            // Basic security check: reject if username contains non-printable characters
            if (!ctype_print($username) || empty($username)) {
                $loginSuccess = false;
            } else {
            // Query the users table for the username (case-sensitive)
            // Using public connection (read-only) for security - limits SQL injection damage
            $sql = "SELECT id, username, password_hash, role FROM users WHERE BINARY username = ?";
            $user = Database::fetchOne($sql, [$username], 'public');
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Successful authentication
                $loginSuccess = true;
            
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            // Session fixation protection
            session_regenerate_id(true);
            
            // Record successful login attempt
            Database::recordUsernameLoginAttempt($clientIP, $user['username'], true);
            
            // Log successful login to audit trail
            Database::logActivity(
                $user['id'], 
                $user['username'], 
                'LOGIN', 
                "User logged in successfully"
            );
            
            // Role-based redirect
            if ($user['role'] === 'doctor') {
                header('Location: doctor_dashboard.php');
            } else {
                header('Location: dashboard.php');
            }
            exit();
            } else {
                // Invalid credentials
                $error_message = 'Invalid username or password. Please try again.';
            }
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error_message = 'Login system temporarily unavailable. Please try again later.';
    }
    
        // Record failed login attempt and handle lockout
        if (!$loginSuccess && !$lockoutInfo) { // Only record if not already locked out
            $attemptResult = Database::recordUsernameLoginAttempt($clientIP, $username, false);
            
            if ($attemptResult['locked']) {
                $error_message = "Account temporarily locked due to multiple failed login attempts.";
                $lockout_until = $attemptResult['lockout_until'];
                $show_timer = true;
            } else {
                $remainingAttempts = 5 - $attemptResult['attempts'];
                $error_message = 'Invalid username or password.';
                if ($remainingAttempts > 0) {
                    $attempt_message = "{$remainingAttempts} attempt(s) remaining before temporary lockout.";
                }
            }
            
            // Log to audit trail for comprehensive tracking
            try {
                Database::logActivity(
                    0, // No user ID for failed login
                    $username, 
                    'LOGIN_FAILED', 
                    "Failed login attempt - Invalid credentials for username: " . $username . " - Attempts: " . $attemptResult['attempts']
                );
            } catch (Exception $e) {
                error_log("Failed to log login attempt: " . $e->getMessage());
            }
        }
    }
}

// Check lockout status for GET requests (when username is provided via URL or session)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['username'])) {
    $username = Database::sanitizeInput($_GET['username']);
    $lockoutInfo = Database::checkUsernameLockout($username);
    if ($lockoutInfo) {
        $error_message = "Account temporarily locked due to multiple failed login attempts.";
        $lockout_until = $lockoutInfo['lockout_until'];
        $show_timer = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Private Clinic - Login</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gradient-primary">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-8 col-md-9">
                <div class="card o-hidden border-0 shadow-lg my-5">
                    <div class="card-body p-0">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="p-5">
                                    <div class="text-center mb-4">
                                        <div class="clinic-logo mb-3">
                                            <i class="bi bi-hospital text-primary" style="font-size: 3rem;"></i>
                                        </div>
                                        <h1 class="h3 text-gray-900 mb-1">Private Clinic</h1>
                                        <p class="text-muted">Patient Record System</p>
                                    </div>
                                    
                                    <?php if (!empty($error_message)): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                            <?php echo htmlspecialchars($error_message); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" action="index.php" class="user">
                                        <div class="form-group mb-3">
                                            <div class="input-group">
                                                <span class="input-group-text bg-light">
                                                    <i class="bi bi-person-fill text-primary"></i>
                                                </span>
                                                <input type="text" 
                                                       class="form-control form-control-lg" 
                                                       id="username" 
                                                       name="username" 
                                                       placeholder="Enter Username"
                                                       required 
                                                       autocomplete="username"
                                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : (isset($_GET['username']) ? htmlspecialchars($_GET['username']) : ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group mb-4">
                                            <div class="input-group">
                                                <span class="input-group-text bg-light">
                                                    <i class="bi bi-lock-fill text-primary"></i>
                                                </span>
                                                <input type="password" 
                                                       class="form-control form-control-lg" 
                                                       id="password" 
                                                       name="password" 
                                                       placeholder="Enter Password"
                                                       required 
                                                       autocomplete="current-password">
                                            </div>
                                            <?php if ($show_timer && !empty($lockout_until)): ?>
                                                <div class="text-end mt-1">
                                                    <small class="text-danger">
                                                        <i class="bi bi-clock me-1"></i>
                                                        <span id="lockout-timer">Calculating...</span>
                                                    </small>
                                                </div>
                                            <?php elseif (!empty($attempt_message)): ?>
                                                <div class="text-end mt-1">
                                                    <small class="text-warning">
                                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                                        <?php echo htmlspecialchars($attempt_message); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary btn-lg btn-block w-100 mb-3">
                                            <i class="bi bi-box-arrow-in-right me-2"></i>
                                            Login to Dashboard
                                        </button>
                                    </form>
                                    

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Lockout countdown timer
        <?php if ($show_timer && !empty($lockout_until)): ?>
        function startLockoutTimer() {
            const lockoutUntil = new Date('<?php echo $lockout_until; ?>').getTime();
            const timerElement = document.getElementById('lockout-timer');
            const formButton = document.querySelector('button[type="submit"]');
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            // Disable form during lockout
            formButton.disabled = true;
            usernameField.disabled = true;
            passwordField.disabled = true;
            formButton.innerHTML = '<i class="bi bi-lock me-2"></i>Account Locked';
            
            const timer = setInterval(function() {
                const now = new Date().getTime();
                const distance = lockoutUntil - now;
                
                if (distance < 0) {
                    clearInterval(timer);
                    timerElement.innerHTML = 'Lockout expired. Please refresh the page.';
                    // Re-enable form
                    formButton.disabled = false;
                    usernameField.disabled = false;
                    passwordField.disabled = false;
                    formButton.innerHTML = '<i class="bi bi-box-arrow-in-right me-2"></i>Login to Dashboard';
                    return;
                }
                
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                timerElement.innerHTML = `Unlocks in ${minutes}m ${seconds}s`;
            }, 1000);
        }
        
        // Start timer when page loads
        document.addEventListener('DOMContentLoaded', startLockoutTimer);
        <?php endif; ?>
        
        // Form validation
        document.querySelector('.user').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                // Focus on the first empty field instead of showing popup
                if (!username) {
                    document.getElementById('username').focus();
                } else {
                    document.getElementById('password').focus();
                }
            }
        });
        
        // Check lockout status when username changes
        document.getElementById('username').addEventListener('blur', function() {
            const username = this.value.trim();
            if (username) {
                // Update URL to include username for lockout checking
                const url = new URL(window.location);
                url.searchParams.set('username', username);
                // Don't navigate, just check if we need to show lockout status
                fetch(url.toString())
                    .then(response => response.text())
                    .then(html => {
                        // Parse response to check for lockout timer
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const timerElement = doc.getElementById('lockout-timer');
                        if (timerElement) {
                            // Reload page to show lockout status
                            window.location.href = url.toString();
                        }
                    })
                    .catch(error => {
                        // Ignore fetch errors
                    });
            }
        });
    </script>

</body>
</html>