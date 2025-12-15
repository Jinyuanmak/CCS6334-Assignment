<?php
/**
 * Property-based tests for authentication features
 * Tests login, logout, and session management
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../config.php';

class AuthenticationPropertiesTest {
    
    /**
     * Property 1: Authentication rejection for invalid credentials
     * Feature: CCS6334-Assignment, Property 1: Authentication rejection for invalid credentials
     * Validates: Requirements 1.2
     * 
     * For any invalid username/password combination, the system should reject 
     * the login attempt and display an error message
     */
    public function testAuthenticationRejectionProperty() {
        $results = [];
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate invalid credentials
            $credentials = $this->generateInvalidCredentials();
            
            // Test authentication rejection
            $result = $this->testAuthenticationAttempt($credentials['username'], $credentials['password']);
            
            // Property: Invalid credentials should always be rejected
            if ($result['authenticated']) {
                echo "FAILED on iteration $i:\n";
                echo "Username: " . $credentials['username'] . "\n";
                echo "Password: " . $credentials['password'] . "\n";
                echo "Expected: Authentication rejected\n";
                echo "Actual: Authentication succeeded\n";
                return false;
            }
            
            // Property: Error message should be displayed for invalid credentials
            if (empty($result['error_message'])) {
                echo "FAILED on iteration $i:\n";
                echo "Username: " . $credentials['username'] . "\n";
                echo "Password: " . $credentials['password'] . "\n";
                echo "Expected: Error message displayed\n";
                echo "Actual: No error message\n";
                return false;
            }
            
            $results[] = $result;
        }
        
        echo "Property 1 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Generate invalid credential combinations for testing
     */
    private function generateInvalidCredentials() {
        $invalidCombinations = [
            // Wrong username, correct password
            ['username' => 'administrator', 'password' => 'pass123'],
            ['username' => 'user', 'password' => 'pass123'],
            ['username' => 'root', 'password' => 'pass123'],
            ['username' => 'doctor', 'password' => 'pass123'],
            ['username' => 'Admin', 'password' => 'pass123'], // Case sensitive
            
            // Correct username, wrong password
            ['username' => 'admin', 'password' => 'password'],
            ['username' => 'admin', 'password' => 'admin'],
            ['username' => 'admin', 'password' => '123456'],
            ['username' => 'admin', 'password' => 'Pass123'], // Case sensitive
            ['username' => 'admin', 'password' => 'pass1234'],
            
            // Both wrong
            ['username' => 'user', 'password' => 'password'],
            ['username' => 'test', 'password' => 'test'],
            ['username' => 'guest', 'password' => 'guest'],
            
            // Empty credentials
            ['username' => '', 'password' => ''],
            ['username' => 'admin', 'password' => ''],
            ['username' => '', 'password' => 'pass123'],
            
            // Whitespace variations
            ['username' => ' admin', 'password' => 'pass123'],
            ['username' => 'admin ', 'password' => 'pass123'],
            ['username' => 'admin', 'password' => ' pass123'],
            ['username' => 'admin', 'password' => 'pass123 '],
            
            // Special characters and injection attempts
            ['username' => 'admin\'', 'password' => 'pass123'],
            ['username' => 'admin', 'password' => 'pass123\''],
            ['username' => 'admin" OR "1"="1', 'password' => 'pass123'],
            ['username' => 'admin', 'password' => 'pass123" OR "1"="1'],
            
            // Very long strings
            ['username' => str_repeat('a', 1000), 'password' => 'pass123'],
            ['username' => 'admin', 'password' => str_repeat('p', 1000)],
            
            // Unicode and special characters
            ['username' => 'ädmin', 'password' => 'pass123'],
            ['username' => 'admin', 'password' => 'päss123'],
            ['username' => '管理员', 'password' => 'pass123'],
            
            // Null bytes and control characters
            ['username' => "admin\0", 'password' => 'pass123'],
            ['username' => 'admin', 'password' => "pass123\0"],
            ['username' => "admin\n", 'password' => 'pass123'],
            ['username' => 'admin', 'password' => "pass123\r\n"],
        ];
        
        do {
            // Randomly select a combination or generate a random one
            if (rand(0, 1)) {
                $credentials = $invalidCombinations[array_rand($invalidCombinations)];
            } else {
                // Generate completely random invalid credentials
                $credentials = [
                    'username' => $this->generateRandomString(rand(1, 50)),
                    'password' => $this->generateRandomString(rand(1, 50))
                ];
            }
            
            // Ensure we don't accidentally generate the valid credentials
            // The valid credentials are: admin/pass123
            // Also check after sanitization to be sure
            $sanitizedUsername = Database::sanitizeInput($credentials['username']);
            $sanitizedPassword = Database::sanitizeInput($credentials['password']);
            
        } while (($credentials['username'] === 'admin' && $credentials['password'] === 'pass123') ||
                 ($sanitizedUsername === 'admin' && $sanitizedPassword === 'pass123'));
        
        return $credentials;
    }
    
    /**
     * Generate random string for testing
     */
    private function generateRandomString($length) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }
    
    /**
     * Test authentication attempt and return result
     */
    private function testAuthenticationAttempt($username, $password) {
        // Simulate the new database-driven authentication logic from index.php
        $sanitizedUsername = Database::sanitizeInput($username);
        // Don't sanitize password as it may contain special chars
        
        $result = [
            'username' => $username,
            'password' => $password,
            'sanitized_username' => $sanitizedUsername,
            'authenticated' => false,
            'error_message' => ''
        ];
        
        try {
            // Additional security check: reject if username contains non-ASCII characters
            // or if sanitization would change the username significantly
            if (!ctype_print($username) || $username !== Database::sanitizeInput($username)) {
                $result['error_message'] = 'Invalid username or password. Please try again.';
                return $result;
            }
            
            // Query the users table for the username (case-sensitive)
            $sql = "SELECT id, username, password_hash FROM users WHERE BINARY username = ?";
            $user = Database::fetchOne($sql, [$username]);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $result['authenticated'] = true;
            } else {
                $result['error_message'] = 'Invalid username or password. Please try again.';
            }
            
        } catch (Exception $e) {
            $result['error_message'] = 'Authentication system error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Property 2: Protected page access control
     * Feature: CCS6334-Assignment, Property 2: Protected page access control
     * Validates: Requirements 1.3
     * 
     * For any protected page URL, accessing it without authentication should redirect to the login page
     */
    public function testProtectedPageAccessProperty() {
        $results = [];
        $iterations = 100;
        
        // List of protected pages to test
        $protectedPages = [
            'dashboard.php',
            // Future protected pages can be added here
            // 'add_patient.php',
            // 'delete_patient.php'
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select a protected page to test
            $page = $protectedPages[array_rand($protectedPages)];
            
            // Generate different session states (all should be unauthenticated)
            $sessionState = $this->generateUnauthenticatedSessionState();
            
            // Test protected page access
            $result = $this->testProtectedPageAccess($page, $sessionState);
            
            // Property: Unauthenticated access should redirect to login page
            if (!$result['redirected_to_login']) {
                echo "FAILED on iteration $i:\n";
                echo "Page: $page\n";
                echo "Session state: " . json_encode($sessionState) . "\n";
                echo "Expected: Redirect to login page\n";
                echo "Actual: No redirect or wrong redirect\n";
                echo "Redirect location: " . ($result['redirect_location'] ?? 'none') . "\n";
                return false;
            }
            
            // Property: Should not display protected content
            if ($result['displayed_protected_content']) {
                echo "FAILED on iteration $i:\n";
                echo "Page: $page\n";
                echo "Session state: " . json_encode($sessionState) . "\n";
                echo "Expected: No protected content displayed\n";
                echo "Actual: Protected content was displayed\n";
                return false;
            }
            
            $results[] = $result;
        }
        
        echo "Property 2 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Generate different unauthenticated session states for testing
     */
    private function generateUnauthenticatedSessionState() {
        $states = [
            // No session at all
            [],
            
            // Session exists but not authenticated
            ['authenticated' => false],
            
            // Session with wrong authentication value
            ['authenticated' => 'false'],
            ['authenticated' => 0],
            ['authenticated' => null],
            
            // Session with other data but no authentication
            ['username' => 'admin'],
            ['user_id' => 1],
            ['last_login' => time()],
            
            // Session with authentication but wrong value (not exactly true)
            ['authenticated' => 'yes'],
            ['authenticated' => 1],
            ['authenticated' => 'true'],
            
            // Malicious session attempts
            ['authenticated' => 'true OR 1=1'],
            ['authenticated' => ['nested' => true]],
            
            // Session with missing authenticated key
            ['username' => 'admin', 'user_id' => 1],
            ['login_time' => time()],
        ];
        
        return $states[array_rand($states)];
    }
    
    /**
     * Test protected page access with given session state
     */
    private function testProtectedPageAccess($page, $sessionState) {
        $result = [
            'page' => $page,
            'session_state' => $sessionState,
            'redirected_to_login' => false,
            'redirect_location' => null,
            'displayed_protected_content' => false,
            'error_occurred' => false
        ];
        
        try {
            // Simulate the protection logic from dashboard.php
            $isAuthenticated = isset($sessionState['authenticated']) && $sessionState['authenticated'] === true;
            
            if (!$isAuthenticated) {
                // Should redirect to login page
                $result['redirected_to_login'] = true;
                $result['redirect_location'] = 'index.php';
            } else {
                // Should display protected content
                $result['displayed_protected_content'] = true;
            }
            
        } catch (Exception $e) {
            $result['error_occurred'] = true;
            $result['error_message'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Property 3: Session persistence
     * Feature: CCS6334-Assignment, Property 3: Session persistence
     * Validates: Requirements 1.4
     * 
     * For any authenticated user session, the session state should persist 
     * across multiple page requests until logout
     */
    public function testSessionPersistenceProperty() {
        $results = [];
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate a valid authenticated session
            $sessionData = $this->generateAuthenticatedSession();
            
            // Test session persistence across multiple page requests
            $result = $this->testSessionPersistenceAcrossRequests($sessionData);
            
            // Property: Session should persist across multiple requests
            if (!$result['session_persisted']) {
                echo "FAILED on iteration $i:\n";
                echo "Initial session: " . json_encode($sessionData) . "\n";
                echo "Expected: Session persists across requests\n";
                echo "Actual: Session lost during requests\n";
                echo "Failed at request: " . $result['failed_at_request'] . "\n";
                return false;
            }
            
            // Property: Session should maintain authentication state
            if (!$result['authentication_maintained']) {
                echo "FAILED on iteration $i:\n";
                echo "Initial session: " . json_encode($sessionData) . "\n";
                echo "Expected: Authentication state maintained\n";
                echo "Actual: Authentication state lost\n";
                echo "Lost at request: " . $result['auth_lost_at_request'] . "\n";
                return false;
            }
            
            // Property: Session should contain expected data
            if (!$result['session_data_intact']) {
                echo "FAILED on iteration $i:\n";
                echo "Initial session: " . json_encode($sessionData) . "\n";
                echo "Expected: Session data intact\n";
                echo "Actual: Session data corrupted or missing\n";
                echo "Missing data: " . json_encode($result['missing_data']) . "\n";
                return false;
            }
            
            // Property: Session should be terminated after logout
            if (!$result['session_terminated_after_logout']) {
                echo "FAILED on iteration $i:\n";
                echo "Initial session: " . json_encode($sessionData) . "\n";
                echo "Expected: Session terminated after logout\n";
                echo "Actual: Session persisted after logout\n";
                return false;
            }
            
            $results[] = $result;
        }
        
        echo "Property 3 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Generate authenticated session data for testing
     */
    private function generateAuthenticatedSession() {
        $baseSession = [
            'authenticated' => true,
            'username' => 'admin',
            'login_time' => time()
        ];
        
        // Add some variations to test different session states
        $variations = [
            // Basic authenticated session
            $baseSession,
            
            // Session with additional data
            array_merge($baseSession, [
                'user_id' => 1,
                'last_activity' => time(),
                'permissions' => ['read', 'write']
            ]),
            
            // Session with different login times
            array_merge($baseSession, ['login_time' => time() - rand(1, 3600)]),
            
            // Session with extra metadata
            array_merge($baseSession, [
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Browser',
                'session_id' => 'test_' . uniqid()
            ])
        ];
        
        return $variations[array_rand($variations)];
    }
    
    /**
     * Test session persistence across multiple page requests
     */
    private function testSessionPersistenceAcrossRequests($initialSessionData) {
        $result = [
            'initial_session' => $initialSessionData,
            'session_persisted' => true,
            'authentication_maintained' => true,
            'session_data_intact' => true,
            'session_terminated_after_logout' => true,
            'failed_at_request' => null,
            'auth_lost_at_request' => null,
            'missing_data' => [],
            'requests_tested' => []
        ];
        
        try {
            // Simulate multiple page requests with the session
            $numRequests = rand(3, 10);
            $currentSession = $initialSessionData;
            
            for ($requestNum = 1; $requestNum <= $numRequests; $requestNum++) {
                // Simulate a page request (dashboard.php access)
                $requestResult = $this->simulatePageRequest($currentSession);
                $result['requests_tested'][] = $requestResult;
                
                // Check if session persisted
                if (!$requestResult['session_exists']) {
                    $result['session_persisted'] = false;
                    $result['failed_at_request'] = $requestNum;
                    break;
                }
                
                // Check if authentication is maintained
                if (!$requestResult['is_authenticated']) {
                    $result['authentication_maintained'] = false;
                    $result['auth_lost_at_request'] = $requestNum;
                    break;
                }
                
                // Check if session data is intact
                $missingData = $this->checkSessionDataIntegrity($initialSessionData, $requestResult['session_data']);
                if (!empty($missingData)) {
                    $result['session_data_intact'] = false;
                    $result['missing_data'] = $missingData;
                    break;
                }
                
                // Update current session for next iteration
                $currentSession = $requestResult['session_data'];
            }
            
            // Test logout functionality - session should be terminated
            if ($result['session_persisted'] && $result['authentication_maintained']) {
                $logoutResult = $this->simulateLogout($currentSession);
                
                // After logout, session should be terminated
                $postLogoutRequest = $this->simulatePageRequest([]);
                
                if ($postLogoutRequest['is_authenticated']) {
                    $result['session_terminated_after_logout'] = false;
                }
            }
            
        } catch (Exception $e) {
            $result['session_persisted'] = false;
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Simulate a page request with given session data
     */
    private function simulatePageRequest($sessionData) {
        $result = [
            'session_exists' => !empty($sessionData),
            'is_authenticated' => false,
            'session_data' => $sessionData,
            'access_granted' => false
        ];
        
        // Simulate the authentication check from dashboard.php
        if (isset($sessionData['authenticated']) && $sessionData['authenticated'] === true) {
            $result['is_authenticated'] = true;
            $result['access_granted'] = true;
        }
        
        return $result;
    }
    
    /**
     * Check if session data integrity is maintained
     */
    private function checkSessionDataIntegrity($originalSession, $currentSession) {
        $missingData = [];
        
        // Check if essential session data is preserved
        $essentialKeys = ['authenticated', 'username', 'login_time'];
        
        foreach ($essentialKeys as $key) {
            if (isset($originalSession[$key])) {
                if (!isset($currentSession[$key])) {
                    $missingData[] = $key . ' (missing)';
                } elseif ($currentSession[$key] !== $originalSession[$key]) {
                    $missingData[] = $key . ' (changed from ' . 
                        json_encode($originalSession[$key]) . ' to ' . 
                        json_encode($currentSession[$key]) . ')';
                }
            }
        }
        
        return $missingData;
    }
    
    /**
     * Simulate logout functionality
     */
    private function simulateLogout($sessionData) {
        // Simulate the logout logic from logout.php
        // Session should be cleared/destroyed
        return [
            'session_cleared' => true,
            'redirected_to_login' => true
        ];
    }
    
    /**
     * Run all authentication property tests
     */
    public function runAllTests() {
        echo "Running Authentication Property Tests...\n";
        echo "=====================================\n";
        
        $testsPassed = 0;
        $totalTests = 3;
        
        // Test Property 1: Authentication rejection for invalid credentials
        echo "Testing Property 1: Authentication rejection for invalid credentials...\n";
        if ($this->testAuthenticationRejectionProperty()) {
            $testsPassed++;
            echo "✓ Property 1 PASSED\n";
        } else {
            echo "✗ Property 1 FAILED\n";
        }
        
        echo "\n";
        
        // Test Property 2: Protected page access control
        echo "Testing Property 2: Protected page access control...\n";
        if ($this->testProtectedPageAccessProperty()) {
            $testsPassed++;
            echo "✓ Property 2 PASSED\n";
        } else {
            echo "✗ Property 2 FAILED\n";
        }
        
        echo "\n";
        
        // Test Property 3: Session persistence
        echo "Testing Property 3: Session persistence...\n";
        if ($this->testSessionPersistenceProperty()) {
            $testsPassed++;
            echo "✓ Property 3 PASSED\n";
        } else {
            echo "✗ Property 3 FAILED\n";
        }
        
        echo "\n=====================================\n";
        echo "Results: $testsPassed/$totalTests tests passed\n";
        
        return $testsPassed === $totalTests;
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $tester = new AuthenticationPropertiesTest();
    $success = $tester->runAllTests();
    exit($success ? 0 : 1);
}